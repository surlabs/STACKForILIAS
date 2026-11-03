/**
 * Character selector object
 * (anonymous constructor function)
 */
il.assStackQuestion = new function () {

	/**
	 * Self reference for usage in event handlers
	 * @type object
	 * @private
	 */
	var self = this;


	/**
	 * Configuration
	 * Has to be provided as JSON when init() is called
	 * @type object
	 * @private
	 */
	var config = {};
	var timeTrackingState = {
		key: '',
		active: false,
		lastStart: 0,
		pendingMs: 0,
		intervalId: null
	};

	/**
	 * Texts to be dynamically rendered
	 * @type object
	 * @private
	 */
	var texts = {
		page: ''
	};


	/**
	 * Initialize the selector
	 * called from ilTemplate::addOnLoadCode,
	 * @param object    start configuration as JSON
	 * @param object    texts to be dynamically rendered
	 */
	this.init = function (a_config, a_texts) {
		config = a_config;
		texts = a_texts;
		$('#ilAssQuestionPreview > form > div.ilc_question_Standard > p:nth-child(1) > button').off('click.assStackQuestion').on('click.assStackQuestion', self.validate);
		$('button.xqcas').off('click.assStackQuestion').on('click.assStackQuestion', self.validate);
		$('#ilc_Page > div.ilc_question_Standard > button').off('click.assStackQuestion').on('click.assStackQuestion', self.validate);
		self.bindHintTracking();
		self.bindTimeTracking();
	};

	this.bindTimeTracking = function () {
		var tracking = config.time_tracking;
		if (!tracking || config.purpose !== 'test') {
			self.stopTimeTracking(true);
			return;
		}

		var key = [tracking.question_id, tracking.active_id, tracking.pass, tracking.user_id].join(':');
		if (timeTrackingState.key === key && timeTrackingState.intervalId !== null) {
			return;
		}

		self.stopTimeTracking(true);
		timeTrackingState.key = key;

		$(document)
			.off('visibilitychange.assStackQuestionTimeTracking')
			.on('visibilitychange.assStackQuestionTimeTracking', function () {
				if (document.hidden) {
					self.pauseTimeTracking();
					self.flushTimeTracking(true);
					return;
				}
				self.resumeTimeTracking();
			});

		$(window)
			.off('focus.assStackQuestionTimeTracking blur.assStackQuestionTimeTracking beforeunload.assStackQuestionTimeTracking pagehide.assStackQuestionTimeTracking')
			.on('focus.assStackQuestionTimeTracking', self.resumeTimeTracking)
			.on('blur.assStackQuestionTimeTracking', function () {
				self.pauseTimeTracking();
				self.flushTimeTracking(true);
			})
			.on('beforeunload.assStackQuestionTimeTracking pagehide.assStackQuestionTimeTracking', function () {
				self.stopTimeTracking(true);
			});

		self.resumeTimeTracking();
		timeTrackingState.intervalId = window.setInterval(function () {
			self.flushTimeTracking(false);
		}, parseInt(tracking.flush_interval_ms, 10) || 15000);
	};

	this.resumeTimeTracking = function () {
		if (!config.time_tracking || document.hidden || !document.hasFocus() || timeTrackingState.active) {
			return;
		}

		timeTrackingState.lastStart = Date.now();
		timeTrackingState.active = true;
	};

	this.pauseTimeTracking = function () {
		if (!timeTrackingState.active) {
			return;
		}

		timeTrackingState.pendingMs += Math.max(0, Date.now() - timeTrackingState.lastStart);
		timeTrackingState.lastStart = 0;
		timeTrackingState.active = false;
	};

	this.flushTimeTracking = function (useBeacon, allowResume) {
		var tracking = config.time_tracking;
		if (!tracking || !tracking.track_url) {
			return;
		}
		if (allowResume === undefined) {
			allowResume = true;
		}

		var wasActive = timeTrackingState.active;
		self.pauseTimeTracking();

		var duration = Math.round(timeTrackingState.pendingMs);
		timeTrackingState.pendingMs = 0;

		if (allowResume && wasActive && !document.hidden && document.hasFocus()) {
			self.resumeTimeTracking();
		}

		if (duration <= 0) {
			return;
		}

		var payload = {
			question_id: tracking.question_id,
			active_id: tracking.active_id,
			pass: tracking.pass,
			user_id: tracking.user_id,
			duration_ms: duration
		};

		if (useBeacon && navigator.sendBeacon) {
			var formData = new FormData();
			Object.keys(payload).forEach(function (key) {
				formData.append(key, payload[key]);
			});
			navigator.sendBeacon(tracking.track_url, formData);
			return;
		}

		$.ajax({
			url: tracking.track_url,
			method: 'POST',
			data: payload
		});
	};

	this.stopTimeTracking = function (flush) {
		if (timeTrackingState.intervalId !== null) {
			window.clearInterval(timeTrackingState.intervalId);
			timeTrackingState.intervalId = null;
		}

		if (flush) {
			self.flushTimeTracking(true, false);
		} else {
			self.pauseTimeTracking();
			timeTrackingState.pendingMs = 0;
		}

		timeTrackingState.key = '';
	};

	this.bindHintTracking = function () {
		if (!config.hint_tracking || config.purpose !== 'test') {
			return;
		}

		$('details.stack-hint').each(function (index) {
			var details = $(this);
			var summary = details.children('summary').first();
			var title = $.trim(summary.text()).substring(0, 255);

			details.attr('data-stack-hint-index', index + 1);
			details.attr('data-stack-hint-title', title);
			details.off('toggle.assStackQuestionHintTracking').on('toggle.assStackQuestionHintTracking', self.trackHintToggle);
		});
	};

	this.trackHintToggle = function () {
		var details = $(this);
		var tracking = config.hint_tracking;

		if (!tracking || !tracking.track_url) {
			return;
		}

		var payload = {
			question_id: tracking.question_id,
			active_id: tracking.active_id,
			pass: tracking.pass,
			user_id: tracking.user_id,
			hint_index: parseInt(details.attr('data-stack-hint-index'), 10) || 0,
			hint_title: details.attr('data-stack-hint-title') || '',
			event_type: details.prop('open') ? 'open' : 'close'
		};

		if (navigator.sendBeacon) {
			var formData = new FormData();
			Object.keys(payload).forEach(function (key) {
				formData.append(key, payload[key]);
			});
			navigator.sendBeacon(tracking.track_url, formData);
			return;
		}

		$.ajax({
			url: tracking.track_url,
			method: 'POST',
			data: payload
		});
	};


	/**
	 * Send the current panel state per ajax
	 */
	this.validate = function (event) {
		add_spinner(this);
		var name = "";
		if (event.target.name === undefined) {
			name = event.target.getAttribute('name');
			if (name === null) {
				alert(5);
			}
		} else {
			name = event.target.name;
		}

		name = name.replace(/cmd\[xqcas_/, '', name);
		name = name.replace(/\]/, '', name);
		var i = name.indexOf('_');
		var question_id = name.substr(0, i);
		var input_name = name.substr(i + 1);
		var is_matrix = $('#xqcas_' + question_id + '_' + input_name + '_sub_0_0').val();
		if (typeof is_matrix === "string") {
			var rows = 0, columns = 0;
			$('input[id^="xqcas_' + question_id + '_' + input_name + '_sub_"]').each(function () {
				var pos = this.id.substr(('xqcas_' + question_id + '_' + input_name + '_sub_').length).split('_');
				rows = Math.max(rows, parseInt(pos[0], 10) + 1);
				columns = Math.max(columns, parseInt(pos[1], 10) + 1);
			});
			var user_response = 'matrix(';
			for (var r = 0; r < rows; r++) {
				user_response += '[';
				for (var c = 0; c < columns; c++) {
					var value = $('#xqcas_' + question_id + '_' + input_name + '_sub_' + r + '_' + c).val();
					if (value.length == 0) {
						user_response += '?';
					} else {
						user_response += value;
					}
					if (c < columns - 1) {
						user_response += ',';
					}
				}
				user_response += ']';
				if (r < rows - 1) {
					user_response += ',';
				}
			}
			user_response += ')';
			var input_value = user_response;
		} else {
			var input_value = $('#xqcas_' + question_id + '_' + input_name).val();
		}

		/**
		 * Hide current question feedback
		 */
		// $(".alert").hide();
		$(".test_specific_feedback").hide();
		/*
		$(".ilAssQuestionRelatedNavigationContainer:first").nextUntil(".ilAssQuestionRelatedNavigationContainer").hide();*/
		$.get(config.validate_url, {
			'question_id': question_id,
			'input_name': input_name,
			'input_value': input_value,
			'purpose': config.purpose
		})
			.done(function (data) {
				remove_spinner();
				$('#validation_xqcas_' + question_id + '_' + input_name).html(data);
				if (typeof MathJax !== 'undefined') {
					if (typeof MathJax.typesetPromise === 'function') {
						MathJax.typesetPromise();
					} else if (typeof MathJax.Hub !== 'undefined') {
						MathJax.Hub.Queue(["Typeset", MathJax.Hub]);
					}
				}
			}).catch(function (error) {
			console.log(error.responseText);
		});

		;

		return false;
	}

	var add_spinner = (function (button) {
		if($(".spinner-container").length==0){
			$(button).after(`
				<div class="spinner-container">
					<style>
						.spinner {
							border: 3px solid;
							border-top: 3px solid transparent !important;
							border-radius: 50%;
							width: 32px;
							height: 32px;
							animation: spin 1s linear infinite;
						}
						.spinner-container {
							display:none;
							margin: 10px auto;
						}
						.spinner-flex{
							display: flex;
							justify-content: center;
							align-items: center;
							width: 100%;
							
						}
						@keyframes spin {
							0% { transform: rotate(0deg); }
							100% { transform: rotate(360deg); }
						}
					</style>
					<div class="spinner-flex">
						<div class="spinner ilEditModified"></div>
					</div>
				</div>
			`);
			$(".spinner-container").show(250);
		}
	});

	var remove_spinner = (function () {
		$(".spinner-container").hide(100, function(){
			$(".spinner-container").remove();
		});
	});
};

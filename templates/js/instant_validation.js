/**
 * Character selector object
 * (anonymous constructor function)
 */
il.instant_validation = new function () {

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
	var config = {
	};

	/**
	 * Texts to be dynamically rendered
	 * @type object
	 * @private
	 */
	var texts = {
		page: ''
	};


	//setup before functions
	var typingTimer;                //timer identifier
	var doneTypingInterval = 2000;  //time in ms, 5 second for example

	/**
	 * Initialize the selector
	 * called from ilTemplate::addOnLoadCode,
	 * @param object    start configuration as JSON
	 * @param object    texts to be dynamically rendered
	 */
	this.init = function (a_config, a_texts) {
		config = a_config;
		texts = a_texts;


		$('.ilc_question_Standard input[type="text"]').keyup(function (event) {
			add_spinner(this, event.target.name);
			delay(function () {
				var name = event.target.name;
				name = name.replace(/xqcas_/, '', name);
				var i = name.indexOf('_');
				var question_id = name.substr(0, i);
				var input_name = name.substr(i + 1);
				if (input_name.indexOf("_sub_") > -1) {
					var matrix_input_name = input_name.substr(0, input_name.indexOf("_sub_"));
					var rows = 0, columns = 0;
					$('input[id^="xqcas_' + question_id + '_' + matrix_input_name + '_sub_"]').each(function () {
						var pos = this.id.substr(('xqcas_' + question_id + '_' + matrix_input_name + '_sub_').length).split('_');
						rows = Math.max(rows, parseInt(pos[0], 10) + 1);
						columns = Math.max(columns, parseInt(pos[1], 10) + 1);
					});
					var user_response = 'matrix(';
					for (var r = 0; r < rows; r++) {
						user_response += '[';
						for (var c = 0; c < columns; c++) {
							var value = $('#xqcas_' + question_id + '_' + matrix_input_name + '_sub_' + r + '_' + c).val();
							if(value.length == 0){
								user_response += '?';
							}else{
								user_response += value;
							}
							if(c < columns-1){
								user_response += ',';
							}
						}
						user_response += ']';
						if(r < rows-1){
							user_response += ',';
						}
					}
					user_response += ')';

					var input_name = matrix_input_name;
					var input_value = user_response;
				} else {
					var input_value = $('#xqcas_' + question_id + '_' + input_name).val();
				}

				$.get(config.validate_url, {
					'question_id': question_id,
					'input_name': input_name,
					'input_value': input_value,
					'purpose': config.purpose
				})
					.done(function (data) {
						remove_spinner(name);
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


				/**
				 * Hide current question feedback
				 */
				// $(".alert").hide();
				$(".test_specific_feedback").hide();
				/*$(".ilAssQuestionRelatedNavigationContainer:first").nextUntil(".ilAssQuestionRelatedNavigationContainer").hide();*/

				var img = new Image();
				img.src = "public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assStackQuestion/templates/css/ajax-loader.gif";
				$('#validation_xqcas_roll_' + question_id + '_' + input_name).html(img)

				return false;
			}, doneTypingInterval);
		});

		$('tr#xqcas_question_display textarea[rows="5"]').keyup(function (event) {
			add_spinner(this, event.data.name);
			delay(function () {
				var name = event.target.name;
				name = name.replace(/xqcas_/, '', name);
				var i = name.indexOf('_');
				var question_id = name.substr(0, i);
				var input_name = name.substr(i + 1);
				var input_value = $('#xqcas_' + question_id + '_' + input_name).val();

				$.get(config.validate_url, {
					'question_id': question_id,
					'input_name': input_name,
					'input_value': input_value,
					'purpose': config.purpose
				})

					.done(function (data) {
						remove_spinner(name);
						$('#validation_xqcas_' + question_id + '_' + input_name).html(data);
						if (typeof MathJax !== 'undefined') {
							if (typeof MathJax.typesetPromise === 'function') {
								MathJax.typesetPromise();
							} else if (typeof MathJax.Hub !== 'undefined') {
								MathJax.Hub.Queue(["Typeset", MathJax.Hub]);
							}
						}
						$('#validation_xqcas_roll_' + question_id + '_' + input_name).html("");
					});

				var img = new Image();
				img.src = "public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assStackQuestion/templates/css/ajax-loader.gif";
				$('#validation_xqcas_roll_' + question_id + '_' + input_name).html(img)

				return false;
			}, doneTypingInterval);
		});

	};

	var delay = (function () {
		var timer = 0;

		return function (callback, ms) {
			clearTimeout(timer);
			timer = setTimeout(callback, ms);
		};
	})();

	var matrix_value = (function () {
		var rows;
		var columns;

		return 5;
		for (rows = 0; rows < 0; rows++) {
			for (columns = 0; columns < 0; rows++) {

			}
		}
	})();

	var add_spinner = (function (button, input) {

		const html = `
			<div class="spinner-container" id="${'spinner_'+input}">
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
			`;
		if(button.id.indexOf("_sub_") > -1){
			if($(".spinner-container").length==0) {
				$(button).parent().parent().parent().after(html);
				$(".spinner-container").show(250);
			}

		} else {
			if($("#spinner_"+input).length==0) {
				$(button).parent().append(html);
				$(".spinner-container").show(250);
			}


		}

	});

	var remove_spinner = (function (id) {
		var selector = "#spinner_xqcas_"+id;
		if(id.indexOf("_sub_") > -1) {
			selector = ".spinner-container";
		}

		$(selector).hide(100, function(){
			$(".spinner-container").remove();
		});
	});
};
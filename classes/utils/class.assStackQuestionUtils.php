<?php
/**
 *  This file is part of the STACK Question plugin for ILIAS, an advanced STEM assessment tool.
 *  This plugin is developed and maintained by SURLABS and is a port of STACK Question for Moodle,
 *  originally created by Chris Sangwin.
 *
 *  The STACK Question plugin for ILIAS is open-source and licensed under GPL-3.0.
 *  For license details, visit https://www.gnu.org/licenses/gpl-3.0.en.html.
 *
 *  To report bugs or participate in discussions, visit the Mantis system and filter by
 *  the category "STACK Question" at https://mantis.ilias.de.
 *
 *  More information and source code are available at:
 *  https://github.com/surlabs/STACK
 *
 *  If you need support, please contact the maintainer of this software at:
 *  stack@surlabs.es
 *
 */


use classes\platform\StackConfig;

/**
 * Class with STATIC METHODS used in the whole STACK Question
 *
 * @author Jesús Copado Mejías <stack@surlabs.es>
 * @version $Id: 7.1$
 *
 */
class assStackQuestionUtils
{
    const FORMAT_HTML = 0;
    const FORMAT_MARKDOWN = 1;
    const FORMAT_MOODLE = 2;
    const FORMAT_PLAIN = 3;

    /**
	 * Prevent comparison operators being interpreted as HTML tags
	 * This would cause errors if CASText is processed with strip_tags.
	 *
	 * @param $text
	 * @return mixed
	 */
	public static function _debugText($text)
	{
        $text = preg_replace('/<([^<>]+)>/', '< $1 >', $text);
        $text = str_replace('< =', '<=', $text);
        $text = str_replace('= >', '=>', $text); // I know this is not a valid operator, but i prfer to avoid it being interpreted as a tag
		return $text;
	}

	/**
	 * Replace key brackets by their ascii code, to avoid
	 * Bug: http://www.ilias.de/mantis/view.php?id=12878
	 * @param string $text the original text.
	 * @return string the text with corrections done.
	 */
	public static function _solveKeyBracketsBug($text)
	{
		$text1 = str_replace("{", "&#123;", $text);

		return str_replace("}", "&#125;", $text1);
	}

	/**
	 * Checks wheter a question uses randomisation or not
	 * @param $text string the text to analyze
	 * @return boolean
	 */
    public static function _hasRandomVariables($text): bool
    {
        return preg_match('~\brand~', $text) || preg_match('~\bmultiselqn~', $text)
            || preg_match('~\bstack_seed~', $text);
    }

	public static function _getSeedFromSTACK2019($question_id, $active_id, $pass, $first_prt_name)
	{
        global $DIC;

        $db = $DIC->database();
        $query = 'SELECT value2 FROM tst_solutions WHERE question_fi = ' . $question_id;
        $query .= ' AND active_fi = ' . $active_id;
        $query .= ' AND pass = ' . $pass;
        $query .= ' AND value1 LIKE "xqcas_prt_%_seed"';

        $db_result = $db->query($query);
        $result = [];

        while ($row = $db->fetchAssoc($db_result)) {
            $result[] = $row;
        }

        // Give priority to the first PRT seed if available else return the first found
        foreach ($result as $res) {
            if (strpos($res['value2'], $first_prt_name) !== false) {
                return (int) $res['value2'];
            }
        }

        return isset($result[0]['value2']) ? (int) $result[0]['value2'] : false;
	}

	/**
	 * This function returns the LaTeX rendered version of $text
	 * @param $text string The raw text
	 * @return string
	 */
    public static function _getLatex(string $text): string
    {
        $mathjax = new ilSetting("MathJax");

        $enabled_server = (bool) $mathjax->get("enable_server", false);

        if ($enabled_server || !class_exists('ilMathJax')) {
            $text = preg_replace('/\\\\\((.*?)\\\\\)/s', '[tex]$1[/tex]', $text);
            $text = preg_replace('/\\\\\[(.*?)\\\\\]/s', '<div style="position: relative; display: block; text-align: center; margin: 1em 0;">[tex]$1[/tex]</div>', $text);
        }

        if (class_exists('ilMathJax')) {
            $text = ilMathJax::getInstance()->insertLatexImages(
                $text,
                "\<span class\=\"latex\">",
                "\<\/span>"
            );

            $text = ilMathJax::getInstance()->insertLatexImages(
                $text,
                "\[tex\]",
                "\[\/tex\]"
            );
        }

        $text = preg_replace_callback('/\\\\begin{pmatrix}(.*?)\\\\end{pmatrix}/s', function ($matches) {
            return str_replace("}\\{", "}\\\\{", $matches[0]);
        }, $text);

        $text = str_replace("{", "&#123;", $text);
        $text = str_replace("}", "&#125;", $text);
        $text = str_replace("\\", "&#92;", $text);

        if (class_exists('ilMathJax')) {
            return ilMathJax::getInstance()->insertLatexImages($text);
        }

        if (class_exists('classes\\platform\\StackRender')) {
            return \classes\platform\StackRender::renderLatexContent($text);
        }

        return $text;
    }

	public static function _adaptUserResponseTo($user_response, $question_id, $format)
	{
		$adapted_user_response = array();
		foreach ($user_response as $input_name => $input_value) {
			if ($format == "only_input_names") {
				$adapted_user_response[str_replace("xqcas_" . $question_id . "_", "", $input_name)] = ilUtil::stripScriptHTML($input_value);
			}
		}
		return $adapted_user_response;
	}

	/**
	 * Returns a text with a format from the content style
	 * @param $a_text
	 * @param $a_format
	 * @return string
	 */

	public static function _getActiveContentStyleId()
	{
		global $DIC;
		$db = $DIC->database();

		$styles_id = array();
		$query = "SELECT value FROM xqcas_configuration WHERE parameter_name = 'feedback_stylesheet_id'";
		$result = $db->query($query);
		while ($row = $db->fetchAssoc($result)) {
			return $row["value"];
		}
	}

	/**
	 * Resolves an author-selected node feedback "style" (0 = default/none, 1-6 =
	 * a slot configured under Administration > STACK plugin > Feedback Styles
	 * Settings) into the wrapper StackRender::renderPRTFeedback() already knows
	 * how to recognize (str_contains($feedback, "ilc_section_")) to load the
	 * matching content stylesheet CSS instead of the plain success/failure
	 * message box.
	 * @param string $html raw, author-entered feedback HTML for this branch
	 * @param int $style_id 0 = no style; 1-6 = configured style slot
	 * @return string
	 */
	public static function _wrapFeedbackWithStyle(string $html, int $style_id): string
	{
		if ($style_id <= 0 || trim($html) === '') {
			return $html;
		}

		$characteristic = StackConfig::get("feedback_styles_style_$style_id");

		if (empty($characteristic)) {
			// Style slot picked by the author but never mapped to a content-style
			// characteristic by the admin: degrade to no wrapping rather than emit
			// a bogus CSS class.
			return $html;
		}

		return '<div class="ilc_section_' . $characteristic . '">' . $html . '</div>';
	}

	/**
	 * Search in the question text and the specific feedback for PRT placeholders
	 * @param string $question_text
	 * @param string|null $specific_feedback
	 * @return array|false
	 */
	public static function _getPRTNamesFromQuestion(string $question_text, $specific_feedback, array $prts_in_db): array
	{
		if ($specific_feedback == null) {
			$specific_feedback = '';
		}
		$prts = stack_utils::extract_placeholders($question_text . $specific_feedback, 'feedback');
		$prt_names = array();

		if (!empty($prts_in_db)) {
			foreach ($prts_in_db as $prt_name_db => $value) {
				if (preg_match('~(' . stack_utils::VALID_NAME_REGEX . ')feedbackvariables~', $prt_name_db, $matches)) {
					$prt_names[$matches[1]] = 0;
				}
			}
		}

		return $prts;
	}

	/**
	 * Called by assStackQuestionDB _readInputs
	 * @param $input
	 * @return string
	 */
	public static function _getInputType($input): string
	{
		switch ($input) {
            case is_a($input, 'stack_parsons_input'):
                return 'parsons';
            case is_a($input, 'stack_string_input'):
                return 'string';
			case is_a($input, 'stack_boolean_input'):
				return 'boolean';
			case is_a($input, 'stack_checkbox_input'):
				return 'checkbox';
			case is_a($input, 'stack_equiv_input'):
				return 'equiv';
			case is_a($input, 'stack_matrix_input'):
				return 'matrix';
			case is_a($input, 'stack_notes_input'):
				return 'notes';
			case is_a($input, 'stack_numerical_input'):
				return 'numerical';
			case is_a($input, 'stack_radio_input'):
				return 'radio';
			case is_a($input, 'stack_singlechar_input'):
				return 'singlechar';
			case is_a($input, 'stack_textarea_input'):
				return 'textarea';
			case is_a($input, 'stack_units_input'):
				return 'units';
			case is_a($input, 'stack_varmatrix_input'):
				return 'varmatrix';
			case is_a($input, 'stack_dropdown_input'):
				return 'dropdown';
            case is_a($input, 'stack_algebraic_input'):
                return 'algebraic';
			default:
                global $tpl;
                $tpl->setOnScreenMessage('failure', 'Input type not found', true);
				return '';
		}
	}

	/**
	 * Called by assStackQuestionDB _readInputs
	 * @param array $extra_options
	 * @return string
	 */
	public static function _serializeExtraOptions($extra_options): string
	{
		$string = '';
		if (is_string($extra_options)) {
			return $extra_options;
		} elseif (is_array($extra_options)) {
			$string = '';
			foreach ($extra_options as $option_name => $status) {
				if ($status === true) {
					$string .= $option_name . ',';
				}
			}
			return substr($string, 0, -1);
		}
		return $string;
	}

	/**
	 * Moodle method
	 * Tests to see whether two arrays have the same value at a particular key.
	 * Missing values are replaced by '', and then the values are cast to
	 * strings and compared with ===.
	 * @param array $array1 the first array.
	 * @param array $array2 the second array.
	 * @param string $key an array key.
	 * @return bool whether the two arrays have the same value (or lack of
	 *      one) for a given key.
	 */
	public static function arrays_same_at_key_missing_is_blank(
		array $array1, array $array2, $key): bool
	{
		if (array_key_exists($key, $array1)) {
			$value1 = $array1[$key];
		} else {
			$value1 = '';
		}
		if (array_key_exists($key, $array2)) {
			$value2 = $array2[$key];
		} else {
			$value2 = '';
		}
		return ((string)$value1) === ((string)$value2);
	}

	/**
	 * Collects the data of a assStackQuestion into an array
	 * @param assStackQuestion $question
	 * @return array
	 */
	public static function _questionToArray(assStackQuestion $question): array
	{

		global $ilias;
		$array = array();
		$plugin = $question->getPlugin();

		/**
		 * question_type
		 * question_id
		 * question_title
		 * question_author
		 * question_text
		 *
		 * ilias_version
		 * plugin_version
		 *
		 * question_options[]
		 * inputs[]
		 * prts[]
		 * deployed_variants[]
		 * extra_info[]
		 * unit_tests[]
		 */

		$array['question_type'] = $question->getQuestionType();
		$array['question_id'] = $question->getId();
		$array['question_title'] = $question->getTitle();
		$array['question_author'] = $question->getAuthor();
		$array['question_text'] = $question->getQuestion();

		$array['ilias_version'] = $ilias->getSetting("ilias_version");
		$array['plugin_version'] = $plugin->getVersion();

		//OPTIONS
		$array['options'] = assStackQuestionDB::_readOptions($question->getId());

		//INPUTS
		$array['inputs'] = assStackQuestionDB::_readInputs($question->getId());

		//PRTS
		$array['prts'] = assStackQuestionDB::_readPRTs($question->getId());

		//DEPLOYED VARIANTS
		$array['deployed_variants'] = assStackQuestionDB::_readDeployedVariants($question->getId());

		//EXTRA INFORMATION
		$array['extra_information'] = assStackQuestionDB::_readExtraInformation($question->getId());

		//UNIT TEXT
		$array['unit_tests'] = assStackQuestionDB::_readUnitTests($question->getId());

		return $array;
	}

	/**
	 * Sets the array's data into assStackQuestion
	 * @param array $array
	 * @param assStackQuestion $question
	 * @return assStackQuestion
	 */
	public static function _arrayToQuestion(array $array, assStackQuestion $question): assStackQuestion
	{
		$question_id = $question->getId();

		//load options
		try {
			$options = new stack_options($array['options']['options']);
			//SET OPTIONS
			$question->options = $options;
		} catch (stack_exception $e) {
            global $tpl;
            $tpl->setOnScreenMessage('failure', $e->getMessage(), true);
		}

		//load Data stored in options but not part of the session options
		$question->question_variables = $array['options']['ilias_options']['question_variables'];
		$question->question_note = $array['options']['ilias_options']['question_note'];

		$question->specific_feedback = $array['options']['ilias_options']['specific_feedback'];
		$question->specific_feedback_format = $array['options']['ilias_options']['specific_feedback_format'];

		$question->prt_correct = $array['options']['ilias_options']['prt_correct'];
		$question->prt_correct_format = $array['options']['ilias_options']['prt_correct_format'];
		$question->prt_partially_correct = $array['options']['ilias_options']['prt_partially_correct'];
		$question->prt_partially_correct_format = $array['options']['ilias_options']['prt_partially_correct_format'];
		$question->prt_incorrect = $array['options']['ilias_options']['prt_incorrect'];
		$question->prt_incorrect_format = $array['options']['ilias_options']['prt_incorrect_format'];

		$question->variants_selection_seed = $array['options']['ilias_options']['variants_selection_seed'];

		//stack version
		if (isset($array['plugin_version'])) {
			$question->stack_version = $array['plugin_version'];
		} else {
			//Stack version TODO CONFIG
			$question->stack_version = '2021120900';
		}

		//load inputs
		//reset inputs first, otherwise the default input/PRT pair set by the question
		//constructor (owner -1) stays alongside the ones loaded from the import array
		$question->inputs = [];
		$inputs_from_array = $array['inputs'];
		$required_parameters = stack_input_factory::get_parameters_used();

		//load only those inputs appearing in the question text
		foreach (stack_utils::extract_placeholders($question->getQuestion(), 'input') as $input_name) {

			$input_data = $inputs_from_array['inputs'][$input_name];
			$input_type = $input_data['type'];

			//Adjust syntax Hint for Textareas
			//Firstline shown as irstlin
			/*
			if ($input_data['type'] == 'equiv' || $input_data['type'] == 'textarea') {
				if (strlen($input_data['syntax_hint']) and !str_starts_with($input_data['syntax_hint'], '[')) {
					$input_data['syntax_hint'] = '[' . $input_data['syntax_hint'] . ']';
				}
				if (strlen($input_data['tans']) and !str_starts_with($input_data['tans'], '[')) {
					$input_data['tans'] = '[' . $input_data['tans'] . ']';
				}
			}*/

			$all_parameters = array(
				'boxWidth' => $input_data['box_size'],
				'strictSyntax' => $input_data['strict_syntax'],
				'insertStars' => $input_data['insert_stars'],
				'syntaxHint' => $input_data['syntax_hint'],
				'syntaxAttribute' => array_key_exists('syntax_attribute', $input_data) ? $input_data['syntax_attribute'] : '', // To support old questions
				'forbidWords' => $input_data['forbid_words'],
				'allowWords' => $input_data['allow_words'],
				'forbidFloats' => $input_data['forbid_float'],
				'lowestTerms' => $input_data['require_lowest_terms'],
				'sameType' => $input_data['check_answer_type'],
				'mustVerify' => $input_data['must_verify'],
				'showValidation' => $input_data['show_validation'],
				'options' => $input_data['options'],
			);

			$parameters = array();
			foreach ($required_parameters[$input_type] as $parameter_name) {
				if ($parameter_name == 'inputType') {
					continue;
				}
				$parameters[$parameter_name] = $all_parameters[$parameter_name];
			}

			//SET INPUTS
			$question->inputs[$input_name] = stack_input_factory::make($input_data['type'], $input_data['name'], $input_data['tans'], $question->options, $parameters);
		}

		//load PRTs and PRT nodes
		//reset PRTs first, otherwise the default PRT set by the question constructor
		//(owner -1) stays alongside the ones loaded from the import array
		$question->prts = [];
		$prt_from_array = $array['prts'];

        // $prt_from_array siempre es un array
        // Cada elemento puede ser un array o un objeto
        // Si es un array, necesito convertirlo en un objeto
        // Si es un objeto, no necesito hacer nada
        // Si el primero es un array, entonces todos son arrays
        // Puede estar vacío

        if (count($prt_from_array) > 0) {
            if (is_array($prt_from_array[array_key_first($prt_from_array)])) {
                foreach ($prt_from_array as $prt_name => $prt_data) {
                    $prt_from_array[$prt_name] = self::prtArrayToObject($prt_data);

                    $prt_from_array[$prt_name]->name = $prt_name;

                    foreach ($prt_from_array[$prt_name]->nodes as $node_name => $node_data) {
                        $prt_from_array[$prt_name]->nodes[$node_name] = self::prtArrayToObject($node_data);

                        $prt_from_array[$prt_name]->nodes[$node_name]->nodename = $node_name;
                        $prt_from_array[$prt_name]->nodes[$node_name]->prtname = $prt_name;
                    }
                }
            }
        }

		//Values
		$total_value = 0;

		//in ILIAS all attempts are graded
		$grade_all = true;

		foreach ($prt_from_array as $prt_name => $prt_data) {
			$total_value += $prt_data->value;
		}

		if ($prt_from_array && $grade_all && $total_value < 0.0000001) {
			try {
				throw new stack_exception('There is an error authoring your question. ' .
					'The $totalvalue, the marks available for the question, must be positive in question ' .
					$question->getTitle());
			} catch (stack_exception $e) {
                global $tpl;
				$tpl->setOnScreenMessage('failure', $e);
				$total_value = 1.0;
			}
		}

		//get PRT and PRT Nodes from DB
        $total_value = 0;
        $all_formative = true;

        foreach ($prt_from_array as $name => $prt_data) {
            $total_value += $prt_data->value;

            if ($prt_data->value > 0) {
                $all_formative = false;
            }
        }

        if ($prt_from_array && !$all_formative && $total_value < 0.0000001) {
            throw new stack_exception('There is an error authoring your question. ' .
                'The $totalvalue, the marks available for the question, must be positive in question ' . $question->getTitle());
        }

        foreach ($prt_from_array as $name => $prt_data) {
            $prt_value = 0;
            if (!$all_formative) {
                $prt_value = $prt_data->value / $total_value;
            }
            $prt_data->feedbackstyle = 1;
            $question->prts[$name] = new stack_potentialresponse_tree_lite($prt_data, $prt_value);
        }

		//load seeds
		$deployed_seeds = $array['deployed_variants'];

		//Needs deployed seeds as key for initialisation
		$depured_deployed_seeds = array();
		foreach ($deployed_seeds as $deployed_seed) {
			$depured_deployed_seeds[$deployed_seed] = $deployed_seed;
		}
		$question->deployed_seeds = $depured_deployed_seeds;

		//load extra info
		$extra_info = $array['extra_information'];
		if (is_array($extra_info)) {
			$question->general_feedback = $extra_info['general_feedback'];
			$question->setPenalty((float) $extra_info['penalty']);
			$question->setHidden((bool) $extra_info['hidden']);
		} else {
			$question->general_feedback = '';
			$question->setPenalty(0.0);
			$question->setHidden(false);
		}

		//load unit tests
		$unit_tests = $array['unit_tests'];
		$question->setUnitTests($unit_tests);

		//Returns question
		return $question;
	}

	/**
	 * @param array $tst_solutions
	 * @param string $question_id
	 * @param array $inputs
	 * @param array $prts
	 * @return array
	 */
	public static function _fromTSTSolutionsToSTACK(array $tst_solutions,string $question_id): array {
        $parsed_user_response_from_db = array();

        if (count($tst_solutions) > 0) {
            if ($tst_solutions[0]['value1'] != "xqcas_raw_data") {
                foreach ($tst_solutions as $solution_entry) {

                    //Question text
                    if ($solution_entry['value1'] == 'xqcas_text_' . $question_id) {
                        $parsed_user_response_from_db['question_text'] = $solution_entry['value2'];
                    }

                    //question note
                    if ($solution_entry['value1'] == 'xqcas_solution_' . $question_id) {
                        $parsed_user_response_from_db['question_note'] = $solution_entry['value2'];
                    }

                    //General feedback
                    if ($solution_entry['value1'] == 'xqcas_general_feedback_' . $question_id) {
                        $parsed_user_response_from_db['general_feedback'] = $solution_entry['value2'];
                    }

                    //Seed
                    if ($solution_entry['value1'] == 'xqcas_question_' . $question_id . '_seed') {
                        $parsed_user_response_from_db['seed'] = $solution_entry['value2'];
                    }

                    //Inputs

                    // User response value
                    if (str_contains($solution_entry['value1'], 'xqcas_input_') && str_contains($solution_entry['value1'], '_value')) {
                        $input_name = str_replace('xqcas_input_', '', $solution_entry['value1']);
                        $input_name = str_replace('_value', '', $input_name);
                        $parsed_user_response_from_db['inputs'][$input_name]['value'] = $solution_entry['value2'];
                    }

                    // User response display
                    if (str_contains($solution_entry['value1'], 'xqcas_input_') && str_contains($solution_entry['value1'], '_display') && !str_contains($solution_entry['value1'], '_model_answer') && !str_contains($solution_entry['value1'], '_validation')) {
                        $input_name = str_replace('xqcas_input_', '', $solution_entry['value1']);
                        $input_name = str_replace('_display', '', $input_name);
                        $parsed_user_response_from_db['inputs'][$input_name]['display'] = $solution_entry['value2'];
                    }

                    // Correct answer value
                    if (str_contains($solution_entry['value1'], 'xqcas_input_') && str_contains($solution_entry['value1'], '_model_answer') && !str_contains($solution_entry['value1'], '_model_answer_display')) {
                        $input_name = str_replace('xqcas_input_', '', $solution_entry['value1']);
                        $input_name = str_replace('_model_answer', '', $input_name);
                        $parsed_user_response_from_db['inputs'][$input_name]['correct_value'] = $solution_entry['value2'];
                    }

                    // Correct answer display
                    if (str_contains($solution_entry['value1'], 'xqcas_input_') && str_contains($solution_entry['value1'], '_model_answer_display')) {
                        $input_name = str_replace('xqcas_input_', '', $solution_entry['value1']);
                        $input_name = str_replace('_model_answer_display', '', $input_name);
                        $parsed_user_response_from_db['inputs'][$input_name]['correct_display'] = $solution_entry['value2'];
                    }

                    // Input validation
                    if (str_contains($solution_entry['value1'], 'xqcas_input_') && str_contains($solution_entry['value1'], '_validation_display')) {
                        $input_name = str_replace('xqcas_input_', '', $solution_entry['value1']);
                        $input_name = str_replace('_validation_display', '', $input_name);
                        $parsed_user_response_from_db['inputs'][$input_name]['validation_display'] = $solution_entry['value2'];
                    }

                    //Prts

                    //PRT name
                    if (str_contains($solution_entry['value1'], 'xqcas_prt_') && str_contains($solution_entry['value1'], '_name')) {
                        $prt_name = str_replace('xqcas_prt_', '', $solution_entry['value1']);
                        $prt_name = str_replace('_name', '', $prt_name);
                        $parsed_user_response_from_db['prts'][$prt_name]['name'] = $solution_entry['value2'];
                    }

                    //PRT errors
                    if (str_contains($solution_entry['value1'], 'xqcas_prt_') && str_contains($solution_entry['value1'], '_errors')) {
                        $prt_name = str_replace('xqcas_prt_', '', $solution_entry['value1']);
                        $prt_name = str_replace('_errors', '', $prt_name);
                        $parsed_user_response_from_db['prts'][$prt_name]['errors'] = $solution_entry['value2'];
                    }

                    //PRT feedback
                    if (str_contains($solution_entry['value1'], 'xqcas_prt_') && str_contains($solution_entry['value1'], '_feedback')) {
                        $prt_name = str_replace('xqcas_prt_', '', $solution_entry['value1']);
                        $prt_name = str_replace('_feedback', '', $prt_name);
                        $parsed_user_response_from_db['prts'][$prt_name]['feedback'] = $solution_entry['value2'];
                    }

                    //PRT status
                    if (str_contains($solution_entry['value1'], 'xqcas_prt_') && str_contains($solution_entry['value1'], '_status')) {
                        $prt_name = str_replace('xqcas_prt_', '', $solution_entry['value1']);
                        $prt_name = str_replace('_status', '', $prt_name);
                        $parsed_user_response_from_db['prts'][$prt_name]['status'] = $solution_entry['value2'];
                    }

                    //PRT answer notes
                    if (str_contains($solution_entry['value1'], 'xqcas_prt_') && str_contains($solution_entry['value1'], '_answernote')) {
                        $prt_name = str_replace('xqcas_prt_', '', $solution_entry['value1']);
                        $prt_name = str_replace('_answernote', '', $prt_name);
                        $parsed_user_response_from_db['prts'][$prt_name]['answer_notes'] = $solution_entry['value2'];
                    }
                }
            } else {
                $parsed_user_response_from_db = (array)json_decode($tst_solutions[0]['value2']);

                //Convert inputs from stdClass to array
                $parsed_user_response_from_db['inputs'] = (array)$parsed_user_response_from_db['inputs'];
                foreach ($parsed_user_response_from_db['inputs'] as $input_name => $input) {
                    $parsed_user_response_from_db['inputs'][$input_name] = (array)$input;
                }

                //Convert prts from stdClass to array
                $parsed_user_response_from_db['prts'] = (array)$parsed_user_response_from_db['prts'];
                foreach ($parsed_user_response_from_db['prts'] as $prt_name => $prt) {
                    $parsed_user_response_from_db['prts'][$prt_name] = (array)$prt;
                }
            }
        }
        
        return $parsed_user_response_from_db;
	}

    public static function _fromDBToReadableFormat(array $db_values, string $question_id): array
    {
        //Prepare array;
        $results = array();

        foreach ($db_values as $index => $value) {
            //if ($value['value1'] == 'xqcas_text_' . $question_id)
            if ($value['value1'] == 'xqcas_text_' . $question_id) {
                $results['question_text'] = $value['value2'];
                $results['id'] = $question_id;
                $results['points'] = (float) $value['points'];

                unset($db_values[$index]);
            } elseif ($value['value1'] == 'xqcas_solution_' . $question_id) {
                $results['question_note'] = $value['value2'];

                unset($db_values[$index]);
            } elseif ($value['value1'] == 'xqcas_general_feedback_' . $question_id) {
                $results['general_feedback'] = $value['value2'];

                unset($db_values[$index]);
            } elseif ($value['value1'] == 'xqcas_question_' . $question_id. '_seed') {
                $results['seed'] = $value['value2'];

                unset($db_values[$index]);
            } else {
                if (str_contains($value['value1'], 'xqcas_prt_') && str_contains($value['value1'], '_name')) {
                    $prt_name = str_replace('xqcas_prt_', '', $value['value1']);
                    $prt_name = str_replace('_name', '', $prt_name);
                    $results['prt'][$prt_name]['points'] = $value['points'];

                    unset($db_values[$index]);
                } elseif (str_contains($value['value1'], 'xqcas_prt_') && str_contains($value['value1'], '_errors')) {
                    $prt_name = str_replace('xqcas_prt_', '', $value['value1']);
                    $prt_name = str_replace('_errors', '', $prt_name);
                    $results['prt'][$prt_name]['errors'] = $value['value2'];

                    unset($db_values[$index]);
                } elseif (str_contains($value['value1'], 'xqcas_prt_') && str_contains($value['value1'], '_feedback')) {
                    $prt_name = str_replace('xqcas_prt_', '', $value['value1']);
                    $prt_name = str_replace('_feedback', '', $prt_name);
                    $results['prt'][$prt_name]['feedback'] = $value['value2'];

                    unset($db_values[$index]);
                } elseif (str_contains($value['value1'], 'xqcas_prt_') && str_contains($value['value1'], '_status')) {
                    $prt_name = str_replace('xqcas_prt_', '', $value['value1']);
                    $prt_name = str_replace('_status', '', $prt_name);
                    $results['prt'][$prt_name]['status']['value'] = $value['value2'];

                    unset($db_values[$index]);
                } elseif (str_contains($value['value1'], 'xqcas_prt_') && str_contains($value['value1'], '_status_message')) {
                    $prt_name = str_replace('xqcas_prt_', '', $value['value1']);
                    $prt_name = str_replace('_status_message', '', $prt_name);
                    $results['prt'][$prt_name]['status']['message'] = $value['value2'];

                    unset($db_values[$index]);
                } elseif (str_contains($value['value1'], 'xqcas_prt_') && str_contains($value['value1'], '_answernote')) {
                    $prt_name = str_replace('xqcas_prt_', '', $value['value1']);
                    $prt_name = str_replace('_answernote', '', $prt_name);
                    $results['prt'][$prt_name]['answernote'] = $value['value2'];

                    unset($db_values[$index]);
                } else {
                    $prt_name = str_replace('xqcas_prt_', '', $value['value1']);
                    $prt_name = substr($prt_name, 0, strpos($prt_name, '_'));

                    if (str_contains($value['value1'], 'xqcas_prt_' . $prt_name . '_value_')) {
                        $input_name = str_replace('xqcas_prt_' . $prt_name . '_value_', '', $value['value1']);
                        $results['prt'][$prt_name]['response'][$input_name]['value'] = $value['value2'];

                        unset($db_values[$index]);
                    } elseif (str_contains($value['value1'], 'xqcas_prt_' . $prt_name . '_display_')) {
                        $input_name = str_replace('xqcas_prt_' . $prt_name . '_display_', '', $value['value1']);
                        $results['prt'][$prt_name]['response'][$input_name]['display'] = $value['value2'];

                        unset($db_values[$index]);
                    } elseif (str_contains($value['value1'], 'xqcas_prt_' . $prt_name . '_model_answer_display_')) {
                        $input_name = str_replace('xqcas_prt_' . $prt_name . '_model_answer_display_', '', $value['value1']);
                        $results['prt'][$prt_name]['response'][$input_name]['model_answer_display'] = $value['value2'];

                        unset($db_values[$index]);
                    } elseif (str_contains($value['value1'], 'xqcas_prt_' . $prt_name . '_model_answer_')) {
                        $input_name = str_replace('xqcas_prt_' . $prt_name . '_model_answer_', '', $value['value1']);
                        $results['prt'][$prt_name]['response'][$input_name]['model_answer'] = $value['value2'];

                        unset($db_values[$index]);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Return the appropriate graded state based on a fraction. That is 0 or less
     * is $graded_incorrect, 1 is $graded_correct, otherwise it is $graded_partcorrect.
     * Appropriate allowance is made for rounding float values.
     *
     * @param number $fraction the grade, on the fraction scale.
     * @return int one of the state constants.
     */
    public static function graded_state_for_fraction($fraction) {
        if ($fraction < 0.000001) {
            return -1;
        } else if ($fraction > 0.999999) {
            return 1;
        } else {
            return 0;
        }
    }

    public static function stack_castext_file_filter(string $castext, array $identifiers): string {
        if ($castext === '') {
            // Nothing to do with empty strings.
            return $castext;
        }

        // In Moodle these are easy to spot.
        if (mb_strpos($castext, '@@PLUGINFILE@@') !== false) {
            // We use the PFS block that has been specicifally
            // built for Moodle to pass on the relevant details.
            $block = '[[pfs';
            switch ($identifiers['field']) {
                case 'questiontext':
                case 'generalfeedback':
                    $block .= ' component="question"';
                    $block .= ' filearea="' . $identifiers['field'] . '"';
                    $block .= ' itemid="' . $identifiers['questionid'] . '"';
                    break;
                case 'specificfeedback':
                case 'prtcorrect': // These three are not in actual use.
                case 'prtpartiallycorrect':
                case 'prtincorrect':
                    $block .= ' component="qtype_stack"';
                    $block .= ' filearea="' . $identifiers['field'] . '"';
                    $block .= ' itemid="' . $identifiers['questionid'] . '"';
                    break;
                case 'prtnodetruefeedback':
                case 'prtnodefalsefeedback':
                    $block .= ' component="qtype_stack"';
                    $block .= ' filearea="' . $identifiers['field'] . '"';
                    $block .= ' itemid="' . $identifiers['prtnodeid'] . '"';
                    break;
            }
            $block .= ']]';
            return $block . $castext . '[[/pfs]]';
        }
        return $castext;
    }

    public static function parseToHTMLWithoutLatex($input): string
    {
        if (str_contains($input, "\r\n")) {
            return str_replace("\r\n", "<br>", $input);
        }

        $components = explode(";\n", $input);

        $htmlOutput = "<div>";

        foreach ($components as $component) {
            $values = explode(":", $component, 2);

            if (count($values) == 2) {
                $variable = $values[0];
                $value = $values[1];
            } else {
                $variable = "";
                $value = "";
            }

            if (strlen($value) > 0 && (strlen($variable) > 0)) {
                $htmlOutput .= "<p>$variable: $value</p>";
            }
        }

        $htmlOutput .= "</div>";
        return $htmlOutput;
    }

    private static function prtArrayToObject(array $prt_data) :stdClass {
        $new_prt = new stdClass();

        foreach ($prt_data as $key => $value) {
            $key = str_replace('_', '', $key);

            $new_prt->$key = $value;
        }

        return $new_prt;
    }

    /**
     * This method is a better alternative to ilUtil::secureString because ensure that the tag is really a tag and not a comparator.
     *
     * @param string $a_str
     * @return string
     */
    public static function secureString(string $a_str) : string {
        $sec_tags = ilUtil::getSecureTags();

        //
        return preg_replace_callback('/<[^>]*>/', function ($matches) use ($sec_tags) {
            if (in_array($matches[0], $sec_tags)) {
                return $matches[0];
            } else {
                return '';
            }
        }, $a_str);
    }

    public static function getMathjaxVersion(): string
    {
        $mathjax = new ilSetting('MathJax');
        $url = (string) ($mathjax->get('path_to_mathjax') ?? '');

        if (str_contains($url, 'mathjax3')) {
            return "3";
        }

        if ($url === '') {
            return "3";
        }

        return "2";
    }

    public static function getMathJaxScriptUrl(): string
    {
        $mathjax = new ilSetting('MathJax');
        $url = (string) ($mathjax->get('path_to_mathjax') ?? '');
        if ($url !== '') {
            return $url;
        }

        return ilUtil::_getHttpPath() . '/node_modules/mathjax/es5/tex-chtml-full.js';
    }
}

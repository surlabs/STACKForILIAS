<?php

/**
 * This file is part of the STACK Question plugin for ILIAS, an advanced STEM assessment tool.
 * This plugin is developed and maintained by SURLABS and is a port of STACK Question for Moodle,
 * originally created by Chris Sangwin.
 *
 * The STACK Question plugin for ILIAS is open-source and licensed under GPL-3.0.
 * For license details, visit https://www.gnu.org/licenses/gpl-3.0.en.html.
 *
 * To report bugs or participate in discussions, visit the Mantis system and filter by
 * the category "STACK Question" at https://mantis.ilias.de.
 *
 * More information and source code are available at:
 * https://github.com/surlabs/STACK
 *
 * If you need support, please contact the maintainer of this software at:
 * stack@surlabs.es
 *
 *********************************************************************/

declare(strict_types=1);

namespace classes\ui\author;

use assStackQuestion;
use assStackQuestionDB;
use assStackQuestionGUI;
use assStackQuestionUtils;
use classes\platform\StackConfig;
use classes\platform\StackException;
use ilAssQuestionPreviewSession;
use public\Customizing\global\plugins\Modules\TestQuestionPool\Questions\assStackQuestion\classes\ui\Component\CustomFactory;
use public\Customizing\global\plugins\Modules\TestQuestionPool\Questions\assStackQuestion\classes\ui\Component\Input\Field\ExpandableSection;
use public\Customizing\global\plugins\Modules\TestQuestionPool\Questions\assStackQuestion\classes\ui\Component\Input\Field\TaxonomySelect;
use ilAssQuestionLifecycle;
use ilassStackQuestionPlugin;
use ilCtrlException;
use ilCtrlInterface;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLanguage;
use ilObject;
use ilObjTaxonomy;
use ilTaxNodeAssignment;
use ilTaxonomyException;
use ilTestQuestionPoolInvalidArgumentException;
use stack_abstract_graph;
use stack_abstract_graph_svg_renderer;
use stack_ans_test_controller;
use stack_cas_security;
use stack_exception;
use stack_input;
use stack_input_factory;
use stack_options;
use stack_potentialresponse_tree_lite;
use stack_utils;
use stdClass;

/**
 * StackQuestionAuthoringUI
 *
 * @authors Jesús Copado Mejías, Saúl Díaz Díaz <stack@surlabs.es>
 */
class StackQuestionAuthoringUI
{
    private ilassStackQuestionPlugin $plugin;
    private assStackQuestion $question;
    private assStackQuestionGUI $parent;
    private ilCtrlInterface $ctrl;
    private Factory $factory;
    private CustomFactory $customFactory;
    private Renderer $renderer;
    private ilLanguage $lng;
    private $request;
    private array $feedback_format_options;

    public function __construct(ilassStackQuestionPlugin $plugin, assStackQuestion $question, assStackQuestionGUI $parent)
    {
        global $DIC;

        $DIC->globalScreen()->layout()->meta()->addCss('Customizing/global/plugins/Modules/TestQuestionPool/Questions/assStackQuestion/templates/css/stack_graph.css');

        $this->plugin = $plugin;
        $this->question = $question;
        $this->parent = $parent;

        $this->ctrl = $DIC->ctrl();
        $this->factory = $DIC->ui()->factory();
        $this->customFactory = new CustomFactory();
        $this->renderer = $DIC->ui()->renderer();
        $this->lng = $DIC->language();
        $this->request = $DIC->http()->request();
    }

    /**
     * @throws ilCtrlException
     * @throws stack_exception
     * @throws ilTaxonomyException
     */
    private function buildForm(): StandardForm
    {
        $sections = [
            "basic" => $this->factory->input()->field()->section($this->buildBasicSection(), $this->plugin->txt("edit_cas_question")),
            "options" => $this->factory->input()->field()->section($this->buildOptionsSection(), $this->plugin->txt("options")),
            "inputs" => $this->factory->input()->field()->section($this->buildInputsSection(), $this->plugin->txt("inputs")),
            "prt" => $this->customFactory->tabSection($this->buildPrtSection(), $this->plugin->txt("prts"))
        ];

        if (!empty($this->parent->getTaxonomyIds())) {
            $sections["taxonomies"] = $this->customFactory->expandableSection($this->buildTaxonomySection(), $this->lng->txt("qpl_qst_edit_form_taxonomy_section"));
        }

        $this->ctrl->setParameterByClass("assStackQuestionGUI", "save_stack_question", "yes");
        return $this->factory->input()->container()->form()->standard(
            $this->ctrl->getLinkTargetByClass("assStackQuestionGUI", "save"),
            $sections
        );
    }

    /**
     * @return array
     * @throws ilCtrlException
     * @throws ilTaxonomyException
     * @throws ilTestQuestionPoolInvalidArgumentException
     * @throws stack_exception
     */
    public function showAuthoringPanel(bool $process_submission = true): array
    {
        $form = $this->buildForm();
        $errors = false;

        if ($process_submission
            && $this->request->getMethod() == "POST"
            && array_key_exists('save_stack_question', $this->request->getQueryParams())
            && $this->request->getQueryParams()['save_stack_question'] == 'yes') {
            $form = $form->withRequest($this->request);
            $result = $form->getData();

            if($result) {
                $errors = $this->save($result);
                $form = $this->buildForm();
            } else {
                $errors = true;
            }
        }

        return [$errors, $this->renderer->render($form)];
    }

    /**
     * @throws stack_exception
     * @throws ilTestQuestionPoolInvalidArgumentException
     * @throws ilTaxonomyException
     * @throws StackException
     */
    private function save(array $result): bool
    {
        global $DIC;

        if (isset($this->request->getQueryParams()["action"])) {
            return $this->checkAction($this->request->getQueryParams());
        }

        // Save basic section
        $basic = $result["basic"];

        $this->question->setTitle($basic["title"]);
        $this->question->setAuthor($basic["author"]);
        $this->question->setComment($basic["description"]);
        $this->question->setLifecycle(ilAssQuestionLifecycle::getInstance($basic["lifecycle"]));

        $this->question->setQuestion($basic["question"]);

        $this->question->question_variables = $basic["question_variables"];

        if (empty($basic["question_note"])) {
            foreach (stack_cas_security::get_all_with_feature('random') as $random) {
                if (str_contains($basic["question_variables"], $random)) {
                    $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->plugin->txt("error_no_question_note"), true);
                    return true;
                }
            }
        }

        $this->question->question_note = $basic["question_note"];
        $this->question->specific_feedback = $basic["specific_feedback"];

        // Save options section
        $options = $result["options"][0];

        $this->question->options = new stack_options(array(
            "simplify" => $options["simplify"] ? 1 : 0,
            "assumepos" => $options["assumepos"] ? 1 : 0,
            "assumereal" => $options["assumereal"] ? 1 : 0,
            "multiplicationsign" => $options["multiplicationsign"],
            "sqrtsign" => $options["sqrtsign"] ? 1 : 0,
            "complexno" => $options["complexno"],
            "inversetrig" => $options["inversetrig"],
            "logicsymbol" => $options["logicsymbol"],
            "matrixparens" => $options["matrixparens"]
        ));

        $this->question->prt_correct = $options["prt_correct"];
        $this->question->prt_partially_correct = $options["prt_partially_correct"];
        $this->question->prt_incorrect = $options["prt_incorrect"];
        $this->question->general_feedback = $options["general_feedback"];

        // Save inputs section
        $inputs = array();

        $required_inputs_parameters = stack_input_factory::get_parameters_used();

        foreach ($result["inputs"] as $name => $input) {
            $parameters = array();

            foreach ($required_inputs_parameters[$input["type"]] as $parameter_name) {
                if ($parameter_name != 'inputType') {
                    $parameters[$parameter_name] = $input[$parameter_name];
                }
            }

            if ($parameters["showValidation"] == 0 && $parameters["mustVerify"] == 1) {
                $DIC->ui()->mainTemplate()->setOnScreenMessage("info", $this->plugin->txt("input_must_verify_to_false"), true);
            }

            $inputs[$name] = stack_input_factory::make($input["type"], $name, $input["teacher_answer"], $this->question->options, $parameters);
        }

        $this->question->inputs = $inputs;

        $inputs_placeholders = stack_utils::extract_placeholders($this->question->getQuestion(), 'input');

        foreach ($inputs_placeholders as $placeholder) {
            if (!isset($this->question->inputs[$placeholder])) {
                $this->question->loadStandardInput($placeholder);
            }
        }

        // Save prt section
        $prts_array = array();

        foreach ($result["prt"] as $prt_name => $_prt) {
            $prt = $_prt[0]["prt"];

            $prt_data = new stdClass();

            $prt_data->name = $prt_name;
            $prt_data->value = $prt["settings"]["prt_value"];
            $prt_data->autosimplify = $prt["settings"]["simplify"];
            $prt_data->feedbackvariables = $prt["settings"]["feedback_variables"];
            $prt_data->feedbackstyle = 1;


            $prt_data->nodes = array();

            foreach ($prt["nodes"] as $node_name => $node) {
                $node_data = new stdClass();

                $node_data->nodename = $node_name;
                $node_data->description = "";
                $node_data->prtname = $prt_name;
                $node_data->answertest = $node["answer_test"];
                $node_data->sans = $node["student_answer"];
                $node_data->tans = $node["teacher_answer"];
                $node_data->testoptions = $node["options"];
                $node_data->quiet = $node["quiet"];

                $node_data->truescoremode = $node["feedback"]["positive"]["mode"];
                $node_data->truescore = $node["feedback"]["positive"]["score"];
                $node_data->truepenalty = $node["feedback"]["positive"]["penalty"];
                $node_data->truenextnode = $node["feedback"]["positive"]["next_node"];
                $node_data->trueanswernote = $node["feedback"]["positive"]["answernote"];
                $node_data->truefeedback = $node["feedback"]["positive"]["specific_feedback"];
                $node_data->truefeedbackraw = $node["feedback"]["positive"]["specific_feedback"];
                $node_data->truefeedbackstyle = (int) $node["feedback"]["positive"]["feedback_class"];
                $node_data->truefeedbackformat = 0;

                $node_data->falsescoremode = $node["feedback"]["negative"]["mode"];
                $node_data->falsescore = $node["feedback"]["negative"]["score"];
                $node_data->falsepenalty = $node["feedback"]["negative"]["penalty"];
                $node_data->falsenextnode = $node["feedback"]["negative"]["next_node"];
                $node_data->falseanswernote = $node["feedback"]["negative"]["answernote"];
                $node_data->falsefeedback = $node["feedback"]["negative"]["specific_feedback"];
                $node_data->falsefeedbackraw = $node["feedback"]["negative"]["specific_feedback"];
                $node_data->falsefeedbackstyle = (int) $node["feedback"]["negative"]["feedback_class"];
                $node_data->falsefeedbackformat = 0;

                $prt_data->nodes[$node_name] = $node_data;
            }

            // Calculate first node by graph
            $graph = new stack_abstract_graph();
            foreach ($prt_data->nodes as $node_name => $node) {
                if ($node->truenextnode == -1) {
                    $left = null;
                } else {
                    $left = $node->truenextnode + 1;
                }
                if ($node->falsenextnode == -1) {
                    $right = null;
                } else {
                    $right = $node->falsenextnode + 1;
                }

                $graph->add_prt_node($node_name + 1, $node->description, $left, $right);
            }
            $graph->layout();
            $roots = $graph->get_roots();
            if ($graph->get_broken_cycles()) {
                throw new StackException('The PRT ' . $prt_name . ' is malformed.');
            }

            $first_node = $this->question->prts[$prt_name]->get_first_node();
            if (count($roots) === 1 || !array_key_exists($first_node, $prt_data->nodes)) {
                $first_node = key($roots) - 1;
            }

            $prt_data->firstnodename = $first_node;

            $prts_array[$prt_name] = $prt_data;
        }

        $total_value = 0;
        $all_formative = true;

        foreach ($prts_array as $prt_data) {
            $total_value += (float) $prt_data->value;

            if ((float) $prt_data->value > 0) {
                $all_formative = false;
            }
        }

        if ($prts_array && !$all_formative && $total_value < 0.0000001) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", 'There is an error authoring your question. The $totalvalue, the marks available for the question, must be positive', true);
            return true;
        }

        $prts = array();

        foreach ($prts_array as $name => $prt_data) {
            $prt_value = 0;
            if (!$all_formative) {
                $prt_value = (float) $prt_data->value / $total_value;
            }
            $prt_data->feedbackstyle = 1;
            $prts[$name] = new stack_potentialresponse_tree_lite($prt_data, $prt_value);
        }

        $this->question->prts = $prts;

        $prts_placeholders = stack_utils::extract_placeholders($this->question->getQuestion() . $this->question->specific_feedback, 'feedback');

        foreach ($prts_placeholders as $placeholder) {
            if (!isset($this->question->prts[$placeholder])) {
                $this->question->loadStandardPrt($placeholder);
            }
        }

        if (!empty($result["taxonomies"])) {
            foreach ($result["taxonomies"] as $taxonomy_id => $nodes) {
                TaxonomySelect::saveTaxonomySelect($this->question->getObjId(), $this->question->getId(), $taxonomy_id, $nodes);
            }
        }
        $this->resetSavedPreviewSession();
        $this->question->saveToDb();

        return false;
    }

    public function resetSavedPreviewSession(): void
    {
        global $DIC;
        $ilUser = $DIC['ilUser'];
        $user_id = $ilUser->getId();
        $question_id = $this->question->getId();
        $ilAssQuestionPreviewSession = new ilAssQuestionPreviewSession($user_id, $question_id);
        $ilAssQuestionPreviewSession->setParticipantsSolution([]);
    }

    /**
     * @throws stack_exception
     */
    private function checkAction(array $params): bool
    {
        return match ($params["action"]) {
            "copyPrt" => $this->copyPrt($params["prt_name"]),
            "pastePrt" => $this->pastePrt(),
            "createNode" => $this->createNode($params["prt_name"]),
            "deleteNode" => $this->deleteNode($params["prt_name"], $params["node_name"]),
            "copyNode" => $this->copyNode($params["prt_name"], $params["node_name"]),
            "pasteNode" => $this->pasteNode($params["to_prt_name"]),
            default => throw new stack_exception("Unknown action"),
        };
    }

    private function generateActionCode(string $id, string $action, array $params = []): string
    {
        $params_string = "&action=$action";

        foreach ($params as $key => $value) {
            $params_string .= "&$key=$value";
        }

        return "$('#$id').click(function(event) {
            event.preventDefault();
            
            let form = $(this).closest('form');
            let action = form.attr('action');
            form.attr('action', action + '$params_string');
            form.submit();
        });";
    }

    private function buildBasicSection(): array
    {
        $inputs = [];

        $inputs["title"] = $this->factory->input()->field()->text($this->lng->txt("title"))->withRequired(true)
            ->withValue(!empty($this->question->getTitle()) ? $this->question->getTitle() : $this->plugin->txt("untitled_question"));
        $inputs["author"] = $this->factory->input()->field()->text($this->lng->txt("author"))->withRequired(true)
            ->withValue($this->question->getAuthor());
        $inputs["description"] = $this->factory->input()->field()->text($this->lng->txt("description"))
            ->withValue($this->question->getComment());
        $inputs["lifecycle"] = $this->factory->input()->field()->select($this->lng->txt("qst_lifecycle"), $this->question->getLifecycle()->getSelectOptions($this->lng))->withRequired(true)
            ->withValue($this->question->getLifecycle()->getIdentifier());
        $inputs["question"] = $this->customFactory->textareaRTE($this->question->getId(), $this->lng->txt("question"), $this->plugin->txt("authoring_input_creation_info"))->withRequired(true)
            ->withValue($this->question->getQuestion());
        $inputs["points"] = $this->factory->input()->field()->numeric($this->plugin->txt("preview_points_message_p3"), $this->plugin->txt("authoring_points_info"))->withRequired(true)
            ->withValue($this->question->getPoints())->withDisabled(true);
        $inputs["question_variables"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("options_question_variables"), $this->plugin->txt("options_question_variables_info"), false)
            ->withValue($this->question->question_variables);
        $inputs["question_note"] = $this->factory->input()->field()->textarea($this->plugin->txt("options_question_note"), $this->plugin->txt("options_question_note_info"))
            ->withValue($this->question->question_note);
        $inputs["specific_feedback"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("options_specific_feedback"), $this->plugin->txt("options_specific_feedback_info"))
            ->withValue($this->question->specific_feedback);

        return $inputs;
    }

    /**
     * @throws stack_exception
     */
    private function buildOptionsSection(): array
    {
        $inputs = [];

        $inputs["simplify"] = $this->factory->input()->field()->checkbox($this->plugin->txt("options_question_simplify"), $this->plugin->txt("options_question_simplify_info"))
            ->withValue((bool) $this->question->options->get_option("simplify"));
        $inputs["assumepos"] = $this->factory->input()->field()->checkbox($this->plugin->txt("options_assume_positive"), $this->plugin->txt("options_assume_positive_info"))
            ->withValue((bool) $this->question->options->get_option("assumepos"));
        $inputs["assumereal"] = $this->factory->input()->field()->checkbox($this->plugin->txt("options_assume_real"), $this->plugin->txt("options_assume_real_info"))
            ->withValue((bool) $this->question->options->get_option("assumereal"));
        $inputs["prt_correct"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("options_prt_correct"))
            ->withValue($this->question->prt_correct);
        $inputs["prt_partially_correct"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("options_prt_partially_correct"))
            ->withValue($this->question->prt_partially_correct);
        $inputs["prt_incorrect"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("options_prt_incorrect"))
            ->withValue($this->question->prt_incorrect);
        $inputs["multiplicationsign"] = $this->factory->input()->field()->select($this->plugin->txt("options_multiplication_sign"), [
            "dot" => $this->plugin->txt('options_mult_sign_dot'),
            "cross" => $this->plugin->txt('options_mult_sign_cross'),
            "onum" => $this->plugin->txt('options_mult_sign_only_numbers'),
            "none" => $this->plugin->txt('options_mult_sign_none')
        ], $this->plugin->txt("options_multiplication_sign_info"))->withRequired(true)
            ->withValue($this->question->options->get_option("multiplicationsign"));
        $inputs["sqrtsign"] = $this->factory->input()->field()->checkbox($this->plugin->txt("options_sqrt_sign"), $this->plugin->txt("options_sqrt_sign_info"))
            ->withValue((bool) $this->question->options->get_option("sqrtsign"));
        $inputs["complexno"] = $this->factory->input()->field()->select($this->plugin->txt("options_complex_numbers"), [
            "i" => $this->plugin->txt('options_complex_numbers_i'),
            "j" => $this->plugin->txt('options_complex_numbers_j'),
            "symi" => $this->plugin->txt('options_complex_numbers_symi'),
            "symj" => $this->plugin->txt('options_complex_numbers_symj')
        ], $this->plugin->txt("options_complex_numbers_info"))->withRequired(true)
            ->withValue($this->question->options->get_option("complexno"));
        $inputs["inversetrig"] = $this->factory->input()->field()->select($this->plugin->txt("options_inverse_trigonometric"), [
            "cos-1" => $this->plugin->txt('options_inverse_trigonometric_cos'),
            "acos" => $this->plugin->txt('options_inverse_trigonometric_acos'),
            "arccos" => $this->plugin->txt('options_inverse_trigonometric_arccos')
        ], $this->plugin->txt("options_inverse_trigonometric_info"))->withRequired(true)
            ->withValue($this->question->options->get_option("inversetrig"));

        $logicSymbol = $this->question->options->get_option("logicsymbol");

        if ($logicSymbol == "0" || $logicSymbol == 0) {
            $logicSymbol = "lang";
        } elseif ($logicSymbol == "1" || $logicSymbol == 1) {
            $logicSymbol = "symbol";
        }

        $inputs["logicsymbol"] = $this->factory->input()->field()->select($this->plugin->txt("options_logic_symbol"), [
            "lang" => $this->plugin->txt('options_logic_symbol_lang'),
            "symbol" => $this->plugin->txt('options_logic_symbol_symbol'),
        ], $this->plugin->txt("options_logic_symbol_info"))->withRequired(true)
            ->withValue($logicSymbol);
        $inputs["matrixparens"] = $this->factory->input()->field()->select($this->plugin->txt("options_matrix_parens"), [
            "[" => "[",
            "(" => "(",
            "" => "",
            "{" => "{",
            "|" => "|"
        ], $this->plugin->txt("options_matrix_parens_info"))
            ->withValue($this->question->options->get_option("matrixparens"));
        $inputs["general_feedback"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("options_how_to_solve"), $this->plugin->txt("options_how_to_solve_info"))
            ->withValue($this->question->general_feedback);


        return [$this->customFactory->expandableSection($inputs, $this->plugin->txt("show_options"))->withExpandedByDefault(true)];
    }

    /**
     * @throws stack_exception
     * @throws ilCtrlException
     */
    private function buildInputsSection(): array
    {
        $inputs = [];

        if (empty($this->question->inputs)) {
            $standard_input = StackConfig::getAll('inputs');

            $required_parameters = stack_input_factory::get_parameters_used();

            $all_parameters = array(
                'boxWidth' => $standard_input['input_box_size'],
                'strictSyntax' => $standard_input['input_strict_syntax'],
                'insertStars' => $standard_input['input_insert_stars'],
                'syntaxHint' => $standard_input['input_syntax_hint'],
                'syntaxAttribute' => $standard_input['input_syntax_attribute'],
                'forbidWords' => $standard_input['input_forbidden_words'],
                'allowWords' => $standard_input['input_allow_words'],
                'forbidFloats' => $standard_input['input_forbid_float'],
                'lowestTerms' => $standard_input['input_require_lowest_terms'],
                'sameType' => $standard_input['input_check_answer_type'],
                'mustVerify' => $standard_input['input_must_verify'],
                'showValidation' => $standard_input['input_show_validation'],
                'options' => $standard_input['input_extra_options'],
            );

            $parameters = array();

            foreach ($required_parameters[$standard_input['input_type']] as $parameter_name) {
                if ($parameter_name == 'inputType') {
                    continue;
                }
                $parameters[$parameter_name] = $all_parameters[$parameter_name];
            }

            $inputs["ans1"] = $this->buildInput("ans1", stack_input_factory::make('algebraic', 'ans1', 1, $this->question->options, $parameters), true);
        } else {
            $isFirst = true;

            $inputs_placeholders = stack_utils::extract_placeholders($this->question->getQuestion(), 'input');

            foreach ($this->question->inputs as $name => $input) {
                if (in_array($name, $inputs_placeholders)) {
                    $inputs[$name] = $this->buildInput($name, $input, $isFirst);
                    $isFirst = false;
                }
            }
        }

        return $inputs;
    }

    /**
     * @throws ilCtrlException
     */
    private function buildInput(string $name, stack_input $input, bool $isFirst): ExpandableSection
    {
        $inputs = [];

        $inputs["type"] = $this->factory->input()->field()->select($this->plugin->txt("input_type"), [
            "algebraic" => $this->plugin->txt('input_type_algebraic'),
            "boolean" => $this->plugin->txt('input_type_boolean'),
            "matrix" => $this->plugin->txt('input_type_matrix'),
            "varmatrix" => $this->plugin->txt('input_type_varmatrix'),
            "singlechar" => $this->plugin->txt('input_type_singlechar'),
            "textarea" => $this->plugin->txt('input_type_textarea'),
            "checkbox" => $this->plugin->txt('input_type_checkbox'),
            "dropdown" => $this->plugin->txt('input_type_dropdown'),
            "equiv" => $this->plugin->txt('input_type_equiv'),
            "notes" => $this->plugin->txt('input_type_notes'),
            "radio" => $this->plugin->txt('input_type_radio'),
            "units" => $this->plugin->txt('input_type_units'),
            "string" => $this->plugin->txt('input_type_string'),
            "numerical" => $this->plugin->txt('input_type_numerical')
        ], $this->plugin->txt("input_type_info"))->withRequired(true)
            ->withValue(assStackQuestionUtils::_getInputType($input));
        $inputs["teacher_answer"] = $this->factory->input()->field()->text($this->plugin->txt("input_model_answer"), $this->plugin->txt("input_model_answer_info"))->withRequired(true)
            ->withValue($input->get_teacher_answer());
        $inputs["boxWidth"] = $this->factory->input()->field()->numeric($this->plugin->txt("input_box_size"), $this->plugin->txt("input_box_size_info"))
            ->withValue($input->get_parameter('boxWidth'));
        $inputs["insertStars"] = $this->factory->input()->field()->select($this->plugin->txt("input_insert_stars"), [
            "0" => $this->plugin->txt('input_stars_no_stars'),
            "1" => $this->plugin->txt('input_stars_implied'),
            "2" => $this->plugin->txt('input_stars_singlechar'),
            "3" => $this->plugin->txt('input_stars_spaces'),
            "4" => $this->plugin->txt('input_stars_implied_spaces'),
            "5" => $this->plugin->txt('input_type_implied_spaces_single')
        ], $this->plugin->txt("input_insert_stars_info"))->withRequired(true)
            ->withValue($input->get_parameter('insertStars'));
        $inputs["syntaxHint"] = $this->factory->input()->field()->text($this->plugin->txt("input_syntax_hint"), $this->plugin->txt("input_syntax_hint_info"))
            ->withValue($input->get_parameter('syntaxHint', ""));
        $inputs["syntaxAttribute"] = $this->factory->input()->field()->select($this->plugin->txt("hint_mode"), [
            0 => $this->plugin->txt('value'),
            1 => $this->plugin->txt('placeholder')
        ])->withRequired(true)
            ->withValue($input->get_parameter('syntaxAttribute'));
        $inputs["forbidWords"] = $this->factory->input()->field()->text($this->plugin->txt("input_forbidden_words"), $this->plugin->txt("input_forbidden_words_info"))
            ->withValue((string) $input->get_parameter('forbidWords', ""));
        $inputs["forbidFloats"] = $this->factory->input()->field()->checkbox($this->plugin->txt("input_forbid_float"), $this->plugin->txt("input_forbid_float_info"))
            ->withValue(boolval($input->get_parameter('forbidFloats')));
        $inputs["allowWords"] = $this->factory->input()->field()->text($this->plugin->txt("input_allow_words"), $this->plugin->txt("input_allow_words_info"))
            ->withValue($input->get_parameter('allowWords', ""));
        $inputs["lowestTerms"] = $this->factory->input()->field()->checkbox($this->plugin->txt("input_require_lowest_terms"), $this->plugin->txt("input_require_lowest_terms_info"))
            ->withValue(boolval($input->get_parameter('lowestTerms')));
        $inputs["sameType"] = $this->factory->input()->field()->checkbox($this->plugin->txt("input_check_answer_type"), $this->plugin->txt("input_check_answer_type_info"))
            ->withValue(boolval($input->get_parameter('sameType')));
        $inputs["mustVerify"] = $this->factory->input()->field()->checkbox($this->plugin->txt("input_must_verify"), $this->plugin->txt("input_must_verify_info"))
            ->withValue(boolval($input->get_parameter('mustVerify')));
        $inputs["showValidation"] = $this->factory->input()->field()->select($this->plugin->txt("input_show_validation"), [
            0 => $this->plugin->txt('show_validation_no'),
            1 => $this->plugin->txt('show_validation_yes_with_vars'),
            2 => $this->plugin->txt('show_validation_yes_without_vars'),
            3 => $this->plugin->txt('show_validation_yes_compact')
        ], $this->plugin->txt("input_show_validation_info"))->withRequired(true)
            ->withValue($input->get_parameter('showValidation'));
        $inputs["options"] = $this->customFactory->casExpression($this->plugin->txt("input_options"), $this->plugin->txt("input_options_info"))
            ->withValue($input->get_parameter('options'));

        $this->ctrl->setParameterByClass("assStackQuestionGUI", "input_name", $name);
        $this->ctrl->clearParameterByClass("assStackQuestionGUI", "input_name");


        return $this->customFactory->expandableSection($inputs, $name)->withExpandedByDefault($isFirst);
    }

    /**
     * @throws stack_exception
     */
    private function buildPrtSection(): array
    {
        $prts = [];

        if (!empty($this->question->prts)) {
            $prts_placeholders = stack_utils::extract_placeholders($this->question->getQuestion() . $this->question->specific_feedback, 'feedback');

            foreach ($this->question->prts as $prt_name => $prt) {
                if (in_array($prt_name, $prts_placeholders)) {
                    $prts[$prt_name] = [$this->customFactory->columnSection([
                        "graph" => [
                            "graph" => $this->customFactory->legacy(stack_abstract_graph_svg_renderer::render($prt->get_prt_graph(), $prt->get_name() . 'graphsvg')),
                        ],
                        "prt" => $this->buildPrt($prt)
                    ], $prt_name)
                    ->withColumnStyles([
                        "graph" => [
                            "flex" => "0",
                            "border" => "0px solid #000",
                            "text-align" => "center",
                            "padding-left" => "50px",
                            "padding-right" => "50px"
                        ]
                    ])];
                }
            }
        }

        return $prts;
    }

    /**
     */
    private function buildPrt(stack_potentialresponse_tree_lite $prt): array
    {
        $inputs = [];

        $inputs["prt_name"] = $this->factory->input()->field()->text($this->plugin->txt("prt_name"), $this->plugin->txt("prt_name_info"))->withRequired(true)
            ->withValue($prt->get_name());
        $node_list = [];
        foreach ($prt->get_nodes() as $node_name => $prt_node) {
            $node_list[$node_name] = $node_name;
        }
        $inputs["settings"] = $this->customFactory->expandableSection($this->buildPrtOptions($prt), $this->plugin->txt("prt_settings_and_nodes"))->withExpandedByDefault(true);
        $node_labels = [];
        foreach (array_keys($prt->get_nodes()) as $node_name) {
            $node_labels[$node_name] = (string) ((int) $node_name + 1);
        }
        $inputs["nodes"] = $this->customFactory->tabSection($this->buildNodeSection($prt), $this->plugin->txt("prt_nodes"))
            ->withTabLabels($node_labels);

        return $inputs;
    }

    /**
     */
    private function buildPrtOptions(stack_potentialresponse_tree_lite $prt): array
    {
        $inputs = [];

        $inputs["prt_value"] = $this->factory->input()->field()->text($this->plugin->txt("prt_value"), $this->plugin->txt("prt_value_info"))->withRequired(true)
            ->withValue((string) $prt->get_value());
        $inputs["simplify"] = $this->factory->input()->field()->checkbox($this->plugin->txt("prt_simplify"), $this->plugin->txt("prt_simplify_info"))
            ->withValue($prt->is_simplify());
        $inputs["feedback_variables"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("prt_feedback_variables"), $this->plugin->txt("prt_feedback_variables_info"), false)
            ->withValue($prt->get_feedbackvariables_keyvals());

        $actions = [
            $this->factory->button()->standard($this->plugin->txt("copy_prt"), "")->withOnLoadCode(function ($id) use ($prt) {
                return $this->generateActionCode($id, "copyPrt", ["prt_name" => $prt->get_name()]);
            })
        ];

        if (isset($_SESSION['copy_prt'])) {
            $actions[] = $this->factory->button()->standard($this->plugin->txt("paste_prt"), "")->withOnLoadCode(function ($id) use ($prt) {
                return $this->generateActionCode($id, "pastePrt");
            });
        }

        $inputs["actions"] = $this->customFactory->buttonSection($actions, $this->plugin->txt("actions"));

        return $inputs;
    }

    /**
     */
    private function buildNodeSection(stack_potentialresponse_tree_lite $prt): array
    {
        $nodes = [];

        if (!empty($prt->get_nodes())) {
            foreach ($prt->get_nodes() as $node_name => $node) {
                $nodes[$node_name] = $this->buildNode($prt, $node);
            }
        }

        return $nodes;
    }

    /**
     */
    private function buildNode(stack_potentialresponse_tree_lite $prt, object $node): array
    {
        $inputs = [];

        $answer_tests = stack_ans_test_controller::get_available_ans_tests();

        $answer_test_choices = array_map(function ($string) {
            return stack_string($string);
        }, $answer_tests);

        $inputs["answer_test"] = $this->factory->input()->field()->select($this->plugin->txt("prt_node_answer_test"), $answer_test_choices, $this->plugin->txt("prt_node_answer_test_info"))->withRequired(true)
            ->withValue($node->answertest);
        $inputs["student_answer"] = $this->customFactory->casExpression($this->plugin->txt("prt_node_student_answer"), $this->plugin->txt("prt_node_student_answer_info"))->withRequired(true)
            ->withValue($node->sans);
        $inputs["teacher_answer"] = $this->customFactory->casExpression($this->plugin->txt("prt_node_teacher_answer"), $this->plugin->txt("prt_node_teacher_answer_info"))->withRequired(true)
            ->withValue($node->tans);
        $inputs["options"] = $this->factory->input()->field()->text($this->plugin->txt("prt_node_options"), $this->plugin->txt("prt_node_options_info"))
            ->withValue($node->testoptions);
        $inputs["quiet"] = $this->factory->input()->field()->select($this->plugin->txt("prt_node_quiet"), [
            0 => $this->lng->txt('no'),
            1 => $this->lng->txt('yes')
        ], $this->plugin->txt("prt_node_quiet_info"))->withRequired(true)
            ->withValue($node->quiet);

        $actions = [
            $this->factory->button()->standard($this->plugin->txt("create_node"), "")->withOnLoadCode(function ($id) use ($prt, $node) {
                return $this->generateActionCode($id, "createNode", ["prt_name" => $prt->get_name(), "node_name" => $node->nodename]);
            }),
            $this->factory->button()->standard($this->plugin->txt("delete_node"), "")->withOnLoadCode(function ($id) use ($prt, $node) {
                return $this->generateActionCode($id, "deleteNode", ["prt_name" => $prt->get_name(), "node_name" => $node->nodename]);
            }),
            $this->factory->button()->standard($this->plugin->txt("copy_node"), "")->withOnLoadCode(function ($id) use ($prt, $node) {
                return $this->generateActionCode($id, "copyNode", ["prt_name" => $prt->get_name(), "node_name" => $node->nodename]);
            })
        ];

        if (isset($_SESSION['copy_node'])) {
            $actions[] = $this->factory->button()->standard($this->plugin->txt("paste_node"), "")->withOnLoadCode(function ($id) use ($prt, $node) {
                return $this->generateActionCode($id, "pasteNode", ["to_prt_name" => $prt->get_name()]);
            });
        }

        $inputs["actions"] = $this->customFactory->buttonSection($actions, $this->plugin->txt("actions"));

        $inputs["feedback"] = $this->customFactory->columnSection([
                "positive" => $this->buildPositivePart($prt, $node),
                "negative" => $this->buildNegativePart($prt, $node)
            ], $this->plugin->txt("prt_node_feedback"))
            ->withColumnStyles([
                "positive" => [
                    "background" => "linear-gradient(45deg, #e2fff1, #a3ffd0);"
                ],
                "negative" => [
                    "background" => "linear-gradient(45deg, #ffe2e3, #ffa3a3);"
                ]
            ]);

        return $inputs;
    }

    private function buildPositivePart(stack_potentialresponse_tree_lite $prt, object $node): array
    {
        $inputs = [];

        $inputs["mode"] = $this->factory->input()->field()->select($this->plugin->txt("prt_node_pos_mod"), [
            "=" => "=",
            "+" => "+",
            "-" => "-"
        ], $this->plugin->txt("prt_node_pos_mod_info"))->withRequired(true)
            ->withValue($node->truescoremode);
        $inputs["score"] = $this->factory->input()->field()->text($this->plugin->txt("prt_node_pos_score"), $this->plugin->txt("prt_node_pos_score_info"))->withRequired(true)
            ->withValue((string) $node->truescore);
        $inputs["penalty"] = $this->factory->input()->field()->text($this->plugin->txt("prt_node_pos_penalty"), $this->plugin->txt("prt_node_pos_penalty_info"))->withRequired(true)
            ->withValue((string) $node->truepenalty);
        $node_list = $this->getNextNodeOptions($prt, $node);
        $inputs["next_node"] = $this->factory->input()->field()->select($this->plugin->txt("prt_node_pos_next"), $node_list, $this->plugin->txt("prt_node_pos_next_info"))->withRequired(true)
            ->withValue($node->truenextnode);
        $inputs["answernote"] = $this->factory->input()->field()->text($this->plugin->txt("prt_node_pos_answernote"), $this->plugin->txt("prt_node_pos_answernote_info"))->withRequired(true)
            ->withValue($node->trueanswernote);
        $inputs["specific_feedback"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("prt_node_pos_specific_feedback"), $this->plugin->txt("prt_node_pos_specific_feedback_info"))
            ->withValue($node->truefeedbackraw ?? $node->truefeedback ?? '');
        $inputs["feedback_class"] = $this->factory->input()->field()->select($this->plugin->txt('prt_node_pos_feedback_class'), $this->getFeedbackFormatOptions(), $this->plugin->txt('prt_node_pos_feedback_class_info'))->withRequired(true)
            ->withValue($node->truefeedbackstyle ?? 0);

        return $inputs;
    }

    private function buildNegativePart(stack_potentialresponse_tree_lite $prt, object $node): array
    {
        $inputs = [];

        $inputs["mode"] = $this->factory->input()->field()->select($this->plugin->txt("prt_node_neg_mod"), [
            "=" => "=",
            "+" => "+",
            "-" => "-"
        ], $this->plugin->txt("prt_node_neg_mod_info"))->withRequired(true)
            ->withValue($node->falsescoremode);
        $inputs["score"] = $this->factory->input()->field()->text($this->plugin->txt("prt_node_neg_score"), $this->plugin->txt("prt_node_neg_score_info"))->withRequired(true)
            ->withValue((string) $node->falsescore);
        $inputs["penalty"] = $this->factory->input()->field()->text($this->plugin->txt("prt_node_neg_penalty"), $this->plugin->txt("prt_node_neg_penalty_info"))->withRequired(true)
            ->withValue((string) $node->falsepenalty);
        $node_list = $this->getNextNodeOptions($prt, $node);
        $inputs["next_node"] = $this->factory->input()->field()->select($this->plugin->txt("prt_node_neg_next"), $node_list, $this->plugin->txt("prt_node_neg_next_info"))->withRequired(true)
            ->withValue($node->falsenextnode);
        $inputs["answernote"] = $this->factory->input()->field()->text($this->plugin->txt("prt_node_neg_answernote"), $this->plugin->txt("prt_node_neg_answernote_info"))->withRequired(true)
            ->withValue($node->falseanswernote);
        $inputs["specific_feedback"] = $this->customFactory->textareaRTE($this->question->getId(), $this->plugin->txt("prt_node_neg_specific_feedback"), $this->plugin->txt("prt_node_neg_specific_feedback_info"))
            ->withValue($node->falsefeedbackraw ?? $node->falsefeedback ?? '');
        $inputs["feedback_class"] = $this->factory->input()->field()->select($this->plugin->txt('prt_node_neg_feedback_class'), $this->getFeedbackFormatOptions(), $this->plugin->txt('prt_node_neg_feedback_class_info'))->withRequired(true)
            ->withValue($node->falsefeedbackstyle ?? 0);

        return $inputs;
    }

    private function getNextNodeOptions(stack_potentialresponse_tree_lite $prt, object $node): array
    {
        $node_list = [
            -1 => $this->plugin->txt('end')
        ];

        foreach ($prt->get_nodes_summary() as $node_name => $prt_node) {
            if ((int) $node_name !== -1 && (string) $node_name !== (string) $node->nodename) {
                $node_list[$node_name] = $prt_node->displayname;
            }
        }

        return $node_list;
    }

    private function getFeedbackFormatOptions(): array
    {
        if (!isset($this->feedback_format_options)) {
            $this->feedback_format_options = array(
                "0" => $this->lng->txt('default'),
            );

            $result = StackConfig::getAll("feedback_styles");

            foreach ($result as $name => $value) {
                if (str_contains($name, "feedback_styles_name_")) {
                    $id = str_replace("feedback_styles_name_", "", $name);

                    $this->feedback_format_options[$id] = $value;
                }
            }
        }

        return $this->feedback_format_options;
    }



    private function copyPrt(string $prt_name): bool
    {
        global $DIC;

        if(!isset($this->question->prts[$prt_name])) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->plugin->txt('copy_error_no_prt'), true);
            return false;
        }

        $_SESSION['copy_prt'] = $this->question->getId() . "_" . $prt_name;

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->plugin->txt('prt_copied_to_clipboard'), true);
        return true;
    }

    private function pastePrt(): bool
    {
        if (isset($_SESSION['copy_prt'])) {
            $raw_data = explode("_", $_SESSION['copy_prt']);
            $original_question_id = $raw_data[0];
            $original_prt_name = $raw_data[1];

            $generated_prt_name = "prt" . rand(20, 1000);

            if (assStackQuestionDB::_copyPRTFunction($original_question_id, $original_prt_name, (string)$this->question->getId(), $generated_prt_name)) {
                $this->question->specific_feedback = "<p>" . $this->question->specific_feedback . "[[feedback:" . $generated_prt_name . "]]</p>";

                $prt_from_db_array = assStackQuestionDB::_readPRTs($this->question->getId());

                $total_value = 0;

                foreach ($prt_from_db_array as $prt_db) {
                    $total_value += $prt_db->value;
                }

                foreach ($prt_from_db_array as $name => $prt_db) {
                    $prt_value = $prt_db->value / $total_value;
                    $this->question->prts[$name] = new stack_potentialresponse_tree_lite($prt_db, $prt_value);
                }

                assStackQuestionDB::updateSpecificFeedback($this->question->getId(), $this->question->specific_feedback);
            }
        }

        return true;
    }

    private function createNode(string $prt_name): bool
    {
        global $DIC;

        if (!isset($this->question->prts[$prt_name])) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->plugin->txt('node_create_error'), true);
            return false;
        }

        $prt = $this->question->prts[$prt_name];

        $max = 0;
        foreach ($prt->get_nodes() as $temp_node_name => $temp_node) {
            if ((int) $temp_node_name > $max) {
                $max = (int) $temp_node_name;
            }
        }

        $new_node_name = (string) ($max + 1);

        assStackQuestionDB::_createStackPrtNode(
            $this->question->getId(),
            $prt_name,
            $new_node_name
        );

        $nodes_from_db_array = assStackQuestionDB::_readPrtNodes($this->question->getId(), $prt_name);
        $prt->set_nodes($nodes_from_db_array);

        $this->question->prts[$prt_name] = $prt;

        assStackQuestionDB::_saveStackPRTs($this->question);

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->plugin->txt('node_created'), true);
        return true;
    }

    private function deleteNode(string $prt_name, string $node_name): bool
    {
        global $DIC;

        if(!isset($this->question->prts[$prt_name])) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->plugin->txt('deletion_error_no_prt'), true);
            return false;
        }

        $prt = $this->question->prts[$prt_name];

        $new_nodes = $prt->get_nodes();
        unset($new_nodes[$node_name]);

        foreach ($new_nodes as $n) {
            if ($n->truenextnode == $node_name) {
                $n->truenextnode = "-1";
            }
            if ($n->falsenextnode == $node_name) {
                $n->falsenextnode = "-1";
            }
        }

        $prt->set_nodes($new_nodes);
        $this->question->prts[$prt_name] = $prt;

        assStackQuestionDB::_saveStackPRTs($this->question);

        assStackQuestionDB::_deleteStackPrtNodes($this->question->getId(), $prt_name, $node_name);

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->plugin->txt('node_deleted'), true);
        return true;
    }

    private function copyNode(string $prt_name, string $node_name): bool
    {
        global $DIC;

        if(!isset($this->question->prts[$prt_name])) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->plugin->txt('copy_error_no_prt'), true);
            return false;
        }

        $_SESSION['copy_node'] = $this->question->getId() . "_" . $prt_name . "_" . $node_name;

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->plugin->txt('node_copied_to_clipboard'), true);
        return true;
    }

    private function pasteNode(string $to_prt_name): bool
    {
        global $DIC;

        if (isset($_SESSION['copy_node'])) {
            $raw_data = explode("_", $_SESSION['copy_node']);
            $original_question_id = $raw_data[0];
            $original_prt_name = $raw_data[1];
            $original_node_name = $raw_data[2];

            if (!isset($this->question->prts[$to_prt_name])) {
                $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->plugin->txt('paste_error_no_prt'), true);
                return false;
            }

            $prt = $this->question->prts[$to_prt_name];

            $max = 0;

            foreach ($prt->get_nodes() as $temp_node_name => $temp_node) {
                if ((int) $temp_node_name > $max) {
                    $max = (int) $temp_node_name;
                }
            }

            $new_node_name = $max + 1;

            assStackQuestionDB::_copyNodeFunction($original_question_id, $original_prt_name, $original_node_name, (string) $this->question->getId(), $to_prt_name, (string) $new_node_name);

            $nodes_from_db_array = assStackQuestionDB::_readPrtNodes($this->question->getId(), $to_prt_name);

            $prt->set_nodes($nodes_from_db_array);
        }

        return true;
    }

    /**
     * @throws ilTaxonomyException
     */
    private function buildTaxonomySection(): array
    {
        $taxonomies = [];

        foreach ($this->parent->getTaxonomyIds() as $taxonomyId) {
            $taxonomy = new ilObjTaxonomy($taxonomyId);

            $taxonomies[$taxonomyId] = $this->customFactory->taxonomySelect($taxonomy);

            $tax_node_ass = new ilTaxNodeAssignment(ilObject::_lookupType($this->question->getObjId()), $this->question->getObjId(), 'quest', $taxonomyId);
            $current_ass = $tax_node_ass->getAssignmentsOfItem($this->question->getId());

            if (!empty($current_ass)) {
                $value = [];

                foreach ($current_ass as $ca) {
                    $value[] = (int) $ca["node_id"];
                }

                $taxonomies[$taxonomyId] = $taxonomies[$taxonomyId]->withValue($value);
            }
        }

        return $taxonomies;
    }
}

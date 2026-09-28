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

/**
 * This class provides all the global variables needed within the stack folder
 *
 * @author Jesús Copado Mejías <stack@surlabs.es>
 * @version $Id: 7.1$
 * @ingroup    ModulesTestQuestionPool
 *
 */

/**
 * Simulating moodles global configuration
 */

use classes\platform\StackConfig;
use classes\platform\StackDatabase;
use classes\platform\StackException;

if (!defined('FORMAT_HTML')) {
    define('FORMAT_HTML', 0);
}
if (!defined('FORMAT_MARKDOWN')) {
    define('FORMAT_MARKDOWN', 1);
}
if (!defined('FORMAT_MOODLE')) {
    define('FORMAT_MOODLE', 2);
}
if (!defined('FORMAT_PLAIN')) {
    define('FORMAT_PLAIN', 3);
}
if (!defined('PARAM_PLUGIN')) {
    define('PARAM_PLUGIN', 'plugin');
}
if (!defined('PARAM_INT')) {
    define('PARAM_INT', 'int');
}
if (!defined('PARAM_RAW')) {
    define('PARAM_RAW', 'raw');
}
if (!defined('SQL_PARAMS_NAMED')) {
    define('SQL_PARAMS_NAMED', 'named');
}
if (!defined('IGNORE_MISSING')) {
    define('IGNORE_MISSING', 0);
}

$CFG = new stdClass;
// the base url of the installation (without script)
$CFG->wwwroot = (isset($GLOBALS['DIC']) && $GLOBALS['DIC']->offsetExists('ilIliasIniFile'))
    ? ilUtil::_getHttpPath()
    : '';
// the server path of the installation
$CFG->dirroot = realpath(dirname(__FILE__) . '/../..');
// the data directory of the plugin
$CFG->dataroot = (defined('ILIAS_WEB_DIR') && defined('CLIENT_ID'))
    ? ILIAS_WEB_DIR . "/" . CLIENT_ID . '/xqcas'
    : '';
$GLOBALS['CFG'] =& $CFG;

if (!class_exists('moodle_exception')) {
    class moodle_exception extends Exception
    {
    }
}

if (!class_exists('coding_exception')) {
    class coding_exception extends moodle_exception
    {
    }
}

if (!class_exists('moodle_url')) {
    class moodle_url
    {
        private string $url;
        private array $params;

        public function __construct($url = '', array $params = [])
        {
            $this->url = (string) $url;
            $this->params = $params;
        }

        public static function make_file_url(string $url, string $path = ''): self
        {
            if ($url === '/question/type/stack/plot.php') {
                return new self(stack_plot_url_base() . ltrim($path, '/'));
            }
            return new self($url . $path);
        }

        public function out(bool $escaped = true): string
        {
            $url = $this->url;
            if ($this->params !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($this->params);
            }

            return $escaped ? htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : $url;
        }

        public function params(): array
        {
            return $this->params;
        }

        public function __toString(): string
        {
            return $this->out(false);
        }
    }
}

if (!class_exists('html_table')) {
    class html_table
    {
        public array $head = [];
        public array $data = [];
        public array $align = [];
        public array $size = [];
        public array $wrap = [];
        public array $attributes = [];
        public array $headspan = [];
        public array $colclasses = [];
        public array $rowclasses = [];
        public string $tablealign = '';
        public string $id = '';
        public string $width = '';
        public string $summary = '';
        public string $cellpadding = '';
        public string $cellspacing = '';
    }
}

if (!class_exists('html_table_cell')) {
    class html_table_cell
    {
        public string $text = '';
        public bool $header = false;
        public string $scope = '';
        public array $attributes = ['class' => ''];
        public string $style = '';
        public int $colspan = 1;
        public int $rowspan = 1;
        public string $id = '';
        public string $abbr = '';

        public function __construct($text = '')
        {
            $this->text = (string) $text;
        }
    }
}

if (!class_exists('html_table_row')) {
    class html_table_row
    {
        public array $cells = [];
        public array $attributes = ['class' => ''];
        public string $style = '';
        public string $id = '';
    }
}

if (!class_exists('progress_bar')) {
    class progress_bar
    {
        public function __construct($name = '', $interval = 500, $autostart = true)
        {
        }

        public function update($current, $total, $message = ''): void
        {
        }
    }
}

if (!class_exists('context_system')) {
    class context_system
    {
        public static function instance(): self
        {
            return new self();
        }
    }
}

if (!class_exists('core_php_time_limit')) {
    class core_php_time_limit
    {
        public static function raise(int $seconds): void
        {
            @set_time_limit($seconds);
        }
    }
}

if (!class_exists('cache')) {
    class cache
    {
        public static function make($component, $area): self
        {
            return new self();
        }

        public function purge(): void
        {
        }
    }
}

if (!class_exists('moodle_database')) {
    class moodle_database
    {
        private ilDBInterface $db;

        public function __construct(ilDBInterface $db)
        {
            $this->db = $db;
        }

        public static function get_driver_instance($type, $library): self
        {
            global $DIC;
            return new self($DIC->database());
        }

        public function connect($host, $user, $password, $database, $prefix, array $options = []): void
        {
        }

        public function __call(string $name, array $arguments)
        {
            throw new moodle_exception("moodle_database::$name is not available in ILIAS.");
        }

        public function get_records(string $table, array $conditions = [], string $sort = ''): array
        {
            $mapped_table = $this->map_table($table);

            if (StackDatabase::isTableAllowed($mapped_table)) {
                $rows = StackDatabase::select($mapped_table, $conditions ?: null);
                $records = [];
                foreach ($rows as $row) {
                    $obj = (object) $row;
                    $records[$obj->id ?? count($records)] = $obj;
                }
                if ($sort !== '') {
                    uasort($records, fn($a, $b) => ($a->$sort ?? null) <=> ($b->$sort ?? null));
                }
                return $records;
            }

            // Fallback for tables outside StackDatabase's allow-list - these are Moodle-only
            // table names this vendored core code path never actually reaches in ILIAS.
            $sql = 'SELECT * FROM ' . $mapped_table . $this->where($conditions);
            if ($sort !== '') {
                $sql .= ' ORDER BY ' . $sort;
            }
            $res = $this->db->query($sql);
            $records = [];
            while ($row = $this->db->fetchObject($res)) {
                $records[$row->id ?? count($records)] = $row;
            }
            return $records;
        }

        public function insert_record(string $table, stdClass $data)
        {
            $mapped_table = $this->map_table($table);

            if (StackDatabase::isTableAllowed($mapped_table)) {
                $id = StackDatabase::nextId($mapped_table);
                $values = ['id' => $id];
                foreach (get_object_vars($data) as $field => $value) {
                    $values[$field] = $value;
                }
                StackDatabase::insert($mapped_table, $values);
                return $id;
            }

            $id = $this->db->nextId($mapped_table);
            $values = ['id' => ['integer', $id]];
            foreach (get_object_vars($data) as $field => $value) {
                $values[$field] = [is_int($value) ? 'integer' : 'clob', $value];
            }
            $this->db->insert($mapped_table, $values);
            return $id;
        }

        public function delete_records(string $table, array $conditions = []): void
        {
            $mapped_table = $this->map_table($table);

            // Empty $conditions means "delete all rows" (e.g. clearing the whole CAS
            // cache) - StackDatabase::delete() always appends a WHERE clause, so it
            // can't express that; keep direct access for that case.
            if ($conditions !== [] && StackDatabase::isTableAllowed($mapped_table)) {
                StackDatabase::delete($mapped_table, $conditions);
                return;
            }

            $this->db->manipulate('DELETE FROM ' . $mapped_table . $this->where($conditions));
        }

        public function delete_records_list(string $table, string $field, array $values): void
        {
            if ($values === []) {
                return;
            }
            $quoted = array_map(fn($value) => $this->db->quote($value, 'integer'), $values);
            $this->db->manipulate(
                'DELETE FROM ' . $this->map_table($table) . ' WHERE ' . $field . ' IN (' . implode(',', $quoted) . ')'
            );
        }

        public function count_records(string $table): int
        {
            $res = $this->db->query('SELECT COUNT(*) cnt FROM ' . $this->map_table($table));
            $row = $this->db->fetchObject($res);
            return (int) ($row->cnt ?? 0);
        }

        private function map_table(string $table): string
        {
            return match ($table) {
                'qtype_stack_cas_cache' => 'xqcas_cas_cache',
                default => $table,
            };
        }

        private function where(array $conditions): string
        {
            if ($conditions === []) {
                return '';
            }
            $parts = [];
            foreach ($conditions as $field => $value) {
                $parts[] = $field . ' = ' . $this->db->quote($value, is_int($value) ? 'integer' : 'text');
            }
            return ' WHERE ' . implode(' AND ', $parts);
        }
    }
}

global $DIC, $DB;
if (!isset($DB) && isset($DIC)) {
    $DB = new moodle_database($DIC->database());
}


if (!function_exists('getLanguage')) {
    function getLanguage()
    {
        global $DIC;

        $lng = $DIC->language();

        return $lng->getUserLanguage();
    }

}

if (!function_exists('getString')) {
    function getString($identifier, $string, $a = null)
    {
        $string = $string[$identifier];
        if ($a !== NULL) {
            if (is_object($a) or is_array($a)) {
                $a = (array)$a;
                $search = array();
                $replace = array();
                foreach ($a as $key => $value) {
                    if (is_int($key)) {
                        // we do not support numeric keys - sorry!
                        continue;
                    }
                    $search[] = '{$a->' . $key . '}';
                    $replace[] = (string)$value;
                }
                if ($search) {
                    $string = str_replace($search, $replace, $string);
                }
            } else {
                $string = str_replace('{$a}', (string)$a, $string);
            }
        }

        return $string;
    }
}

if (!function_exists('get_string')) {
    function get_string($key, $component = 'qtype_stack', $a = null): string
    {
        if ($a === null && $component !== 'qtype_stack' && !is_string($component)) {
            $a = $component;
            $component = 'qtype_stack';
        }

        if ($component !== 'qtype_stack') {
            return (string) $key;
        }

        return stack_string($key, $a);
    }
}

/**
 * Translates a string taken as output from Maxima.
 *
 * This function takes a variable number of arguments, the first of which is assumed to be the identifier
 * of the string to be translated.
 */
if (!function_exists('stack_trans')) {
    function stack_trans()
    {
        $nargs = func_num_args();

        if ($nargs > 0) {
            $arg_list = func_get_args();
            $identifier = func_get_arg(0);
            $a = array();
            if ($nargs > 1) {
                for ($i = 1; $i < $nargs; $i++) {
                    $index = $i - 1;
                    $a["m{$index}"] = func_get_arg($i);
                }
            }
            $return = stack_string($identifier, $a);
            echo $return;
        }
    }
}
/**
 * EXCEPTIONS
 */

/**
 * A Moodle-$CFG-shaped adapter over StackConfig, consumed by classes/stack/**.
 * Do not add config storage logic here, only key translation - StackConfig
 * (classes/platform/StackConfig.php) is the single source of truth.
 */
if (!function_exists('get_config')) {
    function get_config($component = 'qtype_stack', $parameter = null) {
        global $CFG;

        // Si no se solicita un parámetro específico, devolver toda la configuración
        if ($parameter === null) {
            $configs = new stdClass();
            $saved_config = StackConfig::getAll();

            /*
             * CONNECTION CONFIGURATION
             */
            $configs->platform = 'server';
            $configs->maximaversion = $saved_config['maxima_version'];
            $configs->castimeout = $saved_config['cas_connection_timeout'];
            $configs->casresultscache = $saved_config['cas_result_caching'];
            $configs->serveruserpass = $saved_config['serveruserpass'] ?? '';

            $configs->maximacommand = $saved_config['maxima_pool_url'];
            $configs->maximacommandserver = $saved_config['maxima_pool_url'];
            $configs->plotcommand = '';

            if ($saved_config["maxima_uses_proxy"] == "1") {
                $configs->platform = "server-proxy";
            }
            $configs->casdebugging = $saved_config['cas_debugging'] == 1;

            /*
             * DISPLAY CONFIGURATION
             */
            $configs->ajaxvalidation = $saved_config['instant_validation'];
            $configs->mathsdisplay = $saved_config['maths_filter'];
            $configs->replacedollars = $saved_config['replace_dollars'];

            /*
             * DEFAULT OPTIONS CONFIGURATION
             */
            $configs->questionsimplify = $saved_config['options_question_simplify'];
            $configs->assumepositive = $saved_config['options_assume_positive'];
            $configs->prtcorrect = $saved_config['options_prt_correct'];
            $configs->prtpartiallycorrect = $saved_config['options_prt_partially_correct'];
            $configs->prtincorrect = $saved_config['options_prt_incorrect'];
            $configs->multiplicationsign = $saved_config['options_multiplication_sign'];
            $configs->sqrtsign = $saved_config['options_sqrt_sign'];
            $configs->complexno = $saved_config['options_complex_numbers'];
            $configs->inversetrig = $saved_config['options_inverse_trigonometric'];
            $configs->matrixparens = "[";

            $configs->assumereal = $saved_config['options_assume_real'];
            $configs->logicsymbol = $saved_config['options_logic_symbol'];

            /*
             * DEFAULT INPUTS CONFIGURATION
             */
            $configs->inputtype = $saved_config['input_type'];
            $configs->inputboxsize = $saved_config['input_box_size'];
            $configs->inputstrictsyntax = $saved_config['input_strict_syntax'];
            $configs->inputinsertstars = $saved_config['input_insert_stars'];
            $configs->inputforbidwords = $saved_config['input_forbidden_words'];
            $configs->inputforbidfloat = $saved_config['input_forbid_float'];
            $configs->inputrequirelowestterms = $saved_config['input_require_lowest_terms'];
            $configs->inputcheckanswertype = $saved_config['input_check_answer_type'];
            $configs->inputmustverify = $saved_config['input_must_verify'];
            $configs->inputshowvalidation = $saved_config['input_show_validation'];

            $configs->maximalocalfolder = realpath($CFG->dataroot) . '/stack';
            $configs->stackmaximaversion = "2026062900";
            $configs->version = "2026062900";

            $configs->geogebrabaseurl = $saved_config['geogebra_base_url'] ?? '';
            $configs->maximalibraries = $saved_config['cas_maxima_libraries'] ?? '';

            return $configs;
        }

        // Si se solicita un parámetro específico, devolverlo si existe
        if (property_exists($CFG, $parameter)) {
            return $CFG->$parameter;
        }

        return "";
    }
}

/**
 * Simple html output class
 *
 * @copyright 2009 Tim Hunt, 2010 Petr Skoda
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.0
 * @package core
 * @category output
 */
if (!class_exists('html_writer')) {

    class html_writer
    {

        /**
         * Outputs a tag with attributes and contents
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @param string $contents What goes between the opening and closing tags
         * @param array|null $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function tag($tagname, $contents, ?array $attributes = null)
        {
            return self::start_tag($tagname, $attributes) . $contents . self::end_tag($tagname);
        }

        /**
         * Outputs an opening tag with attributes
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @param array|null $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function start_tag($tagname, ?array $attributes = null)
        {
            return '<' . $tagname . self::attributes($attributes) . '>';
        }

        /**
         * Outputs a closing tag
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @return string HTML fragment
         */
        public static function end_tag($tagname)
        {
            return '</' . $tagname . '>';
        }

        /**
         * Outputs an empty tag with attributes
         *
         * @param string $tagname The name of tag ('input', 'img', 'br' etc.)
         * @param array|null $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function empty_tag($tagname, ?array $attributes = null)
        {
            return '<' . $tagname . self::attributes($attributes) . ' />';
        }

        /**
         * Outputs a tag, but only if the contents are not empty
         *
         * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
         * @param string $contents What goes between the opening and closing tags
         * @param array|null $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         * @return string HTML fragment
         */
        public static function nonempty_tag($tagname, $contents, ?array $attributes = null)
        {
            if ($contents === '' || is_null($contents)) {
                return '';
            }

            return self::tag($tagname, $contents, $attributes);
        }

        /**
         * Outputs a HTML attribute and value
         *
         * @param string $name The name of the attribute ('src', 'href', 'class' etc.)
         * @param string $value The value of the attribute. The value will be escaped with {@link s()}
         * @return string HTML fragment
         */
        public static function attribute($name, $value)
        {
            if ($value instanceof moodle_url) {
                return ' ' . $name . '="' . $value->out() . '"';
            }

            // special case, we do not want these in output
            if ($value === null) {
                return '';
            }

            // no sloppy trimming here!
            return ' ' . $name . '="' . s($value) . '"';
        }


        /**
         * Outputs a list of HTML attributes and values
         *
         * @param array|null $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
         *       The values will be escaped with {@link s()}
         * @return string HTML fragment
         */
        public static function attributes(?array $attributes = null)
        {
            $attributes = (array)$attributes;
            $output = '';
            foreach ($attributes as $name => $value) {
                $output .= self::attribute($name, $value);
            }

            return $output;
        }

        /**
         * Generates random html element id.
         *
         * @staticvar int $counter
         * @staticvar type $uniq
         * @param string $base A string fragment that will be included in the random ID.
         * @return string A unique ID
         */
        public static function random_id($base = 'random')
        {
            static $counter = 0;
            static $uniq;

            if (!isset($uniq)) {
                $uniq = uniqid();
            }

            $counter++;

            return $base . $uniq . $counter;
        }

        /**
         * Generates a simple html link
         *
         * @param string|moodle_url $url The URL
         * @param string $text The text
         * @param array|null $attributes HTML attributes
         * @return string HTML fragment
         */
        public static function link($url, $text, ?array $attributes = null)
        {
            $attributes = (array)$attributes;
            $attributes['href'] = $url;

            return self::tag('a', $text, $attributes);
        }

        /**
         * Generates a simple checkbox with optional label
         *
         * @param string $name The name of the checkbox
         * @param string $value The value of the checkbox
         * @param bool $checked Whether the checkbox is checked
         * @param string $label The label for the checkbox
         * @param array|null $attributes Any attributes to apply to the checkbox
         * @return string html fragment
         */
        public static function checkbox($name, $value, $checked = true, $label = '', ?array $attributes = null)
        {
            $attributes = (array)$attributes;
            $output = '';

            if ($label !== '' and !is_null($label)) {
                if (empty($attributes['id'])) {
                    $attributes['id'] = self::random_id('checkbox_');
                }
            }
            $attributes['type'] = 'checkbox';
            $attributes['value'] = $value;
            $attributes['name'] = $name;
            $attributes['checked'] = $checked ? 'checked' : null;

            $output .= self::empty_tag('input', $attributes);

            if ($label !== '' and !is_null($label)) {
                $output .= self::tag('label', $label, array('for' => $attributes['id']));
            }

            return $output;
        }

        /**
         * Generates a simple select yes/no form field
         *
         * @param string $name name of select element
         * @param bool $selected
         * @param array|null $attributes - html select element attributes
         * @return string HTML fragment
         */
        public static function select_yes_no($name, $selected = true, ?array $attributes = null)
        {
            $options = array('1' => get_string('yes'), '0' => get_string('no'));

            return self::select($options, $name, $selected, null, $attributes);
        }

        /**
         * Generates a simple select form field
         *
         * @param array $options associative array value=>label ex.:
         *                array(1=>'One, 2=>Two)
         *              it is also possible to specify optgroup as complex label array ex.:
         *                array(array('Odd'=>array(1=>'One', 3=>'Three)), array('Even'=>array(2=>'Two')))
         *                array(1=>'One', '--1uniquekey'=>array('More'=>array(2=>'Two', 3=>'Three')))
         * @param string $name name of select element
         * @param string|array $selected value or array of values depending on multiple attribute
         * @param array|bool $nothing add nothing selected option, or false of not added
         * @param array|null $attributes html select element attributes
         * @return string HTML fragment
         */
        public static function select(array $options, $name, $selected = '', $nothing = array('' => 'choosedots'), ?array $attributes = null)
        {
            $attributes = (array)$attributes;
            if (is_array($nothing)) {
                foreach ($nothing as $k => $v) {
                    if ($v === 'choose' or $v === 'choosedots') {
                        $nothing[$k] = get_string('choosedots');
                    }
                }
                $options = $nothing + $options; // keep keys, do not override
            } else {
                if (is_string($nothing) and $nothing !== '') {
                    // BC
                    $options = array('' => $nothing) + $options;
                }
            }

            // we may accept more values if multiple attribute specified
            $selected = (array)$selected;
            foreach ($selected as $k => $v) {
                $selected[$k] = (string)$v;
            }

            if (!isset($attributes['id'])) {
                $id = 'menu' . $name;
                // name may contaion [], which would make an invalid id. e.g. numeric question type editing form, assignment quickgrading
                $id = str_replace('[', '', $id);
                $id = str_replace(']', '', $id);
                $attributes['id'] = $id;
            }

            if (!isset($attributes['class'])) {
                $class = 'menu' . $name;
                // name may contaion [], which would make an invalid class. e.g. numeric question type editing form, assignment quickgrading
                $class = str_replace('[', '', $class);
                $class = str_replace(']', '', $class);
                $attributes['class'] = $class;
            }
            $attributes['class'] = 'select ' . $attributes['class']; // Add 'select' selector always

            $attributes['name'] = $name;

            if (!empty($attributes['disabled'])) {
                $attributes['disabled'] = 'disabled';
            } else {
                unset($attributes['disabled']);
            }

            $output = '';
            foreach ($options as $value => $label) {
                if (is_array($label)) {
                    // ignore key, it just has to be unique
                    $output .= self::select_optgroup(key($label), current($label), $selected);
                } else {
                    $output .= self::select_option($label, $value, $selected);
                }
            }

            return self::tag('select', $output, $attributes);
        }

        /**
         * Returns HTML to display a select box option.
         *
         * @param string $label The label to display as the option.
         * @param string|int $value The value the option represents
         * @param array $selected An array of selected options
         * @return string HTML fragment
         */
        private static function select_option($label, $value, array $selected)
        {
            $attributes = array();
            $value = (string)$value;
            if (in_array($value, $selected, true)) {
                $attributes['selected'] = 'selected';
            }
            $attributes['value'] = $value;

            return self::tag('option', $label, $attributes);
        }

        /**
         * Returns HTML to display a select box option group.
         *
         * @param string $groupname The label to use for the group
         * @param array $options The options in the group
         * @param array $selected An array of selected values.
         * @return string HTML fragment.
         */
        private static function select_optgroup($groupname, $options, array $selected)
        {
            if (empty($options)) {
                return '';
            }
            $attributes = array('label' => $groupname);
            $output = '';
            foreach ($options as $value => $label) {
                $output .= self::select_option($label, $value, $selected);
            }

            return self::tag('optgroup', $output, $attributes);
        }

        /**
         * This is a shortcut for making an hour selector menu.
         *
         * @param string $type The type of selector (years, months, days, hours, minutes)
         * @param string $name fieldname
         * @param int $currenttime A default timestamp in GMT
         * @param int $step minute spacing
         * @param array|null $attributes - html select element attributes
         * @return HTML fragment
         */
        public static function select_time($type, $name, $currenttime = 0, $step = 5, ?array $attributes = null)
        {
            if (!$currenttime) {
                $currenttime = time();
            }
            $currentdate = usergetdate($currenttime);
            $userdatetype = $type;
            $timeunits = array();

            switch ($type) {
                case 'years':
                    for ($i = 1970; $i <= 2020; $i++) {
                        $timeunits[$i] = $i;
                    }
                    $userdatetype = 'year';
                    break;
                case 'months':
                    for ($i = 1; $i <= 12; $i++) {
                        $timeunits[$i] = userdate(gmmktime(12, 0, 0, $i, 15, 2000), "%B");
                    }
                    $userdatetype = 'month';
                    $currentdate['month'] = (int)$currentdate['mon'];
                    break;
                case 'days':
                    for ($i = 1; $i <= 31; $i++) {
                        $timeunits[$i] = $i;
                    }
                    $userdatetype = 'mday';
                    break;
                case 'hours':
                    for ($i = 0; $i <= 23; $i++) {
                        $timeunits[$i] = sprintf("%02d", $i);
                    }
                    break;
                case 'minutes':
                    if ($step != 1) {
                        $currentdate['minutes'] = ceil($currentdate['minutes'] / $step) * $step;
                    }

                    for ($i = 0; $i <= 59; $i += $step) {
                        $timeunits[$i] = sprintf("%02d", $i);
                    }
                    break;
                default:
                    throw new Exception("Time type $type is not supported by html_writer::select_time().");
            }

            if (empty($attributes['id'])) {
                $attributes['id'] = self::random_id('ts_');
            }
            $timerselector = self::select($timeunits, $name, $currentdate[$userdatetype], null, array('id' => $attributes['id']));
            $label = self::tag('label', get_string(substr($type, 0, -1), 'form'), array('for' => $attributes['id'], 'class' => 'accesshide'));

            return $label . $timerselector;
        }

        /**
         * Shortcut for quick making of lists
         *
         * Note: 'list' is a reserved keyword ;-)
         *
         * @param array $items
         * @param array|null $attributes
         * @param string $tag ul or ol
         * @return string
         */
        public static function alist(array $items, ?array $attributes = null, $tag = 'ul')
        {
            $output = '';

            foreach ($items as $item) {
                $output .= html_writer::start_tag('li') . "\n";
                $output .= $item . "\n";
                $output .= html_writer::end_tag('li') . "\n";
            }

            return html_writer::tag($tag, $output, $attributes);
        }

        /**
         * Returns hidden input fields created from url parameters.
         *
         * @param moodle_url $url
         * @param array|null $exclude list of excluded parameters
         * @return string HTML fragment
         */
        public static function input_hidden_params(moodle_url $url, ?array $exclude = null)
        {
            $exclude = (array)$exclude;
            $params = $url->params();
            foreach ($exclude as $key) {
                unset($params[$key]);
            }

            $output = '';
            foreach ($params as $key => $value) {
                $attributes = array('type' => 'hidden', 'name' => $key, 'value' => $value);
                $output .= self::empty_tag('input', $attributes) . "\n";
            }

            return $output;
        }

        /**
         * Generate a script tag containing the the specified code.
         *
         * @param string $jscode the JavaScript code
         * @param moodle_url|string $url optional url of the external script, $code ignored if specified
         * @return string HTML, the code wrapped in <script> tags.
         */
        public static function script($jscode, $url = null)
        {
            if ($jscode) {
                $attributes = array('type' => 'text/javascript');

                return self::tag('script', "\n//<![CDATA[\n$jscode\n//]]>\n", $attributes) . "\n";
            } else {
                if ($url) {
                    $attributes = array('type' => 'text/javascript', 'src' => $url);

                    return self::tag('script', '', $attributes) . "\n";
                } else {
                    return '';
                }
            }
        }

        /**
         * Renders HTML table
         *
         * This method may modify the passed instance by adding some default properties if they are not set yet.
         * If this is not what you want, you should make a full clone of your data before passing them to this
         * method. In most cases this is not an issue at all so we do not clone by default for performance
         * and memory consumption reasons.
         *
         * @param html_table $table data to be rendered
         * @return string HTML code
         */
        public static function table(html_table $table)
        {
            // prepare table data and populate missing properties with reasonable defaults
            if (!empty($table->align)) {
                foreach ($table->align as $key => $aa) {
                    if ($aa) {
                        $table->align[$key] = 'text-align:' . fix_align_rtl($aa) . ';'; // Fix for RTL languages
                    } else {
                        $table->align[$key] = null;
                    }
                }
            }
            if (!empty($table->size)) {
                foreach ($table->size as $key => $ss) {
                    if ($ss) {
                        $table->size[$key] = 'width:' . $ss . ';';
                    } else {
                        $table->size[$key] = null;
                    }
                }
            }
            if (!empty($table->wrap)) {
                foreach ($table->wrap as $key => $ww) {
                    if ($ww) {
                        $table->wrap[$key] = 'white-space:nowrap;';
                    } else {
                        $table->wrap[$key] = '';
                    }
                }
            }
            if (!empty($table->head)) {
                foreach ($table->head as $key => $val) {
                    if (!isset($table->align[$key])) {
                        $table->align[$key] = null;
                    }
                    if (!isset($table->size[$key])) {
                        $table->size[$key] = null;
                    }
                    if (!isset($table->wrap[$key])) {
                        $table->wrap[$key] = null;
                    }
                }
            }
            if (empty($table->attributes['class'])) {
                $table->attributes['class'] = 'generaltable';
            }
            if (!empty($table->tablealign)) {
                $table->attributes['class'] .= ' boxalign' . $table->tablealign;
            }

            // explicitly assigned properties override those defined via $table->attributes
            $table->attributes['class'] = trim($table->attributes['class']);
            $attributes = array_merge($table->attributes, array('id' => $table->id, 'width' => $table->width, 'summary' => $table->summary, 'cellpadding' => $table->cellpadding, 'cellspacing' => $table->cellspacing,));
            $output = html_writer::start_tag('table', $attributes) . "\n";

            $countcols = 0;

            if (!empty($table->head)) {
                $countcols = count($table->head);

                $output .= html_writer::start_tag('thead', array()) . "\n";
                $output .= html_writer::start_tag('tr', array()) . "\n";
                $keys = array_keys($table->head);
                $lastkey = end($keys);

                foreach ($table->head as $key => $heading) {
                    // Convert plain string headings into html_table_cell objects
                    if (!($heading instanceof html_table_cell)) {
                        $headingtext = $heading;
                        $heading = new html_table_cell();
                        $heading->text = $headingtext;
                        $heading->header = true;
                    }

                    if ($heading->header !== false) {
                        $heading->header = true;
                    }

                    if ($heading->header && empty($heading->scope)) {
                        $heading->scope = 'col';
                    }

                    $heading->attributes['class'] .= ' header c' . $key;
                    if (isset($table->headspan[$key]) && $table->headspan[$key] > 1) {
                        $heading->colspan = $table->headspan[$key];
                        $countcols += $table->headspan[$key] - 1;
                    }

                    if ($key == $lastkey) {
                        $heading->attributes['class'] .= ' lastcol';
                    }
                    if (isset($table->colclasses[$key])) {
                        $heading->attributes['class'] .= ' ' . $table->colclasses[$key];
                    }
                    $heading->attributes['class'] = trim($heading->attributes['class']);
                    $attributes = array_merge($heading->attributes, array('style' => $table->align[$key] . $table->size[$key] . $heading->style, 'scope' => $heading->scope, 'colspan' => $heading->colspan,));

                    $tagtype = 'td';
                    if ($heading->header === true) {
                        $tagtype = 'th';
                    }
                    $output .= html_writer::tag($tagtype, $heading->text, $attributes) . "\n";
                }
                $output .= html_writer::end_tag('tr') . "\n";
                $output .= html_writer::end_tag('thead') . "\n";

                if (empty($table->data)) {
                    // For valid XHTML strict every table must contain either a valid tr
                    // or a valid tbody... both of which must contain a valid td
                    $output .= html_writer::start_tag('tbody', array('class' => 'empty'));
                    $output .= html_writer::tag('tr', html_writer::tag('td', '', array('colspan' => count($table->head))));
                    $output .= html_writer::end_tag('tbody');
                }
            }

            if (!empty($table->data)) {
                $oddeven = 1;
                $keys = array_keys($table->data);
                $lastrowkey = end($keys);
                $output .= html_writer::start_tag('tbody', array());

                foreach ($table->data as $key => $row) {
                    if (($row === 'hr') && ($countcols)) {
                        $output .= html_writer::tag('td', html_writer::tag('div', '', array('class' => 'tabledivider')), array('colspan' => $countcols));
                    } else {
                        // Convert array rows to html_table_rows and cell strings to html_table_cell objects
                        if (!($row instanceof html_table_row)) {
                            $newrow = new html_table_row();

                            foreach ($row as $cell) {
                                if (!($cell instanceof html_table_cell)) {
                                    $cell = new html_table_cell($cell);
                                }
                                $newrow->cells[] = $cell;
                            }
                            $row = $newrow;
                        }

                        $oddeven = $oddeven ? 0 : 1;
                        if (isset($table->rowclasses[$key])) {
                            $row->attributes['class'] .= ' ' . $table->rowclasses[$key];
                        }

                        $row->attributes['class'] .= ' r' . $oddeven;
                        if ($key == $lastrowkey) {
                            $row->attributes['class'] .= ' lastrow';
                        }

                        $output .= html_writer::start_tag('tr', array('class' => trim($row->attributes['class']), 'style' => $row->style, 'id' => $row->id)) . "\n";
                        $keys2 = array_keys($row->cells);
                        $lastkey = end($keys2);

                        $gotlastkey = false; //flag for sanity checking
                        foreach ($row->cells as $key => $cell) {
                            if ($gotlastkey) {
                                //This should never happen. Why do we have a cell after the last cell?
                                mtrace("A cell with key ($key) was found after the last key ($lastkey)");
                            }

                            if (!($cell instanceof html_table_cell)) {
                                $mycell = new html_table_cell();
                                $mycell->text = $cell;
                                $cell = $mycell;
                            }

                            if (($cell->header === true) && empty($cell->scope)) {
                                $cell->scope = 'row';
                            }

                            if (isset($table->colclasses[$key])) {
                                $cell->attributes['class'] .= ' ' . $table->colclasses[$key];
                            }

                            $cell->attributes['class'] .= ' cell c' . $key;
                            if ($key == $lastkey) {
                                $cell->attributes['class'] .= ' lastcol';
                                $gotlastkey = true;
                            }
                            $tdstyle = '';
                            $tdstyle .= isset($table->align[$key]) ? $table->align[$key] : '';
                            $tdstyle .= isset($table->size[$key]) ? $table->size[$key] : '';
                            $tdstyle .= isset($table->wrap[$key]) ? $table->wrap[$key] : '';
                            $cell->attributes['class'] = trim($cell->attributes['class']);
                            $tdattributes = array_merge($cell->attributes, array('style' => $tdstyle . $cell->style, 'colspan' => $cell->colspan, 'rowspan' => $cell->rowspan, 'id' => $cell->id, 'abbr' => $cell->abbr, 'scope' => $cell->scope,));
                            $tagtype = 'td';
                            if ($cell->header === true) {
                                $tagtype = 'th';
                            }
                            $output .= html_writer::tag($tagtype, $cell->text, $tdattributes) . "\n";
                        }
                    }
                    $output .= html_writer::end_tag('tr') . "\n";
                }
                $output .= html_writer::end_tag('tbody') . "\n";
            }
            $output .= html_writer::end_tag('table') . "\n";

            return $output;
        }

        /**
         * Renders form element label
         *
         * By default, the label is suffixed with a label separator defined in the
         * current language pack (colon by default in the English lang pack).
         * Adding the colon can be explicitly disabled if needed. Label separators
         * are put outside the label tag itself so they are not read by
         * screenreaders (accessibility).
         *
         * Parameter $for explicitly associates the label with a form control. When
         * set, the value of this attribute must be the same as the value of
         * the id attribute of the form control in the same document. When null,
         * the label being defined is associated with the control inside the label
         * element.
         *
         * @param string $text content of the label tag
         * @param string|null $for id of the element this label is associated with, null for no association
         * @param bool $colonize add label separator (colon) to the label text, if it is not there yet
         * @param array $attributes to be inserted in the tab, for example array('accesskey' => 'a')
         * @return string HTML of the label element
         */
        public static function label($text, $for, $colonize = true, array $attributes = array())
        {
            if (!is_null($for)) {
                $attributes = array_merge($attributes, array('for' => $for));
            }
            $text = trim($text);
            $label = self::tag('label', $text, $attributes);

            // TODO MDL-12192 $colonize disabled for now yet
            // if (!empty($text) and $colonize) {
            //     // the $text may end with the colon already, though it is bad string definition style
            //     $colon = get_string('labelsep', 'langconfig');
            //     if (!empty($colon)) {
            //         $trimmed = trim($colon);
            //         if ((substr($text, -strlen($trimmed)) == $trimmed) or (substr($text, -1) == ':')) {
            //             //debugging('The label text should not end with colon or other label separator,
            //             //           please fix the string definition.', DEBUG_DEVELOPER);
            //         } else {
            //             $label .= $colon;
            //         }
            //     }
            // }

            return $label;
        }


    }
}

/**
 * Add quotes to HTML characters.
 *
 * Returns $var with HTML characters (like "<", ">", etc.) properly quoted.
 * This function is very similar to {@link p()}
 *
 * @param string $var the string potentially containing HTML characters
 * @return string
 */
if (!function_exists('s')) {
    function s($var)
    {

        if ($var === false) {
            return '0';
        }

        // When we move to PHP 5.4 as a minimum version, change ENT_QUOTES on the
        // next line to ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, and remove the
        // 'UTF-8' argument. Both bring a speed-increase.
        return preg_replace('/&amp;#(\d+|x[0-9a-f]+);/i', '&#$1;', htmlspecialchars($var, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('make_upload_directory')) {
    function make_upload_directory($path) {
        $path = realpath("./" . ILIAS_WEB_DIR . "/" . CLIENT_ID) . '/xqcas/' . $path;

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}

if (!function_exists('stack_plot_url_base')) {
    function stack_plot_url_base(): string {
        return \classes\platform\StackRender::getPlotUrlBase();
    }
}

if (!function_exists('stack_cors_link')) {
    function stack_cors_link(string $filename): string {
        return \classes\platform\StackRender::getCorsLink($filename);
    }
}

if (!function_exists('stack_get_system_language')) {
    function stack_get_system_language(): string {
        global $DIC;

        $language = $DIC->user()->getLanguage();

        if (!$language) {
            $language = 'en';
        }

        return $language;
    }
}

if (!function_exists("format_text")) {
    function format_text($text, $format = FORMAT_HTML, $options = null)
    {
        return $text;
    }
}

if (!function_exists("stack_fetch_included_content")) {
    function stack_fetch_included_content(string $url) {
        static $cache = [];
        $lc = trim(strtolower($url));
        $good = false;
        $islocalfile = false;
        // Not actually passing the $error out now, it is here for documentation
        // and possible future use.
        $error = 'Not a fetchable URL type.';
        $translated = $url;
        if (strpos($url, '://') === false) {
            $good = false;
            return false;
        }
        $path = explode('://', $url, 2)[1];
        if (strpos($lc, 'http://') === 0 || strpos($lc, 'https://') === 0) {
            $good = true;
        } else {
            if (strpos($path, '..') !== false || strpos($path, '/') === 0 || strpos($path, '~') === 0) {
                $error = 'Traversing the directory tree is forbidden.';
                $good = false;
                return false;
            }
        }

        if (strpos($lc, 'contrib://') === 0 || strpos($lc, 'contribl://') === 0) {
            $good = true;
            if (strpos($lc, 'contrib://') === 0) {
                $translated = 'https://raw.githubusercontent.com/maths/moodle-qtype_stack/' .
                    'master/stack/maxima/contrib/' . $path;
            } else {
                $islocalfile = true;
                $translated = __DIR__ . '/../stack/maxima/contrib/' . $path;
            }
        } else if (strpos($lc, 'template://') === 0 || strpos($lc, 'templatel://') === 0) {
            $good = true;
            if (strpos($lc, 'template://') === 0) {
                $translated = 'https://raw.githubusercontent.com/maths/moodle-qtype_stack/' .
                    'master/stack/cas/castext2/template/' . $path;
            } else {
                $islocalfile = true;
                $translated = __DIR__ . '/../stack/cas/castext2/template/' . $path;
            }
        }

        if ($good) {
            if (!isset($cache[$translated])) {
                // Feel free to apply any proxying here if you want.
                // Just remember that $islocalfile might be true and you might do
                // something else then.

                if ($islocalfile) {
                    $cache[$translated] = file_get_contents($translated);
                } else {
                    $headers = get_headers($translated);
                    if (strpos($headers[0], '404') === false) {
                        $cache[$translated] = file_get_contents($translated);
                    } else {
                        $cache[$translated] = false;
                    }
                }
            }
            return $cache[$translated];
        }
        $cache[$translated] = false;
        return false;
    }
}

if (!function_exists('current_language')) {
    function current_language(): string
    {
        return getLanguage();
    }
}

if (!function_exists('force_current_language')) {
    function force_current_language(?string $language = null): string
    {
        return getLanguage();
    }
}

if (!function_exists('optional_param')) {
    function optional_param(string $name, $default, $type)
    {
        return clean_param($_REQUEST[$name] ?? $default, $type);
    }
}

if (!function_exists('format_string')) {
    function format_string($string, $striplinks = true, $options = null): string
    {
        return (string) $string;
    }
}

if (!function_exists('mtrace')) {
    function mtrace($string = '', $eol = "\n"): void
    {
        if (PHP_SAPI === 'cli') {
            echo (string) $string . $eol;
        }
    }
}

if (!function_exists('debugging')) {
    function debugging($message = '', $level = null, $backtrace = null): bool
    {
        return false;
    }
}

if (!function_exists('usergetdate')) {
    function usergetdate($timestamp = null): array
    {
        return getdate($timestamp ?? time());
    }
}

if (!function_exists('userdate')) {
    function userdate($date, string $format = ''): string
    {
        if ($format === '') {
            return date('Y-m-d H:i:s', (int) $date);
        }

        $replacements = [
            '%A' => 'l',
            '%a' => 'D',
            '%B' => 'F',
            '%b' => 'M',
            '%d' => 'd',
            '%e' => 'j',
            '%H' => 'H',
            '%I' => 'h',
            '%M' => 'i',
            '%m' => 'm',
            '%p' => 'A',
            '%S' => 's',
            '%Y' => 'Y',
            '%y' => 'y',
        ];

        return date(strtr($format, $replacements), (int) $date);
    }
}

if (!function_exists('stack_get_mathjax_version')) {
    function stack_get_mathjax_version(): string
    {
        return assStackQuestionUtils::getMathjaxVersion();
    }
}

if (!function_exists('stack_get_mathjax_url')) {
    function stack_get_mathjax_url(): string
    {
        return assStackQuestionUtils::getMathJaxScriptUrl();
    }
}

if (!function_exists('stack_castext_file_filter')) {
    function stack_castext_file_filter(string $castext, array $identifiers): string
    {
        return assStackQuestionUtils::stack_castext_file_filter($castext, $identifiers);
    }
}

if (!function_exists('clean_param')) {
    function clean_param($param, $type)
    {
        if ($type === PARAM_PLUGIN) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', (string) $param);
        }

        if ($type === PARAM_INT) {
            return (int) $param;
        }

        return $param;
    }
}

/**
 * Base class for all the types of exception we throw.
 */
if (!class_exists('stack_exception')) {
    class stack_exception extends StackException {
        public function __construct($error) {
            parent::__construct($error);
        }
    }
}

/**
 * You need to call this method on the string you get from
 * $castext->get_display_castext() before you echo it. This ensures that equations
 * are displayed properly.
 * @param string $castext the result of calling $castext->get_display_castext().
 * @return string HTML ready to output.
 */
if (!function_exists('stack_ouput_castext')) {
    function stack_ouput_castext($castext) {
        return stack_maths::process_display_castext($castext);
    }
}

/**
 * Equivalent to get_string($key, 'qtype_stack', $a), but this method ensure that
 * any equations in the string are displayed properly.
 * @param string $key the string name.
 * @param mixed $a (optional) any values to interpolate into the string.
 * @return string the language string
 */
if (!function_exists('stack_string')) {
    function stack_string($key, $a = null): string
    {
        global $DIC;
        $lng = $DIC->language();
        $user_language = $lng->getUserLanguage();
        static $string = array();
        static $available_languages = ["en", "de", "es"];

        if (!in_array($user_language, $available_languages)) {
            $user_language = 'de';
        }

        if (empty($string)) {
            include_once ILIAS_ABSOLUTE_PATH . "/public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assStackQuestion/lang/stack_$user_language.php";
        }

        if (!array_key_exists($key, $string)) {
            include_once ILIAS_ABSOLUTE_PATH . "/public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assStackQuestion/lang/stack_en.php";
        }

        try {
            return stack_maths::process_lang_string(getString($key, $string, $a));
        } catch (Exception $e) {
            // Fallback to English if something goes wrong.
            include_once ILIAS_ABSOLUTE_PATH . "/public/Customizing/global/plugins/Modules/TestQuestionPool/Questions/assStackQuestion/lang/stack_en.php";

            return stack_maths::process_lang_string(getString($key, $string, $a));
        }
    }
}

/**
 * Equivalent to get_string($key, 'qtype_stack', $a), but this method ensure that
 * any equations in the string are displayed properly and that this message is formatted as an error.
 * @param string $key the string name.
 * @param mixed $a (optional) any values to interpolate into the string.
 * @return string the language string
 */
if (!function_exists('stack_string_error')) {
    function stack_string_error($key, $a = null) {
        $key = stack_maths::process_lang_string(get_string($key, 'qtype_stack', $a));
        return '<i class="icon fa fa-exclamation-circle text-danger fa-fw " title="' . $key . '" aria-label="' .
                $key . '"></i>' . $key;
    }
}

/**
 * Private helper used by the next function.
 *
 * @return array search => replace strings.
 */
if (!function_exists('get_stack_maxima_latex_replacements')) {
    function get_stack_maxima_latex_replacements() {
        // This is an array language code => replacements array.
        static $replacements = [];

        $lang = getLanguage();
        if (!isset($replacements[$lang])) {
            $replacements[$lang] = [
                'QMCHAR' => '?',
                '!LEFTSQ!' => '\left[',
                '!LEFTR!' => '\left(',
                '!RIGHTSQ!' => '\right]',
                '!RIGHTR!' => '\right)',
                '!ANDOR!' => stack_string('equiv_ANDOR'),
                '!SAMEROOTS!' => stack_string('equiv_SAMEROOTS'),
                '!MISSINGVAR!' => stack_string('equiv_MISSINGVAR'),
                '!ASSUMEPOSVARS!' => stack_string('equiv_ASSUMEPOSVARS'),
                '!ASSUMEPOSREALVARS!' => stack_string('equiv_ASSUMEPOSREALVARS'),
                '!LET!' => stack_string('equiv_LET'),
                '!AND!' => stack_string('equiv_AND'),
                '!OR!' => stack_string('equiv_OR'),
                '!NOT!' => stack_string('equiv_NOT'),
                '!NAND!' => stack_string('equiv_NAND'),
                '!NOR!' => stack_string('equiv_NOR'),
                '!XOR!' => stack_string('equiv_XOR'),
                '!XNOR!' => stack_string('equiv_XNOR'),
                '!IMPLIES!' => stack_string('equiv_IMPLIES'),
                '!BOOLTRUE!' => stack_string('true'),
                '!BOOLFALSE!' => stack_string('false'),
            ];
        }
        return $replacements[$lang];
    }
}

/**
 * This function tidies up LaTeX from Maxima.
 * @param string $rawfeedback
 * @return string
 */
if (!function_exists('stack_maxima_latex_tidy')) {
    function stack_maxima_latex_tidy($latex) {
        $replacements = get_stack_maxima_latex_replacements();
        $latex = str_replace(array_keys($replacements), array_values($replacements), $latex);

        // Also previously some spaces have been eliminated and line changes dropped.
        // Apparently returning verbatim LaTeX was not a thing.
        $latex = str_replace("\n ", '', $latex);
        $latex = str_replace("\n", '', $latex);
        // Just don't want to use regexp.
        $latex = str_replace('    ', ' ', $latex);
        $latex = str_replace('   ', ' ', $latex);
        $latex = str_replace('  ', ' ', $latex);

        return $latex;
    }
}

/**
 * This function takes a feedback string from Maxima and unpacks and translates it.
 * @param string $rawfeedback
 * @return string
 */
if (!function_exists('stack_maxima_translate')) {
    function stack_maxima_translate($rawfeedback) {

        if (strpos($rawfeedback, 'stack_trans') === false) {
            return trim(stack_maxima_latex_tidy($rawfeedback));
        } else {
            $rawfeedback = str_replace('[[', '', $rawfeedback);
            $rawfeedback = str_replace(']]', '', $rawfeedback);
            $rawfeedback = str_replace("\n", '', $rawfeedback);
            $rawfeedback = str_replace('\n', '', $rawfeedback);
            $rawfeedback = str_replace('!quot!', '"', $rawfeedback);

            $translated = [];
            preg_match_all('/stack_trans\(.*?\);/', $rawfeedback, $matches);
            $feedback = $matches[0];
            foreach ($feedback as $fb) {
                $fb = substr($fb, 12, -2);
                if (strstr($fb, "' , \"") === false) {
                    // We only have a feedback tag, with no optional arguments.
                    $translated[] = trim(stack_string(substr($fb, 1, -1)));
                } else {
                    // We have a feedback tag and some optional arguments.
                    $tag = substr($fb, 1, strpos($fb, "' , \"") - 1);
                    $arg = substr($fb, strpos($fb, "' , \"") + 5, -2);
                    $args = explode('"  , "', $arg);

                    $a = [];
                    for ($i = 0; $i < count($args); $i++) {
                        $a["m{$i}"] = $args[$i];
                    }
                    $translated[] = trim(stack_string($tag, $a));
                }
            }

            return stack_maxima_latex_tidy(implode(' ', $translated));
        }
    }
}

// phpcs:ignore moodle.Commenting.MissingDocblock.Function
if (!function_exists('stack_maxima_format_casstring')) {
    function stack_maxima_format_casstring($str) {
        // Santise the output, E.g. '>' -> '&gt;'.
        $str = stack_string_sanitise($str);
        $str = str_replace('[[syntaxexamplehighlight]', '<span class="stacksyntaxexamplehighlight">', $str);
        $str = str_replace('[syntaxexamplehighlight]]', '</span>', $str);

        return html_writer::tag('span', $str, ['class' => 'stacksyntaxexample']);
    }
}

// phpcs:ignore moodle.Commenting.MissingDocblock.Function
if (!function_exists('stack_string_sanitise')) {
    function stack_string_sanitise($str) {
        // Students may not input strings containing specific LaTeX
        // i.e. no math-modes due to us being unable to decide if
        // it is safe.
        $str = str_replace('\\[', '\\&#8203;[', $str);
        $str = str_replace('\\]', '\\&#8203;]', $str);
        $str = str_replace('\\(', '\\&#8203;(', $str);
        $str = str_replace('\\)', '\\&#8203;)', $str);
        $str = str_replace('$$', '$&#8203;$', $str);
        // Also any script tags need to be disabled.
        $str = str_ireplace('<script', '&lt;&#8203;script', $str);
        $str = str_ireplace('</script>', '&lt;&#8203;/script&gt;', $str);
        $str = str_ireplace('<iframe', '&lt;&#8203;iframe', $str);
        $str = str_ireplace('</iframe>', '&lt;&#8203;/iframe&gt;', $str);
        $str = str_ireplace('<style', '&lt;&#8203;style', $str);
        $str = str_ireplace('</style>', '&lt;&#8203;/style&gt;', $str);
        $str = str_ireplace('<div', '&lt;&#8203;div', $str);
        $str = str_ireplace('</div>', '&lt;&#8203;/div&gt;', $str);
        $str = str_ireplace('/>', '/&gt;', $str);
        $str = str_ireplace('</', '&lt;/', $str);
        $str = str_ireplace('<!--', '&lt;!--', $str);
        $str = str_ireplace('-->', '--&gt;', $str);

        $pat = ['/(on)([a-z]+[ ]*)(=)/i', '/(href)([ ]*)(=)/i', '/(src)([ ]*)(=)/i'];
        $rep = ['on&#0;$2&#0;&#61;', 'href&#0;$2&#61;', 'src&#0;$2&#61;'];
        $str = preg_replace($pat, $rep, $str);
        return $str;
    }
}

/**
 * This class is needed to ignore requests for pluginfile rewrites in the bulk tester
 * and possibly elsewhere, e.g. API.
 */
if (!class_exists('stack_outofcontext_process')) {
    class stack_outofcontext_process {

        // phpcs:ignore moodle.Commenting.MissingDocblock.Function
        public function __construct() {
        }

        /**
         * Calls {@link question_rewrite_question_urls()} with appropriate parameters
         * for content belonging to this question.
         * @param string $text the content to output.
         * @param string $component the component name (normally 'question' or 'qtype_...')
         * @param string $filearea the name of the file area.
         * @param int $itemid the item id.
         * @return string the content with the URLs rewritten.
         */
        public function rewrite_pluginfile_urls($text, $component, $filearea, $itemid) {
            return $text;
        }

        /**
         * Get the name (in the sense a HTML name="" attribute, or a $_POST variable
         * name) to use for a question_type variable belonging to this question_attempt.
         *
         * @param string $varname The short form of the variable name.
         * @return string The field name to use.
         */
        public function get_qt_field_name($varname) {
            return $varname;
        }
    }
}

if (!function_exists('stack_cors_scripts_dir')) {
    /**
     * Get the directory path for the corsscripts directory.
     * @return string
     */
    function stack_cors_scripts_dir(): string
    {
        global $CFG;
        return $CFG->dirroot . '/question/type/stack/corsscripts/';
    }
}

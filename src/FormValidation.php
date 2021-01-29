<?php

namespace Ms100\FormValidation;

use Ms100\FormValidation\traits\ArrayTrait;
use Ms100\FormValidation\traits\CiTrait;
use Ms100\FormValidation\traits\ExtendTrait;
use Ms100\FormValidation\traits\FileTrait;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

class FormValidation
{
    use ArrayTrait;
    use CiTrait;
    use ExtendTrait;
    use FileTrait;

    /**
     * Validation data for the current form submission
     *
     * @var array
     */
    protected array $_field_data = [];

    /**
     * Array of validation errors
     *
     * @var array
     */
    protected array $_error_array = [];

    /**
     * Array of custom error messages
     *
     * @var array
     */
    protected array $_error_messages = [];

    /**
     * Custom data to validate
     *
     * @var array
     */
    protected array $validation_data;

    protected Translator $translator;

    /**
     * FormValidation constructor.
     *
     * @param array  $rules
     * @param string $locale
     * @param string $language_dir
     */
    public function __construct(
        array $rules = [],
        string $locale = 'zh',
        string $language_dir = ''
    ) {
        $this->locale = $locale;

        $loader = new FileLoader(
            new Filesystem(),
            $language_dir ?: $this->getSelfLanguagePath()
        );

        if (!empty($language_dir)) {
            $loader->addJsonPath($this->getSelfLanguagePath());
        }

        $this->translator = new Translator($loader, $locale);

        if (!empty($rules)) {
            $this->setRules($rules);
        }
    }

    /**
     * Set Rules
     * This function takes an array of field names and validations
     * rules as input, any custom error messages, validates the info,
     * and stores it
     *
     * @param mixed  $field
     * @param string $label
     * @param mixed  $rules
     * @param array  $errors
     *
     * @return    FormValidation
     */
    public function setRules($field, $label = '', $rules = [], $errors = [])
    {
        // If an array was passed via the first parameter instead of individual string
        // values we cycle through it and recursively call this function.
        if (is_array($field)) {
            foreach ($field as $row) {
                // Houston, we have a problem...
                if (!isset($row['field'], $row['rules'])) {
                    continue;
                }

                // If the field label wasn't passed we use the field name
                $label = $row['label'] ?? $row['field'];

                // Add the custom error message array
                $errors = (isset($row['errors']) && is_array($row['errors']))
                    ? $row['errors'] : [];

                // Here we go!
                $this->setOneRule(
                    $row['field'],
                    $label,
                    $row['rules'],
                    $errors
                );
            }
        } else {
            $this->setOneRule($field, $label, $rules, $errors);
        }

        return $this;
    }

    protected function setOneRule(
        $field,
        $label = '',
        $rules = [],
        $errors = []
    ) {
        // No fields or no rules? Nothing to do...
        if (!is_string($field) || $field === '') {
            return $this;
        } elseif (!is_array($rules)) {
            // BC: Convert pipe-separated rules string to an array
            if (!is_string($rules) || empty($rules)) {
                $rules = [];
            } else {
                $rules = preg_split('/\|(?![^\[]*\])/', $rules);
            }
        }

        // If the field label wasn't passed we use the field name
        $label === '' && $label = $field;

        $field === '[]' && $field = '';

        $indexes = $this->parseFieldStr($field);

        // Build our master array
        $this->_field_data[$field] = [
            'field'  => $field,
            'label'  => $label,
            'rules'  => $rules,
            'errors' => $errors,
            'keys'   => $indexes,
        ];

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * 用来变换$_FILES的数组格式
     *
     * @param array $input
     *
     * @return array
     */
    public static function restructureFiles(array $input)
    {
        $output = [];
        foreach ($input as $name => $array) {
            foreach ($array as $field => $value) {
                $pointer = &$output[$name];
                if (!is_array($value)) {
                    $pointer[$field] = $value;
                    continue;
                }
                $stack = [&$pointer];
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveArrayIterator($value),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $k => $v) {
                    array_splice($stack, $iterator->getDepth() + 1);
                    $pointer = &$stack[count($stack) - 1];
                    $pointer = &$pointer[$k];
                    $stack[] = &$pointer;
                    if (!$iterator->hasChildren()) {
                        $pointer[$field] = $v;
                    }
                }
            }
        }

        return $output;
    }

    /**
     * @param array $data
     * @param bool  $return_error
     *
     * @return bool|array
     * @throws FormException
     */
    public function verify(array &$data, bool $return_error = false)
    {
        // Were we able to set the rules correctly?
        if (count($this->_field_data) === 0) {
            //log_message('warn', 'Unable to find validation rules');
            /*if ($return_error) {
                return false;
            } else {*/
            throw new FormException();
            //}
        }

        //做一个属性，方便别的方法取数据
        if (is_array($data)) {
            $this->validation_data = &$data;
        } else {
            $this->validation_data = [&$data];
        }

        $form = $this->makeFieldDataToTree();

        $this->execute($form, $data);

        //解除关联
        unset($this->validation_data);
        //$this->validation_data = null;

        if (count($this->_error_array)) {
            $error_array = $this->_error_array;
            $this->_error_array = [];

            if ($return_error) {
                return $error_array;
            } else {
                throw new FormException($error_array);
            }
        } else {
            return true;
        }
    }

    // --------------------------------------------------------------------

    protected function makeFieldDataToTree()
    {
        foreach ($this->_field_data as $field => $row) {
            while (count($row['keys']) > 1) {
                array_pop($row['keys']);
                $parent_field = vsprintf(
                    '%s'.str_repeat('[%s]', count($row['keys']) - 1),
                    $row['keys']
                );
                if (isset($this->_field_data[$parent_field])) {
                    if (!isset($this->_field_data[$parent_field]['rules']['is_array'])) {
                        $this->_field_data[$parent_field]['rules']['is_array']
                            = 'is_array';
                    }
                    break;
                }
                $this->setOneRule($parent_field, $parent_field, ['is_array']);
            }
        }

        $tree = [];
        $field_data = $this->_field_data;

        foreach ($field_data as $field => $row) {
            $field_data[$field]['rules'] = $this->prepareRules($row['rules']);
            if (count($row['keys']) > 1) {
                $t = array_pop($row['keys']);
                $parent_field = vsprintf(
                    '%s'.str_repeat('[%s]', count($row['keys']) - 1),
                    $row['keys']
                );

                $field_data[$parent_field]['sub'][$t] = &$field_data[$field];
            } else {
                $tree[$field] = &$field_data[$field];
            }
        }

        return $tree;
    }

    // --------------------------------------------------------------------

    protected function execute(
        array &$field_data,
        array &$validation_data,
        array $indexes = []
    ) {
        $data = $validation_data;
        foreach ($field_data as $field => $row) {
            if (empty($row['rules'])) {
                continue;
            }
            if ($field == '') {
                foreach ($data as $key => $value) {
                    $indexes[] = (string)$key;
                    $res = $this->validate($row, $indexes, $data[$key]);

                    if ($res && isset($row['sub']) && is_array($data[$key])) {
                        $this->execute($row['sub'], $data[$key], $indexes);
                    }
                    array_pop($indexes);
                }
            } else {
                $indexes[] = (string)$field;
                if (array_key_exists($field, $data)) {
                    $res = $this->validate($row, $indexes, $data[$field]);

                    if ($res && isset($row['sub']) && is_array($data[$field])) {
                        $this->execute($row['sub'], $data[$field], $indexes);
                    }

                    $store[$field] = $data[$field];
                    unset($data[$field]);
                } elseif ($rules = array_intersect_key(
                    $row['rules'],
                    [
                        'required'           => '',
                        'isset'              => '',
                        'matches'            => '',
                        'least_one_required' => '',
                    ]
                )
                ) {
                    $temp = null;
                    $row['rules'] = $rules;
                    $this->validate($row, $indexes, $temp);
                } elseif (isset($row['rules']['default_value'])) {
                    $store[$field] = '';
                    $this->validate($row, $indexes, $store[$field]);
                }
                array_pop($indexes);
            }
        }

        if (isset($store)) {
            $validation_data = $store + $data;
        } else {
            $validation_data = $data;
        }
    }

    // --------------------------------------------------------------------


    protected function validate(array $row, array $indexes, &$data)
    {
        $rules = $row['rules'];

        foreach ($rules as $rule) {
            // Is the rule a callback?
            $callable = false;
            $param = null;
            if (is_string($rule)) {
                if (preg_match(
                    '/(?<rule>.*?)\[(?<param>.*)\]/',
                    $rule,
                    $match
                )
                ) {
                    $rule = $match['rule'];
                    $param = $match['param'];
                }
            } elseif (is_callable($rule)) {
                $callable = true;
            } elseif (is_array($rule) && isset($rule[0], $rule[1])
                && is_callable($rule[1])
            ) {
                // We have a "named" callable, so save the name
                $callable = $rule[0];
                $rule = $rule[1];
            }


            // Ignore empty, non-required inputs with a few exceptions ...
            if (
                ($data === null || $data === '' || $data === [])
                && $callable === false
                && !in_array(
                    $rule,
                    [
                        'required',
                        'isset',
                        'not_empty_str',
                        'not_empty_array',
                        'matches',
                        'least_one_required',
                        'default_value',
                        'is_array',
                        'is_bool',
                        'is_string',
                        'is_numeric',
                        'is_int',
                        'is_float',
                    ],
                    true
                )
            ) {
                continue;
            }

            // Call the function that corresponds to the rule
            if ($callable !== false) {
                $result = is_array($rule)
                    ? $rule[0]->{$rule[1]}($data)
                    : $rule($data);

                // Is $callable set to a rule name?
                if (!is_bool($callable)) {
                    $rule = $callable;
                }

                // Re-assign the result to the master data array
                is_bool($result) || $data = $result;
            } elseif (method_exists($this, $rule)) {
                if (in_array(
                    $rule,
                    [
                        'matches',
                        'differs',
                        'least_one_required',
                        'date_later_than',
                        'date_before_than',
                        'fix_image_ext',
                        'match_then_rule_other_filed',
                    ]
                )
                ) {
                    $result = $this->$rule($data, $indexes, $param);
                } else {
                    $result = isset($param) ? $this->$rule($data, $param)
                        : $this->$rule($data);
                }

                is_bool($result) || $data = $result;
            } elseif (function_exists($rule)) {
                // If our own wrapper function doesn't exist we see if a native PHP function does.
                // Users can use any native PHP function call that has one param.
                // Native PHP functions issue warnings if you pass them more parameters than they use
                $result = isset($param)
                    ? $rule($data, $param)
                    : $rule(
                        $data
                    );

                is_bool($result) || $data = $result;
            } elseif ($rule == 'isset') {
                $result = isset($data);
            } elseif ($rule == 'empty') {
                $result = empty($data);
            } else {
                //log_message('debug', 'Unable to find validation rule: ' . $rule);
                throw new FormException(
                    [
                        $row['field'] => 'Unable to find validation rule: '
                            .$rule,
                    ]
                );
                //$result = false;
            }


            // Did the rule test negatively? If so, grab the error.
            if ($result === false) {
                // Callable rules might not have named error messages
                if (!is_string($rule)) {
                    $line = $this->languageLine(
                            'form_validation',
                            'error_message_not_set'
                        ).'(Anonymous function)';
                } else {
                    $line = $this->getErrorMessage($rule, $row['field']);
                }

                // Is the parameter we are inserting into the error message the name
                // of another field? If so we need to grab its "field label"
                if (isset($param)
                    && in_array(
                        $rule,
                        [
                            'differs',
                            'matches',
                            'least_one_required',
                            'date_later_than',
                            'date_before_than',
                        ]
                    )
                    && isset($this->_field_data[$param], $this->_field_data[$param]['label'])
                ) {
                    $param = $this->translateFieldName(
                        $this->_field_data[$param]['label']
                    );
                }

                // Build the error message
                $message = $this->buildErrorMsg(
                    $line,
                    $this->translateFieldName(
                        $row['label']
                    ),
                    $param ?? ''
                );

                $full_key = vsprintf(
                    '%s'.str_repeat('[%s]', count($indexes) - 1),
                    $indexes
                );

                if (!isset($this->_error_array[$full_key])) {
                    $this->_error_array[$full_key] = $message;
                }

                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Prepare rules
     * Re-orders the provided rules in order of importance, so that
     * they can easily be executed later without weird checks ...
     * "Callbacks" are given the highest priority (always called),
     * followed by 'required' (called if callbacks didn't fail),
     * and then every next rule depends on the previous one passing.
     *
     * @param array $rules
     *
     * @return    array
     */
    protected function prepareRules($rules)
    {
        $new_rules = [];
        $special = [];
        $callbacks = [];

        foreach ($rules as &$rule) {
            // Let 'required' always be the first (non-callback) rule
            if (in_array(
                $rule,
                [
                    'required',
                    'isset',
                    'not_empty_str',
                    'not_empty_array',
                ]
            )
            ) {
                $special = [$rule => $rule] + $special;
            } elseif (in_array(
                $rule,
                [
                    'is_string',
                    'is_array',
                    'is_bool',
                    'is_numeric',
                    'is_int',
                    'is_float',
                ]
            )
            ) {
                $special[$rule] = $rule;
            } // Proper callables
            elseif (is_array($rule) && is_callable($rule)) {
                $callbacks[] = $rule;
            } // "Named" callables; i.e. array('name' => $callable)
            elseif (is_array($rule) && isset($rule[0], $rule[1])
                && is_callable(
                    $rule[1]
                )
            ) {
                $callbacks[] = $rule;
            } elseif (preg_match(
                '/(?<rule>default_value|least_one_required)\[.*\]/',
                $rule,
                $match
            )
            ) {
                $special = [$match['rule'] => $rule] + $special;
            } // Everything else goes at the end of the queue
            else {
                $new_rules[] = $rule;
            }
        }
        if (!array_intersect_key(
            [
                'is_string'  => '',
                'is_array'   => '',
                'is_bool'    => '',
                'is_numeric' => '',
                'is_int'     => '',
                'is_float'   => '',
            ],
            $special
        )
        ) {
            //$callbacks[] = 'is_string';
            $special['is_string'] = 'is_string';
        }

        return array_merge($callbacks, $special, $new_rules);
    }

    // --------------------------------------------------------------------

    /**
     * Set Error Message
     * Lets users set their own error messages on the fly. Note:
     * The key name has to match the function name that it corresponds to.
     *
     * @param array
     * @param string
     *
     * @return    FormValidation
     */
    public function setErrorMessage($lang, $val = '')
    {
        if (!is_array($lang)) {
            $lang = [$lang => $val];
        }

        $this->_error_messages = array_merge($this->_error_messages, $lang);

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Get the error message for the rule
     *
     * @param string $rule  The rule name
     * @param string $field The field name
     *
     * @return    string
     */
    protected function getErrorMessage($rule, $field)
    {
        // check if a custom message is defined through validation config row.
        if (isset($this->_field_data[$field]['errors'][$rule])) {
            return $this->_field_data[$field]['errors'][$rule];
        } // check if a custom message has been set using the set_message() function
        elseif (isset($this->_error_messages[$rule])) {
            return $this->_error_messages[$rule];
        } elseif ($rule !== ($line = $this->languageLine(
                'form_validation',
                $rule
            ))
        ) {
            return $line;
        }

        return $this->languageLine('form_validation', 'error_message_not_set')
            .'('
            .$rule.')';
    }

    // --------------------------------------------------------------------

    /**
     * @param $field_name
     *
     * @return mixed
     */
    protected function translateFieldName(string $field_name): string
    {
        return $this->languageLine('form_label', $field_name);
    }

    // --------------------------------------------------------------------

    /**
     * Build an error message using the field and param.
     *
     * @param string    The error message line
     * @param string    A field's human name
     * @param mixed    A rule's optional parameter
     *
     * @return    string
     */
    protected function buildErrorMsg(
        string $line,
        string $field = '',
        string $param = ''
    ): string {
        // Check for %s in the string for legacy support.
        if (strpos($line, '%s') !== false) {
            return sprintf($line, $field, $param);
        }

        return str_replace(['{field}', '{param}'], [$field, $param], $line);
    }

    // --------------------------------------------------------------------

    /**
     * Checks if the rule is present within the validator
     * Permits you to check if a rule is present within the validator
     *
     * @param string    the field name
     *
     * @return    bool
     */
    public function hasRule(?string $field = null): bool
    {
        if (is_null($field)) {
            return !empty($this->_field_data);
        }

        return isset($this->_field_data[$field]);
    }

    // --------------------------------------------------------------------

    protected function getValidationDataElement(array $indexes)
    {
        $data = $this->validation_data;

        foreach ($indexes as $i) {
            if (isset($data[$i])) {
                $data = $data[$i];
            } else {
                return null;
            }
        }

        return $data;
    }

    protected function replaceUncertainIndex(
        array $subject,
        array $replace
    ): array {
        if (count($replace) > 1 && in_array('', $subject)) {
            $count = min($replace, $subject);

            for ($i = 0; $i < $count; $i++) {
                if ($replace[$i] === $subject[$i]) {
                    continue;
                } elseif ($subject[$i] === '') {
                    $subject[$i] = $replace[$i];
                } else {
                    break;
                }
            }
        }

        return $subject;
    }

    protected function parseFieldStr(string $field): array
    {
        $indexes = [];
        if ((bool)preg_match_all('/\[(?<sub>.*?)\]/', $field, $matches)) {
            sscanf($field, '%[^[][', $indexes[0]);

            $indexes = array_merge($indexes, $matches['sub']);
        } else {
            $indexes[] = $field;
        }

        return $indexes;
    }

    protected function getSelfLanguagePath(): string
    {
        return __DIR__.DIRECTORY_SEPARATOR.'language'.DIRECTORY_SEPARATOR;
    }


    // --------------------------------------------------------------------

    /**
     * Language line
     * Fetches a single line of text from the language array
     *
     * @param string $line Language line key
     *
     * @return    string    Translation
     */
    protected function languageLine(string $type, string $line): string
    {
        $key = $type.'_'.$line;

        $res = $this->translator->get($key);

        return $res === $key ? $line : $res;
    }
}

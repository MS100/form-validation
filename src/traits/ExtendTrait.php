<?php

namespace Ms100\FormValidation\traits;

Trait ExtendTrait
{
    /**
     * 设置默认值
     *
     * @param $str
     * @param $default
     *
     * @return mixed
     */
    public function default_value($str, $default)
    {
        if (!isset($str) || $str === '') {
            $str = $default;
        }
        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * 可以没有此字段，但是不能传空字符串和null
     *
     * @param $str
     *
     * @return bool
     */
    public function not_empty_str($str)
    {
        return is_string($str) && $str !== '';
    }

    // --------------------------------------------------------------------

    /**
     * 可以没有此字段，但是不能传空字符串和null
     *
     * @param $arr
     *
     * @return bool
     */
    public function not_empty_array($arr)
    {
        return is_array($arr) && !empty($arr);
    }

    // --------------------------------------------------------------------

    /**
     * 不能同时为空 用法：least_one_required[field_name]
     *
     * @param string $str
     * @param array $indexes
     * @param string $field
     *
     * @return bool
     */
    public function least_one_required($str, array $indexes, $field)
    {
        $subject = $this->parseFieldStr($field);
        $subject = $this->replaceUncertainIndex($subject, $indexes);
        $value = $this->getValidationDataElement($subject);

        return $this->required($str) || $this->required($value);
    }

    /**
     * 判断字符长度，中文2个字节，英文1个字节
     *
     * @param $str
     * @param $val
     *
     * @return bool
     */
    public function max_length_gbk($str, $val)
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            //return (mb_strlen($str, 'gb2312') > $val) ? FALSE : TRUE; //效率太低，下面的方式比它快4到5倍
            return ((strlen($str) + mb_strlen($str, 'UTF-8')) / 2) <= $val;
        }

        return strlen($str) <= $val;
    }

    /**
     * 判断字符长度，中文2个字节，英文1个字节
     *
     * @param $str
     * @param $val
     *
     * @return bool
     */
    public function min_length_gbk($str, $val)
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            //return (mb_strlen($str, 'gb2312') < $val) ? FALSE : TRUE; //效率太低，下面的方式比它快4到5倍
            return ((strlen($str) + mb_strlen($str, 'UTF-8')) / 2) >= $val;
        }

        return strlen($str) >= $val;
    }

    /**
     * 时间晚于某个字段
     *
     * @param string $end_date
     * @param array $indexes
     * @param string $start_date_field
     *
     * @return bool
     */
    public function date_later_than($end_date, array $indexes, $start_date_field)
    {
        $start = strtotime($start_date_field);
        if ($start === false) {
            $subject = $this->parseFieldStr($start_date_field);
            $subject = $this->replaceUncertainIndex($subject, $indexes);
            $start_date = $this->getValidationDataElement($subject);

            $start_date = strtr(
                $start_date,
                [
                    '/' => '-',
                    '.' => '-',
                    '年' => '-',
                    '月' => '-',
                    '日' => '',
                ]
            );

            $start_date = trim($start_date, '-');

            $start = strtotime($start_date);
            if ($start === false) {
                return false;
            }
        }


        $end_date = strtr(
            $end_date,
            [
                '/' => '-',
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => '',
            ]
        );
        $end_date = trim($end_date, '-');

        $end = strtotime($end_date);

        if ($end === false || $end < $start) {
            return false;
        }

        return true;
    }

    /**
     * 时间早某个字段
     *
     * @param string $start_date
     * @param array $indexes
     * @param string $end_date_field
     *
     * @return bool
     */
    public function date_before_than($start_date, array $indexes, $end_date_field)
    {
        $end = strtotime($end_date_field);
        if ($end === false) {
            $subject = $this->parseFieldStr($end_date_field);
            $subject = $this->replaceUncertainIndex($subject, $indexes);
            $end_date = $this->getValidationDataElement($subject);

            $end_date = strtr(
                $end_date,
                [
                    '/' => '-',
                    '.' => '-',
                    '年' => '-',
                    '月' => '-',
                    '日' => '',
                ]
            );

            $end_date = trim($end_date, '-');

            $end = strtotime($end_date);
            if ($end === false) {
                return false;
            }
        }


        $start_date = strtr(
            $start_date,
            [
                '/' => '-',
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => '',
            ]
        );
        $start_date = trim($start_date, '-');

        $start = strtotime($start_date);

        if ($start === false || $start > $end) {
            return false;
        }

        return true;
    }

    /**
     * 验证合法的日期
     *
     * @param      $str
     * @param int $flag
     *
     * @return bool
     */
    public function valid_date($str, $flag = 0)
    {
        if (!preg_match(
            '#^(?:(?:19|20)\d{2}(?:(\-|\.|\/)\d{1,2}(?:\1\d{1,2})?)?|(?:19|20)\d{2}年(?:\d{1,2}月(?:\d{1,2}日)?))?$#',
            $str
        )) {
            return false;
        }

        $str = strtr(
            $str,
            [
                '/' => '-',
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => '',
            ]
        );

        $str = trim($str, '-');

        $time = strtotime($str . '-01');
        if ($time === false) {
            return false;
        }

        if ($flag > 0) {
            return $time >= time();
        } elseif ($flag < 0) {
            return $time <= time();
        } else {
            return true;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Prep URL
     *
     * @param string
     *
     * @return    string
     */
    public function prep_url_can_no_scheme($str = '')
    {
        if ($str === 'http://' OR $str === '' OR $str === '//') {
            return '';
        }

        if (strpos($str, '//') !== 0 && strpos($str, 'http://') !== 0 && strpos($str, 'https://') !== 0) {
            return '//' . ltrim($str, ':/');
        }

        return $str;
    }



    // --------------------------------------------------------------------

    /**
     * 关联其他字段验证 用法：match_then_rule_other_filed[本字段期望值, 要验证的字段a, a的验证规则b, 验证规则b参数1, 验证规则b参数2...]
     *
     * @param string $str
     * @param array $indexes
     * @param string $param
     *
     * @return bool
     */
    public function match_then_rule_other_filed($str, array $indexes, $param)
    {
        $arr = explode(',', $param, 4);
        if (count($arr) == 3) {
            list($expect_value, $other_field_name, $other_field_rule) = $arr;
        } elseif (count($arr) == 4) {
            list($expect_value, $other_field_name, $other_field_rule, $other_field_rule_args) = $arr;
        } else {
            return false;
        }

        if ($expect_value != $str) {
            return true;
        }

        $other_field_subject = $this->parseFieldStr($other_field_name);
        $other_field_subject = $this->replaceUncertainIndex($other_field_subject, $indexes);
        $other_field_value = $this->getValidationDataElement($other_field_subject);

        if (method_exists($this, $other_field_rule)) {
            $call_func = [$this, $other_field_rule];
        } else {
            $call_func = $other_field_rule;
        }

        if (isset($other_field_rule_args)) {
            $res = call_user_func($call_func, $other_field_value, $other_field_rule_args);
        } else {
            $res = call_user_func($call_func, $other_field_value);
        }

        if ($res === false) {
            $line = $this->getErrorMessage($other_field_rule, $other_field_name);

            // Build the error message
            $message = $this->buildErrorMsg($line,
                $this->translateFieldname($this->_field_data[$other_field_name]['label']),
                $other_field_rule_args ?? null);

            if (!isset($this->_error_array[$other_field_name])) {
                $this->_error_array[$other_field_name] = $message;
            }
        }
        return true;
    }

}
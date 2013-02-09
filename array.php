<?php

class knjarray
{
    static function keydiffs($arr1, $arr2)
    {
        $arr_res = array();
        foreach ($arr2 as $key => $value) {
            if ($arr1[$key] != $value) {
                $arr_res[$key] = array(
                    '1' => $arr1[$key],
                    '2' => $arr2[$key],
                );
            }
        }

        return $arr_res;
    }

    static function stringsearch($string, $arr)
    {
        foreach ($arr as $value) {
            $pos = strpos($string, $value);
            if ($pos !== false) {
                return array(
                    'matched' => $value,
                    'pos' => $pos,
                );
            }
        }

        return false;
    }

    static function implode_func($arr, $impl, $func, $func_para = null)
    {
        $string = '';

        $first = true;
        foreach ($arr as $key => $value) {
            if ($first) {
                $first = false;
            } else {
                $string .= $impl;
            }

            $string .= call_user_func(array($value, $func), $func_para);
        }

        return $string;
    }

    static function remove_value($arr, $value)
    {
        foreach ($arr as $key => $value) {
            if ($value == $value) {
                unset($arr[$key]);
            }
        }

        return $arr;
    }
}

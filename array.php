<?php

class knjarray
{
    function keydiffs($arr1, $arr2)
    {
        $arr_res = array();
        foreach ($arr2 as $key => $value) {
            if ($arr1[$key] != $value) {
                $arr_res[$key] = array(
                    "1" => $arr1[$key],
                    "2" => $arr2[$key]
                );
            }
        }

        return $arr_res;
    }

    function stringsearch($string, $arr)
    {
        foreach ($arr as $value) {
            $pos = strpos($string, $value);
            if ($pos !== false) {
                return array(
                    "matched" => $value,
                    "pos" => $pos
                );
            }
        }

        return false;
    }

    function implode_func($arr, $impl, $func, $func_para = null)
    {
        $string = "";

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

    function implode($args)
    {
        $string = "";

        $first = true;
        foreach ($args["array"] as $key => $value) {
            if ($first) {
                $first = false;
            } elseif ($args["impl"]) {
                $string .= $args["impl"];
            }

            if ($args["bykey"]) {
                $val = $key;
            } else {
                $val = $value;
            }

            if ($args["surr"]) {
                $string .= $args["surr"];
            }

            if ($args["func_callback"]) {
                if (is_array($args["func_callback"])) {
                    foreach ($args["func_callback"] as $func_callback) {
                        $val = call_user_func(array($val, $func_callback));
                    }
                } else {
                    if (!is_callable(array($value, $args["func_callback"]))) {
                        print_r($args);
                        throw new exception(sprintf(_('Callback-array was not callable: %1$s->%2$s().'), gettype($value), $args["func_callback"]));
                    }

                    $val = call_user_func(array($value, $args["func_callback"]), $args["func_paras"]);
                }
            }

            if ($args["self_callback"]) {
                if (!is_callable($args["self_callback"])) {
                    throw new exception(_("Callback was not callable."));
                }

                $val = call_user_func($args["self_callback"], $val);
            }

            $string .= $val;

            if ($args["surr"]) {
                $string .= $args["surr"];
            }
        }

        return $string;
    }

    function remove_value($arr, $value)
    {
        foreach ($arr as $key => $value) {
            if ($value == $value) {
                unset($arr[$key]);
            }
        }

        return $arr;
    }
}
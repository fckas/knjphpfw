<?php

global $functions_knjlocales;
$functions_knjlocales = array(
    'date_out_format' => 'd/m/Y',
    'date_out_short_format' => 'd/m/y',
    'date_out_format_time' => 'H:i'
);

/**
 * Initilializes the chosen locales-module.
 */
function knjlocales_setmodule($domain, $dir, $language = 'auto')
{
    global $functions_knjlocales;

    $functions_knjlocales['dir'] = $dir;

    if ($language == 'auto') {
        if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER) && $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
            $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            foreach (explode(',', $accept) as $value) {
                $value = explode(';', $value);
                $language = $value[0];
                break;
            }
        } elseif ($_SERVER['LANG']) {
            if (preg_match('/^([a-z]{2}_[A-Z]{2})/u', $_SERVER['LANG'], $match)) {
                $language = $match[1];
            } else {
                //Language could not be matched - default english.
                $language = 'en_GB';
            }
        }

        if ($language == 'da') {
            $language = 'da_DK';
        } elseif ($language == 'de') {
            $language = 'de_DE';
        } elseif ($language == 'en') {
            $language = 'en_GB';
        }
    }

    $language = strtr($language, array(
        '-' => '_'
    ));
    if (preg_match('/^([A-z]{2})_([A-z]{2})$/u', $language, $match)) {
        $language = strtolower($match[1]) . '_' . strtoupper($match[2]);
    }

    require_once 'os.php';
    $os = knj_os::getOS();

    /**
     * Country/Region http://msdn.microsoft.com/en-us/library/cdax410z(v=vs.71).aspx
     * Language       http://msdn.microsoft.com/en-us/library/39cwe7zf(v=vs.71).aspx
     */
    if ($os == 'windows') {
        switch ($language) {
            case 'da':
            case 'dk':
            case 'da_DK':
                $language = 'danish';
                break;
            case 'de':
            case 'de_DE':
                $language = 'german';
                break;
            case 'en':
            case 'uk':
            case 'en_GB':
                $language = 'english-uk';
                break;
            case 'us':
            case 'en_US':
                $language = 'english-us';
                break;
        }
    }

    $functions_knjlocales['language'] = $language;

    if (!file_exists($dir)) {
        throw new exception('Dir does not exist: ' . $dir);
    }

    putenv('LANGUAGE=' . $language);
    putenv('LC_ALL=' . $language);
    putenv('LC_MESSAGE=' . $language);
    putenv('LANG=' . $language);
    putenv('LC_NUMERIC=C');
    if ($os == 'windows') {
        setlocale(LC_ALL, $language);
    } else {
        setlocale(LC_ALL, $language . '.utf8');
        if (defined('LC_MESSAGES')) {
            setlocale(LC_MESSAGES, $language . '.utf8');
        }
    }
    setlocale(LC_NUMERIC, 'C');

    bindtextdomain($domain, $dir);
    bind_textdomain_codeset($domain, 'UTF-8');
    textdomain($domain);
}

function date_out($unixt = null, $args = null)
{
    global $functions_knjlocales;

    if ($functions_knjlocales['date_out_callback']) {
        return call_user_func($functions_knjlocales['date_out_callback']);
    }

    if (!$unixt) {
        $unixt = time();
    }

    if ($args['short']) {
        $string = date($functions_knjlocales['date_out_short_format'], $unixt);
    } else {
        $string = date($functions_knjlocales['date_out_format'], $unixt);
    }

    if ($args['time']) {
        $string .= ' ' . date($functions_knjlocales['date_out_format_time'], $unixt);
    }

    return $string;
}

function date_in($date_string)
{
    global $functions_knjlocales;

    if ($functions_knjlocales['date_in_callback']) {
        return call_user_func($functions_knjlocales['date_in_callback']);
    }

    if (preg_match('/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{1,4})(| ([0-9]{1,2}):([0-9]{1,2})(|[0-9]{1,2}))$/', $date_string, $match)) {
        $date = $match[1];
        $month = $match[2];
        $year = $match[3];

        $hour = $match[5];
        $min = $match[6];

        if ($match[7]) {
            $sec = $match[7]; //fix notice if empty.
        }
    }

    if (!$date || !$month || !$year) {
        throw new InvalidDate('Could not understand the date.');
    }

    return mktime($hour, $min, $sec, $month, $date, $year);
}

function knjlocales_localeconv($lang = null)
{
    global $functions_knjlocales;

    if (!$lang) {
        $lang = $functions_knjlocales['language'];
    }

    require_once 'os.php';
    $os = knj_os::getOS();

    if ($os == 'windows') {
        if (in_array($lang, array('da_DK', 'de_DE'))) {
            return array(
                'mon_decimal_point' => ',',
                'mon_thousands_sep' => '.'
            );
        } else {
            return array(
                'mon_decimal_point' => '.',
                'mon_thousands_sep' => ','
            );
        }
    }

    putenv('LC_MONETARY=' . $lang);
    setlocale(LC_MONETARY, $lang . '.utf8');

    $return = localeconv();

    putenv('LC_MONETARY=' . $functions_knjlocales['language']);
    setlocale(LC_MONETARY, $functions_knjlocales['language'] . '.utf8');

    return $return;
}

function number_out($number, $len = 0, $local = null)
{
    $moneyformat = knjlocales_localeconv($local);
    return number_format($number, $len, $moneyformat['mon_decimal_point'], $moneyformat['mon_thousands_sep']);
}

function number_in($number, $local = null)
{
    $moneyformat = knjlocales_localeconv($local = null);

    $number = str_replace($moneyformat['mon_thousands_sep'], '', $number);
    if ($moneyformat['mon_decimal_point'] != '.') {
        $number = str_replace($moneyformat['mon_decimal_point'], '.', $number);
    }

    return (float) $number;
}

class InvalidDate extends exception
{
}


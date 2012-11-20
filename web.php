<?php
/**
 * This file contains functions that may come in handy when building web-applications
 *
 * PHP version 5
 *
 * @category Framework
 * @package  Knjphpfw
 * @author   Kasper Johansen <kaspernj@gmail.com>
 * @license  Public domain http://en.wikipedia.org/wiki/Public_domain
 * @link     https://github.com/kaspernj/knjphpfw
 */

/**
 * TODO
 *
 * @category Framework
 * @package  Knjphpfw
 * @author   Kasper Johansen <kaspernj@gmail.com>
 * @license  Public domain http://en.wikipedia.org/wiki/Public_domain
 * @link     https://github.com/kaspernj/knjphpfw
 */
class web
{
    static private $alert_sent = false;

    /**
     * TODO
     *
     * @param array $args TODO
     *
     * @return TODO
     */
    function inputs($args)
    {
        $html = '';
        foreach ($args as $arg) {
            $html .= web::input($arg);
        }

        return $html;
    }

    /**
     * TODO
     *
     * @param mixed $args TODO
     *
     * @return string TODO
     */
    function input($args)
    {
        ob_start();

        $value = isset($args['value']) ? $args['value'] : '';
        $id = isset($args['name']) ? $args['name'] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        $title = $args['title'];

        if (empty($args['html'])) {
            $title = htmlspecialchars($title);
        }

        if ($type == 'password' && !isset($args['class'])) {
            $args['class'] = 'input_text';
        } elseif (!isset($args['class'])) {
            $args['class'] = 'input_' . $type;
        }

        echo '<tr>';
        $td_html = '<td class="tdc"';

        if (!empty($args['colspan']) && $args['colspan'] > 2) {
            $td_html .= ' colspan="' . ($args['colspan'] - 1) . '"';
        }

        $rowspan = '';
        if (!empty($args['rowspan']) && $args['rowspan'] > 1) {
            $rowspan = ' rowspan="' .$args['rowspan'] .'"';
            $td_html .= $rowspan;
        }

        $td_html .= '>';
        $td_end_html = '</td>';

        $js_tags = '';
        $js_tags_arr = array('onkeyup', 'onkeydown', 'onchange', 'onclick');
        foreach ($js_tags_arr as $js_tag) {
            if ($args[$js_tag]) {
                $js_tags .= ' ' . $js_tag . '="' . $args[$js_tag] . '"';
            }
        }

        if (array_key_exists('autocomplete', $args) && !$args['autocomplete']) {
            $js_tags .= ' autocomplete="off"';
        }

        if ($type == 'numeric') {
            $value = number_out($value, $args['decis']);
        }

        if ($args['classes']) {
            $classes = $args['classes'];
        } else {
            $classes = array();
        }

        $classes[] = $args['class'];
        $args['class'] = implode(' ', $classes);

        if ($type == 'checkbox') {
            echo '<td' .$rowspan .' colspan="2" class="tdcheck"><input';
            if ($args['disabled']) {
                echo ' disabled="disabled"';
            }
            echo ' type="' .$type .'" class="'. $args['class'] .'" name="' .$id .'" id="' .$id .'"';
            if ($value) {
                echo ' checked="checked"';
            }
            echo $js_tags .' /><label for="' .$id .'">' .$title .'</label></td>';
        } elseif ($type == 'select') {
            $etags = '';
            if ($args['disabled']) {
                $etags .= ' disabled="disabled"';
            }

            if ($args['multiple']) {
                $etags .= ' multiple="multiple"';
            }

            if ($args['height']) {
                $etags .= ' height="' . htmlspecialchars($args['height']) . '"';
            }

            echo '<td' .$rowspan .' class="tdt">' .$title_html .'</td>' .$td_html .'<select' .$etags;
            if ($args['size']) {
                echo ' size="' .htmlspecialchars($args['size']) .'"';
            }
            echo ' name="' .htmlspecialchars($id);
            if ($args['multiple'] && mb_substr($id, -2) != '[]') {
                echo '[]';
            }
            echo '" id="' .htmlspecialchars($id) .'" class="' .$args['class'] .'"' .$js_tags .'>' .self::drawOpts($args['opts'], $value) .'</select>';

            if ($args['moveable']) {
                echo '<div style="padding-top: 3px;"><input type="button" value="' ._('Up') .'" onclick="select_moveup($(\'#' .$id .'\'));" /><input type="button" value="' ._('Down') .'" onclick="select_movedown($(\'#' .$id .'\'));" /></div>';
            }
            echo $td_end_html;
        } elseif ($type == 'file') {
            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html .'<input type="file" class="input_' .$type .'" name="' .htmlspecialchars($id) .'" id="' .htmlspecialchars($id) .'"' .$js_tags .' />' .$td_end_html;
        } elseif ($type == 'textarea') {
            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html .'<textarea name="' .htmlspecialchars($id) .'" id="' .htmlspecialchars($id) .'" class="' .htmlspecialchars($args['class']) .'"';
            if ($args['height']) {
                echo ' style="height: ' .$args['height'] .';"';
            }
            if ($args['readonly']) {
                echo ' readonly="readonly"';
            }
            if ($args['disabled']) {
                echo ' disabled="disabled"';
            }
            echo $js_tags .'>' .htmlspecialchars($value, null, 'UTF-8') .'</textarea>' .$td_end_html;
        } elseif ($type == 'fckeditor') {
            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html;

            $fck = new fckeditor($id);

            if ($args['height']) {
                $fck->Height = $args['height'];
            } else {
                $fck->Height = 300;
            }

            $fck->Value = $value;
            $fck->Create();
            echo $td_end_html;
        } elseif ($type == 'radio') {
            $id = $id .'_' .$value;
            echo '<td' .$rowspan .' class="tdt" colspan="2">
            <input type="radio" id="' .htmlspecialchars($id)
                .'" name="' .htmlspecialchars($id)
                .'" value="' .htmlspecialchars($value)
                .'"  class="' .htmlspecialchars($args['class']) .'"';
            if ($args['checked']) {
                echo ' checked="checked"';
            }
            if ($args['disabled']) {
                echo ' disabled="disabled"';
            }
            echo $js_tags. ' /><label for="' .htmlspecialchars($id) .'">' .$title .'</label></td>';
        } elseif ($type == 'info') {
            echo '<td' .$rowspan .' class="tdt">' .$title. '</td>' .$td_html .$value .$td_end_html;
        } elseif ($type == 'headline') {
            echo '<td' .$rowspan .' class="tdheadline" colspan="2">' .$title .'</td>';
        } else {
            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html .'<input type="' .htmlspecialchars($type) .'"';
            if ($args['readonly']) {
                echo ' readonly="readonly"';
            }
            if ($args['disabled']) {
                echo ' disabled="disabled"';
            }
            if ($args['maxlength']) {
                echo ' maxlength="' .$args['maxlength'] .'"';
            }
            echo ' class="' .$args['class'] .'" id="' .htmlspecialchars($id) .'" name="' .htmlspecialchars($id) .'" value="' .htmlspecialchars($value) .'"' .$js_tags .' />' .$td_end_html;
        }

        if (!array_key_exists('tr', $args) || $args['tr']) {
            echo '</tr>';
        }

        if ($args['descr']) {
            $descr = $args['descr'];

            if ($args['div']) {
                $descr = '<div class="td">' .$descr .'</div>';
            }

            echo '<tr><td' .$rowspan .' colspan="2"';
            if (!$args['div']) {
                echo ' class="tdd"';
            }
            echo '>' .$descr .'</td></tr>';
        }
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /**
     * TODO
     *
     * @param mixed $opts     TODO
     * @param mixed $selected TODO
     *
     * @return TODO
     */
    function drawOpts($opts, $selected = null)
    {
        if (is_object($selected)) {
            $selected = $selected->id();
        } elseif (is_array($selected) && is_object($selected[0])) {
            $selected = call_user_func(array($selected[0], $selected[1]), $selected[2]);
        }

        $html = '';
        foreach ($opts as $key => $value) {
            $html .= '<option';

            $is_selected = false;
            if (is_array($selected) && in_array($key, $selected)) {
                $is_selected = true;
            } elseif (is_array($selected) && ($selected['type'] == 'arr_rows' || $selected['type'] == 'arr_values')) {
                if (is_array($selected['values'])) {
                    foreach ($selected['values'] as $sel_key => $sel_val) {
                        if (is_a($sel_val, 'knjdb_row')) {
                            if ($key == $sel_val->id()) {
                                $is_selected = true;
                            }
                        } else {
                            if ($selected['type'] == 'arr_values') {
                                if ($key == $sel_val) {
                                    $is_selected = true;
                                }
                            } else {
                                if ($key == $sel_key) {
                                    $is_selected = true;
                                }
                            }
                        }
                    }
                }
            } elseif ($key == $selected) {
                if (!is_numeric($key) || intval($key) != 0) {
                    $is_selected = true;
                }
            }

            if ($is_selected) {
                $html .= ' selected="selected"';
            }

            $html .= ' value="' .htmlspecialchars($key) .'">' .htmlspecialchars($value) ."</option>\n";
        }

        return $html;
    }

    /**
     * Function to show a message through the JS-alert-function.
     *
     * @param string $msg Message to display.
     *
     * @return null
     */
    function alert($msg)
    {
        $msg = knj_strings::jsparse($msg);

        echo '<script type="text/javascript"><!--
            alert("' .$msg .'");
        --></script>';

        self::$alert_sent = true;
    }

    /**
     * Redirect browser to a new address
     *
     * @param string $url    Address to go to.
     * @param int    $status The http status code tog give.
     * @param bool   $exit   End execution emidiatly after the redirect
     *
     * @return null
     */
    function redirect($url, $status = 303, $exit = true)
    {
        if (!headers_sent() && !self::$alert_sent) {
            $url = parse_url($url);
            if (!$url['scheme']) {
                $url['scheme'] = $_SERVER['HTTPS'] != 'on' ? 'http' : 'https';
            }
            if (!$url['host']) {
                $url['host'] = $_SERVER['HTTP_HOST'];
            }
            if (!$url['path']) {
                $url['path'] = $_SERVER['REQUEST_URL'];
            } elseif (substr($url['path'], 0, 1) != '/') {
                preg_match('#^\S+/#u', $_SERVER['REQUEST_URL'], $path);
                $url['path'] = $path[0] . $url['path'];
            }
            $url = self::unparseUrl($url);

            apache_setenv('no-gzip', 1);
            ini_set('zlib.output_compression', 0);

            header('Location: ' . $url, true, $status);
        } else {
            echo '<script type="text/javascript"><!--
                location.href = "' .$url .'";
            --></script>';
        }

        if ($exit) {
            exit;
        }
    }

    /**
     * TODO
     *
     * @return string TODO
     */
    function back()
    {
        echo '<script type="text/javascript"><!--
            history.back(-1);
        --></script>';
        exit;
    }

    /**
     * Build a url string from an array
     *
     * @param array $parsed_url Array as returned by parse_url()
     *
     * @return string The URL
     */
    static function unparseUrl($parsed_url)
    {
        $scheme   = $parsed_url['scheme'] ? $parsed_url['scheme'] .'://' : '';
        $host     = $parsed_url['host'] ? $parsed_url['host'] : '';
        $port     = $parsed_url['port'] ? ':' .$parsed_url['port'] : '';
        $user     = $parsed_url['user'] ? $parsed_url['user'] : '';
        $pass     = $parsed_url['pass'] ? ':' . $parsed_url['pass'] : '';
        $pass     .= ($user || $pass) ? '@' : '';
        $path     = $parsed_url['path'] ? $parsed_url['path'] : '';
        $query    = $parsed_url['query'] ? '?' . $parsed_url['query'] : '';
        $fragment = $parsed_url['fragment'] ? '#' . $parsed_url['fragment'] : '';
        return $scheme .$user .$pass .$host .$port .$path .$query .$fragment;
    }
}

/**
 * This class handels code for the users browser.
 *
 * @category Framework
 * @package  Knjphpfw
 * @author   Kasper Johansen <kaspernj@gmail.com>
 * @license  Public domain http://en.wikipedia.org/wiki/Public_domain
 * @link     https://github.com/kaspernj/knjphpfw
 */
class knj_browser
{
    /** Returns the browser.
     *
     * @return string ie|chrome|safari|konqueror|opera|mozilla|firefox
     */
    static function getBrowser()
    {
        $uagent = '';
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $uagent = $_SERVER['HTTP_USER_AGENT'];
        }

        if (strpos($uagent, 'MSIE') !== false) {
            return 'ie';
        } elseif (strpos($uagent, 'Chrome') !== false) {
            return 'chrome';
        } elseif (strpos($uagent, 'Safari') !== false) {
            return 'safari';
        } elseif (strpos($uagent, 'Konqueror') !== false) {
            return 'konqueror';
        } elseif (strpos($uagent, 'Opera') !== false) {
            return 'opera';
        } else {
            return 'mozilla';
        }
    }

    /**
     * Returns the major version of the browser.
     *
     * @return int
     */
    static function getVersion()
    {
        $uagent = '';
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $uagent = $_SERVER['HTTP_USER_AGENT'];
        }

        if (knj_browser::getBrowser() == 'ie') {
            if (preg_match('/MSIE (\d+)/', $uagent, $match)) {
                return $match[1];
            } elseif (strpos($uagent, '7.0') !== false) {
                return 7;
            } else {
                return 6;
            }
        } elseif (knj_browser::getBrowser() == 'safari') {
            if (strpos($uagent, 'Version/4.0') !== false) {
                return 4;
            }
        } elseif (knj_browser::getBrowser() == 'konqueror') {
            if (strpos($uagent, 'Konqueror/3') !== false) {
                return 3;
            } elseif (strpos($uagent, 'Konqueror/4') !== false) {
                return 4;
            }
        } elseif (knj_browser::getBrowser() == 'mozilla' || knj_browser::getBrowser() == 'firefox') {
            if (strpos($uagent, 'Firefox/3') !== false) {
                return 3;
            } elseif (strpos($uagent, 'Firefox/2') !== false) {
                return 2;
            }
        } elseif (knj_browser::getBrowser() == 'chrome') {
            if (strpos($uagent, 'Chrome/4') !== false) {
                return 4;
            }
        }

        return 0;
    }

    /**
     * Returns the registered operating-system.
     *
     * @return string windows|linux|mac|bot|unknown|playstation|wii|sun
     */
    static function getOS()
    {
        $bots = array(
            'yahoo! slurp',
            'msnbot',
            'googlebot',
            'adsbot',
            'ask jeeves',
            'conpilot crawler',
            'yandex',
            'exabot',
            'hostharvest',
            'dotbot',
            'ia_archiver',
            'httpclient',
            'spider.html',
            'comodo-certificates-spider',
            'sbider',
            'speedy spider',
            'spbot',
            'aihitbot',
            'scoutjet',
            'com_bot',
            'aihitbot',
            'robot.html',
            'robot.htm',
            'catchbot',
            'baiduspider',
            'setoozbot',
            'sslbot',
            'browsershots',
            'perl',
            'wget',
            'w3c_validator',
        );

        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        } else {
            return 'unknown';
        }

        if (strpos($ua, 'windows') !== false) {
            return 'windows';
        } elseif (strpos($ua, 'linux') !== false) {
            return 'linux';
        } elseif (strpos($ua, 'mac') !== false) {
            return 'mac';
        } elseif (strpos($ua, 'playstation') !== false) {
            return 'playstation';
        } elseif (strpos($ua, 'nintendo wii') !== false) {
            return 'wii';
        } elseif (knjarray::stringsearch($ua, $bots)) {
            return 'bot';
        } elseif (strpos($ua, 'sunos') !== false) {
            return 'sun';
        } else {
            return 'unknown';
        }
    }

    /**
     * Returns the version of the users operating-system.
     *
     * @return array TODO
     */
    static function getOSVersion()
    {
        $version = 'unknown';

        if (knj_browser::getOS() == 'windows') {
            if (preg_match('/Windows\s+NT\s+([\d\.]+)/', $_SERVER['HTTP_USER_AGENT'], $match)) {
                if ($match[1] == 6.0) {
                    $version = 'vista';
                } elseif ($match[1] == 5.1) {
                    $version = 'xp';
                }
            } else {
                throw new exception('Could not match version.');
            }
        } elseif (knj_browser::getOS() == 'linux') {
            if (preg_match('/Ubuntu\/([\d+\.]+)/', $_SERVER['HTTP_USER_AGENT'], $match)) {
                $version = 'ubuntu_' . str_replace('.', '_', $match[1]);
            } else {
                throw new exception('Unknown user-agent for OS "' . knj_browser::getOS() . '": ' . $_SERVER['HTTP_USER_AGENT']);
            }
        } else {
            throw new exception('Unknown user-agent for OS "' . knj_browser::getOS() . '": ' . $_SERVER['HTTP_USER_AGENT']);
        }

        return array('version' => $version);
    }

    /**
     * Detect the browseres prefered language
     *
     * @param array $servervar Array to use instead of $_SERVER
     *
     * @return string ISO 3166-1 alpha-2
     */
    static function locale($servervar = array())
    {
        if (!$servervar) {
            $servervar = $_SERVER;
        }

        $locale = explode(',', $servervar['HTTP_ACCEPT_LANGUAGE']);
        if (preg_match("/^([a-z]{2})(_|-)[A-Z]{2}/i", $locale[0], $match)) {
            $locale = $match[1];
        } elseif (preg_match("/^([a-z]{2})$/", $locale[0], $match)) {
            $locale = $match[1];
        } else {
            $locale = 'en';
        }

        return $locale;
    }
}


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
    static function inputs($args)
    {
        $html = '';
        foreach ($args as $arg) {
            $html .= self::input($arg);
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
    static function input($args)
    {
        ob_start();

        $value = isset($args['value']) ? $args['value'] : '';
        $id = isset($args['name']) ? $args['name'] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        $title = $args['title'];

        if ($type == 'text' && !empty($args['opts'])) {
            $type = 'select';
        }

        if (empty($args['html'])) {
            $title = htmlspecialchars($title);
        }

        if (!isset($args['class'])) {
            $args['class'] = '';
        } else {
            $args['class'] .= ' ';
        }

        if ($type == 'password') {
            $args['class'] .= 'input_text';
        } elseif ($type == 'radiogroup') {
            $args['class'] .= 'input_radio';
            //TODO Merge radiogroup into radio using value as an array
        } else {
            $args['class'] .= 'input_' . $type;
        }

        if (!isset($args['tr']) || $args['tr']) {
            echo '<tr>';
        }
        $td_html = '<td class="tdc"';

        if (!empty($args['width'])) {
            $td_html .= ' style="width:' . $args['width'] . ';"';
        }

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
            if (!empty($args[$js_tag])) {
                $js_tags .= ' ' . $js_tag . '="' . $args[$js_tag] . '"';
            }
        }

        if (array_key_exists('autocomplete', $args) && !$args['autocomplete']) {
            $js_tags .= ' autocomplete="off"';
        }

        if ($type == 'checkbox') {
            echo '<td' .$rowspan .' colspan="2" class="tdcheck"><input';
            if ($args['disabled']) {
                echo ' disabled="disabled"';
            }
            echo ' type="' .$type .'" class="'. $args['class'] .'" name="' .$id .'" id="' .$id .'"';
            if ($value || !empty($args['checked'])) {
                echo ' checked="checked"';
            }
            echo $js_tags .' /><label for="' .$id .'">' .$title .'</label></td>';
        } elseif ($type == 'select') {
            $etags = '';
            if (!empty($args['disabled'])) {
                $etags .= ' disabled="disabled"';
            }

            if (!empty($args['multiple'])) {
                $etags .= ' multiple="multiple"';
            }

            if (!empty($args['height'])) {
                $etags .= ' height="' . htmlspecialchars($args['height']) . '"';
            }

            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html .'<select' .$etags;
            if ($args['size']) {
                echo ' size="' .htmlspecialchars($args['size']) .'"';
            }
            echo ' name="' .htmlspecialchars($id);
            if ($args['multiple'] && mb_substr($id, -2) != '[]') {
                echo '[]';
            }
            echo '" id="' .htmlspecialchars($id) .'" class="' .$args['class'] .'"'
                .$js_tags .'>' .self::drawOpts($args['opts'], $value) .'</select>';

            echo $td_end_html;
        } elseif ($type == 'treeselect') {
            $etags = '';
            if (!empty($args['disabled'])) {
                $etags .= ' disabled="disabled"';
            }

            if (mb_substr($id, -2) == '[]') {
                $id = mb_substr($id, 0, -2);
            }

            if (!empty($args['multiple'])) {
                $etags .= ' type="checkbox"';
            } else {
                //TODO expand
                $etags .= ' type="radio"';
            }

            $etags .= $js_tags;

            $style = 'overflow-y: scroll;';
            if (!empty($args['height'])) {
                $style .= 'height: ' .$args['height'] .';';
            }

            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html .
                '<div style="' . $style . '" class="' .$args['class'] .'">';

            self::drawTreeOpts(
                array(
                    'id' => $id,
                    'html' => $etags,
                    'multiple' => !empty($args['multiple']),
                ),
                $args['opts'],
                $value
            );
            echo '</div>';

            echo $td_end_html;
        } elseif ($type == 'file') {
            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html .'<input type="file" class="input_' .$type .'" name="' .htmlspecialchars($id) .'" id="' .htmlspecialchars($id) .'"' .$js_tags .' />' .$td_end_html;
        } elseif ($type == 'textarea') {
            echo '<td' .$rowspan .' class="tdt">' .$title .'</td>' .$td_html .'<textarea name="' .htmlspecialchars($id) .'" id="' .htmlspecialchars($id) .'" class="' .htmlspecialchars($args['class']) .'"';
            if (!empty($args['height'])) {
                echo ' style="height: ' .$args['height'] .';"';
            }
            if ($args['readonly']) {
                echo ' readonly="readonly"';
            }
            if ($args['disabled']) {
                echo ' disabled="disabled"';
            }
            echo $js_tags .'>' .htmlspecialchars($value, null, 'UTF-8') .'</textarea>' .$td_end_html;
        } elseif ($type == 'radio') {
            echo '<td' .$rowspan .' class="tdt" colspan="2">
            <input type="radio" id="' .htmlspecialchars($id .'_' .$value)
                .'" name="' .htmlspecialchars($id)
                .'" value="' .htmlspecialchars($value)
                .'"  class="' .htmlspecialchars($args['class']) .'"';
            if ($args['checked']) {
                echo ' checked="checked"';
            }
            if ($args['disabled']) {
                echo ' disabled="disabled"';
            }
            echo $js_tags. ' /><label for="' .htmlspecialchars($id .'_' .$value) .'">' .$title .'</label></td>';
        } elseif ($type == 'radiogroup') {
            echo '<td' .$rowspan .' class="tdt" style="vertical-align: middle">' .$title .'</td>' .$td_html;
            $class = $args['class'];
            foreach ($args['group'] as $item) {
                if (!empty($item['class'])) {
                    $class = $item['class'] . ' ' . $class;
                }
                echo '<input type="radio" id="' .htmlspecialchars($id .'_' .$item['value'])
                .'" name="' .htmlspecialchars($id)
                .'" value="' .htmlspecialchars($item['value'])
                .'"  class="' .htmlspecialchars($class) .'"';
                if (!empty($item['checked'])) {
                    echo ' checked="checked"';
                }
                if (!empty($item['disabled'])) {
                    echo ' disabled="disabled"';
                }
                echo $js_tags. ' /><label for="' .htmlspecialchars($id .'_' .$item['value']) .'">' .$item['title'] .'</label>';
            }
            echo $td_end_html;
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


        if (!isset($args['tr']) || $args['tr']) {
            echo '</tr>';
        }

        if (!empty($args['descr'])) {
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
     * @param array $selected TODO
     *
     * @return null
     */
    static function drawOpts($opts, $selected = null)
    {
        $html = '';
        foreach ($opts as $key => $value) {
            $html .= '<option';

            $is_selected = false;
            if (is_array($selected) && in_array($key, $selected)) {
                $is_selected = true;
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
     * TODO
     *
     * @param mixed $args     TODO
     * @param array $opts     key is input value, contaning and array with 'title'
     *                        and optionally 'subs' with a recursive array
     * @param array $selected Array of selected values
     *
     * @return null
     */
    static function drawTreeOpts($args, $opts, $selected = null)
    {
        $selected = (array) $selected;
        foreach ($opts as $key => $value) {
            $isSelected = false;
            if (in_array($key, $selected)) {
                $isSelected = true;
            }

            //TODO expand parents for non multiple

            echo '<div>';

            $name = $args['id'];
            if ($args['multiple']) {
                $name .= '[' . $key . ']';
            }

            $id = $args['id'] .'_' .$key;

            if (!empty($value['subs'])) {
                echo '<div class="pointer';
                if ($isSelected && $args['multiple']) {
                    echo ' open';
                }
                echo '"></div>';
            } else {
                echo '<div class="blank"></div>';
            }

            echo '<input id="' .htmlspecialchars($id)
                .'" name="' .htmlspecialchars($name)
                .'" value="' .htmlspecialchars($key) . '"';
            if ($isSelected) {
                echo ' checked="checked"';
            }
            echo $args['html'] . ' /><label for="' .htmlspecialchars($id) .'">'
            .$value['title'] .'</label>';
            if (!empty($value['subs'])) {
                echo '<div class="subs"';
                if (!$isSelected || !$args['multiple']) {
                    echo ' style="display:none;"';
                }
                echo '>';
                self::drawTreeOpts($args, $value['subs'], $selected);
                echo '</div>';
            }
            echo '</div>';
        }
    }

    /**
     * Function to show a message through the JS-alert-function.
     *
     * @param string $msg Message to display.
     *
     * @return null
     */
    static function alert($msg)
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
    static function redirect($url, $status = 303, $exit = true)
    {
        if (!headers_sent() && !self::$alert_sent) {
            $url = parse_url($url);
            if (!$url['scheme']) {
                $url['scheme'] = $_SERVER['HTTPS'] != 'on' ? 'http' : 'https';
            }
            if (!$url['host']) {
                //http://stackoverflow.com/questions/2297403/http-host-vs-server-name
                //http://shiflett.org/blog/2006/mar/server-name-versus-http-host
                if ($_SERVER['HTTP_HOST']) {
                    // Browser
                    $url['host'] = $_SERVER['HTTP_HOST'];
                } elseif ($_SERVER['SERVER_NAME']) {
                    // Can both be from Browser and Apache (virtual) server config
                    $url['host'] = $_SERVER['SERVER_NAME'];
                } elseif ($_SERVER['SERVER_ADDR']) {
                    // IP
                    $url['host'] = $_SERVER['SERVER_ADDR'];
                } else {
                    // default to empty
                    $url['host'] = '';
                }
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
    static function back()
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


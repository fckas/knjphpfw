<?php
/**
 * Implement knj_autoload
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
 * Function for automtically loading the needed file for a given class
 *
 * Use it from with in your spl_autoload_register() function
 *
 * @category Framework
 * @package  Knjphpfw
 * @author   Kasper Johansen <kaspernj@gmail.com>
 * @license  Public domain http://en.wikipedia.org/wiki/Public_domain
 * @link     https://github.com/kaspernj/knjphpfw
 */
class knj_autoload
{
    static $knj = array(
        'web' => 'web',
        'knj_browser' => 'web',
        'knj_ftp' => 'ftp',
        'knj_os' => 'os',
        'objects' => 'objects',
        'knjarray' => 'array',
        'knjdb' => 'db',
        'knjdb_async' => 'knjdb/class_knjdb_async',
        'knjobjects' => 'objects',
        'knj_strings' => 'strings',
        'epay' => 'epay',
    );

    /**
     * Load the file based on the class name
     *
     * @param string $classname TODO
     *
     * @return null
     */
    static function load($classname)
    {
        $class = mb_strtolower($classname);
        if (isset(self::$knj[$class])) {
            include_once self::$knj[$class] . '.php';
        }
    }
}


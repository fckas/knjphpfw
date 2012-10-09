<?php
/**
 * TODO
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
class knj_autoload
{
    /**
     * TODO
     */
    function __construct()
    {
        $this->knj = array(
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
            'notfoundexc' => 'exceptions',
            'epay' => 'epay'
        );
    }

    /**
     * TODO
     *
     * @param string $classname TODO
     *
     * @return null
     */
    function load($classname)
    {
        $class = mb_strtolower($classname);
        if (array_key_exists($class, $this->knj)) {
            include_once 'knj/' .$this->knj[$class] .'.php';
        }
    }
}


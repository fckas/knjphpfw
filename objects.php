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
class knjobjects
{
    private $_objects;
    public $db;

    /**
     * Set database connection and reference array
     *
     * @param knjdb $db The database connection to use
     */
    public function __construct(knjdb $db)
    {
        $this->db = $db;
        $this->_objects = array();
    }

    /**
     * Create an object and keep it in memory for future use
     *
     * @param string $class The class type of the object
     * @param mixed  $id    The database id of the object
     * @param array  $data  Avoides fetching it from the database
     *
     * @return object
     */
    public function get($class, $id, array $data = array())
    {
        if (is_array($id)) {
            $data = $id;
            //FIXME respect col_id
            $id = $data['id'];
        } elseif (!$data) {
            $data = $id;
        }

        if (isset($this->_objects[$class])
            && isset($this->_objects[$class][$id])
        ) {
            return $this->_objects[$class][$id];
        }

        $object = new $class(array('ob' => $this, 'data' => $data));

        $this->_objects[$class][$id] = $object;

        return $object;
    }

    /**
     * Get a single object using getList
     *
     * @param string $class The class type of the object
     * @param array  $args  Search parameters
     *
     * @return object
     */
    public function getBy($class, array $args = array())
    {
        $args['limit'] = 1;

        $objects = $this->getList($class, $args);
        if (!$objects) {
            return false;
        }

        return reset($objects);
    }

    /**
     * Get multiple objects
     *
     * @param string $class The class type of the objects
     * @param array  $args  Search parameters
     *
     * @return array
     */
    public function getList($class, array $args = array())
    {
        $objects = $class::getList($args);

        if (is_array($objects)) {
            foreach ($objects as $object) {
                $this->_objects[$class][$object->id()] = $object;
            }
        }

        return $objects;
    }

    public function listOpts($class, $getkey, array $args = array())
    {
        $opts = array();

        if ($args['addnew']) {
            unset($args['addnew']);
            $opts[0] = _('Add new');
        }

        if ($args['none']) {
            unset($args['none']);
            $opts[0] = _('None');
        }

        if ($args['choose']) {
            unset($args['choose']);
            $opts[0] = _('Choose') . ':';
        }

        if ($args['all']) {
            unset($args['all']);
            $opts[0] = _('All');
        }

        if (!$args['col_id']) {
            //FIXME respect the general setting
            $args['col_id'] = 'id';
        }

        if (!$args['list_args']) {
            $args['list_args'] = array();
        }

        foreach ($this->getList($class, $args['list_args']) as $object) {
            if (is_array($getkey) && $getkey['funccall']) {
                $value = call_user_func(array($object, $getkey['funccall']));
            } else {
                $value = $object->get($getkey);
            }

            $opts[$object->get($args['col_id'])] = $value;
        }

        return $opts;
    }

    public function getListBySql($class, $sql, array $args = array())
    {
        $objects = array();
        $results = $this->db->query($sql);
        while ($data = $results->fetch()) {
            if (isset($args['col_id'])) {
                $objects[] = $this->get($class, $data[$args['col_id']], $data);
            } else {
                $objects[] = $this->get($class, $data);
            }
        }
        $results->free();

        return $objects;
    }

    public function sqlHelper(array &$list_args, array $args)
    {
        if ($args && array_key_exists('db', $args) && $args['db']) {
            $db = $args['db'];
        } else {
            $db = $this->db;
        }

        if ($args && array_key_exists('table', $args) && $args['table']) {
            $table = $db->conn->sep_table . $db->escape_table($args['table']) . $db->conn->sep_table . '.';
        } else {
            $table = '';
        }

        $colsep = $db->conn->sep_col;

        $sql_where = '';
        $sql_limit = '';
        $sql_order = '';

        $dbrows_exist = array_key_exists('cols_dbrows', $args);
        $num_exists = array_key_exists('cols_num', $args);
        $str_exists = array_key_exists('cols_str', $args);

        foreach ($list_args as $list_key => $list_val) {
            $found = false;

            if ($str_exists
                && (
                    in_array($list_key, $args['cols_str'])
                    || ($num_exists && in_array($list_key, $args['cols_num']))
                )
            ) {
                if (is_array($list_val)) {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " IN (" . knjarray::implode(array('array' => $list_val, 'impl' => ",", 'surr' => "'", 'self_callback' => array($db, 'sql'))) . ")";
                } elseif ($list_val === null) {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " IS NULL";
                } else {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " = '" . $db->sql($list_val) . "'";
                }

                $found = true;
            } elseif (($str_exists || $num_exists)
                && preg_match('/^(.+)_(has|not)$/', $list_key, $match)
                && (
                    ($str_exists && in_array($match[1], $args['cols_str']))
                    || ($num_exists && in_array($match[1], $args['cols_num']))
                )
            ) {
                if ($match[2] == 'has') {
                    if ($list_val) {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " != ''";
                    } elseif ($list_val === null) {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " IS NULL";
                    } else {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " = ''";
                    }
                    $found = true;
                } elseif ($match[2] == 'not') {
                    if ($list_val === null) {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " IS NOT NULL";
                    } else {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " != '" . $db->sql($list_val) . "'";
                    }
                    $found = true;
                }
            } elseif ($dbrows_exist
                && in_array($list_key . '_id', $args['cols_dbrows'])
            ) {
                if (!is_object($list_val) && !is_bool($list_val) && !is_array($list_val)) {
                    throw new exception('Unknown type: ' . gettype($list_val) . ' for argument ' . $list_key);
                } elseif (is_object($list_val) && !method_exists($list_val, 'id')) {
                    throw new exception('Unknown method on object: ' . get_class($list_val) . '->id().');
                }

                if ($list_val === true) {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . '_id') . $colsep . " != '0'";
                } elseif ($list_val === false) {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . '_id') . $colsep . " = '0'";
                } elseif (is_array($list_val)) {
                    if (empty($list_val)) {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . '_id') . $colsep . " = '-1'";
                    } else {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . '_id') . $colsep . " IN (" . knjarray::implode(array('array' => $list_val, 'impl' => ",", 'surr' => "'", 'func_callback' => 'id')) . ")";
                    }
                } else {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . '_id') . $colsep . " = '" . $db->sql($list_val->id()) . "'";
                }

                $found = true;
            } elseif ($dbrows_exist
                && in_array($list_key . 'Id', $args['cols_dbrows'])
            ) {
                if (!is_object($list_val) && !is_bool($list_val)) {
                    throw new exception('Unknown type: ' . gettype($list_val));
                } elseif (is_object($list_val) && !method_exists($list_val, 'id')) {
                    throw new exception('Unknown method on object: ' . get_class($list_val) . '->id().');
                }

                if ($list_val === true) {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . 'Id') . $colsep . " != '0'";
                } elseif ($list_val === false) {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . 'Id') . $colsep . " = '0'";
                } else {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key . 'Id') . $colsep . " = '" . $db->sql($list_val->id()) . "'";
                }

                $found = true;
            } elseif ($dbrows_exist
                && in_array($list_key, $args['cols_dbrows'])
            ) {
                if (is_array($list_val)) {
                    if (empty($list_val)) {
                        throw new exception('No elements was given in array.');
                    }

                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " IN (" . knjarray::implode(array('array' => $list_val, 'impl' => ",", 'surr' => "'", 'self_callback' => array($db, 'sql'))) . ")";
                } else {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " = '" . $db->sql($list_val) . "'";
                }

                $found = true;
            } elseif (array_key_exists('cols_bool', $args)
                && in_array($list_key, $args['cols_bool'])
            ) {
                if ($list_val) {
                    $list_val = '1';
                } else {
                    $list_val = '0';
                }
                $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " = '" . $db->sql($list_val) . "'";
                $found = true;
            } elseif (substr($list_key, -7, 7) == '_search'
                && preg_match('/^(.+)_search$/', $list_key, $match)
                && (
                    ($str_exists && in_array($match[1], $args['cols_str']))
                    || ($dbrows_exist && in_array($match[1], $args['cols_dbrows']))
                    || ($num_exists && in_array($match[1], $args['cols_num']))
                )
            ) {
                $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " LIKE '%" . $db->sql($list_val) . "%'";
                $found = true;
            } elseif (substr($list_key, -6, 6) == '_lower'
                && preg_match('/^(.+)_lower$/', $list_key, $match)
                && in_array($match[1], $args['cols_str'])
            ) {
                $sql_where .= " AND LOWER(" . $table . $colsep . $db->escape_column($match[1]) . $colsep . ") = LOWER('" . $db->sql($list_val) . "')";
                $found = true;
            } elseif (array_key_exists('cols_num', $args)
                && preg_match('/^(.+)_(from|to)/', $list_key, $match)
                && in_array($match[1], $args['cols_num'])
            ) {
                $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep;
                $found = true;

                switch ($match[2]) {
                case 'from':
                    $sql_where .= " >= '" . $db->sql($list_val) . "'";
                    break;
                case 'to':
                    $sql_where .= " <= '" . $db->sql($list_val) . "'";
                    break;
                default:
                    throw new exception('Invalid mode: ' . $match[2]);
                }
            } elseif (array_key_exists('cols_dates', $args)
                && preg_match('/^(.+)_(date|time|from|to)/', $list_key, $match)
                && in_array($match[1], $args['cols_dates'])
            ) {
                $found = true;

                switch ($match[2]) {
                case 'date':
                    if (is_array($list_val)) {
                        if (!$list_val) {
                            throw new exception('Array was empty!');
                        }

                        $sql_where .= " AND (";
                        $first = true;

                        foreach ($list_val as $time_s) {
                            if ($first) {
                                $first = false;
                            } else {
                                $sql_where .= " OR ";
                            }

                            $sql_where .= "DATE(" . $table . $colsep . $db->escape_column($match[1]) . $colsep . ")";
                            $sql_where .= " = '" . $db->sql($db->date_format($time_s, array('time' => false))) . "'";
                        }

                        $sql_where .= ")";
                    } else {
                        $sql_where .= " AND DATE(" . $table . $colsep . $db->escape_column($match[1]) . $colsep . ")";
                        $sql_where .= " = '" . $db->sql($db->date_format($list_val, array('time' => false))) . "'";
                    }

                    break;
                case 'time':
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep;
                    $sql_where .= " = '" . $db->sql($db->date_format($list_val, array('time' => true))) . "'";
                case 'from':
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep;
                    $sql_where .= " >= '" . $db->sql($db->date_format($list_val, array('time' => true))) . "'";
                    break;
                case 'to':
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep;
                    $sql_where .= " <= '" . $db->sql($db->date_format($list_val, array('time' => true))) . "'";
                    break;
                default:
                    throw new exception('Invalid mode: ' . $match[2]);
                }
            } elseif ($list_key == 'limit') {
                $sql_limit .= " LIMIT " . intval($list_val);
                $found = true;
            } elseif ($list_key == 'limit_from' && $list_args['limit_to']) {
                $sql_limit .= " LIMIT " . intval($list_val) . ", " . intval($list_args['limit_to']);
                $found = true;
            } elseif ($list_key == 'limit_to') {
                $found = true;
            } elseif ($list_key == 'orderby') {
                if (is_string($list_val)) {
                    $sql_order .= " ORDER BY " . $table . $colsep . $db->escape_column($list_val) . $colsep;
                    $found = true;
                } elseif (is_array($list_val)) {
                    $found = true;
                    $sql_order .= " ORDER BY ";

                    $first = true;
                    foreach ($list_val as $val_ele) {
                        if ($first) {
                            $first = false;
                        } else {
                            $sql_order .= ", ";
                        }

                        $ordermode = 'asc';
                        if (is_array($val_ele)) {
                            $ordermode = strtolower($val_ele[1]);
                            $val_ele = $val_ele[0];
                        }

                        $sql_order .= $table . $colsep . $db->escape_column($val_ele) . $colsep;

                        if ($ordermode == 'desc') {
                            $sql_order .= " DESC";
                        } elseif ($ordermode == 'asc') {
                            $sql_order .= " ASC";
                        } elseif ($ordermode) {
                            throw new exception('Invalid order-mode: ' . $ordermode);
                        }
                    }
                }
            }

            if ($found) {
                unset($list_args[$list_key]);
            }
        }

        return array(
            'sql_where' => $sql_where,
            'sql_limit' => $sql_limit,
            'sql_order' => $sql_order,
        );
    }

    /**
     * Unset ferences for a specefic object
     *
     * @param string $object The object or class
     * @param mixed  $id     The id of the object
     *
     * @return null
     */
    public function unsetOb($object, $id = null)
    {
        if (is_object($object)) {
            $id = $object->id();
            $object = get_class($object);
        }

        unset($this->_objects[$object][$id]);
    }

    /**
     * Unset all references for a certen class type
     *
     * @param string $class The class to clear
     *
     * @return null
     */
    public function unsetClass($class)
    {
        unset($this->_objects[$class]);
    }

    /**
     * Unset all references
     *
     * @return null
     */
    public function unsetAll()
    {
        $this->_objects = array();
    }

    /**
     * Run unsetAll if 52MB or more memory is being used
     *
     * @return null
     */
    public function cleanMemory()
    {
        $usage = memory_get_usage() / 1024 / 1024;
        //TODO Why 52MiB ?
        if ($usage >= 52) {
            $this->unsetAll();
        }
    }
}

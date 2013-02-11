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

        $objects = (array) $this->getList($class, $args);

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
        //Get DB connection
        if (!empty($args['db'])) {
            $db = $args['db'];
        } else {
            $db = $this->db;
        }

        //Escape table
        $table = '';
        if (!empty($args['table'])) {
            $table = $db->conn->sep_table . $db->escape_table($args['table']) . $db->conn->sep_table . '.';
        }

        $sql_where = '';
        $sql_limit = '';
        $sql_order = '';

        $colsep = $db->conn->sep_col;

        //Process each directive
        foreach ($list_args as $list_key => $list_val) {
            //Having limit, limit_from and orderby first, makes them reserved names and differes from the original implementation
            if (is_array($list_val) && !$list_val) {
                continue;
            } elseif ($list_key == 'limit_from') {
                $sql_limit .= " LIMIT " . (int) $list_val . ", " . (int) $list_args['limit'];
                //FIXME test this doesn't iterate 'limit' later, posibly use where(list()=each()) instead of foreach().
                unset($list_args[$list_key], $list_args['limit']);
                continue;
            } elseif ($list_key == 'limit' && !isset($list_args['limit_from'])) {
                $sql_limit .= " LIMIT " . (int) $list_val;
                unset($list_args[$list_key]);
                continue;
            } elseif ($list_key == 'orderby') {
                if (is_string($list_val)) {
                    $list_val = array($list_val);
                }

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
                        $ordermode = mb_strtolower($val_ele[1]);
                        $val_ele = $val_ele[0];
                    }

                    $sql_order .= $table . $colsep . $db->escape_column($val_ele) . $colsep;

                    if ($ordermode == 'desc') {
                        $sql_order .= " DESC";
                    } elseif ($ordermode == 'asc') {
                        $sql_order .= " ASC";
                    } elseif ($ordermode) {
                        throw new exception(_('Invalid order-mode: ') . $ordermode);
                    }
                }
                unset($list_args[$list_key]);
                continue;
            }

            //Extract valid modifier
            $modifier = '';
            $match = array();
            preg_match('/^(.+)_(.+?)$/ui', $list_key, $match);
            if ($match
                && in_array($match[2], array('not', 'search', 'date', 'time', 'from', 'to'))
            ) {
                $modifier = $match[2];
                unset($match[2]);
            } else {
                $match = array($list_key);
            }

            //Look for valid colum name
            $matchKey = '';
            $matchNumber = false;
            $matchDate = false;
            $matchReference = false;
            foreach ($match as $key => $colname) {
                if (isset($args['cols_str']) && in_array($colname, $args['cols_str'])) {
                    $matchKey = $colname;
                }
                if (isset($args['cols_num']) && in_array($colname, $args['cols_num'])) {
                    $matchNumber = true;
                    $matchKey = $colname;
                }
                if (isset($args['cols_dates']) && in_array($colname, $args['cols_dates'])) {
                    $matchDate = true;
                    $matchKey = $colname;
                }
                if (isset($args['cols_dbrows']) && in_array($colname, $args['cols_dbrows'])) {
                    $matchReference = true;
                    $matchKey = $colname;
                } elseif (isset($args['cols_dbrows']) && in_array($colname . '_id', $args['cols_dbrows'])) {
                    $matchReference = true;
                    $matchKey = $colname . '_id';
                }
                if ($matchKey) {
                    unset($list_args[$list_key]);
                    break;
                }
            }
            if (!$matchKey) {
                continue;
            }

            //Create query
            if ($matchReference) {
                if (is_object($list_val) && !method_exists($list_val, 'id')) {
                    throw new exception('Unknown method on object: ' . get_class($list_val) . '->id().');
                }

                $sql_where .= " AND " . $table . $colsep . $db->escape_column($matchKey) . $colsep;

                if ($list_val === true) {
                    $sql_where .= " IS NOT NULL";
                } elseif ($list_val === false) {
                    $sql_where .= " IS NULL";
                } else {
                    if (!is_array($list_val)) {
                        $list_val = array($list_val);
                    }
                    foreach ($list_val as $key => $value) {
                        if (is_object($value)) {
                            $list_val[$key] = $value->id();
                        }
                    }
                    $list_val = array_map(array($db, 'sql'), $list_val);
                    $list_val = implode("', '", $list_val);
                    $sql_where .= " IN ('" . $list_val . "')";
                }
            } elseif ($matchNumber
                && in_array($modifier, array('from', 'to'))
            ) {
                $sql_where .= " AND " . $table . $colsep . $db->escape_column($matchKey) . $colsep;

                switch ($modifier) {
                case 'from':
                    $sql_where .= " >= '" . $db->sql($list_val) . "'";
                    break;
                case 'to':
                    $sql_where .= " <= '" . $db->sql($list_val) . "'";
                    break;
                default:
                    throw new exception('Invalid mode: ' . $modifier);
                }
            } elseif ($matchDate
                && in_array($modifier, array('date', 'time', 'from', 'to'))
            ) {
                switch ($modifier) {
                case 'date':
                    if (!$list_val) {
                        throw new exception('Array was empty!');
                    }
                    if (!is_array($list_val)) {
                        $list_val = array($list_val);
                    }

                    $sql_where .= " AND (";
                    $first = true;

                    foreach ($list_val as $time_s) {
                        if ($first) {
                            $first = false;
                        } else {
                            $sql_where .= " OR ";
                        }

                        $sql_where .= "DATE(" . $table . $colsep . $db->escape_column($matchKey) . $colsep . ")";
                        $sql_where .= " = '" . $db->sql($db->date_format($time_s, array('time' => false))) . "'";
                    }

                    $sql_where .= ")";

                    break;
                case 'time':
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($matchKey) . $colsep;
                    $sql_where .= " = '" . $db->sql($db->date_format($list_val, array('time' => true))) . "'";
                case 'from':
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($matchKey) . $colsep;
                    $sql_where .= " >= '" . $db->sql($db->date_format($list_val, array('time' => true))) . "'";
                    break;
                case 'to':
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($matchKey) . $colsep;
                    $sql_where .= " <= '" . $db->sql($db->date_format($list_val, array('time' => true))) . "'";
                    break;
                default:
                    throw new exception('Invalid mode: ' . $modifier);
                }
            } else {
                $sql_where .= " AND " . $table . $colsep . $db->escape_column($matchKey) . $colsep;
                if ($modifier == 'search') {
                    $sql_where .= " LIKE '%" . $db->sql($list_val) . "%'";
                } elseif (is_array($list_val)) {
                    $list_val = array_map(array($db, 'sql'), $list_val);
                    $list_val = implode("', '", $list_val);
                    if ($modifier == 'not') {
                        $sql_where .=  " NOT";
                    }
                    $sql_where .=  " IN ('" . $list_val . "')";
                } elseif ($list_val === null) {
                    if ($modifier == 'not') {
                        $sql_where .=  " IS NOT NULL";
                    } else {
                        $sql_where .=  " IS NULL";
                    }
                } else {
                    if ($modifier == 'not') {
                        $sql_where .=  " != '";
                    } else {
                        $sql_where .=  " = '";
                    }
                    $sql_where .=  $db->sql($list_val) . "'";
                }
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

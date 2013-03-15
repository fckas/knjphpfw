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
     * @param array  $data  Avoids fetching it from the database
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

        if ($args['blank']) {
            unset($args['blank']);
            $opts[''] = '';
        }

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

    /**
     * Generate SQL parts ready for use in a query
     *
     * @param array &$list_args Key is field name to match against. Fields can be
     *                          suffixed with a modifier to change the type of match.
     *                          *_not:    Invert match
     *                          *_search: Containing string
     *                          *_to:     Lower or equal
     *                          *_from:   Equal or higher
     *                          There are 3 special keys
     *                          limit:      Max rows to return
     *                          limit_from: Skip number of rows (requires limit)
     *                          orderby:    Array|string of fields to order by.
     *                          Keys|string is field, value can be DESC for decending
     *                          or array for specific order.
     *                          Recogniced feilds will be unset form the array.
     * @param array $args       Configuraitions for and table information db: The
     *                          knjdb object the SQL is ment for
     *                          table: The table that will be queried
     *                          cols:  List of valid feilds as key, keys limit,
     *                          limit_from and orderby are reserved. If value is
     *                          'time' input will be converted from timestamp into db
     *                          native format.
     *
     * @return array(sql_where => string, sql_order => string, sql_limit => string).
     *                          The SQL statement is included with sql_order and
     *                          sql_limit, but not sql_where.
     */
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
            $table = $db->conn->sep_table . $db->escape_table($args['table'])
                . $db->conn->sep_table . '.';
        }

        $sql_where = '';
        $sql_limit = '';
        $sql_order = '';

        $colsep = $db->conn->sep_col;

        //Process each directive
        foreach ($list_args as $list_key => $list_val) {
            //empty input
            if (is_array($list_val) && !$list_val) {
                unset($list_args[$list_key]);
                continue;
            }
            //FIXME move 'limit', 'limit_from' and 'order' to a new parameter so they
            // are separate from WHERE criteria
            if ($list_key == 'limit' || $list_key == 'limit_from') {
                //Use knjdb driver to stay compatible with non-MySQL
                if (isset($list_args['limit_from'])) {
                    $sql_limit = " LIMIT " . (int) $list_args['limit_from'] . ", "
                        . (int) $list_args['limit'];
                    unset($list_args['limit_from']);
                } else {
                    $sql_limit = " LIMIT " . (int) $list_val;
                }
                unset($list_args['limit']);
                continue;
            } elseif ($list_key == 'orderby') {
                if (is_string($list_val)) {
                    $list_val = array($list_val => 'ASC');
                }

                $sql_order .= " ORDER BY ";

                $first = true;
                foreach ($list_val as $field => $ordermode) {
                    if ($first) {
                        $first = false;
                    } else {
                        $sql_order .= ", ";
                    }

                    if (is_array($ordermode)) {
                        $sql_order .=  "CASE " . $table
                            . $colsep . $db->escape_column($feild) . $colsep;
                        foreach ($ordermode as $key => $value) {
                            $sql_order .=  " WHEN '" . $db->escape_column($value)
                                . "' THEN " . $key;
                        }
                        $sql_order .=  " END";
                        continue;
                    } elseif (mb_strtoupper($ordermode) == 'DESC') {
                        $ordermode = " DESC";
                    } else {
                        $ordermode = " ASC";
                    }

                    $sql_order .=  $table
                        . $colsep . $db->escape_column($field) . $colsep
                        . $ordermode;
                }
                unset($list_args[$list_key]);
                continue;
            }

            //Extract valid modifier
            $modifier = '';
            if (preg_match('/^(.+)_(.+?)$/ui', $list_key, $match)
                && in_array($match[2], array('not', 'search', 'from', 'to'))
            ) {
                $modifier = $match[2];
                unset($match[2]);
            } else {
                $match = array($list_key);
            }

            //Look for valid column name
            $matchKey = '';
            foreach ($match as $colname) {
                if (isset($args['cols'][$colname])) {
                    $matchKey = $colname;
                    unset($list_args[$list_key]);

                    if ($args['cols'][$colname] === 'time') {
                        if (!$list_val) {
                            $list_val = "0000-00-00 00:00:00";
                        } else {
                            $list_val = $db->date_format(
                                $list_val,
                                array('time' => true)
                            );
                        }
                    }

                    break;
                }
            }
            if (!$matchKey) {
                continue;
            }

            $sql_where .= " AND " . $table . $colsep . $db->escape_column($matchKey)
                . $colsep;

            //Create query
            if ($modifier == 'search') {
                $sql_where .= " LIKE '%" . $db->sql($list_val) . "%'";
            } elseif (is_array($list_val)) {
                if ($modifier == 'not') {
                    $sql_where .=  " NOT";
                }

                $list_val = array_map(array($db, 'sql'), $list_val);
                $list_val = implode("', '", $list_val);
                $sql_where .= " IN ('" . $list_val . "')";
            } elseif ($list_val === null) {
                if ($modifier == 'not') {
                    $sql_where .=  " IS NOT NULL";
                } else {
                    $sql_where .=  " IS NULL";
                }
            } else {
                if ($modifier == 'not') {
                    $sql_where .=  " != ";
                } elseif ($modifier == 'from') {
                    $sql_where .= " >= ";
                } elseif ($modifier == 'to') {
                    $sql_where .= " <= ";
                } else {
                    $sql_where .=  " = ";
                }

                $sql_where .=  "'" . $db->sql($list_val) . "'";
            }
        }

        return array(
            'sql_where' => $sql_where,
            'sql_order' => $sql_order,
            'sql_limit' => $sql_limit,
        );
    }

    /**
     * Unset references for a specific object
     *
     * @param string $object The object or class
     * @param mixed  $id     The id of the object
     *
     * @return void
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
     * @return void
     */
    public function unsetClass($class)
    {
        unset($this->_objects[$class]);
    }

    /**
     * Unset all references
     *
     * @return void
     */
    public function unsetAll()
    {
        $this->_objects = array();
    }

    /**
     * Run unsetAll if 52MB or more memory is being used
     *
     * @return void
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

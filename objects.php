<?php

class knjobjects
{
    public $objects;
    public $db;

    function __construct($args)
    {
        $this->db = $args;
        $this->objects = array();
    }

    function add($ob, $arr)
    {
        $call_args = array($arr);

        return call_user_func_array(array($ob, 'addNew'), $call_args);
    }

    function get($ob, $id, $data = null)
    {
        if (is_array($id)) {
            $data = $id;
            $rdata = &$data;
            $id = $data['id'];
        } elseif (is_array($data) && $data) {
            $rdata = &$data;
        } else {
            $rdata = &$id;
        }

        if (!is_string($ob)) {
            throw new exception('Invalid object: ' . gettype($ob));
        } elseif (is_object($id)) {
            throw new exception('Invalid object: ' . get_class($id));
        }

        $id_exists = false;
        if (isset($this->objects[$ob])) {
            $id_exists = array_key_exists($id, $this->objects[$ob]);
        }

        if ($id_exists) {
            if ($this->weakmap) {
                $ref = $this->objects[$ob][$id];

                if ($this->weakmap_refs[$ref]) {
                    print 'Reusing! ' . $ob . '-' . $id . "\n";
                    return $this->weakmap_refs[$ref];
                }
            } elseif ($this->weakref) {
                if ($this->objects[$ob][$id]->acquire()) {
                    print 'Reusing! ' . $ob . '-' . $id . "\n";
                    $obj = $this->objects[$ob][$id]->get();
                    $this->objects[$ob][$id]->release();
                    return $obj;
                }
            } else {
                return $this->objects[$ob][$id];
            }
        }

        $obj = new $ob(
            array(
                'ob' => $this,
                'data' => $rdata
            )
        );

        if ($this->weakref) {
            $this->objects[$ob][$id] = new weakref($obj);
        } elseif ($this->weakmap) {
            $ref = new stdclass;
            $this->weakmap_refs[$ref] = $obj;
            $this->objects[$ob][$id] = $ref;
        } else {
            $this->objects[$ob][$id] = $obj;
        }

        return $obj;
    }

    function getBy($obj, array $args)
    {
        $args['limit'] = 1;

        $objs = $this->getList($obj, $args);
        if (!$objs) {
            return false;
        }

        $data = each($objs);
        return $data[1];
    }

    function getList($ob, $args = array(), $list_args = array())
    {
        return call_user_func_array(array($ob, 'getList'), array($args));
    }

    function listOpts($ob, $getkey, $args = null)
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
            $args['col_id'] = 'id';
        }

        if (!$args['list_args']) {
            $args['list_args'] = array();
        }

        foreach ($this->getList($ob, $args['list_args']) as $object) {
            if (is_array($getkey) && $getkey['funccall']) {
                $value = call_user_func(array($object, $getkey['funccall']));
            } else {
                $value = $object->get($getkey);
            }

            $opts[$object->get($args['col_id'])] = $value;
        }

        return $opts;
    }

    function getListBySql($ob, $sql, $args = array())
    {
        $ret = array();
        $q_obs = $this->db->query($sql);
        while ($d_obs = $q_obs->fetch()) {
            if ($args['col_id']) {
                $ret[] = $this->get($ob, $d_obs[$args['col_id']], $d_obs);
            } else {
                $ret[] = $this->get($ob, $d_obs);
            }
        }
        $q_obs->free();

        return $ret;
    }

    function sqlHelper(array &$list_args, $args)
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

            if (($str_exists && in_array($list_key, $args['cols_str']) || ($num_exists && in_array($list_key, $args['cols_num'])))) {
                if (is_array($list_val)) {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " IN (" . knjarray::implode(array('array' => $list_val, 'impl' => ",", 'surr' => "'", 'self_callback' => array($db, 'sql'))) . ")";
                } else {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " = '" . $db->sql($list_val) . "'";
                }

                $found = true;
            } elseif (($str_exists || $num_exists) && preg_match('/^(.+)_(has|not)$/', $list_key, $match) && (($str_exists && in_array($match[1], $args['cols_str'])) || ($num_exists && in_array($match[1], $args['cols_num'])))) {
                if ($match[2] == 'has') {
                    if ($list_val) {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " != ''";
                    } else {
                        $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " = ''";
                    }
                    $found = true;
                } elseif ($match[2] == 'not') {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " != '" . $db->sql($list_val) . "'";
                    $found = true;
                }
            } elseif ($dbrows_exist && in_array($list_key . '_id', $args['cols_dbrows'])) {
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
            } elseif ($dbrows_exist && in_array($list_key . 'Id', $args['cols_dbrows'])) {
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
            } elseif ($dbrows_exist && in_array($list_key, $args['cols_dbrows'])) {
                if (is_array($list_val)) {
                    if (empty($list_val)) {
                        throw new exception('No elements was given in array.');
                    }

                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " IN (" . knjarray::implode(array('array' => $list_val, 'impl' => ",", 'surr' => "'", 'self_callback' => array($db, 'sql'))) . ")";
                } else {
                    $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " = '" . $db->sql($list_val) . "'";
                }

                $found = true;
            } elseif (array_key_exists('cols_bool', $args) && in_array($list_key, $args['cols_bool'])) {
                if ($list_val) {
                    $list_val = '1';
                } else {
                    $list_val = '0';
                }
                $sql_where .= " AND " . $table . $colsep . $db->escape_column($list_key) . $colsep . " = '" . $db->sql($list_val) . "'";
                $found = true;
            } elseif (substr($list_key, -7, 7) == '_search' && preg_match('/^(.+)_search$/', $list_key, $match) && (($str_exists && in_array($match[1], $args['cols_str'])) || ($dbrows_exist && in_array($match[1], $args['cols_dbrows'])) || ($num_exists && in_array($match[1], $args['cols_num'])))) {
                $sql_where .= " AND " . $table . $colsep . $db->escape_column($match[1]) . $colsep . " LIKE '%" . $db->sql($list_val) . "%'";
                $found = true;
            } elseif (substr($list_key, -6, 6) == '_lower' && preg_match('/^(.+)_lower$/', $list_key, $match) && in_array($match[1], $args['cols_str'])) {
                $sql_where .= " AND LOWER(" . $table . $colsep . $db->escape_column($match[1]) . $colsep . ") = LOWER('" . $db->sql($list_val) . "')";
                $found = true;
            } elseif (array_key_exists('cols_num', $args) && preg_match('/^(.+)_(from|to)/', $list_key, $match) && in_array($match[1], $args['cols_num'])) {
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
            } elseif (array_key_exists('cols_dates', $args) && preg_match('/^(.+)_(date|time|from|to)/', $list_key, $match) && in_array($match[1], $args['cols_dates'])) {
                $found = true;

                switch ($match[2]) {
                case 'date':
                    if (is_array($list_val)) {
                        if (empty($list_val)) {
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
                    if ($args['orderby_callbacks'][$list_val]) {
                        $orderby_res = $args['orderby_callbacks'][$list_val]();
                        if ($orderby_res) {
                            $sql_order .= " ORDER BY " . $db->escape_column($orderby_res);
                            $found = true;
                        }
                    } else {
                        $sql_order .= " ORDER BY " . $table . $colsep . $db->escape_column($list_val) . $colsep;
                        $found = true;
                    }
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
            'sql_order' => $sql_order
        );
    }

    function unsetOb($ob, $id = null)
    {
        if ($this->weakref || $this->weakmap) {
            return false;
        }

        if (is_object($ob) && is_null($id)) {
            $id = $ob->id();

            if ($this->objects[get_class($ob)][$id]) {
                unset($this->objects[get_class($ob)][$id]);
            }
        } else {
            if ($this->objects[$ob][$id]) {
                unset($this->objects[$ob][$id]);
            }
        }
    }

    function unsetClass($classname)
    {
        if ($this->weakref || $this->weakmap) {
            return false;
        }

        unset($this->objects[$classname]);
    }

    function unsetAll()
    {
        if ($this->weakref || $this->weakmap) {
            return false;
        }

        $this->objects = array();
    }

    function cleanMemory()
    {
        if ($this->weakref || $this->weakmap) {
            return false;
        }

        $usage = memory_get_usage() / 1024 / 1024;
        if ($usage >= 52) {
            $this->unsetAll();
        }
    }
}

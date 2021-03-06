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
class knjdb_mysqli
{
    private $_args;
    private $_knjdb;
    public $sep_col   = "`";
    public $sep_val   = "'";
    public $sep_table = "`";
    public $sep_index = "`";

    /**
     * TODO
     *
     * @param object $knjdb TODO
     * @param array  &$args TODO
     */
    function __construct(knjdb $knjdb, &$args)
    {
        $this->_args  = $args;
        $this->_knjdb = $knjdb;
    }

    /**
     * TODO
     *
     * @return array TODO
     */
    static function getArgs()
    {
        return array(
            'host' => array(
                'type' => 'text',
                'title' => 'Hostname',
            ),
            'user' => array(
                'type' => 'text',
                'title' => 'Username',
            ),
            'pass' => array(
                'type' => 'passwd',
                'title' => 'Password',
            ),
            'db' => array(
                'type' => 'text',
                'title' => 'Database',
            ),
        );
    }

    /**
     * TODO
     *
     * @return null
     */
    function connect()
    {
        $this->conn = new MySQLi(
            $this->_args['host'],
            $this->_args['user'],
            $this->_args['pass'],
            $this->_args['db']
        );

         //do not use the OO-way - it was broken until 5.2.9.
        if (mysqli_connect_error()) {
            $msg = 'Could not connect (' .mysqli_connect_errno() .'): '
            .mysqli_connect_error();
            throw new Exception($msg);
        }
    }

    /**
     * Close the database connection
     *
     * @return null
     */
    function close()
    {
        $this->conn->close();
        unset($this->conn);
    }

    /**
     * TODO
     *
     * @param string $query The SQL query to be executed
     *
     * @return object TODO
     */
    function query($query)
    {
        knjdb::$queries_called++;
        $res = $this->conn->query($query);
        if (!$res) {
            $msg = 'Query error: ' .$this->error() ."\n\nSQL: " .$query;
            throw new exception($msg);
        }

        return new knjdb_result($this->_knjdb, $this, $res);
    }

    /**
     * TODO
     *
     * @param TODO $res TODO
     *
     * @return TODO
     */
    function fetch($res)
    {
        return $res->fetch_assoc();
    }

    /**
     * TODO
     *
     * @return TODO
     */
    function error()
    {
        return $this->conn->error;
    }

    /**
     * TODO
     *
     * @param TODO $res TODO
     *
     * @return TODO
     */
    function free($res)
    {
        return $res->free();
    }

    /**
     * TODO
     *
     * @param string $string TODO
     *
     * @return null
     */
    function sql($string)
    {
        return $this->conn->real_escape_string($string);
    }

    /**
     * TODO
     *
     * @param string $string TODO
     *
     * @return string TODO
     */
    function escape_table($string)
    {
        if (strpos($string, '`')) {
            throw new exception('Tablename contains invalid character.');
        }

        return $string;
    }

    /**
     * TODO
     *
     * @return null
     */
    function trans_begin()
    {
        $this->conn->autocommit(false); //turn off autocommit.
    }

    /**
     * TODO
     *
     * @return null
     */
    function trans_commit()
    {
        $this->conn->commit();
        $this->conn->autocommit(true); //turn on autocommit.
    }

    /**
     * Insert a single row in to a table
     *
     * @param string $table  Table to insert into
     * @param array  $values Values to insert in the row
     * @param string $mode   How to handle duplicates:
     *                       insert:  Fail with exception (default)
     *                       replace: Delete existing row
     *                       update:  Update existing row
     *                       ignore:  Do nothing
     *
     * @return string The id of the newly created row
     */
    function insert($table, array $values, $mode = 'insert')
    {
        if ($mode == 'replace') {
            $sql = "REPLACE";
        } elseif ($mode == 'ignore') {
            $sql = "INSERT IGNORE";
        } else {
            $sql = "INSERT";
        }

        $sql .= " INTO "  .$this->sep_table .$table .$this->sep_table ." (";
        $first = true;
        foreach ($values as $key => $value) {
            if ($first == true) {
                $first = false;
            } else {
                $sql .= ", ";
            }

            $sql .= $this->sep_col .$key .$this->sep_col;
        }

        $sql .= ") VALUES (";
        $first = true;
        foreach ($values as $key => $value) {
            if ($first == true) {
                $first = false;
            } else {
                $sql .= ", ";
            }

            if ($value !== null) {
                $sql .= $this->sep_val .$this->sql($value) .$this->sep_val;
            } else {
                $sql .= "NULL";
            }
        }
        $sql .= ")";

        if ($mode == 'update') {
            $sql .= " ON DUPLICATE KEY UPDATE";

            $first = true;
            foreach ($values as $key => $value) {
                if ($first == true) {
                    $first = false;
                } else {
                    $sql .= ", ";
                }

                $sql .= $this->sep_col .$key .$this->sep_col . " = VALUES(" .  $this->sep_col .$key .$this->sep_col . ")";
            }
        }

        $this->query($sql);

        return $this->conn->insert_id;
    }

    /**
     * TODO
     *
     * @param string $table TODO
     * @param array  $where TODO
     * @param array  $args  TODO
     *
     * @return object TODO
     */
    function select($table, $where = null, $args = null)
    {
        $sql = "SELECT";

        if (isset($args['count'])) {
            $sql .= " COUNT(*) as count";
        } else {
            $sql .= " *";
        }

        $sql .= " FROM " .$this->sep_table .$table .$this->sep_table;

        if ($where) {
            $sql .= " WHERE " .$this->makeWhere($where);
        }

        if (isset($args['orderby'])) {
            $sql .= " ORDER BY " .$args['orderby'];
        }

        if (isset($args['limit'])) {
            $sql .= " LIMIT";

            if (isset($args['limit_from'])) {
                $sql .= " " .$args['limit_from'] .",";
            }

            $sql .= " " .$args['limit'];
        }

        return $this->query($sql);
    }

    /**
     * TODO
     *
     * @param string $table TODO
     * @param array  $where TODO
     *
     * @return object TODO
     */
    function delete($table, $where = null)
    {
        $sql = "DELETE FROM " .$this->sep_table .$table .$this->sep_table;

        if ($where) {
            $sql .= " WHERE " .$this->makeWhere($where);
        }

        return $this->query($sql);
    }

    /**
     * TODO
     *
     * @param string $table TODO
     * @param array  $data  TODO
     * @param array  $where TODO
     *
     * @return object TODO
     */
    function update($table, array $data, $where = null)
    {
        $sql = "UPDATE " .$this->sep_table .$table .$this->sep_table ." SET ";

        $first = true;
        foreach ($data as $key => $value) {
            if ($first == true) {
                $first = false;
            } else {
                $sql .= ", ";
            }

            $sql .= $this->sep_col .$key .$this->sep_col ." = ";

            if ($value !== null) {
                $sql .= $this->sep_val .$this->sql($value) .$this->sep_val;
            } else {
                $sql .= "NULL";
            }
        }

        if ($where) {
            $sql .= " WHERE " .$this->makeWhere($where);
        }

        return $this->query($sql);
    }

    /**
     * TODO
     *
     * @param array $tables TODO
     *
     * @return object TODO
     */
    function optimize($tables)
    {
        if (!is_array($tables)) {
            $tables = array($tables);
        }

        $tables = array_map(array($this, 'escape_table'), $tables);
        $tables = implode("`, `", $tables);
        $sql = "OPTIMIZE TABLE `" .$tables . "`";

        return $this->query($sql);
    }

    /**
     * TODO
     *
     * @param array $where TODO
     *
     * @return string TODO
     */
    function makeWhere($where)
    {
        $sql = "";

        $first = true;
        foreach ($where as $key => $value) {
            if ($first == true) {
                $first = false;
            } else {
                $sql .= " AND ";
            }

            if (is_array($value)) {
                $value = array_map(array($this, 'sql'), $value);
                $value = implode("', '", $value);

                $sql .= $this->sep_col .$key .$this->sep_col ." IN ('"
                .$value ."')";
            } else {
                $sql .= $this->sep_col .$key .$this->sep_col;
                if ($value !== null) {
                    $sql .= " = " .$this->sep_val .$this->sql($value) .$this->sep_val;
                } else {
                    $sql .= 'IS NULL';
                }
            }
        }

        return $sql;
    }

    /**
     * Alias of strtotime()
     *
     * @param string $str See PHP documentation for strtotime()
     *
     * @return int See PHP documentation for strtotime()
     */
    function date_in($str)
    {
        return strtotime($str);
    }

    /**
     * TODO
     *
     * @param int   $unixt TODO
     * @param array $args  TODO
     *
     * @return string TODO
     */
    function date_format($unixt, $args = array())
    {
        $format = 'Y-m-d';

        if (!array_key_exists('time', $args) || $args['time']) {
            $format .= ' H:i:s';
        }

        return date($format, $unixt);
    }
}


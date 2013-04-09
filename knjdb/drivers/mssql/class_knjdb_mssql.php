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
class knjdb_mssql
{
    private $args;
    public $sep_col = '';
    public $sep_val = "'";
    public $sep_table = '';
    public $sep_index = '';

    function __construct($knjdb, $args)
    {
        if (!function_exists('mssql_connect')) {
            throw new Exception('Missing MSSQL extension.');
        }

        $this->args = $args;
        $this->knjdb = $knjdb;
    }

    function connect()
    {
        $this->conn = @mssql_connect($this->args['host'], $this->args['user'], $this->args['pass']);
        if (!$this->conn) {
            throw new Exception('Could not connect to the database.');
        }

        if ($this->args['db']) {
            if (!mssql_select_db($this->args['db'], $this->conn)) {
                throw new Exception('Could not select database.');
            }
        }
    }

    function close()
    {
        mssql_close($this->conn);
        unset($this->conn);
    }

    function query($sql)
    {
        knjdb::$queries_called++;
        $res = mssql_query($sql, $this->conn);
        if (!$res) {
            throw new Exception('Query error: ' . mssql_get_last_message());
        }

        return new knjdb_result($this->knjdb, $this, $res);
    }

    function fetch($res)
    {
        $data = mssql_fetch_assoc($res);

        if (is_array($data)) {
            /** NOTE: This prevents the weird empty columns from MS-SQL. */
            foreach ($data as $key => $value) {
                if (mb_strlen($value) == 1 && ord($value) == 2) {
                    $data[$key] = '';
                }
            }
        }

        return $data;
    }

    function error()
    {
        throw new Exception('Not supported.');
    }

    function free($res)
    {
        return mssql_free_result($res);
    }

    function sql($sql)
    {
        return strtr($sql, array("'" => "''"));
    }

    function escape_table($string)
    {
        if (strpos($string, '`')) {
            throw new exception('Tablename contains invalid character.');
        }

        return $string;
    }

    /**
     * A quick way to do a simple select.
     */
    function select($table, $where = null, $args = null)
    {
        $sql = "SELECT";

        if ($args['limit']) {
            $sql .= " TOP " . $args['limit'];
        }

        if ($args['count']) {
            $sql .= " COUNT(*) as count";
        } else {
            $sql .= " *";
        }

        $sql .= " FROM [" . $table . "]";

        if ($where) {
            $sql .= " WHERE " . $this->makeWhere($where);
        }

        if ($args['orderby']) {
            $sql .= " ORDER BY " . $args['orderby'];
        }

        return $this->query($sql);
    }

    /**
     * Insert a single row in to a table
     *
     * @param string $table  Table to insert into
     * @param array  $values Values to insert in the row
     * @param string $mode   How to handle duplicates:
     *                       insert:  Fail with exception (default)
     *                       replace: Unimplemnted
     *                       update:  Unimplemnted
     *                       ignore:  Unimplemnted
     *
     * @return string Unimplemnted
     */
    function insert($table, array $values, $mode = 'insert')
    {
        $sql = "INSERT INTO [" . $table . "] (";

        $first = true;
        foreach ($values as $key => $value) {
            if ($first == true) {
                $first = false;
            } else {
                $sql .= ", ";
            }

            $sql .= "[" . $key . "]";
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

        $this->query($sql);

        return '';
    }

    /**
     * A quick way to do a simple update.
     */
    function update($table, array $data, $where = null)
    {
        $sql = "UPDATE [" . $table . "] SET ";

        $first = true;
        foreach ($data as $key => $value) {
            if ($first == true) {
                $first = false;
            } else {
                $sql .= ", ";
            }

            $sql .= "[" . $key . "] = ";

            if ($value !== null) {
                $sql .= $this->sep_val .$this->sql($value) .$this->sep_val;
            } else {
                $sql .= "NULL";
            }
        }

        if ($where) {
            $sql .= " WHERE " . $this->makeWhere($where);
        }

        return $this->query($sql);
    }

    /**
     * A quick way to do a simple delete.
     */
    function delete($table, $where = null)
    {
        $sql = "DELETE FROM [" . $table . "]";

        if ($where) {
            $sql .= " WHERE " . $this->makeWhere($where);
        }

        return $this->query($sql);
    }

    /**
     * Returns the SQL for the query based on an array.
     */
    function makeWhere($where)
    {
        $first = true;
        foreach ($where as $key => $value) {
            if ($first == true) {
                $first = false;
            } else {
                $sql .= " AND ";
            }

            $sql .= "[" . $key . "] = ";
            if ($value !== null) {
                $sql .= $this->sep_val . $this->sql($value) . $this->sep_val;
            } else {
                $sql .= 'IS NULL';
            }
        }

        return $sql;
    }

    function date_format($unixt, $args = array())
    {
        $format = 'm/d/Y';

        if (!array_key_exists('time', $args) || $args['time']) {
            $format .= ' H:i:s';
        }

        return date($format, $unixt);
    }

    function date_in($str)
    {
        if (!preg_match('/^([a-z]{3})\s+(\d+)\s+(\d+)\s+(\d+):(\d+):(\d+):(\d+)$/', $str, $match)) {
            throw new exception('Could not match date.');
        }

        $monthMap = array (
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'maj' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'okt' => 10,
            'nov' => 11,
            'dec' => 12
        );

        $month_no = $monthMap[$match[1]];
        if (!$month_no) {
            throw new exception('Invalid month str: ' . $match[1]);
        }

        $unixt = mktime($match[4], $match[5], $match[6], $month_no, $match[2], $match[3]);

        if (!$unixt) {
            throw new exception('Could not make time.');
        }

        return $unixt;
    }
}


<?php

class knjdb
{
    public $conn, $rows;
    public $args = array(
        'col_id' => 'id',
        'host' => 'localhost',
    );
    static $queries_called = 0;
    private $drivers = array();
    public $insert_autocommit, $insert_countcommit; //variables used by the transaction-autocommit-feature.

    /**
     * The constructor.
     */
    function __construct(array $args = array())
    {
        require_once 'class_knjdb_result.php';
        $this->rows = array();
        $this->args = array_merge($this->args, $args);
        $this->connect();
    }

    /**
     * Returns the current type (mysqli, mssql...).
     */
    function getType()
    {
        return $this->args['type'];
    }

    /**
     * Returns a specific table by its name.
     */
    function getTable($name)
    {
        if ($this->tables()->tables[$name]) {
            return $this->tables()->tables[$name];
        }

        foreach ($this->tables()->getTables() as $table) {
            if ($table->get('name') == $name) {
                return $table;
            }
        }

        return false;
    }

    /**
     * Connects to a database.
     */
    function connect()
    {
        require_once 'drivers/' .$this->args['type'] .'/class_knjdb_' .$this->args['type'] .'.php';
        $obname = 'knjdb_' .$this->args['type'];
        $this->conn = new $obname($this, $this->args);
        $this->conn->connect();
    }

    /**
     * Reconnects to the database.
     */
    function reconnect()
    {
        $this->connect();
    }

    function module($module)
    {
        if ($module == 'indexes') {
            $short = 'index';
        } else {
            $short = substr($module, 0, -1);
        }

        if (!array_key_exists($module, $this->drivers)) {
            require_once 'interfaces/class_knjdb_driver_' . $module . '.php';
            require_once 'class_knjdb_' . $short . '.php';

            if (method_exists($this->conn, $module)) {
                $this->drivers[$module] = $this->conn->$module();
            } else {
                $obname = 'knjdb_' . $this->args['type'] . '_' . $module;
                require_once 'drivers/' . $this->args['type'] . '/class_' . $obname . '.php';
                $this->drivers[$module] = new $obname($this);
            }
        }

        return $this->drivers[$module];
    }

    function rows()
    {
        return $this->module('rows');
    }

    function dbs()
    {
        return $this->module('dbs');
    }

    /**
     * Returns the tables-module.
     */
    function tables()
    {
        return $this->module('tables');
    }

    /**
     * Returns the columns-module.
     */
    function columns()
    {
        return $this->module('columns');
    }

    /**
     * Returns the indexes-module.
     */
    function indexes()
    {
        return $this->module('indexes');
    }

    function procedures()
    {
        return $this->module('procedures');
    }

    /**
     * Returns a row by its ID and table.
     */
    function getRow($id, $table, $data = null)
    {
        require_once 'class_knjdb_row.php';

        if (is_array($id)) {
            $data = $id;
            $id = $id[$this->args['col_id']];
        }

        if (!is_numeric($id) || $id < 0) {
            throw new Exception('ID was not valid "' . $id . '".');
        }

        if (!array_key_exists($table, $this->rows)) {
            $this->rows[$table] = array();
        }

        if (!array_key_exists($id, $this->rows[$table])) {
            $this->rows[$table][$id] = new knjdb_row($this, $table, $id, $data, array('col_id' => $this->args['col_id']));
        }

        return $this->rows[$table][$id];
    }

    /**
     * Closes the connection to the database.
     */
    function close()
    {
        $this->conn->close();
        unset($this->conn);
    }

    /**
     * Performs a query.
     */
    function query($sql)
    {
        return $this->conn->query($sql);
    }

    /**
     * Fetches a result.
     */
    function fetch($ident)
    {
        if (is_object($ident) && get_class($ident) == 'knjdb_result') {
            $ident = $ident->result;
        }

        return $this->conn->fetch($ident);
    }

    /**
     * Returns an escape string (mysql_escape_string etc) for the current database-driver.
     */
    function sql($string)
    {
        return $this->conn->sql($string);
    }

    function escape_table($string)
    {
        return $this->conn->escape_table($string);
    }

    function escape_column($string)
    {
        if ($this->conn->sep_col
            && mb_strpos($string, $this->conn->sep_col) !== false
        ) {
            throw new exception(_('Possible trying to hack the database!'));
        }

        if (is_object($string)) {
            throw new exception(_('Does not support objects.'));
        }

        return strval($string);
    }

    /**
     * Used with transactions.
     */
    function insert_autocommit($value)
    {
        if (is_numeric($value)) {
            $this->trans_begin();
            $this->insert_autocommit = $value;
            $this->insert_countcommit = 0;
        } elseif ($value == false) {
            if ($this->insert_countcommit > 0) {
                $this->trans_commit();
            }

            unset($this->insert_autocommit);
            unset($this->insert_countcommit);
        } else {
            throw new Exception('Invalid argument (' . $value . '.');
        }
    }

    /**
     * A quick way to do a simple select and fetch the result..
     */
    function selectfetch($table, $where = null, $args = null)
    {
        $result = $this->select($table, $where, $args);
        $results = array();
        while ($data = $result->fetch($result)) {
            if (array_key_exists('return', $args) && $args['return'] == 'array') {
                $results[] = $data;
            } else {
                $results[] = $this->getRow($data, $table);
            }
        }

        return $results;
    }

    /**
     * Selects a single row and returns it.
     */
    function selectsingle($table, $where, $args = array())
    {
        $args['limit'] = '1';
        return $this->select($table, $where, $args)->fetch();
    }

    /**
     * A quick way to do a simple select.
     */
    function select($table, $where = null, $args = null)
    {
        return $this->conn->select($table, $where, $args);
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
        $id = $this->conn->insert($table, $values, $mode);

        //check whether autocommit is on.
        if ($this->insert_autocommit) {
            $this->insert_countcommit++;

            if ($this->insert_countcommit >= $this->insert_autocommit) {
                $this->trans_commit();
                $this->trans_begin();
                $this->insert_countcommit = 0;
            }
        }

        return $id;
    }

    /**
     * A quick way to do a simple update.
     */
    function update($table, $data, $where = null)
    {
        $this->conn->update($table, $data, $where);
    }

    /**
     * A quick way to do a simple delete.
     */
    function delete($table, $where = null)
    {
        if (!is_null($where) && !is_array($where)) {
            throw new exception('The where-parameter was not an array or null.');
        }

        $this->conn->delete($table, $where);
    }

    function optimize($tables)
    {
        $this->conn->optimize($tables);
    }

    function countRows($res)
    {
        return $this->conn->countRows($res->result);
    }

    /**
     * Returns the SQL for the query based on an array.
     */
    function makeWhere($where)
    {
        return $this->conn->makeWhere($where);
    }

    function trans_begin()
    {
        if (method_exists($this->conn, 'trans_begin')) {
            $this->conn->trans_begin();
        }
    }

    function trans_commit()
    {
        if (method_exists($this->conn, 'trans_commit')) {
            $this->conn->trans_commit();
        }
    }

    function date_format($unixt, $args = array())
    {
        return $this->conn->date_format($unixt, $args);
    }

    function date_in($str)
    {
        return $this->conn->date_in($str);
    }
}


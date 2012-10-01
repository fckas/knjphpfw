<?php
/**
 * Parses a string to be SQL-safe.
 */
function sql($string)
{
    return mysql_escape_string($string);
}

/**
 * Parses an array to become a SQL-insert.
 */
function sql_parseInsert($arr, $table)
{
    $sql = "INSERT INTO " . $table . " (";

    $first = true;
    foreach ($arr as $key => $value) {
        if ($first == true) {
            $first = false;
        } else {
            $sql .= ", ";
        }

        $sql .= $key;
    }

    $sql .= ") VALUES " . sql_parseInsertMPart($arr);

    return $sql;
}

/**
 * Parses an array to become part of an multiple SQL-insert.
 */
function sql_parseInsertMPart($arr)
{
    $first = true;
    $sql = "(";
    foreach ($arr as $value) {
        if ($first == true) {
            $first = false;
        } else {
            $sql .= ", ";
        }

        $sql .= "'" . sql($value) . "'";
    }
    $sql .= ")";

    return $sql;
}

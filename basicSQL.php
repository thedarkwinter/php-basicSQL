<?php

/** basicSQL
 * Version 0.22
 * Copywrite 2014-2105 Michael Holloway <michael@thedarkwinter.com>
 * License: GPL2, see LICENSE file.
 *
 * https://github.com/thedarkwinter/php-basicSQL
 *
 * See README.md for usage
 */

namespace thedarkwinter;
use mysqli;

class basicSQL
{
    private $mysqli;
    private $database;
    private $host;
    private $port;
    private $user;
    private $password;

    private $test_mode;
    private $error_log_length = 100;
    private $query_log_length = 100;
    private $error_log = array();
    private $query_log  = array();
    private $totals = array('exec_time' => 0, 'errors' => 0, 'warnings' => 0, 'slow_queries' => 0);

    private $error_callback = array();
    private $query_callback = array();
    private $slow_query_callback = array();
    private $slow_query_time = 5;

    /**
     * Connects to database
     *
     * @param params array DB connection config that is not already specified in php.ini, database host port user password
     *
     * @return boolean
     */
    public function connect($params = array())
    {
        // return active connection if exists
        if (isset($this->con) && mysql_ping($this->con)) {
            return $this->con;
        }

        // load db defaults from php.ini
        foreach (array('database', 'host', 'port', 'user', 'password') as $p) {
            $this->$p = ini_get("mysql.default_{$p}");
        }
        foreach ($params as $p => $v) {
            if (isset($this->$p)) {
                $this->$p = $v;
            }
        }

        if (isset($params['test_mode']) && $params['test_mode'] == true) {
            $this->test_mode = true;
        }
        $this->mysqli = new mysqli($this->host.(($this->port != '') ? ':'.$this->port : ''), $this->user, $this->password, $this->database);
        if ($this->mysqli->connect_errno) {
            self::addError('connect', '', $this->mysqli->connect_errno, $this->mysqli->connect_error);

            return false;
        }

        return true;
    }

    /**
     * Close connection
     *
     * @return void
     */
    public function close()
    {
        if ($this->mysqli) {
            return $this->mysqli->close();
        }

        return true;
    }

    /**
     * Private add error
     *
     * @param string  $type  Type of error
     * @param string  $query Query string
     * @param integer $no    Error number
     * @param string  $error Error string
     *
     * @return boolean
     */
    public function addError($type, $query, $no, $error)
    {
        $this->totals['errors']++;
        $entry = array(
                'time' => time(),
                'type' => $type,
                'query' => $query,
                'no' => $no,
                'error' => $error,
            );
        $index = count($this->error_log);
        $this->error_log[$index] = $entry;

        if (is_callable($this->error_callback)) {
            call_user_func($this->error_callback, $entry); // TODO: Add some protection here
        }
        if ($this->error_log_length>0 && isset($this->error_log[$index - $this->error_log_length])) {
            unset($this->error_log[$index - $this->error_log_length]);
        }

        return true;
    }

    /**
     * public lastError
     *
     * @return array Retrieve array of the last error
     */
    public function lastError()
    {
        $index = count($this->error_log);

        return array_values($this->error_log[$index-1]);
    }

    /**
     * public errorLog
     *
     * @return array Retrieve array of all errors
     */
    public function errorLog()
    {
        return array_values($this->error_log);
    }

    /**
     * privately add to query log
     *
     * @return boolean
     */
    private function addQueryLog($type, $query, $num_rows = 0, $field_count = 0, $insert_id = 0, $rows_affected = 0, $matched = 0, $changed = 0, $warnings = 0, $exec_time = 0, $error = false)
    {
        if (!isset($this->totals[$type.'s'])) {
            $this->totals[$type.'s'] = 0;
        }
        $this->totals[$type.'s']++;
        $this->totals['warnings'] += $warnings;
        if ($exec_time > $this->slow_query_time) {
            $this->totals['slow_queries']++;
        }
        $entry = array(
                'time' => time(),
                'type' => $type,
                'query' => $query,
                'num_rows' => $num_rows,
                'field_count' => $field_count,
                'insert_id' => $insert_id,
                'rows_affected' => $rows_affected,
                'matched' => $matched,
                'changed' => $changed,
                'warnings' => $warnings,
                'exec_time' => $exec_time,
                'error' => $error,
            );
        foreach ($entry as $k => $v) {
            if ($v == '') {
                unset($entry[$k]);
            }
        }
        $index = count($this->query_log);
        $this->query_log[$index] = $entry;

        if (is_callable($this->query_callback)) {
            call_user_func($this->query_callback, $entry); // TODO: Add some protection here
        }
        if ($exec_time > $this->slow_query_time && is_callable($this->slow_query_callback)) {
            call_user_func($this->slow_query_callback, $entry); // TODO: Add some protection here
        }
        if ($this->query_log_length>0 && isset($this->query_log[$index-$this->query_log_length])) {
            unset($this->query_log[$index-$this->query_log_length]);
        }

        return true;
    }

    /**
     * public queryLog
     *
     * @return array Retrieve array of all queries
     */
    public function queryLog()
    {
        return array_values($this->query_log);
    }

    /**
     * public queryLog
     *
     * @return array Retrieve array of all queries
     */
    public function slowQueryLog($exec_time = 1)
    {
        $slow_queriues = array();
        foreach (array_values($this->query_log) as $entry) {
            if ($entry['exec_time']>$exec_time) {
                $slow_queriues[] = $entry;
            }
        }

        return $slow_queriues;
    }

    /**
     * public report
     *
     * @return array Returns the full report
     */
    public function report()
    {
        return $this->totals;
    }

    /**
     * public query
     *
     * @params string Raw SQL query
     *
     * @return int|mysql_resource Returns the index on an Insert statement, or mysql_resource
     */
    public function query($query)
    {
        $type = strtolower(array_shift(explode(' ', trim($query))));
        if ($this->test_mode && !in_array($type, array('SELECT', 'SHOW', 'DESC'))) {
            self::addQueryLog($type, $query);

            return true;
        }

        $starttime = microtime();
        $result = $this->mysqli->query($query);
        $exec_time = microtime() - $starttime;
        $this->totals['exec_time'] += $exec_time;

        if (!$result) {
            $error = self::addError($type, $query, $this->mysqli->errno, $this->mysqli->error);
            self::addQueryLog($type, $query, 0, 0, 0, 0, 0, 0, 0, 0, $exec_time, $error);

            return false;
        }

        $insert_id = ($type == 'insert') ? $this->mysqli->insert_id : 0;
        $num_rows = (isset($result->num_rows)) ? $result->num_rows : 0;
        $rows_affected = (isset($result->rows_affected)) ? $result->rows_affected : 0;
        $field_count = (isset($result->field_count)) ? $result->field_count : 0;
        $matched = $changed = $warnings = 0;
        if (isset($this->mysqli->info)) {
            list($matched, $changed, $warnings) = sscanf($this->mysqli->info, "Rows matched: %d Changed: %d Warnings: %d");
        }
        self::addQueryLog($type, $query, $num_rows, $field_count, $insert_id, $rows_affected, $matched, $changed, $warnings, $exec_time);

        return ($type == 'insert') ? $insert_id : $result;
    }

    /**
     * public lookup
     *
     * @params string Raw SQL query
     *
     * @return string|array Returns the single field or array of fields
     */
    public function lookup($field, $table, $where)
    {
        $field = $this->mysqli->real_escape_string($field);
        $table = $this->mysqli->real_escape_string($table);
        //$where = $this->mysqli->real_escape_string($where);
        $query = "SELECT $field FROM $table WHERE $where ORDER BY $field DESC LIMIT 0,1";
        $result = self::Query($query);
        if (!$result or $result->num_rows == 0) {
            return false;
        }
        $row = $result->fetch_row();

        return (count($row)>1) ? $row : $row[0]; // return array for more than one field or string if only one field
    }
}

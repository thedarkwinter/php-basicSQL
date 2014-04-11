<?php

/** basicSQL
  * Version 0.21
  * Copywrite 2014 Michael Holloway <michael@thedarkwinter.com>
  * License: GPL2, see LICENSE file.
  *
  * https://github.com/thedarkwinter/php-basicSQL
  *
  * See README.md for usage
*/

class basicSQL
{
    private static $mysqli;
    private static $database;
    private static $host;
    private static $port;
    private static $user;
    private static $password;

    private static $test_mode;
    private static $error_log_length=100;
    private static $query_log_length=100;
    private static $error_log = array();
    private static $query_log  = array();
    private static $totals=array('exec_time'=>0,'errors'=>0,'warnings'=>0,'slow_queries'=>0);

    private static $error_callback = array();
    private static $query_callback = array();
    private static $slow_query_callback = array();
    private static $slow_query_time = 5;

    /** Public Connection Functions  */
    public function connect($params=array())
    {
        if (isset(self::$con) && mysql_ping(self::$con)) return self::$con;
        foreach (array('database','host','port','user','password') as $p) self::$$p = ini_get("mysql.default_{$p}"); // load db defaults from php.ini
        foreach ($params as $p => $v) {
            if (!isset(self::$$p)) continue;
            self::$$p = $v;
        }

        if (isset($params['test_mode']) && $params['test_mode']==true) self::$test_mode = true;
        self::$mysqli = new mysqli(self::$host . ((self::$port!='')?':'.self::$port:''), self::$user, self::$password, self::$database );
        if (self::$mysqli->connect_errno) {
            self::addError('connect','', self::$mysqli->connect_errno, self::$mysqli->connect_error);
            return false;
        }
        return true;
    }

    public function close()
    {
        if (!self::$mysqli) { return true; }
        return self::$mysqli->close();
    }

    /** Error Functions */
    private function addError($type,$query,$no,$error)
    {
        self::$totals['errors']++;
        $entry = array(
                'time' => time(),
                'type' => $type,
                'query' => $query,
                'no' => $no,
                'error' => $error
            );
        $index = count(self::$error_log);
        self::$error_log[$index] = $entry;

        if (is_callable(self::$error_callback)) call_user_func(self::$error_callback,$entry); // TODO: Add some protection here
        if (self::$error_log_length>0 && isset(self::$error_log[$index - self::$error_log_length])) unset(self::$error_log[$index - self::$error_log_length]);
        return true;
    }

    public function lastError()
    {
        $index = count(self::$error_log);
        return array_values(self::$error_log[$index-1]);
    }

    public function errorLog() {
        return array_values(self::$error_log);
    }

    /** Query Log Functions */
    private function addQueryLog($type,$query,$num_rows=0,$field_count=0,$insert_id=0,$rows_affected=0,$matched=0,$changed=0,$warnings=0,$exec_time=0,$error=false)
    {
        if (!isset(self::$totals[$type.'s'])) self::$totals[$type.'s']=0;
        self::$totals[$type.'s']++;
        self::$totals['warnings'] += $warnings;
        if ($exec_time > self::$slow_query_time) { self::$totals['slow_queries']++; }
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
                'error' => $error
            );
        foreach ($entry as $k => $v) {
            if ($v=='') unset($entry[$k]);
        }
        $index = count(self::$query_log);
        self::$query_log[$index] = $entry;

        if (is_callable(self::$query_callback)) call_user_func(self::$query_callback,$entry); // TODO: Add some protection here
        if ($exec_time > self::$slow_query_time && is_callable(self::$slow_query_callback)) call_user_func(self::$slow_query_callback,$entry); // TODO: Add some protection here
        if (self::$query_log_length>0 && isset(self::$query_log[$index-self::$query_log_length])) unset(self::$query_log[$index-self::$query_log_length]);
        return true;
    }

    public function queryLog() {
        return array_values(self::$query_log);
    }

    public function slowQueryLog($exec_time=1) {
        $slow_queriues = array();
        foreach (array_values(self::$query_log) as $entry) {
            if ($entry['exec_time']>$exec_time) $slow_queriues[] = $entry;
        }
        return $slow_queriues;
    }

    public function report() {
        return self::$totals;
    }

    /** Public Query Functions */
    public static function query($query)
    {
        $type = strtolower(array_shift(explode(' ',trim($query))));
        if ( (self::$test_mode) and (!in_array($type,array('SELECT','SHOW','DESC'))) ) {
            self::addQueryLog($type,$query);
            return true;
        }

        $starttime = microtime();
        $result = self::$mysqli->query($query);
        $exec_time = microtime() - $starttime;
        self::$totals['exec_time'] += $exec_time;

        if (!$result) {
            $error = self::addError($type,$query,self::$mysqli->errno, self::$mysqli->error);
            self::addQueryLog($type,$query,0,0,0,0,0,0,0,0,$exec_time,$error);
            return false;
        }

        $insert_id = ($type=='insert') ? self::$mysqli->insert_id : 0;
        $num_rows = (isset($result->num_rows))?$result->num_rows:0;
        $rows_affected = (isset($result->rows_affected))?$result->rows_affected:0;
        $field_count = (isset($result->field_count))?$result->field_count:0;
        $matched = $changed = $warnings = 0;
        if (isset(self::$mysqli->info)) list($matched, $changed, $warnings) = sscanf(self::$mysqli->info,"Rows matched: %d Changed: %d Warnings: %d");
        self::addQueryLog($type,$query,$num_rows,$field_count,$insert_id,$rows_affected,$matched,$changed,$warnings,$exec_time);

        return ($type=='insert') ? $insert_id : $result;
    }

}

?>

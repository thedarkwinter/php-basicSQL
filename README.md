php-basicSQL
============

A very basic MySQLi wrapper with some reporting tools/callbacks

This static class provides a MySQLi interface with query() function that accepts pure SQL. It then stores the query along with statistics such as execute time, number of rows affected etc. At the end of execution, you can call report() to get an array of statistics. It also has callbacks for errors, queries, and slow_queries where your script can decide what to do (log, abort etc).

#### What it does not [yet?] do


* Any form in SQL injection protection. This is up to you
* Add framework style functions
* Use prepared statements

#### What it does do (usage)

##### Include the class, and load up the DB
```php
include('basicSQL.php');

basicSQL::Connect( array(
    'database'=>'mydb', 'host'=>'myhost', 'port', 'user'=>'myuser', 'password'=>'mypass', // these can be in php.ini
    'error_callback' => 'handle_error',
    'slow_query_callback' => 'handle_slow', slow_query_time => 7, // queries longer than 7 seconds callback here
    'query_callback' => array('StaticLogClass','LogQueries'),
    'error_log_length' => 20, 'query_log_length'=>20 // store the last X queries/errors (default 100)
    ));
```

##### Write some callbacks, optional of course

```php
// if we cant connect the might as well just die, otherwise we just echo the error to screen
function handle_error($e=array()) {
    if ($e['type']=='connect']) { 
      die("{$e['no']} : {$e['error']} \n");
    }
    echo "SQL Error on '{$r['type']}' query {$e['no']} : {$e['error']} \nThe query was {$e['query']}\n");
}

// we want to log slow queries
function handle_slow($q=array()) {
    StaticLogClass::LogQueries("Slow query took {$q['exec_time']} to complete: {$q['query']});
}

```

##### Now your script does stuff

```php

if ($result = basicSQL::query("SELECT id FROM users WHERE name='fred'")) {
  // do stuff
} else {
  print_r(basicSQL::lastError()); // if you don't have a call_back you get get last error like so
}
basicSQL::query("DELETE FROM users WHERE name='fred'");

```

##### At the end of the script we can have a look at the report

```php
print_r(basicSQL::report());
Array
(
    [exec_time] => 0.0018630000000001
    [errors] => 0
    [warnings] => 0
    [slow_queries] => 1
    [selects] => 1
    [updates] => 0
    [inserts] => 0
    [deletes] => 1
)
```
##### Or view the query / error / slow_queries logs
```php
print_r(basicSQL::errorLog());

print_r(basicSQL::queryLog());
Array
(
    [0] => Array
        (
            [time] => 1397200192
            [type] => select
            [query] => SELECT id FROM users WHERE name='fred'
            [num_rows] => 1
            [field_count] => 1
            [exec_time] => 10.00054899999999999
        )
    [1] => Array
            [time] => 1397200192
            [type] => delete
            [query] => DELETE FROM users WHERE name='fred'
            [exec_time] => 0.00023000000000001
        )
)


print_r(basicSQL::slowQueryLog()); // depending on query_log_length, the may no longer be stored
Array
(
    [0] => Array
        (
            [time] => 1397200192
            [type] => select
            [query] => SELECT id FROM users WHERE name='fred'
            [num_rows] => 1
            [field_count] => 1
            [exec_time] => 10.00054899999999999
        )
)
```

##### And thats it, for the moment.

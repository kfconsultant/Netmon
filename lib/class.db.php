<?php

//Load Default Config:
// namespace Mahya ;
require_once __DIR__ . '/../config/database.conf.php';

class DB {

// class DB {
    protected $error;
    protected $sql;
    public $pdo_obj;
    public $catchException;
    protected $bind;
    protected $errorCallbackFunction;
    protected $errorMsgFormat;
    protected $driver_name;
    protected $table_name;

    const MESSAGE_ERROR = E_USER_ERROR;
    const MESSAGE_WARNING = E_USER_WARNING;

    public function __construct($dbName = MySQL_DB, $host = MySQL_HOST, $user = MySQL_USER, $passwd = MySQL_PASS, $port = MySQL_PORT, $driver_name = 'mysql', $catchException = true) {
        $this->db_name = $dbName;
        $this->catchException = $catchException;
        //ACTIVE REPORT ERRORS FOR DEVELOPMENT MODE:
        //Warning: Comment It In Production Environment 
        $this->setErrorCallbackFunction('echo');

        $this->driver_name = $driver_name;
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        );

        if ($driver_name == 'mysql') {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
//			$options[PDO::MYSQL_ATTR_INIT_COMMAND]= "SET NAMES 'utf8'";
        } else if ($driver_name == 'sqlsrv') {
            $dsn = "sqlsrv:server={$host};Database={$dbName}";
            if (defined(PDO::SQLSRV_ENCODING_UTF8))
                $options[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
            unset($options[PDO::ATTR_EMULATE_PREPARES]);
            unset($options[PDO::ATTR_PERSISTENT]);
        }
        else if ($driver_name == 'pgsql')
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};charset=utf8";
        else {
            $this->_trigger_error("Invalid DBMS TYPE.", self::MESSAGE_ERROR);
            return;
        }

        if ($this->catchException) {
            try {
                $this->pdo_obj = new PDO($dsn, $user, $passwd, $options);
            } catch (PDOException $e) {
                $this->error = $e->getMessage() ? $e->getMessage() : '';
                $this->debug();
                die();
            }
        } else {
            $this->pdo_obj = new PDO($dsn, $user, $passwd, $options);
        }
    }

    function __destruct() {
        unset($this->pdo_obj);
    }

    public function debug() {
        $isCli = (php_sapi_name() === 'cli');
        if($isCli){
            $this->errorCallbackFunction='echo';
            $this->errorMsgFormat='text';
        }
        if (!empty($this->errorCallbackFunction)) {
            $error = array("Error" => $this->error);
            if (!empty($this->sql)) {
                $error["SQL Statement"] = "$this->sql";
                $error["Parsed Query"] = $this->getParsedQuery();
            }
            
            if (!empty($this->bind)) {
                $error["Bind Parameters"] = trim(print_r($this->bind, true));
            }
            
            $backtrace = debug_backtrace();
            if (!empty($backtrace)) {
                for ($i = count($backtrace) - 1; $i > 0; $i--) {
                    $info = $backtrace[$i];
                    if ($info["file"] != __FILE__)
                        $error["Backtrace"] = $info["file"] . " at line " . $info["line"];
                }
            }

            $msg = "";
            if ($this->errorMsgFormat == "html") {
                if (!empty($error["Bind Parameters"]))
                    $error["Bind Parameters"] = "<pre>" . $error["Bind Parameters"] . "</pre>";
                $css = trim(file_get_contents(dirname(__FILE__) . "/error.css"));
                $msg .= '<style type="text/css">' . "\n" . $css . "\n</style>";
                $msg .= "\n" . '<div class="db-error">' . "\n\t<h3>SQL Error</h3>";
                foreach ($error as $key => $val)
                    $msg .= "\n\t<label>" . $key . ":</label>" . $val;
                $msg .= "\n\t</div>\n</div>";
            }
            elseif ($this->errorMsgFormat == "text") {
                $msg .= "SQL Error\n" . str_repeat("-", 50);
                foreach ($error as $key => $val){
                    $msg .= "\n\n$key:\n$val";
                }
                $msg .=PHP_EOL;    
            }

            $func = $this->errorCallbackFunction;
            if($isCli){
                fwrite(STDERR, "$msg");
            }else{
                $func($msg);
            }
            
        }
    }

    public function delete($table, $bind = "", $where = NULL) {

        $this->table_name = $table;
        $sql = "DELETE FROM " . $table . (empty($where) ? "" : " WHERE ") . $where . ";";
        $result = $this->query($sql, $bind);

        return $result;
    }

    private function filter($table, &$info) {
        $this->table_name = $table;
        if ($this->driver_name == 'mysql') {
            $sql = "DESCRIBE " . $table . ";";
            $key = "Field";
        } elseif ($this->driver_name == 'sqlite') {
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        } else {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
            $key = "column_name";
        }
        // foreach($info as $index=>$value)if($value!==null)$info[$index]=str_replace("`","",$value);
        if (false !== ($list = $this->query($sql))) {
            $fields = array();
            foreach ($list as $record)
                $fields[] = $record[$key];
            $info = array_change_key_case($info);
            $fields = array_map('strtolower', $fields);
            if (count($info) > count($fields))
                $this->_trigger_error("Some Of Your Selected Fields Seems To Does Not Exist In Real Table.", self::MESSAGE_WARNING);
            return array_values(array_intersect($fields, array_keys($info)));
        }
        return array();
    }

    private function formatFields($table, &$fields) {

        if (!preg_match("/^[a-z0-9]$/i", $table))
            return false;
        $sql = "SELECT column_name FROM information_schema.columns WHERE data_type='decimal' and table_name = '{$table}' ;";
        $key = "column_name";
        // foreach($info as $index=>$value)if($value!==null)$info[$index]=str_replace("`","",$value);
        if (false !== ($list = $this->query($sql))) {
            $targetList = array();
            foreach ($list as $record)
                $targetList[] = $record['column_name'];
            foreach ($fields as &$field) {
                if (in_array($field, $targetList) and preg_match("/^[a-z0-9]$/i", $field)) {
                    $field = "TRIM(TRAILING '.' FROM TRIM(TRAILING '0' $field)) as $field";
                }
            }
        }
        return true;
    }

    private function cleanup($bind) {
        if (!is_array($bind)) {
            if (!empty($bind))
                $bind = array($bind);
            else
                $bind = array();
        }
        return $bind;
    }

    public function insert($table, $info, $ignore = false, $onDublicate = Null) {
        $this->table_name = $table;

        $fields = $this->filter($table, $info);

        // $fields =array_keys($info);
        $sql = "INSERT " . ($ignore ? ' IGNORE ' : '') . "INTO " . $table . " (" . implode($fields, ", ") . ") VALUES (:" . implode($fields, ", :") . ")";
        if ($onDublicate) {
            $sql.=" ON DUPLICATE KEY $onDublicate";
        }
        $bind = array();
        foreach ($fields as $field)
            $bind[":$field"] = $info[$field];
        return $this->query($sql, $bind);
    }

    public function query($sql, $bind = "") {

        $this->sql = trim($sql);
        $sql = preg_replace(array('/[\r\n]+/', '/\t+/'), array(' ', ' '), $sql);
        $this->bind = $this->cleanup($bind);
        $this->error = "";

        if ($this->catchException) {
            try {
                $pdostmt = $this->pdo_obj->prepare($this->sql);

                if (($pdostmt->execute($this->bind)) !== false) {
                    if (preg_match("/^(" . implode("|", array("select", "describe", "pragma")) . ") /i", $this->sql))
                        return $pdostmt->fetchAll(PDO::FETCH_ASSOC);
                    elseif (preg_match("/^(" . implode("|", array("replace", "delete", "insert", "update")) . ") /i", $this->sql)) {
                        return $pdostmt->rowCount();
                    }
                } else
                    return false;
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                $this->debug();
                return false;
            }
        } else {
            $pdostmt = $this->pdo_obj->prepare($this->sql);

            if (($pdostmt->execute($this->bind)) !== false) {
                if (preg_match("/^(" . implode("|", array("select", "describe", "pragma")) . ") /i", $this->sql))
                    return $pdostmt->fetchAll(PDO::FETCH_ASSOC);
                elseif (preg_match("/^(" . implode("|", array("delete", "insert", "update")) . ") /i", $this->sql)) {
                    return $pdostmt->rowCount();
                }
            } else
                return false;
        }
    }

    public function select($table, $fields = "*", $bind = "", $where = "", $OrderByTerm = "", $limit = null, $offset = null, $distinct = false) {
        if (preg_match('/^[\s\t]*DISTINCT.*/i', $fields)) {
            $fields = preg_replace('/^[\s\t]*DISTINCT/i', '', $fields);
            $distinct = true;
        }
        $this->table_name = $table;
        if (empty($fields))
            $fields = "*";
        // 
        if ($fields != "*" and ( !is_array($fields) and ! preg_match("/([\*\(\)`']+)|(\sAS[\s,])/i", $fields))) {
            $this->formatFields($table, $fields);
        }

        if (is_array($fields)) {
            $fields_buffer = "";
            foreach ($fields as $value)
                $fields_buffer.=($fields_buffer != "" ? "," : "") . ($this->driver_name == "mysql" ? "`$value`" : ($this->driver_name == "sqlsrv" ? "[$value]" : $value));
            $fields = $fields_buffer;
        }

        $sql = "SELECT " . ($distinct ? " DISTINCT " : "") . $fields . " FROM " . $table;

        if (!empty($where))
            $sql .= " WHERE " . $where;
        if (!empty($OrderByTerm))
            $sql .=" order by $OrderByTerm ";
        if (!is_null($limit)) {
            $limit = (int) $limit;
            if ($this->driver_name == "mysql") {
                $sql .= " LIMIT $limit";
                if (is_int($offset))
                    $sql .= " OFFSET $limit";
            }
            else if ($this->driver_name == "sqlsrv") {
                $sql = preg_replace('/^SELECT/i', "SELECT TOP($limit) ", $sql);
                if (is_int($offset))
                    trigger_error("DBCLASS[select function]: Offset Is Supported In SQL SERVER MODE.", E_USER_WARNING);
            }
        }

        $sql .= ";";
        return $this->query($sql, $bind);
    }

    public function setErrorCallbackFunction($errorCallbackFunction, $errorMsgFormat = "html") {
        //Variable functions for won't work with language constructs such as echo and print, so these are replaced with print_r.
        if (in_array(strtolower($errorCallbackFunction), array("echo", "print")))
            $errorCallbackFunction = "print_r";

        if (function_exists($errorCallbackFunction)) {
            $this->errorCallbackFunction = $errorCallbackFunction;
            if (!in_array(strtolower($errorMsgFormat), array("html", "text")))
                $errorMsgFormat = "html";
            $this->errorMsgFormat = $errorMsgFormat;
        }
    }

    public function update($table, $info, $bind = Null, $where = "") {
        // var_dump($info);
        $this->table_name = $table;



        $fields = $this->filter($table, $info);



        $fieldSize = count($fields);
        $sql = "UPDATE " . $table . " SET ";

        for ($f = 0; $f < $fieldSize; ++$f) {
            if ($f > 0)
                $sql .= ", ";

            $sql .= ($this->driver_name == "mysql" ? "`${fields[$f]}`" : ($this->driver_name == "sqlsrv" ? "[${fields[$f]}]" : "${fields[$f]}")) . " = :update_" . $fields[$f];
        }
        if (!empty($where))
            $sql .= " WHERE " . $where . ";";

        $bind = $this->cleanup($bind);
        foreach ($fields as $field)
            $bind[":update_$field"] = $info[$field];

        return $this->query($sql, $bind);
    }

    public function edit() {
        return call_user_func_array(array($this, 'update'), func_get_args());
    }

    public function getNameAndTypeFields($tbl) {
        $Typequery = //MYSQL
                "SELECT
	 COLUMN_NAME AS Name,DATA_TYPE AS `Type`,IF(ISNULL(CHARACTER_MAXIMUM_LENGTH),NUMERIC_PRECISION,CHARACTER_MAXIMUM_LENGTH) AS `length`, IF(IS_NULLABLE='YES',1,0) AS `isnull`
	 FROM information_schema.columns 
	 WHERE TABLE_SCHEMA= DATABASE() AND TABLE_NAME='$tbl';";

        ($NameType = $this->query($Typequery));
        return $NameType;
    }

    public function Count($tbl, $where = null, $bind = null) {
        $this->table_name = $table;
        $r = $this->select($tbl, "COUNT(*) As TABLE_COUNT_ROWS", $bind, $where);
        return $r[0]['TABLE_COUNT_ROWS'];
    }

    public function isAssocArray($arr) {

        return is_array($arr) and ( array_keys($arr) !== range(0, count($arr) - 1));
    }

    private function splitFields($csv) {
        $feilds = explode(',', $csv);
        $feilds = array_map('trim', $feilds);
        return $feilds;
    }

    private function _trigger_error($message, $type = self::MESSAGE_ERROR) {
        $bt = (debug_backtrace());
        $caller_info = $bt[count($bt) - 2];
        $color = array(self::MESSAGE_ERROR => "red", self::MESSAGE_WARNING => "orange");
        $error_type_string = array(self::MESSAGE_ERROR => "Error", self::MESSAGE_WARNING => "Warning");
        $massage_string = "[{$bt[1]['class']}::{$bt[1]['function']}()] {$error_type_string[$type]}: $message";
        echo("<h4 style=\"color:{$color[$type]}\">$massage_string</h4>");
        echo "<pre>";
        echo("[Table Name] :  {$this->table_name}\r\n");
        echo ("[Function Arguments] :\r\n " . print_r($caller_info['args'][1], true) . "");
        echo "</pre>";
        error_log("$massage_string File: {$bt[0]['file']}:{$bt[0]['line']}", E_USER_ERROR);
        if ($type == self::MESSAGE_ERROR) {
            die();
        }
    }

    public function call_proc($proc_name, $prams, $bind = array()) {
        $this->pdo_obj->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        if (is_array($prams)) {
            $prams_buffer = "";
            foreach ($prams as $value)
                $prams_buffer.=($prams_buffer != "" ? "," : "") . ($this->driver_name == "mysql" ? "'$value'" : ($this->driver_name == "sqlsrv" ? "[$value]" : $value));
            $prams = $prams_buffer;
        }

        $this->query("CALL {$proc_name}({$prams})", $bind);
        $this->pdo_obj->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function lastInsertId() {
        return $this->pdo_obj->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo_obj->beginTransaction();
    }

    public function commit() {
        return $this->pdo_obj->commit();
    }

    public function rollBack() {
        return $this->pdo_obj->rollBack();
    }

    public function getQuery() {
        return $this->sql;
    }

    public function getParsedQuery() {
        $sql = $this->sql;

        foreach ($this->bind as $key => $value) {
            if (is_string($key)) {
                if (!preg_match('/^\:\w+$/', $key)) {
                    $key = ":$key";
                }

                $sql = preg_replace("/([^\w]){$key}([^\w$])/", "\\1".(is_null($value)?"NULL":"'$value'")."\\2", $sql, 1);
            } else {
                $pos = strpos($sql, "?");
                $sql = substr_replace($sql, "'$value'", $pos, 1);
            }
        }
        return $sql;
    }

}

// require_once 'config/ConfigShahrdari.php';
/* 
Author: 		MSS
Built Number: 	8
Last Update: 	2015-04-23 
*/
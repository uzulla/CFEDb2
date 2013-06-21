<?php

/*
 * CFEDb2
 * Author: uzulla <uzulla@himitsukichi.com>
 * 20100310 Sqlite 対応
 * 20110606 デバッグ追加 ForceInsert追加
 * 20110607 CFECsv連携追加
 * 20110609 getSomeColVal 追加
 * 20110703 getsHashBySQL追加
 * 20120112 sqlite3 now()support
 *          add validate
 * 20120208 IN Query関数追加
 * 20120208 マージをした
 * 20120208 $lastRowCount追加
 * 20120225 sGetById()
 * 20120716 simpleQueryの挙動修正（0行でもレスポンスをするように
 * 20120719 validateをもっと良く
 * 20120719 loadFromRequest()追加
 * 20130618 CFEDb2にフォーク、過去互換性放棄
 * 20130618 コード大改修、エラー周りを例外化、error_log化、Static化
 */

namespace Uzulla;

//class DbConfig {
//    public $_db_type	= "sqlite";
//    public $_db_sv      = "test.db";
//    public $_db_name	= "";
//    public $_db_user	= "";
//    public $_db_pass	= "";
//    public $_db_pre_exec = false;//"SET NAMES UTF8"
//    public $_db_reuse_pdo = true;
//    public $_db_reuse_pdo_global_name = 'CFEDb2_DBH';
//    public $DEBUG = true;
//}
class CFEDb2 {

    public $PDO = null;
    static $tablename = 'MUSTOVERRIDE';
    static $pkeyname = 'MUSTOVERRIDE';
    public $values;
    public $error;
    public $csvHeaders;
    public $lastRowCount;
    public $dbconfig;
    public $validateData = array(
        'colmn_name'=>array('require'=>true, 'regexp'=>'/\A.*@.*\..*\z/u','error_text'=>'colmn_name を正しく設定してください'), //サンプルなので、かならずオーバーライドすること
    );

    static function log($message){
        $dbconfig = new DbConfig();

        $btstr = '';
        if($dbconfig->DEBUG){
            $btstr .="\n -backtrace-";

            $bt = array_reverse(debug_backtrace());
            foreach ($bt as $i) {
                $filename = ( isset($i['file']) ) ? basename($i['file']) : ( (isset($i['class'])) ? $i['class'] : 'UNKNOWN' );
                $funcname = $i['function'];
                $line = (isset($i['line'])) ? $i['line'] : '??';

                if ($funcname != 'log') {
                    if (isset($i['args']) && count($i['args']) > 0) {
                        $argsdump = static::plog_tostr($i['args']);
                    }else{
                        $argsdump = '';
                    }
                } else {
                    $argsdump = "SEE UNDER";
                }
                $btstr .= "\n {$filename} => {$funcname} : {$line} args({$argsdump}) / ";
            }
            $btstr .="\n --\n";
        }

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        if(isset($_SERVER['REMOTE_ADDR'])){
            error_log("IP:{$_SERVER['REMOTE_ADDR']}{$btstr} {$message}");
        }else{
            error_log("CLI{$btstr} {$message}");
        }
    }
    static function plog_tostr($o) {
        $str = '';
        if (is_array($o) || is_object($o)) {
            $str .="{";
            foreach ($o as $k => $v) {
                $str.=" [{$k}] => " . static::plog_tostr($v);
            }
            $str .="}";
            return $str;
        } else {
            return "{$str}{$o},";
        }
    }


    public function __construct($PDO=null) {
        $this->csvHeaders = array();
        if(isset($this->values)){
            foreach(array_keys($this->values) as $k){
                $this->csvHeaders[] = $k;
            }
        }

        $this->dbconfig = new DbConfig();

        if($this->dbconfig->_db_reuse_pdo){
            if(!is_null($PDO)){
                $GLOBALS[$this->dbconfig->_db_reuse_pdo_global_name] = $PDO;
            }elseif(!isset($GLOBALS[$this->dbconfig->_db_reuse_pdo_global_name])){
                $GLOBALS[$this->dbconfig->_db_reuse_pdo_global_name] = static::getPDO(new DbConfig());
            }
            $this->PDO = $GLOBALS[$this->dbconfig->_db_reuse_pdo_global_name];
        }else{
            $this->PDO = static::getPDO(new DbConfig());
        }

        return $this;
    }

    //PDO取得
    static function getPDO($config=null) {
        if(is_null($config)){
            $config = new DbConfig();
        }
        try {
            if ($config->_db_type == 'sqlite') {
                $PDO = new \PDO("{$config->_db_type}:{$config->_db_sv}", '');
            } elseif($config->_db_type == 'mysql') {
                $PDO = new \PDO("{$config->_db_type}:host={$config->_db_sv};dbname={$config->_db_name}", $config->_db_user, $config->_db_pass);
            } else {
                throw new \PDOException('invalid db_type');
            }

            if($config->_db_pre_exec) {
                $PDO->query($config->_db_pre_exec);
            }

        } catch (PDOException $e) {
            static::log(array("fail db conn", $e->getMessage(), $config));
            throw new \Exception('fail db conn');
        }
        return $PDO;
    }

    public function transactionBegin(){
        $this->PDO->query('BEGIN;');
    }
    public function transactionCommit(){
        $this->PDO->query('COMMIT;');
    }
    public function transactionRollback(){
        $this->PDO->query('ROLLBACK;');
    }

    //SQL指定クエリ
    static function simpleQuery($sql, $params, $PDO=null){
        $_tmp = new static($PDO);
        $sth = $_tmp->PDO->prepare($sql);
        if(!$sth){
            static::log(array($sql,$params,$sth));
            throw new \Exception('DB ERROR: null sth, invalid sql?');
        }

        try{
            $sth->execute($params);
        } catch (PDOException $e) {
            static::log(array("DB ERROR: simpleQuery",$e->getMessage(),$sql,$params,$_tmp->PDO->errorInfo(),$sth->errorInfo()));
            throw new \Exception('DB ERROR: execute error');
        }
        $items = $sth->fetchAll(\PDO::FETCH_ASSOC);
        return $items;
    }
    static function simpleQueryOne($sql, $params, $PDO=null){
        $items = static::simpleQuery($sql, $params, $PDO);
        return $items[0];
    }

    static function getBySQL($sql, $params, $PDO=null) {
        $item = static::simpleQueryOne($sql, $params, $PDO);
        return static::getByHash($item);
    }
    static function getsBySQL($sql, $params, $PDO=null) {
        $items = static::simpleQuery($sql, $params, $PDO);
        return static::getsByHashList($items);
    }

    static function countAll() {
        $items = static::simpleQuery('SELECT count(*) FROM '.static::$tablename.';', array());
        return (int) $items[0]["count(*)"];
    }

    public function val($key, $val=null) {
        if (is_null($val)) {
            if (isset($this->values[$key])) {
                return $this->values[$key];
            } else {
                return null;
            }
        } else {
            $this->values[$key] = $val;
        }
    }

    static function getsHashByLists($items) {
        $rtn = array();
        foreach($items as $item){
            $rtn[] = $item->values;
        }
        return $rtn;
    }

    static function getsByHashList($items) {
        if (count($items) == 0) { return null; }

        $rtnArr = array();
        foreach ($items as $item) {
            $tmp = new static;
            $tmp->values = array();
            foreach ($item as $k => $v) {
                $tmp->values[$k] = $v;
            }
            $rtnArr[] = $tmp;
        }
        return $rtnArr;
    }
    static function getByHash($item) {
        $tmp = new static;
        foreach ($item as $k => $v) {
            $tmp->values[$k] = $v;
        }
        return $tmp;
    }

    static function getsBySome($col, $val) {
        $items = static::simpleQuery("SELECT * FROM `".static::$tablename."` WHERE `{$col}` = :val", array('val'=>$val));
        if (count($items) == 0) {
            return null;
        }
        return static::getsByHashList($items);
    }
    static function getBySome($col, $val) {
        $items = static::simpleQuery("SELECT * FROM `".static::$tablename."` WHERE `{$col}` = :val LIMIT 1", array('val'=>$val));
        if (count($items) == 0) {
            return null;
        }
        return static::getByHash($items[0]);
    }

    static function getById($_key, $PDO=null) {
        $obj = new static($PDO);

        $sth = $obj->PDO->prepare('SELECT * FROM ' . static::$tablename . ' WHERE ' . static::$pkeyname . ' = ?');
        $sth->execute(array($_key));

        $row = $sth->fetchObject();
        if ($row == false) {
            return null; // notfound key
        }

        return static::getByHash($row);
    }

    static function getsAll() {
        $items = static::simpleQuery('select * from '.static::$tablename, array());
        return static::getsByHashList($items);
    }

    static function getRand() {
        $config = new DbConfig();
        if ($config->_db_type == 'sqlite') {
            $rand_func_name = "random()";
        } elseif($config->_db_type == 'mysql') {
            $rand_func_name = "random()";
        } else {
            throw new \PDOException('invalid db_type');
        }

        $items = static::simpleQuery('SELECT * FROM ' . static::$tablename . ' ORDER BY '.$rand_func_name.' LIMIT 1', array());
        if(count($items)>0){
            $obj = new static;
            foreach ($items[0] as $k => $v) {
                $obj->values["$k"] = $v;
            }
            return $obj;
        }else{
            return null;
        }
    }

    public function getHash() {
        $rtn = array();
        foreach ($this->values as $k => $v) {
            $rtn[$k] = $v;
        }
        return $rtn;
    }

    static function getsBySomeList($col, $val_list){
        $list = static::simpleQuery('SELECT * FROM '.static::$tablename.' WHERE `'.$col.'` IN :list ', array('list'=>static::buildINStr($val_list)) );
        return static::getsByHashList($list);
    }
    static function buildINStr($list){
        $in = '( ';
        $in .= join(',', $list);
        $in .= ' )';
        return $in;
    }

    //配列の、特定のキー名のリストを返す
    static function getsSomeColVal($list, $col){
        $val_list = array();
        foreach($list as $item){
            $val_list[] = $item->val($col);
        }
        return $val_list;
    }


    static function buildINQuery($list){
        $i = 0;
        $in = '(';
        $_list = array();
        foreach($list as $item){
            $_list[] = ":param_{$item}_{$i}";
            $i++;
        }
        $in .= join(',', $_list);
        $in .= ')';
        return $in;
    }

    static function buildINParams($list){
        $i = 0;
        $_list = array();
        foreach($list as $item){
            $_list[":param_{$item}_{$i}"] = $item;
            $i++;
        }
        return $_list;
    }

    public function loadFromRequest(Request $r){
        foreach($this->values as $k=>$v){
            if(!is_null($r->val($k))){
                $this->val($k, $r->val($k));
            }
            if($k=='ua'){
                $this->val('ua', $_SERVER['HTTP_USER_AGENT']);
            }
            if($k=='ip'){
                $this->val('ip', $_SERVER['REMOTE_ADDR']);
            }
        }
    }

    public function validate(){
        $error_list = array();
        $item_list = $this->validateData;
        $item = array();

        foreach($item_list as $k=>$v){
            $item[$k] = $this->values[$k];
            $require = $v['require'];
            $regexp = (isset($v['regexp'])) ? $v['regexp'] : false;

            if($regexp){
                if($require){
                    if(preg_match($regexp, $item[$k])){
                        //ok
                    }else{
                        $error_list[$k] = $v['error_text'];
                    }
                }else{
                    if(mb_strlen($item[$k])==0 || preg_match($regexp, $item[$k])){
                        //ok
                    }else{
                        $error_list[$k] = $v['error_text'];
                    }
                }
            }else if($require){
                if(is_array($item[$k]) && count($item[$k])>0 ){
                    //ok
                }else if(mb_strlen($item[$k])>0){
                    //ok
                }else{
                    if(isset($v['error_text'])){
                        $error_list[$k] = $v['error_text'];
                    }else{
                        $error_list[$k] = "{$k}を正しく入力してください";
                    }
                }
            }
        }
        return $error_list;
    }

    public function _delete($where_col, $where_val) {
        if (is_null($where_col)) {
            $sql = 'DELETE FROM '.static::$tablename.' ;';
            $sth = $this->PDO->prepare($sql);
            $params = null;
            $rtn = $sth->execute();
            $this->lastRowCount = $sth->rowCount();
        } else {
            $sql = 'DELETE FROM '.static::$tablename.' WHERE ' . $where_col . ' = :val ;';
            $sth = $this->PDO->prepare($sql);
            $params = array('val' => $where_val);
            $rtn = $sth->execute($params);
            $this->lastRowCount = $sth->rowCount();
        }
        if (!$rtn) {
            static::log(array("DB ERROR: insert fail",$sql,$params,$this->PDO->errorInfo(),$sth->errorInfo()));
            throw new \Exception('DB ERROR: insert fail');
        }else{
            return true;
        }
    }
    public function deleteItem() {
        return $this->_delete(static::$pkeyname, $this->values[static::$pkeyname]);
    }

    public function saveItem($forceInsert=FALSE) {
        if ($this->dbconfig->DEBUG) {
            $this->PDO->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING );
        }

        $isInsert = 0;
        if (is_null(static::$pkeyname) || is_null($this->values[static::$pkeyname]) || '' == $this->values[static::$pkeyname] || $forceInsert == TRUE ) { // dont have id, so this is new one. goto insert
            try{
                $sql = $this->createInsertSQL($forceInsert);
                $sth = $this->PDO->prepare($sql);
                if(!$sth){
                    static::log(array($sql,$sth));
                    throw new \Exception('DB ERROR: null sth, invalid sql?');
                }
                $params = $this->createKVArray('insert', $forceInsert);
                $state = $sth->execute($params);
            }catch(PDOException $e){
                static::log(array("DB ERROR: simpleQuery",$e->getMessage(),$sql,$params,$this->PDO->errorInfo(),$sth->errorInfo()));
                throw new \Exception('DB ERROR: execute error');
            }
            $this->lastRowCount = $sth->rowCount();
            $isInsert = 1;
        } else {
            $sql = $this->createUpdateSQL();
            $sth = $this->PDO->prepare($sql);
            if(!$sth){
                static::log(array($sql,$sth));
                throw new \Exception('DB ERROR: null sth, invalid sql?');
            }
            try{
                $params = $this->createKVArray('update');
                $state = $sth->execute($params);
            }catch (PDOException $e){
                static::log(array("DB ERROR: ",$e->getMessage(),$sql,$params,$this->PDO->errorInfo(),$sth->errorInfo()));
                throw new \Exception('DB ERROR: execute error');
            }
            $this->lastRowCount = $sth->rowCount();
        }
        $id = $this->PDO->lastInsertId();

        if ($state) {
            if ($isInsert && $id==0) {
                static::log(array("DB ERROR: insert fail",$sql,$params,$this->PDO->errorInfo(),$sth->errorInfo()));
                throw new \Exception('DB ERROR: insert fail');
            }
            if(!$isInsert){
                $id = $this->val('id');
            }
            $_tmp = static::getById($id);
            $this->values = $_tmp->values;
            return $this;

        } else {
            throw new \Exception('DB ERROR: save fail');
        }
    }

    public function createUpdateSQL() {
        $sql = 'UPDATE '.static::$tablename.' SET ';
        foreach ($this->values as $k => $v) {
            if ($k == static::$pkeyname){
                continue;
            }else if ('updated_at' == $k){
                if($this->dbconfig->_db_type == 'sqlite'){
                    $sql .= " ${k}=datetime('now'),";
                }else{
                    $sql .= " ${k}=now(),";
                }
            }else{
                $sql .= " `${k}`=:${k},";
            }
        }
        $sql = preg_replace('/,$/', '', $sql);
        $sql .= " WHERE ".static::$pkeyname." = :".static::$pkeyname;
        return $sql;
    }

    public function createInsertSQL($forceId=false) {
        $sql = "INSERT INTO ".static::$tablename." (";

        foreach ($this->values as $k => $v) {
            if ($k == static::$pkeyname && $forceId==FALSE)
                continue;
            $sql .= " `${k}`,";
        }
        $sql = preg_replace('/,$/', '', $sql);

        $sql .= " )VALUES( ";

        foreach ($this->values as $k => $v) {
            if ($k == static::$pkeyname && $forceId==FALSE) {

            } else if ('created_at' == $k || 'updated_at' == $k) {
                if($this->dbconfig->_db_type == 'sqlite'){
                    $sql .= "datetime('now'),";
                }else{
                    $sql .= "now(),";
                }
            } else {
                $sql .= " :${k},";
            }
        }
        $sql = preg_replace('/,$/', '', $sql);

        $sql .= " );";

        return $sql;
    }

    public function createKVArray($mode = 'insert', $forceId=false) {
        $arr = array();
        foreach ($this->values as $k => $v) {
            if ($mode == 'insert') {
                if ('created_at' == $k || 'updated_at' == $k)
                    continue;
                if ('id' == $k && $forceId == false)
                    continue;
            }else if ($mode == 'update') {
                if ('updated_at' == $k)
                    continue;
            }
            $arr[":{$k}"] = $v;
        }
        return $arr;
    }

    //MysqlのDateTimeでつかう日付文字列を生成する
    static function timeToMysqlInsertableDateStr($time){
        return date('Y-m-d H:i:s', $time);
    }

    public function getCsvLine(){
        $l = array();
        foreach( $this->csvHeaders as $k){
            $l[] = $this->values[$k];
        }
        return CFECsv::buildLineFromArray($l);
    }
    public function getCsvHeader(){
        return CFECsv::buildLineFromArray($this->csvHeaders);
    }

    //Hashから値を取得する際に、値がセットされていない場合でも、エラーにならないようにする
    public function getIfKeyExists($arr, $key) {
        if (is_null($key))
            return null;
        if (!isset($arr[$key]))
            return null;
        return $arr[$key];
    }

    //二水文字を含んでいないかチェックする
    public function isContained2suiChar($str) {
        mb_substitute_character('none');
        $str1 = mb_convert_encoding(mb_convert_encoding($str, 'SJIS', 'UTF-8'), 'UTF-8', 'SJIS');
        if (mb_strlen($str1) != mb_strlen($str))
            return true;
        else
            return false;
    }


}

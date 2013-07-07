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
 * 20130704 getsHashByListを追加
 * 20130706 validatorを拡張
 * 20130708 DB接続定義のやり方を大幅に変更
 *          CSV関連を削除
 *          リファクタリング
 *          コードを大幅にクリーンアップ、頻度の低い関数削除
 */

namespace Uzulla;

class CFEDb2 {
	static $config = array(
        '_db_type'=> "sqlite",
        '_db_sv' => "test.db",
        '_db_name' => "",
        '_db_user' => "",
        '_db_pass' => "",
        '_db_pre_exec' => false, //"SET NAMES UTF8"
        '_db_reuse_pdo' => true,
        'DEBUG' => true,
	);

    static $REUSE_PDO = null;
    public $PDO = null;
    static $tablename = 'MUSTOVERRIDE';
    static $pkeyname = 'MUSTOVERRIDE';
    public $values;
    public $lastRowCount;
    public $validateData = array(
        'colmn_name'=>array('require'=>true, 'regexp'=>'/\A.*@.*\..*\z/u','error_text'=>'colmn_name を正しく設定してください'), //サンプルなので、かならずオーバーライドすること
    );

    static function log($message){
        $config = static::$config;

        $btstr = '';
        if($config['DEBUG']){
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

    //PDO取得
    static function getPDO($config=null) {
        if(is_null($config)){
            $config = static::$config;
        }

        if( $config['_db_reuse_pdo'] && isset(static::$REUSE_PDO) && 'PDO' == get_class(static::$REUSE_PDO)){
            return static::$REUSE_PDO;
        }else{
            try {
                if ($config['_db_type'] == 'sqlite') {
                    $PDO = new \PDO("{$config['_db_type']}:{$config['_db_sv']}", '');
                } elseif($config['_db_type'] == 'mysql') {
                    $PDO = new \PDO("{$config['_db_type']}:host={$config['_db_sv']};dbname={$config['_db_name']}", $config['_db_user'], $config['_db_pass']);
                } else {
                    throw new \PDOException('invalid db_type');
                }

                if($config['_db_pre_exec']) {
                    $PDO->query($config['_db_pre_exec']);
                }

            } catch (\PDOException $e) {
                static::log(array("fail db conn", $e->getMessage()));
                throw new \Exception('fail db conn');
            }
            $PDO->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

            if($config['_db_reuse_pdo']){
                static::$REUSE_PDO = $PDO;
            }
            return $PDO;
        }
    }

    public function transactionBegin($PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        $PDO->query('BEGIN;');
        return $PDO;
    }
    public function transactionCommit($PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        $PDO->query('COMMIT;');
        return $PDO;
    }
    public function transactionRollback($PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        $PDO->query('ROLLBACK;');
        return $PDO;
    }

    //配列になったCFEDb2のインスタンスを、普通のハッシュ配列に変換します。 
    static function getsHashByList($item_list){
        if(!is_array($item_list)){
            return array();
        }
        $rtn = array();
        foreach($item_list as $item){
            $_array = array();
            foreach($item->values as $k=>$v){
                $_array[$k] = $v;
            }
            $rtn[] = $_array;
        }
        return $rtn;
    }

    //SQL指定クエリ
    static function simpleQuery($sql, $params, $PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        try{
            $sth = $PDO->prepare($sql);
            $sth->execute($params);
        } catch (\PDOException $e) {
            static::log(array("DB ERROR: simpleQuery",$e->getMessage(),$sql,$params,$PDO->errorInfo()));
            throw new \Exception('DB ERROR: execute error');
        }
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
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
        return 0+$items[0]["count(*)"];
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
        if (empty($items)) { return null; }
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
        if (empty($items)) {
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
        $res = static::simpleQuery(
            'SELECT * FROM ' . static::$tablename . ' WHERE ' . static::$pkeyname . ' = ?',
            array($_key),
            $PDO
        );

        if (count($res)==1) {
            return static::getByHash($res[0]);
        }else if(count($res)>1){
            throw new \Exception('multiple row found.');
        }else{
            return null; // notfound
        }
    }

    static function getsAll() {
        $items = static::simpleQuery('select * from '.static::$tablename, array());
        return static::getsByHashList($items);
    }

    static function getRand() {
        $config = static::$config;
        if ($config['_db_type'] == 'sqlite') {
            $rand_func_name = "random()";
        } elseif($config['_db_type'] == 'mysql') {
            $rand_func_name = "random()";
        } else {
            throw new \PDOException('invalid db_type');
        }

        $items = static::simpleQuery('SELECT * FROM ' . static::$tablename . ' ORDER BY '.$rand_func_name.' LIMIT 1', array());

        if(empty($items)){
            return null;
        }else{
            $obj = new static;
            foreach ($items[0] as $k => $v) {
                $obj->values["$k"] = $v;
            }
            return $obj;
        }
    }

//エスケープが不十分に見えるのでDeprecated
//    static function getsBySomeList($col, $val_list){
//        $list = static::simpleQuery('SELECT * FROM '.static::$tablename.' WHERE `'.$col.'` IN :list ', array('list'=>static::buildINStr($val_list)) );
//        return static::getsByHashList($list);
//    }
//    static function buildINStr($list){
//        $in = '( ';
//        $in .= join(',', $list);
//        $in .= ' )';
//        return $in;
//    }

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


    public function loadFromRequest($params){
        if(!is_array($params)){
            throw new \Exception('Must array');
        }
        foreach($this->values as $k=>$v){
            if($k=='ua'){
                $this->val('ua', $_SERVER['HTTP_USER_AGENT']);
            }elseif($k=='ip'){
                $this->val('ip', $_SERVER['REMOTE_ADDR']);
            }elseif(isset($params[$k])){
                $this->val($k, $params[$k]);
            }
        }
    }

    public function validate(){
        $error_list = array();
        $item_list = $this->validateData;

        foreach($item_list as $k=>$test_list){
            if(isset($test_list['require'])){ // 条件を入れ子にすることができる hmmm, any neat ideas?
                $test_list = array($test_list);
            }

            foreach($test_list as $v){
                $value = $this->values[$k];
                $require = (isset($v['require'])) ? $v['require'] : false;
                $regexp = (isset($v['regexp'])) ? $v['regexp'] : false;
                $callback = (isset($v['callback'])) ? $v['callback'] : false;
                $error_text = (isset($v['error_text'])) ? $v['error_text'] : "{$k}を正しく入力してください";

                if($regexp){
                    if( $require || mb_strlen($value)!=0 ){
                        if(!preg_match($regexp, $value)){
                            $error_list[$k] = $error_text;
                            continue 2;
                        }
                    }
                }else if($callback){
                    if( $require || mb_strlen($value)!=0 ){
                        if(!call_user_func($callback, $value, $this)){
                            $error_list[$k] = $error_text;
                            continue 2;
                        }
                    }
                }else if($require){
                    if( mb_strlen($value)==0 ){
                        $error_list[$k] = $error_text;
                        continue 2;
                    }
                }
            }
        }
        return $error_list;
    }

    public function deleteItem() { //事前処理が必要な場合、ここに継承させる
        return $this->_delete(static::$pkeyname, $this->values[static::$pkeyname]);
    }
    public function _delete($where_col, $where_val, $PDO = null) {
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        try{
            if (is_null($where_col)) {
                $sql = 'DELETE FROM '.static::$tablename.' ;';
                $params = null;
                $sth = $PDO->prepare($sql);
                $rtn = $sth->execute();
            } else {
                $sql = 'DELETE FROM '.static::$tablename.' WHERE ' . $where_col . ' = :val ;';
                $params = array('val' => $where_val);
                $sth = $PDO->prepare($sql);
                $rtn = $sth->execute($params);
            }
            $this->lastRowCount = $sth->rowCount();
        }catch(\PDOException $e){
            static::log(array("DB ERROR: delete fail",$sql,$params,$PDO->errorInfo(),$sth->errorInfo()));
            throw new \Exception('DB ERROR: delete fail');
        }
        if($this->lastRowCount==0){
            throw new \Exception('DB ERROR: delete fail, item notfound.');
        }

        return true;
    }

    public function saveItem($forceInsert=FALSE, $PDO=null) {
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }

        if (is_null(static::$pkeyname) || is_null($this->values[static::$pkeyname]) || '' == $this->values[static::$pkeyname] || $forceInsert == TRUE ) { // dont have id, so this is new one. goto insert
            $sql = $this->createInsertSQL($forceInsert);
            $params = $this->createKVArray('insert', $forceInsert);
            $isInsert = 1;
        } else {
            $sql = $this->createUpdateSQL();
            $params = $this->createKVArray('update');
            $isInsert = 0;
        }
        try{
            $sth = $PDO->prepare($sql);
            $state = $sth->execute($params);
            $this->lastRowCount = $sth->rowCount();
        }catch(\PDOException $e){
            static::log(array("DB ERROR: save item",$e->getMessage(),$sql,$params,$PDO->errorInfo(),$sth->errorInfo()));
            throw new \Exception('DB ERROR: save item');
        }
        $id = $PDO->lastInsertId();

        if ($state) {
            if ($isInsert && $id==0) {
                static::log(array("DB ERROR: insert fail",$sql,$params,$PDO->errorInfo(),$sth->errorInfo()));
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
                $config = static::$config ;
                if($config['_db_type'] == 'sqlite'){
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
        $keys = array();
        foreach ($this->values as $k => $v) {
            if ($k == static::$pkeyname && $forceId==FALSE)
                continue;
            $keys[] = "`${k}`";
        }

        $values = array();
        foreach ($this->values as $k => $v) {
            if ($k == static::$pkeyname && $forceId==FALSE) {
                continue;

            } else if ('created_at' == $k || 'updated_at' == $k) {
                if(static::$config['_db_type'] == 'sqlite'){
                    $values[] = "datetime('now')";
                }else{
                    $values[] = "now()";
                }
            } else {
                $values[] = ":${k}";
            }
        }

        $sql = "INSERT INTO ".static::$tablename;
        $sql .= " (".implode(',', $keys).") VALUES (".implode(',', $values).");";
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

}
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
 * 20130709 countBySome()を追加
 *          getHash*(), getsHash*()系を追加(Hashで取得できる)
 *          細々とリファクタリング
 * 20130724 コンフィグの互換性変更
 */

namespace Uzulla;

class CFEDb2 {
    static $config = array(
        'type'=> 'mysql',
        'dsn' => 'host=127.0.0.1;dbname=test',
        //'dsn' => 'unix_socket=/tmp/mysql.sock;dbname=test',
        'user' => "",
        'pass' => "",
        'pre_exec' => false, // "SET NAMES UTF8"
        'reuse_pdo' => true,
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

        $backtrace_str = '';
        if($config['DEBUG']){
            $backtrace_str .="\n -backtrace-";

            $bt = array_reverse(debug_backtrace());
            foreach ($bt as $i) {
                $filename = ( isset($i['file']) ) ? basename($i['file']) : ( (isset($i['class'])) ? $i['class'] : 'UNKNOWN' );
                $function_name = $i['function'];
                $line = (isset($i['line'])) ? $i['line'] : '??';

                if ($function_name != 'log') {
                    if (isset($i['args']) && count($i['args']) > 0) {
                        $args_dump = static::plog_tostr($i['args']);
                    }else{
                        $args_dump = '';
                    }
                } else {
                    $args_dump = "SEE UNDER";
                }
                $backtrace_str .= "\n {$filename} => {$function_name} : {$line} args({$args_dump}) / ";
            }
            $backtrace_str .="\n --\n";
        }

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        if(isset($_SERVER['REMOTE_ADDR'])){
            error_log("IP:{$_SERVER['REMOTE_ADDR']}{$backtrace_str} {$message}");
        }else{
            error_log("CLI{$backtrace_str} {$message}");
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

        if( $config['reuse_pdo'] && isset(static::$REUSE_PDO) && 'PDO' == get_class(static::$REUSE_PDO)){
            return static::$REUSE_PDO;
        }else{
            try {
                if ($config['type'] == 'sqlite') {
                    $PDO = new \PDO("{$config['type']}:{$config['dsn']}", '');
                } elseif($config['type'] == 'mysql') {
                    $PDO = new \PDO("{$config['type']}:{$config['dsn']}", $config['user'], $config['pass']);
                } else {
                    throw new \PDOException('invalid db_type');
                }

                if($config['pre_exec']) {
                    $PDO->query($config['pre_exec']);
                }

            } catch (\PDOException $e) {
                static::log(array("DB ERROR: connect to db fail", $e->getMessage()));
                throw new \Exception('DB ERROR: connect to db fail');
            }
            $PDO->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

            if($config['reuse_pdo']){
                static::$REUSE_PDO = $PDO;
            }
            return $PDO;
        }
    }

    public static function transactionBegin($PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        $PDO->beginTransaction();
        return $PDO;
    }
    public static function transactionCommit($PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        $PDO->commit();
        return $PDO;
    }
    public static function transactionRollback($PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        $PDO->rollBack();
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
            foreach($params as $p_key => $p_val){
                if(is_int($p_val) || ctype_digit($p_val)){
                    $sth->bindValue( ":{$p_key}", (int)$p_val, \PDO::PARAM_INT );
                }else{
                    $sth->bindValue( ":{$p_key}", $p_val, \PDO::PARAM_STR );
                }
            }
            $sth->execute();
        } catch (\PDOException $e) {
            static::log(array("DB ERROR: simpleQuery",$e->getMessage(),$sql,$params,$e->errorInfo));
            throw new \Exception('DB ERROR: execute error');
        }
        if(static::$config['DEBUG']){
            static::log("simpleQuery debug output\nsql: {$sql} \n".print_r($params,1));
        }
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    static function simpleExec($sql, $params, $PDO=null){
        if(is_null($PDO)){
            $PDO = static::getPDO();
        }
        try{
            $sth = $PDO->prepare($sql);
            foreach($params as $p_key => $p_val){
                if(is_int($p_val) || ctype_digit($p_val)){
                    $sth->bindValue( ":{$p_key}", (int)$p_val, \PDO::PARAM_INT );
                }else{
                    $sth->bindValue( ":{$p_key}", $p_val, \PDO::PARAM_STR );
                }
            }
            $sth->execute();
        } catch (\PDOException $e) {
            static::log(array("DB ERROR: simpleQuery",$e->getMessage(),$sql,$params,$e->errorInfo));
            throw new \Exception('DB ERROR: execute error');
        }
        if(static::$config['DEBUG']){
            static::log("simpleQuery debug output\nsql: {$sql} \n".print_r($params,1));
        }
    }

    static function simpleQueryOne($sql, $params, $PDO=null){
        $items = static::simpleQuery($sql, $params, $PDO);
        if(empty($items)) return null;
        return $items[0];
    }

    static function getBySQL($sql, $params, $PDO=null) {
        $item = static::simpleQueryOne($sql, $params, $PDO);
        if(empty($item)) return null;
        return static::getByHash($item);
    }

    static function getsBySQL($sql, $params, $PDO=null) {
        $items = static::simpleQuery($sql, $params, $PDO);
        if(empty($items)) return null;
        return static::getsByHashList($items);
    }

    static function countAll($PDO=null) {
        $items = static::simpleQuery('SELECT count(*) as count FROM '.static::$tablename.';', array(), $PDO);
        return 0+$items[0]["count"];
    }

    static function countBySome($col, $val, $PDO=null) {
        $params = array();
        if(is_array($col) && is_array($val)){
            $_tmp = array();
            foreach($col as $k=>$v){
                $_tmp[] = "`{$v}` = :col_{$k}";
                $params["col_{$k}"] = $val[$k];
            }
            $where = " WHERE ".implode(' AND ', $_tmp);
        }else{
            $where = " WHERE `{$col}` = :val";
            $params['val'] = $val;
        }

        $items = static::simpleQuery("SELECT count(*) as count FROM `".static::$tablename."`".$where, $params, $PDO);
        return 0+$items[0]["count"];
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
            return true;
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
            $tmp = new static;  /** @var \Uzulla\CFEDb2 $tmp */
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
        /** @var \Uzulla\CFEDb2 $tmp */
        foreach ($item as $k => $v) {
            $tmp->values[$k] = $v;
        }
        return $tmp;
    }

    static function getsHashBySome($col, $val, $PDO=null) {
        $items = static::simpleQuery("SELECT * FROM `".static::$tablename."` WHERE `{$col}` = :val", array('val'=>$val), $PDO);
        if (empty($items)) {
            return null;
        }
        return $items;
    }

    static function getsBySome($col, $val, $PDO=null) {
        $items = static::getsHashBySome($col, $val, $PDO);
        if (empty($items)) {
            return null;
        }
        return static::getsByHashList($items);
    }

    static function getHashBySome($col, $val, $PDO=null) {
        $items = static::simpleQuery("SELECT * FROM `".static::$tablename."` WHERE `{$col}` = :val LIMIT 1", array('val'=>$val), $PDO);
        if (empty($items)) {
            return null;
        }
        return $items[0];
    }

    static function getHashBySQL($sql, $val, $PDO=null) {
        $items = static::simpleQuery($sql, $val, $PDO);
        if (empty($items)) {
            return null;
        }
        return $items[0];
    }

    static function getsHashBySQL($sql, $val, $PDO=null) {
        return static::simpleQuery($sql, $val, $PDO);
    }

    static function getBySome($col, $val, $PDO=null) {
        $item = static::getHashBySome($col, $val, $PDO);
        if (empty($item)) {
            return null;
        }
        return static::getByHash($item);
    }

    static function getHashById($_key, $PDO=null) {
        $res = static::simpleQuery(
            'SELECT * FROM ' . static::$tablename . ' WHERE ' . static::$pkeyname . ' = :key LIMIT 1',
            array('key'=>$_key),
            $PDO
        );
        if(empty($res)){
            return null; // notfound
        }else{
            return $res[0];
        }
    }

    static function getById($_key, $PDO=null) {
        $res = static::getHashById($_key, $PDO);
        if (empty($res)) {
            return null; // notfound
        }else{
            return static::getByHash($res);
        }
    }

    static function getsHashAll($PDO=null) {
        $res = static::simpleQuery('select * from '.static::$tablename, array(),$PDO);
        if (empty($res)) {
            return null; // notfound
        }else{
            return $res;
        }
    }

    static function getsAll($PDO=null) {
        $res = static::getsHashAll($PDO);
        if (empty($res)) {
            return null; // notfound
        }else{
            return static::getsByHashList($res);
        }
    }

    static function getRand() {
        if (static::$config['type'] == 'sqlite') {
            $rand_func_name = "random()";
        } elseif(static::$config['type'] == 'mysql') {
            $rand_func_name = "RAND()";
        } else {
            throw new \PDOException('invalid db_type');
        }

        $items = static::simpleQuery('SELECT * FROM ' . static::$tablename . ' ORDER BY '.$rand_func_name.' LIMIT 1', array());

        if(empty($items)){
            return null;
        }else{
            $obj = new static;
            /** @var \Uzulla\CFEDb2 $obj */
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

    //あるカラムで、複数の値(配列)を指定して、アイテムリストを取得する
    static function getsHashByColVals($col, $val_list, $PDO=null) {
        $inq = static::buildINQuery($val_list);
        $param = static::buildINParams($val_list);
        $WHERE = "WHERE `{$col}` IN {$inq}" ;
        $sql  ="SELECT * FROM `".static::$tablename."` ".$WHERE;
        $items = static::simpleQuery($sql, $param, $PDO);
        if (empty($items)) {
            return null;
        }
        return $items;
    }
    static function getsByColVals($col, $val_list, $PDO=null) {
        $items = static::getsHashByColVals($col, $val_list, $PDO);
        if (empty($items)) {
            return null;
        }
        return static::getsByHashList($items);
    }

//    static function getsSomeColVal($list, $col){ //非推奨
//        return static::getValuesByItemListAndColumnName($list, $col);
//    }
    //アイテムリストから、カラム名を指定して、値の配列をフィルタ的に取り出す
    static function getValuesByItemListAndColumnName($list, $col){ /** @var \Uzulla\CFEDb2[] $list */
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

    public function updateByHash($hash){
        if(!is_array($hash)){
            throw new \Exception('Must list');
        }
        foreach($this->values as $k=>$v){
            if($k=='id' ||$k=='created_at' ||$k=='updated_at' ){
                continue;
            }elseif($k=='ua'){
                $this->val('ua', $_SERVER['HTTP_USER_AGENT']);
            }elseif($k=='ip'){
                $this->val('ip', $_SERVER['REMOTE_ADDR']);
            }elseif(isset($hash[$k])){
                $this->val($k, $hash[$k]);
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
                $callback_available = (isset($v['callback'])) ? true : false;
                $callback = (isset($v['callback'])) ? $v['callback'] : function(){};
                $error_text = (isset($v['error_text'])) ? $v['error_text'] : "{$k}を正しく入力してください";

                if($regexp){
                    if( $require || mb_strlen($value)!=0 ){
                        if(!preg_match($regexp, $value)){
                            $error_list[$k] = $error_text;
                            continue 2;
                        }
                    }
                }else if($callback_available){
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
        $sql = 'DELETE FROM '.static::$tablename.' WHERE ' . $where_col . ' = :val ;';
        $params = array('val' => $where_val);
        try{
            $sth = $PDO->prepare($sql);
            if (!empty($sth)) {
                $sth->execute($params);
            }
            $this->lastRowCount = $sth->rowCount();
        }catch(\PDOException $e){
            static::log(array("DB ERROR: delete fail",$sql,$params,$e->errorInfo));
            throw new \Exception('DB ERROR: delete fail');
        }
        if($this->lastRowCount==0){
            throw new \Exception('DB ERROR: delete fail, item not found.');
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
            static::log(array("DB ERROR: save item fail",$e->getMessage(),$sql,$params,$e->errorInfo));
            throw new \Exception('DB ERROR: save item fail');
        }
        $id = $PDO->lastInsertId();

        if ($state) {
            if ($isInsert && $id==0) { //  if you not set AUTO_INCREMENT=1, $id is 0... become fail.
                static::log(array("DB ERROR: insert fail",$sql,$params));
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
                if(static::$config['type'] == 'sqlite'){
                    $sql .= " ${k}=datetime('now', 'localtime'),";
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
                if(static::$config['type'] == 'sqlite'){
                    $values[] = "datetime('now', 'localtime')";
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
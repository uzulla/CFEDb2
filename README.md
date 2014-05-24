# CFEDb2
オレオレO/Rマッパー


## REQUIRE

- PDO
- mysql or sqlite3
- PHP>=5.3.x

## オレオレライブラリです

ながれるようにチェーンでSqlをBuildするとか、Joinがすばらしくできるとか、そういうものはありません。
設計の自由度もありません。
昔ActiveRecordをつかって、ちゃっちゃっとモデルを作るのが楽だなーって思い、当時の理解で（パラダイムの変遷を理解せず）書いてます。
しかし、ARはfind()とかjoin()とかつなぐのかったるい、いいからSQLで書かせろって思ったので、SQLBuilder系は作っていません。


* まずSQLをかいたほうがハヤイと思っている
* 出てくるのがハッシュだとちょっと不便
* DBにインサートする前にオブジェクトを作りたい
* FatだろうがなんだろうがRowオブジェクト最高


という変わった人なら本ライブラリも良いかもしれません。


## SYNOPSIS

```
$post = new Post();
$post->val('text', 'this is text');
$post->val('num', 123);
$post->saveItem();

$post2 = Post::getById(1);
echo $post2->val('text');

$post3 = Post::getBySome('text', 'this is text');
if(empty($post3)){
    echo "not found";
}

$post_list = Post::getsBySQL('SELECT * FROM post WHERE id>:id', array('id'=>5));
if(empty($post_list)){
    echo "not found";
}
foreach($post_list as $p){
    echo $p->val('id');
}

$post->deleteItem();

//もし貴方がROWオブジェクト嫌いなら…

$post_list = Post::getsHashBySome('text', 'this is text');

```
テストコードを見てください(しかし、全部が羅列されているわけではありません)


## インストール

DLして適当に配置する、または、Composerで
```
{
	"require": {
		"uzulla/cfedb2": "*"
	}
}
```


## モデルクラス例
```
<?php
require_once('../lib/Uzulla/CFEDb2.php'); // ComposerのAutoloaderをつかっているなら不要

class Post extends \Uzulla\CFEDb2{
    static $tablename = 'post';
    static $pkeyname = 'id';
    public function __construct() {
        $this->values['id'] = null;
        $this->values['text'] = null;
        $this->values['num'] = null;
        $this->values['created_at'] = null;
        $this->values['updated_at'] = null;
    }
    public function as_you_like(){
        return $this->val('id').' as you like!';
    }
    static function getMySpecial(){
        return static::getById(3);
    }
}
```


## DB接続情報設定

```
\Uzulla\CFEDb2::$config = array(
    'type'=> 'mysql',
    // 'type'=> 'sqlite',
    'dsn' => 'host=127.0.0.1;dbname=test;charset=utf8mb4',
    // 'dsn' => 'unix_socket=/tmp/mysql.sock;dbname=test',
    // 'dsn' => __DIR__.'/sqlite.db',
    'user' => "",
    'pass' => "",
    'pre_exec' => false,
    'reuse_pdo' => true,
    'DEBUG_BACKTRACE' => true, // or false, enable backtrace log.
    'log'=> null // null will use error_log, Psr-3 logger instance(ex:monolog)
);
```

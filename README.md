# CFEDb2
PHP 5.3 以上向け、オレオレO/Rマッパーもどき


## support DB
PDO + mysql, PDO + sqlite3


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


## 典型的な使い方
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
DLして適当に配置する、

または、Composerで
```
{
	"repositories": [
		{
			"type": "vcs",
			"url": "https://github.com/uzulla/CFEDb2"
		}
	],
	"require": {
		"uzulla/cfedb2": "dev-master"
	}
}
```
(まだバージョン番号降るような状態じゃないので、dev-master指定してください)


## モデルクラス例
```
<?php
require_once('../lib/Uzulla/CFEDb2.php'); # ComposerのAutoloaderをつかっているなら不要

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
    '_db_type' => "sqlite",
    '_db_sv' => DB_FILENAME,
    '_db_name' => "",
    '_db_user' => "",
    '_db_pass' => "",
    '_db_pre_exec' => false, //"SET NAMES UTF8"
    '_db_reuse_pdo' => true,
    'DEBUG' => true,
);
```


## 使わない方が無難です
昔から使っているCFEDb（非公開）というライブラリがさすがに厳しくなってきたので、少し整理した上で、バックアップを兼ねてGithubに置きました。
まだ使い込んでいないので、今後まだまだバグが出てくると思います。


正直な所、これを使う人がいるとはおもえませんが、つかわないほうが良いでしょう。下記を見て人里に帰りましょう。


## 世間一般的にまともであろうORM達
[www.phpactiverecord.org](http://www.phpactiverecord.org/)

[propelorm.org](http://propelorm.org/)

[c9s/LazyRecord](https://github.com/c9s/LazyRecord)

[nekoya/php-ganc](https://github.com/nekoya/php-ganc)

[redbeanphp.com](http://redbeanphp.com/)

[GitHub search](https://github.com/search?q=php+orm&ref=cmdform)

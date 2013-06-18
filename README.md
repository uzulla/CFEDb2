# CFEDb2
PHP 5.3 以上向け、オレオレO/Rマッパーもどき


## support DB
PDO + mysql, PDO + sqlite3


## オレオレライブラリです
ながれるようにチェーンでSqlをBuildするとか、Joinがすばらしくできるとか、そういうものはありません。
設計の自由度もありません。
5年位は前にActiveRecordをつかってから、ちゃっちゃっとモデルを作るのが楽だなーっておもって、当時の知識で（パラダイムの変遷を理解せず）書いてます。
しかしながら、find()とかjoin()とかつなぐのかったるいSQLで書かせろって思ったので、SQLBuilder系は作っていません。


* まずSQLをかいたほうがハヤイと思っている
* 出てくるのがハッシュだとちょっと不便
* DBにインサートする前にオブジェクトを作りたい


という変わった人なら本ライブラリも良いかもしれませんが、自作したほうが良いと思います。

## 典型的な使い方
```
        $post = new Post();
        $post->val('text', 'this is text');
        $post->val('num', 123);
        $post->saveItem();

        $post2 = Post::getById(1);
        echo $post2->val('text');
        
        $post3 = Post::getBySome('text', 'this is text');
        if(is_null($post3)){
            echo "notfound";
        }
        
        $post_list = Post::getsBySQL('SELECT * FROM post WHERE id>:id', array('id'=>5));
        foreach($post_list as $p){
            echo $p->val('id');
        }
        
        $post->deleteItem();

```
まあ、テストコードを見てください。


## モデルクラス例

```
<?php
require_once('CFEDb2.php');

class Post extends CFEDb2{
    static $tablename = 'post';
    static $pkeyname = 'id';
    public function __construct() {
        $this->values['id'] = null;
        $this->values['text'] = null;
        $this->values['num'] = null;
        $this->values['created_at'] = null;
        $this->values['updated_at'] = null;
        parent::__construct();
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
コード全体の何処かに、以下の設定用のクラスが必要です。

```
class DbConfig {
    public $_db_type	= "sqlite";
    public $_db_sv      = "test.db";
    public $_db_name	= "";
    public $_db_user	= "";
    public $_db_pass	= "";
    public $_db_pre_exec = false; // ex: "SET NAMES UTF8"
    public $_db_reuse_pdo = true;
    public $_db_reuse_pdo_global_name = 'CFEDb2_DBH';
    public $DEBUG = true;
}
```


## 使わない方が無難です
昔から使っているCFEDb（非公開）というライブラリがさすがに厳しくなってきたので、少し整理した上で、バックアップを兼ねてGithubに置きました。
まだ使い込んでいないので、今後まだまだバグが出てくると思います。


正直な所、これを使う人がいるとはおもえませんが、つかわないほうが良いでしょう。下記を見て人里に帰りましょう。


## まともであろうORM達
[www.phpactiverecord.org](http://www.phpactiverecord.org/)

[www.phpactiverecord.org](http://www.phpactiverecord.org/)

[propelorm.org](http://propelorm.org/)

[c9s/LazyRecord](https://github.com/c9s/LazyRecord)

[nekoya/php-ganc](https://github.com/nekoya/php-ganc)

[redbeanphp.com](http://redbeanphp.com/)

[GitHub search](https://github.com/search?q=php+orm&ref=cmdform)


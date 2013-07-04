<?php
require_once '../example/Post.php';
require_once '../example/DBConfig.php';
ini_set('error_log', __DIR__ . '/phperror.log');


class PostTest extends PHPUnit_Framework_TestCase{

    protected function setUp(){
        try {
            $dbh = \Uzulla\CFEDb2::getPDO();

            $dbh->exec("DROP TABLE IF EXISTS post ;");

            $sql = 'CREATE TABLE post (
                      id INTEGER PRIMARY KEY NOT NULL,
                      text text ,
                      num integer ,
                      created_at text NOT NULL,
                      updated_at text NOT NULL
                    );
                    INSERT INTO "post" VALUES(1,\'TEXT 1\', 10001,\'1999-12-12 00:00:00\',\'1999-12-12 00:00:00\');
                    INSERT INTO "post" VALUES(2,\'TEXT 2\', 10002,\'1999-12-12 00:00:00\',\'1999-12-12 00:00:00\');
                    INSERT INTO "post" VALUES(3,\'TEXT 3\', 10003,\'1999-12-12 00:00:00\',\'1999-12-12 00:00:00\');
                    INSERT INTO "post" VALUES(4,\'TEXT 4\', 10004,\'1999-12-12 00:00:00\',\'1999-12-12 00:00:00\');
                    INSERT INTO "post" VALUES(5,\'TEXT 5\', 10005,\'1999-12-12 00:00:00\',\'1999-12-12 00:00:00\');
                    ';
            $dbh->exec($sql);

        } catch (PDOException $e) {
            die("DB ERROR: ". $e->getMessage());
        }
    }

    public function testGetPDO(){
        $PDO = Post::getPDO();
        $this->assertEquals('PDO', get_class($PDO));
    }

    public function testNew(){
        $db = new Post();
        $this->assertEquals('Post', get_class($db));
    }

    public function testGetById(){
        $db = Post::getById(1);
        $this->assertEquals('Post', get_class($db));
        $this->assertEquals(1, $db->val('id'));
    }

    public function testSimpleQuery(){
        $db = Post::simpleQuery('select * from post', array());
        $this->assertTrue(is_array($db));
        $this->assertGreaterThan(2, count($db));
        $this->assertGreaterThan(2, count($db[0]));
    }

    /**
     * @expectedException Exception
     */
    public function testSimpleQueryFail(){
        $db = Post::simpleQuery('select bad sql * from post', array());
    }

    /**
     * @expectedException Exception
     */
    public function testBadConfig(){
        $config = new \Uzulla\DbConfig();
        $config->_db_type = "GREATFUL_DB_ENGINE";
        $PDO = Post::getPdo($config);
    }

    public function testSimpleQueryOne(){
        $db = Post::simpleQueryOne('select * from post', array());
        $this->assertTrue(is_array($db));
        $this->assertGreaterThan(2, count($db));
        $this->assertEquals(1, $db['id']);
    }
    public function testGetBySQL(){
        $db = Post::getBySQL('select * from post where `id`=:id', array('id'=>1));
        $this->assertEquals(1, $db->val('id'));
    }
    public function testGetsBySQL(){
        $db = Post::getsBySQL('select * from post where `id` > :id', array('id'=>1));
        $this->assertGreaterThan(1, count($db));
        $_db = $db[0];
        $this->assertGreaterThan(1, $_db->val('id'));
    }


    public function testInsert(){
        $post = new Post();
        $post->val('text', 'this is text');
        $time = time();
        $post->val('num', $time);
        $post->saveItem();

        $id = $post->val('id');
        $_post = Post::getById($id);

        $this->assertEquals('Post', get_class($_post));
        $this->assertEquals('this is text', $_post->val('text'));
        $this->assertEquals($time, $_post->val('num'));
        $this->assertGreaterThan(0, strtotime($_post->val('created_at')));
        $this->assertGreaterThan(0, strtotime($_post->val('updated_at')));
    }

    public function testUpdate(){
        $post = Post::getById(1);
        $post->val('text', "NOWHERE MAN");
        $post->val('num', 9999999);
        $updated_at = $post->val('updated_at');
        $created_at = $post->val('created_at');
        $post->saveItem();

        $this->assertEquals('Post', get_class($post));
        $this->assertEquals('NOWHERE MAN', $post->val('text'));
        $this->assertEquals(9999999, $post->val('num'));
        $this->assertEquals($created_at, $post->val('created_at'));
        $this->assertNotEquals($updated_at, $post->val('updated_at'));
    }

    public function testDelete(){
        $post = Post::getById(1);
        $post->deleteItem();

        $post = Post::getById(1);
        $this->assertEquals($post, NULL);

        $post = Post::getById(2);
        $this->assertEquals('Post', get_class($post));
    }

    public function testGetRand(){
        $beforePost = Post::getRand();
        $flag = false;
        for($i=0; 10>$i; $i++){
            $post = Post::getRand();
            if($post->val('id') !=$beforePost->val('id')){
                $flag=true;
            }
            $beforePost = $post;
        }
        $this->assertEquals(true, $flag);
    }

    public function testTransactionRollback(){
        $PDO = \Uzulla\CFEDb2::getPDO();

        $post = Post::getById(1, $PDO);
        $beforeTransaction = $post->val('text');
        //echo $beforeTransaction;

        $post->transactionBegin();
        $post->val('text', "トランザクション中保存");
        $post->saveItem();

        $_post = Post::getById(1, $PDO);
        $beforeRollbackText = $_post->val('text');
        //echo $beforeRollbackText;

        $post->transactionRollback();
        $_post = Post::getById(1, $PDO);
        $aftertRollbackText = $_post->val('text');
        //echo $aftertRollbackText;

        $this->assertEquals($beforeTransaction, $aftertRollbackText);
        $this->assertNotEquals($aftertRollbackText, $beforeRollbackText);

    }

    public function testGetsHashByList(){
        $post_list = Post::getsAll();
	$hash_array = Post::getsHashByList($post_list);

        $this->assertEquals(5, count($hash_array));
	$this->assertEquals(true, is_array($hash_array[0]));
        $this->assertEquals(5, count($hash_array[0]));
        $this->assertEquals(true, isset($hash_array[0]['num']));
	$this->assertEquals(true, is_string($hash_array[1]['text']));
    }
}

<?php
require_once '../example/Post.php';
ini_set('error_log', __DIR__ . '/phperror.log');

class PostTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if(isset($_SERVER['TEST_MYSQL_PERL_DSN'])){
            //DBI:mysql:dbname=test;mysql_socket=/path/to/tmp/mysql.sock;user=root
            list($dsn, $path, $user) = preg_split('/;/', $_SERVER['TEST_MYSQL_PERL_DSN'], null, PREG_SPLIT_NO_EMPTY);
            $type = 'mysql';
            $db_name  = preg_replace('/DBI:mysql:/', '', $dsn);
            $path = preg_replace('/mysql_socket/', 'unix_socket', $path);
            $dsn = "{$path};{$db_name}";
            $user = preg_replace('/user=/', '', $user);
            $pass = '';

            \Uzulla\CFEDb2::$config = array(
                'type'=> $type,
                //'dsn' => 'host=127.0.0.1;dbname=test',
                'dsn' => $dsn,
                'user' => $user,
                'pass' => $pass,
                'pre_exec' => "SET NAMES UTF8",
                'reuse_pdo' => true,
                'DEBUG_BACKTRACE' => true, // enable backtrace log.
                'log'=> null // psr-3 logger instance
            );
        }else{
            \Uzulla\CFEDb2::$config = array(
                'type'=> 'sqlite',
                'dsn' => 'test.db',//':memory:',
                'user' => "",
                'pass' => "",
                'pre_exec' => false,
                'reuse_pdo' => true,
                'DEBUG_BACKTRACE' => true, // enable backtrace log.
                'log'=> null // psr-3 logger instance
            );
        }

        try {
            $dbh = \Uzulla\CFEDb2::getPDO();
            if(\Uzulla\CFEDb2::$config['type'] == 'mysql'){
                $sql = file_get_contents("init_mysql.sql");
            }else if(\Uzulla\CFEDb2::$config['type'] == 'sqlite'){
                $sql = file_get_contents("init.sql");
            }else{
                die('setup error: unknown type');
            }

            $dbh->exec($sql);

        } catch (PDOException $e) {
            die("setup error: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass()
    {
    }

    public function testGetPDO()
    {
        $PDO = Post::getPDO();
        $this->assertEquals('PDO', get_class($PDO));
    }

    public function testNew()
    {
        $db = new Post();
        $this->assertEquals('Post', get_class($db));
    }

    public function testGetById()
    {
        $db = Post::getById(1);
        $this->assertEquals('Post', get_class($db));
        $this->assertEquals(1, $db->val('id'));
    }

    public function testSimpleQuery()
    {
        $db = Post::simpleQuery('select * from post', array());
        $this->assertTrue(is_array($db));
        $this->assertGreaterThan(2, count($db));
        $this->assertGreaterThan(2, count($db[0]));
    }

    /**
     * @expectedException Exception
     */
    public function testSimpleQueryFail()
    {
        Post::simpleQuery('select bad sql * from post', array());
    }

    /**
     * @expectedException Exception
     */
    public function testDeleteFail()
    {
        $post = new Post();
        $post->val('text', 'this is text');
        $time = time();
        $post->val('num', $time);
        $post->saveItem();
        $post->deleteItem();
        $post->deleteItem(); // double delete.
    }

    /**
     * @expectedException Exception
     */
    public function testBadConfig()
    {
        $config = array(
            '_db_type' => "bad_db",
            '_db_sv' => "test.db",
            '_db_name' => "",
            '_db_user' => "",
            '_db_pass' => "",
            '_db_pre_exec' => false, //"SET NAMES UTF8"
            '_db_reuse_pdo' => false,
            'DEBUG' => true,
        );
        Post::getPDO($config);
    }

    public function testSimpleQueryOne()
    {
        $db = Post::simpleQueryOne('select * from post', array());
        $this->assertTrue(is_array($db));
        $this->assertGreaterThan(2, count($db));
        $this->assertEquals(1, $db['id']);
    }

    public function testGetBySQL()
    {
        $db = Post::getBySQL('select * from post where `id`=:id', array('id' => 1));
        $this->assertEquals(1, $db->val('id'));

        $db = Post::getBySQL('select * from post where `id`=:id', array('id' => 999));
        $this->assertEquals(null, $db);
    }

    public function testGetsBySQL()
    {
        $db = Post::getsBySQL('select * from post where `id` > :id', array('id' => 1));
        $this->assertGreaterThan(1, count($db));
        /** @var $_db \Uzulla\CFEDb2 */
        $_db = $db[0];
        $this->assertGreaterThan(1, $_db->val('id'));

        $db = Post::getsBySQL('select * from post where `id` > :id', array('id' => 9999));
        $this->assertEquals(null, $db);
    }


    public function testInsert()
    {
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

    public function testUpdate()
    {
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

    public function testDelete()
    {
        $post = Post::getById(1);
        $post->deleteItem();

        $post = Post::getById(1);
        $this->assertEquals($post, NULL);

        $post = Post::getById(2);
        $this->assertEquals('Post', get_class($post));
    }

    public function testExec()
    {
        Post::simpleExec(
            'UPDATE post SET text=:text WHERE id=:id',
            array('text'=>'updated', 'id'=>1)
        );

        $post = Post::getById(1);
        $this->assertEquals($post->val('text'), 'updated');

        Post::simpleExec(
            'DELETE FROM post WHERE id=:id',
            array('id'=>1)
        );

        $post = Post::getById(1);
        $this->assertEquals($post, null);
    }

    public function testGetRand()
    {
        $beforePost = Post::getRand();
        $flag = false;
        for ($i = 0; 10 > $i; $i++) {
            $post = Post::getRand();
            if ($post->val('id') != $beforePost->val('id')) {
                $flag = true;
            }
            $beforePost = $post;
        }
        $this->assertEquals(true, $flag);
    }

    public function testTransactionRollback()
    {
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

    public function testGetsHashByList()
    {
        $post_list = Post::getsAll();
        $hash_array = Post::getsHashByList($post_list);

        $this->assertEquals(5, count($hash_array));
        $this->assertEquals(true, is_array($hash_array[0]));
        $this->assertEquals(5, count($hash_array[0]));
        $this->assertEquals(true, isset($hash_array[0]['num']));
        $this->assertEquals(true, is_string($hash_array[1]['text']));
    }

    public function testValidation()
    {
        $post = new Post;
        $post->val('text', 'TEXT is text');
        $post->val('num', 10);
        $error_list = $post->validate();
        $this->assertEquals(0, count($error_list));

        $post = new Post;
        $post->val('text', 'bad text');
        $post->val('num', 10);
        $error_list = $post->validate();
        $this->assertEquals(1, count($error_list));

        $post = new Post;
        $post->val('text', 'TEXT is text');
        $post->val('num', 'NaN');
        $error_list = $post->validate();
        $this->assertEquals(1, count($error_list));

        $post = new Post;
        $post->val('text', 'TEXT is text');
        $post->val('num', -1);
        $error_list = $post->validate();
        $this->assertEquals(1, count($error_list));

        $post = new Post;
        $post->val('text', 'bad text');
        $post->val('num', 'bad number');
        $error_list = $post->validate();
        $this->assertEquals(2, count($error_list));

        $post = new Post;
        $post->val('text', '');
        $post->val('num', '');
        $error_list = $post->validate();
        $this->assertEquals(1, count($error_list));

    }

    public function testCount()
    {
        $this->assertEquals(5, Post::countAll());
        $this->assertEquals(1, Post::countBySome('num', '10001'));
        $this->assertEquals(1, Post::countBySome(array('num', 'text'), array('10001', 'TEXT 1')));
    }

    public function testLimit()
    {
        $post_list = Post::simpleQuery('SELECT * FROM post', array());
        $this->assertEquals(5, count($post_list));

        $post_list = Post::simpleQuery('SELECT * FROM post LIMIT :limit', array('limit'=>3));
        $this->assertEquals(3, count($post_list));
    }

}

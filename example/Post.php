<?php
/*
this is CFEDb2 sample.
*/
require_once('../lib/Uzulla/CFEDb2.php');

class Post extends \Uzulla\CFEDb2{

    static $tablename = 'post';
    static $pkeyname = 'id';

    public function __construct() {
        //settings colmun, and default value;
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
}

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

        $this->validateData = array(
            'text' => array(
                'require' => false,
                'regexp'=>"/\ATEXT/",
            ),
            'num'  => array(
	            array(
	            	'require' => true,
	            	'regexp' => "/\A[0-9]+\z/",
	            	'error_text' => "数字以外が混ざっています",
            	),
            	array(
		            'require' => true,
		            'callback' => function($str){
		                if(0+$str < 0){
			                return false;
		                }else{
		                	return true;
		                }
		            },
		            'error_text' => '数字が負数です',
	            ),
	        ),
        );

        parent::__construct();
    }

    public function as_you_like(){
        return $this->val('id').' as you like!';
    }
}

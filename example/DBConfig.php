<?php
namespace Uzulla;

class DbConfig {
    public $_db_type    = "sqlite";
    public $_db_sv      = "test.db";
    public $_db_name    = "";
    public $_db_user    = "";
    public $_db_pass    = "";
    public $_db_pre_exec = false;//"SET NAMES UTF8"
    public $_db_reuse_pdo = true;
    public $_db_reuse_pdo_global_name = 'CFEDb2_DBH';
    public $DEBUG = true;
}

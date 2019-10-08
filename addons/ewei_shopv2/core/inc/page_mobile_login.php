<?php


if (!defined('IN_IA')) {
    exit('Access Denied');
}

class MobileLoginPage extends MobilePage {

    public function __construct() {

        global $_W,$_GPC;
        parent::__construct();
    }
}
	
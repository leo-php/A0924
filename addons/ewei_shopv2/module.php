<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}
require_once IA_ROOT . '/addons/ewei_shopv2/version.php';
require_once IA_ROOT . '/addons/ewei_shopv2/defines.php';
require_once EWEI_SHOPV2_INC . 'functions.php';
class Ewei_shopv2Module extends WeModule {

      public function welcomeDisplay() {
          //判断是否已经安装成功
          if(!is_file(EWEI_SHOPV2_INC.'receiver.php') ) {
              header('location: ' . webUrl('system/install'));
          }else{
              header('location: ' . webUrl());
          }
		exit;
	}
}

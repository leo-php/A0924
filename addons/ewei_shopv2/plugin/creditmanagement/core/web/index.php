<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);

class Index_EweiShopV2Page extends PluginWebPage {

    public function main() {
        header('location: ' . webUrl('creditmanagement/log'));
        exit;
    }


}
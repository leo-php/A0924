<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);

class Set_EweiShopV2Page extends PluginWebPage {

    public function main() {
        global $_W, $_GPC;
        $no_left = false;
        if ($_W['ispost']) {
            $data = is_array($_GPC['data']) ? $_GPC['data'] : array();
            
            m('common')->updatePluginset(array('creditmanagement' => $data));
            show_json(1, array('url' => webUrl('creditmanagement/set', array('tab' => str_replace("#tab_", "", $_GPC['tab'])))));
            exit;
        }
        $data = m('common')->getPluginset('creditmanagement');

        include $this->template();
    }
    

}
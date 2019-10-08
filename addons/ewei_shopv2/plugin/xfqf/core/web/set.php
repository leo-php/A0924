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
            //$data['status'] = intval($_GPC['status']);
            m('common')->updatePluginset(array('xfqf' => $data));
            show_json(1, array('url' => webUrl('xfqf/set', array('tab' => str_replace("#tab_", "", $_GPC['tab'])))));
        }
        $set = m('common')->getPluginset('xfqf');

        $data = array(
            'status'         => isset($set['status']) ? $set['status'] : 0,
            'max_price'      => isset($set['max_price']) ? $set['max_price'] : 0,
            'max_point'      => isset($set['max_point']) ? $set['max_point'] : 0,
            'percentage'     => isset($set['percentage']) ? $set['percentage'] : 0.1,
            'purchase'       => isset($set['purchase']) ? $set['purchase'] : 10,
            'level1'         => isset($set['level1']) ? $set['level1'] : 10,
            'level2'         => isset($set['level2']) ? $set['level2'] : 5,
            'rebusine'       => isset($set['rebusine']) ? $set['rebusine'] : 10,
            'area'           => isset($set['area']) ? $set['area'] : 10,
            'city'           => isset($set['city']) ? $set['city'] : 5,
            'up_point'       => isset($set['up_point']) ? $set['up_point'] : 99900,
            'commsion_apply' => isset($set['commsion_apply']) ? $set['commsion_apply'] : 0
        );
        include $this->template();
    }

}
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
            m('common')->updatePluginset(array('jflc' => $data));

            $date_list = $_GPC['date'];

            foreach ($date_list as $row) {
                $id = intval($row['id']);
                $date = $row['date'];
                $proportion = $row['proportion'];
                if (empty($id)) {
                    pdo_insert('ewei_shop_jflc_income', array(
                        'uniacid'    => $_W['uniacid'],
                        'createtime' => TIMESTAMP,
                        'proportion' => $proportion,
                        'createdate' => $date
                    ));
                } else {
                    pdo_update('ewei_shop_jflc_income', array(
                        'proportion' => $proportion
                    ), array(
                        'id' => $id
                    ));
                }
            }

            show_json(1, array('url' => webUrl('jflc/set', array('tab' => str_replace("#tab_", "", $_GPC['tab'])))));
        }
        $data = m('common')->getPluginset('jflc');

        $default = floatval($data['default']);

        $list = array();
        for ($i = 0; $i < 10; $i++) {
            $date = date('Y-m-d', strtotime('+' . $i . ' day'));

            $row = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_jflc_income') . ' WHERE uniacid=:uniacid AND createdate=:createdate LIMIT 1', array(
                ':uniacid'    => $_W['uniacid'],
                ':createdate' => $date
            ));

            $list[] = array(
                'index'      => $i,
                'id'         => empty($row) ? 0 : $row['id'],
                'date'       => $date,
                'proportion' => empty($row) ? $default : $row['proportion']
            );
        }

        include $this->template();
    }


}
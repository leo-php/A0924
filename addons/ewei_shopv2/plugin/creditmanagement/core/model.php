<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}


class creditmanagementModel extends PluginModel {


    public function status() {
        $set = $this->getSet();
        return empty($set['status']) ? false : true;
    }

    public function getTotals() {
        global $_W;
        $applies = pdo_fetchall('SELECT SUM(1) num,status FROM ' . tablename('ewei_shop_creditmanagement_apply') . ' WHERE uniacid=:uniacid GROUP BY status ', array(
            ':uniacid' => $_W['uniacid']
        ), 'status');

        return array(
            'total1'  => empty($applies[1]) ? 0 : $applies[1]['num'],
            'total2'  => empty($applies[2]) ? 0 : $applies[2]['num'],
            'total3'  => empty($applies[3]) ? 0 : $applies[3]['num'],
            'total_1' => empty($applies[-1]) ? 0 : $applies[-1]['num']
        );
    }



}
<?php
/**
 * Created by PhpStorm.
 * User: gwind
 * Date: 2017/10/23
 * Time: 下午 9:11
 */

if (!(defined('IN_IA'))) {
    exit('Access Denied');
}


class Merch_EweiShopV2Page extends PluginMobileLoginPage {
    public function main() {
        global $_W;
        $commission = pdo_fetchall('SELECT status,SUM(commission) commission FROM ims_ewei_shop_jflc_merch_order_log WHERE uniacid=:uniacid AND openid=:openid GROUP BY status', array(
            ':uniacid' => $_W['uniacid'],
            ':openid'  => $_W['openid']
        ), 'status');

        $apply_commission = empty($commission[1]['commission']) ? 0.00 : round($commission[1]['commission'], 2);
        $no_commission = empty($commission[0]['commission']) ? 0.00 : round($commission[0]['commission'], 2);

        $all_commission = round($apply_commission + $no_commission, 2);

        $merch_num = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename('ewei_shop_merch_user') . ' WHERE uniacid=:uniacid AND commissionopenid=:openid', array(
            ':uniacid' => $_W['uniacid'],
            ':openid'  => $_W['openid']
        ));

        include $this->template();
    }

    public function log() {
        global $_W, $_GPC;
        $status = empty($_GPC['status']) ? 0 : $_GPC['status'];


        include $this->template();
    }

    public function get_list() {
        global $_W, $_GPC;

        $openid = $_W['openid'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $status = intval($_GPC['status']);

        $condition = ' uniacid=:uniacid AND openid=:openid ';
        $params = array(
            ':openid'  => $openid,
            ':uniacid' => $_W['uniacid']
        );

        if ($status != 0) {
            if ($status < 0) {
                $condition .= ' AND status=0 ';
            } else {
                $condition .= ' AND status=1 ';
            }
        }

        $list = pdo_fetchall('SELECT * FROM ' . tablename('ewei_shop_jflc_merch_order_log') . ' WHERE ' . $condition . ' order by id desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_jflc_merch_order_log') . ' where ' . $condition, $params);

        foreach ($list as &$row) {
            $row['createtime'] = date('Y-m-d H:i', $row['createtime']);
        }
        unset($row);

        show_json(1, array(
            'total'    => $total,
            'list'     => $list,
            'pagesize' => $psize
        ));
    }

    public function merch_list(){
        global $_W,$_GPC;

        include $this->template();
    }

    public function get_all(){
        global $_W,$_GPC;

        $openid = $_W['openid'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;

        $condition = ' uniacid=:uniacid AND commissionopenid=:openid ';
        $params = array(
            ':openid'  => $openid,
            ':uniacid' => $_W['uniacid']
        );

        $list = pdo_fetchall('SELECT * FROM ' . tablename('ewei_shop_merch_user') . ' WHERE ' . $condition . ' order by id desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_merch_user') . ' where ' . $condition, $params);

        foreach ($list as &$row) {

            $merch_log = pdo_fetch('SELECT SUM(commission) commission,SUM(price) money,MAX(createtime) createtime FROM '.tablename('ewei_shop_jflc_merch_order_log').' WHERE uniacid=:uniacid AND merchid=:merchid LIMIT 1',array(
                ':uniacid' => $_W['uniacid'],
                ':merchid' => intval($row['id'])
            ));

            $row['commission']  = $merch_log['commission'];
            $row['money']       = $merch_log['money'];
            $row['createtime'] = date('Y-m-d H:i', $merch_log['createtime']);
        }
        unset($row);

        show_json(1, array(
            'total'    => $total,
            'list'     => $list,
            'pagesize' => $psize
        ));
    }
}
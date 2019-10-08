<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/6/17
 * Time: 11:51
 */

if (!(defined('IN_IA'))) {
    exit('Access Denied');
}


class Log_EweiShopV2Page extends PluginMobilePage {
    public function main() {

        global $_W;
        $member = m('member')->getMember($_W['openid']);
        $intotal = pdo_fetchcolumn('SELECT SUM(point) FROM ' . tablename('ewei_shop_jflc_point_log') . ' WHERE uniacid=:uniacid AND mid=:mid AND point>0 LIMIT 1', array(
            ':uniacid' => $_W['uniacid'],
            ':mid'     => $member['id']
        ));
        include $this->template();
    }

    public function get_list() {
        global $_W;
        global $_GPC;
        $member = m('member')->getMember($_W['openid']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $condition = '`mid`=:mid and uniacid=:uniacid';
        $params = array(
            ':mid'     => $member['id'],
            ':uniacid' => $_W['uniacid']
        );

        $list = pdo_fetchall('select * from ' . tablename('ewei_shop_jflc_point_log') . ' where ' . $condition . ' order by id desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_jflc_point_log') . ' where ' . $condition, $params);

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

}
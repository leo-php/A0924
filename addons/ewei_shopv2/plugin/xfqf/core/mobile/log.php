<?php

/**
 * Created by PhpStorm.
 * User: gwind
 * Date: 2017/7/22
 * Time: 上午 10:49
 */

class Log_ZmShopPage extends PluginMobileLoginPage {

    function main() {
        global $_W, $_GPC;

        include $this->template();
    }

    function get_list() {
        global $_W, $_GPC;

        $pindex = max(1, intval($_GPC['page']));
        $psize = 10;
        $uid = intval(pdo_fetchcolumn('SELECT uid FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid', array(
            ':uniacid' => $_W['uniacid'],
            ':openid'  => $_W['openid']
        )));

        if (empty($uid)) show_json(1, array('list' => array(), 'total' => 0, 'pagesize' => $psize));


        $condition = " credittype='credit1' and uid=:uid and uniacid=:uniacid";
        $params = array(
            ':uniacid' => $_W['uniacid'],
            ':uid'     => $uid
        );

        $list = pdo_fetchall("select * from " . tablename('mc_credits_record') . " where {$condition} order by createtime desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('mc_credits_record') . " where {$condition}", $params);

        foreach ($list as &$row) {
            $row['createtime'] = date('Y-m-d H:i', $row['createtime']);
        }
        unset($row);


        show_json(1, array('list' => $list, 'total' => $total, 'pagesize' => $psize));
    }
}

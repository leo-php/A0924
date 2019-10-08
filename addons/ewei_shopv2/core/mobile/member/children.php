<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Children_EweiShopV2Page extends MobileLoginPage
{
    public function main()
    {
        global $_W;
        global $_GPC;
        $memberId = pdo_getcolumn("ewei_shop_member", array("openid" => $_W['openid']), 'id');
        $firstChildren = pdo_fetchall("select id,openid,inviter from ".tablename("ewei_shop_member")." where inviter=:id", array(":id" => $memberId));
        $firstTotal = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member') . ' where inviter=:id and uniacid=:uniacid limit 1', array(':id' => $memberId, ':uniacid' => $_W['uniacid']));
        $secondTotal = 0;
        foreach ($firstChildren as $k => $v) {
            $secondTotal += pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member') . ' where inviter=:id and uniacid=:uniacid limit 1', array(':id' => $v['id'], ':uniacid' => $_W['uniacid']));
        }
        $total = $firstTotal + $secondTotal;
        include $this->template();
    }

    public function get_list()
    {
        global $_W;
        global $_GPC;
        $openid = $_W['openid'];
        $level = intval($_GPC['level']);
        $memberId = pdo_getcolumn("ewei_shop_member", array("openid" => $_W['openid']), 'id');
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        if ($level == 1 || empty($level)) {
            //直推
            $list = pdo_fetchall("select * from ".tablename("ewei_shop_member")." where 
            inviter=:id ORDER BY childtime DESC limit " . (($pindex - 1) * $psize) . ',' . $psize, array(":id" => $memberId));
            foreach ($list as $k => $v) {
                $list[$k]['myChildTotal'] = pdo_fetchcolumn('select count(*) from ' .
                    tablename('ewei_shop_member') . ' where inviter=:id and uniacid=:uniacid limit 1', array(':id' => $v['id'], ':uniacid' => $_W['uniacid']));
                $list[$k]['createtime'] = date("Y-m-d H:i", $v['createtime']);
                $list[$k]['levelname'] = pdo_getcolumn("ewei_shop_member_level", array('id' => $v['level']), 'levelname');
            }
            $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member') . ' where 
            inviter=:id and uniacid=:uniacid limit 1', array(':id' => $memberId, ':uniacid' => $_W['uniacid']));
            show_json(1, array('list' => $list, 'total' => $total, 'pagesize' => $psize));
        } else if ($level == 2) {
            //间推
            $firstChildren = pdo_fetchall("select id from ".tablename("ewei_shop_member")." where 
            inviter=:id ORDER BY childtime DESC limit " . (($pindex - 1) * $psize) . ',' . $psize, array(":id" => $memberId));
            $firstChildrenIds = implode(',', array_column($firstChildren, 'id'));
            $list = pdo_fetchall("select * from ".tablename("ewei_shop_member")." where inviter in ({$firstChildrenIds}) 
            ORDER BY childtime DESC limit " . (($pindex - 1) * $psize) . ',' . $psize, array(":id" => $memberId));
            foreach ($list as $k => $v) {
                $list[$k]['myChildTotal'] = pdo_fetchcolumn('select count(*) from ' .
                    tablename('ewei_shop_member') . ' where inviter=:id and uniacid=:uniacid limit 1', array(':id' => $v['id'], ':uniacid' => $_W['uniacid']));
                $list[$k]['createtime'] = date("Y-m-d H:i", $v['createtime']);
                $list[$k]['levelname'] = pdo_getcolumn("ewei_shop_member_level", array('id' => $v['level']), 'levelname');
            }
            $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member') . ' where 
            inviter=:id and uniacid=:uniacid limit 1', array(':id' => $memberId, ':uniacid' => $_W['uniacid']));
            show_json(1, array('list' => $list, 'total' => $total, 'pagesize' => $psize));
        }
    }

}

?>

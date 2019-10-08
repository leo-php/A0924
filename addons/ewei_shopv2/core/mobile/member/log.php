<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Log_EweiShopV2Page extends MobileLoginPage
{

    function main()
    {
        global $_W, $_GPC;
        $_GPC['type'] = intval($_GPC['type']);
        include $this->template();
    }

    function get_list()
    {
        global $_W, $_GPC;
        $type = intval($_GPC['type']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 10;
        $apply_type = array(0 => '微信钱包', 2 => '支付宝', 3 => '银行卡');

        $condition = " and openid=:openid and uniacid=:uniacid and type=:type";
        $params = array(
            ':uniacid' => $_W['uniacid'],
            ':openid' => $_W['openid'],
            ':type' => intval($_GPC['type'])
        );
        $uidinfo = M('member')->getInfo($_W['openid']);
        $uid = $uidinfo['uid'];
        $credit_condition = " and r.uniacid=" . $_W['uniacid'] . " and r.credittype='credit2' and r.uid = " . $uid . " and r.num > 0 and remark not like '%充值%' order by r.createtime desc LIMIT ";
        if ($uid > 0) {
            $r = pdo_fetchall("select m.uid,m.mobile,m.nickname,r.remark title,r.num money,r.createtime from " . tablename("ewei_shop_member_credit_record") . "r left join " . tablename('ewei_shop_member') . " m on m.uid = r.uid where 1 " . $credit_condition . ($pindex - 1) * $psize . ',' . $psize);
            foreach ($r as &$item) {
                $item['createtime'] = date("Y-m-d H:i:s", $item['createtime']);
                // 交易类型
                $item['rechargetype'] = 'credit';
            }
            unset($item);
        }
        $list = pdo_fetchall("select * from " . tablename('ewei_shop_member_log') . " where 1 {$condition} order by createtime desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member_log') . " where 1 {$condition}", $params);
        foreach ($list as &$row) {
            $row['createtime'] = date('Y-m-d H:i', $row['createtime']);
            $row['typestr'] = $apply_type[$row['applytype']];
        }
        unset($row);
        if (is_array($r)) {
            $list = array_merge($r, $list);
        }
        show_json(1, array('list' => $list, 'total' => $total, 'pagesize' => $psize));
    }

    //卫卷明细重写
    public function get_credit2_list()
    {
        global $_W,$_GPC;
        $list = pdo_fetchall("select * from ");
    }

    public function credit_index()
    {
        global $_W, $_GPC;
        $_GPC['type'] = intval($_GPC['type']);
        include $this->template('member/credit_index');
    }

    public function get_credit_list()
    {
        global $_W, $_GPC;
        $type = intval($_GPC['type']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 50;
        $condition = " and openid=:openid and credittype = 'credit1'";
        $params = array(':openid' => $_W['openid']);
        $list = pdo_fetchall("select * from " . tablename('ewei_shop_member_credit_record') . " 
        where 1 {$condition} order by createtime desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
        foreach ($list as $k => $v) {
            $list[$k]['createtime'] = date("Y-m-d H:i", $v['createtime']);
            $remarkPos = mb_strpos($v['remark'], 'OPENID');
            if (!empty($remarkPos)){
                $remarkPos = $remarkPos < 25 ? $remarkPos : 25;
                $list[$k]['remark'] = mb_substr($v['remark'], 0, $remarkPos);
            }
        }
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member_credit_record') . " where 1 {$condition}", $params);
        show_json(1, array('list' => $list, 'total' => $total, 'pagesize' => $psize));
    }

    public function money_index()
    {
        global $_W, $_GPC;
        $_GPC['type'] = intval($_GPC['type']);
        include $this->template('member/money_index');
    }

    public function get_money_list()
    {
        global $_W, $_GPC;
        $type = intval($_GPC['type']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 50;
        $condition = " and openid=:openid and credittype = 'credit2'";
        $params = array(':openid' => $_W['openid']);
        $list = pdo_fetchall("select * from " . tablename('ewei_shop_member_credit_record') . "
        where 1 {$condition} order by createtime desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
        foreach ($list as $k => $v) {
            $list[$k]['createtime'] = date("Y-m-d H:i", $v['createtime']);
            $remarkPos = mb_strpos($v['remark'], 'OPENID');
            if (!empty($remarkPos)){
                $remarkPos = $remarkPos < 25 ? $remarkPos : 25;
                $list[$k]['remark'] = mb_substr($v['remark'], 0, $remarkPos);
            }
        }
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member_credit_record') . " where 1 {$condition}", $params);
        show_json(1, array('list' => $list, 'total' => $total, 'pagesize' => $psize));
    }
}

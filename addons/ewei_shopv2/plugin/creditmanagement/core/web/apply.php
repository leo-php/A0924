<?php
if (!(defined('IN_IA'))) {
    exit('Access Denied');
}

class Apply_EweiShopV2Page extends PluginWebPage {
    public function main() {
        global $_W;
        global $_GPC;
        $status = intval($_GPC['status']);
        empty($status) && ($status = 1);

        $apply_type = array('余额', '微信钱包', '支付宝', '银行卡');

        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $condition = ' and a.uniacid=:uniacid and a.status=:status';
        $params = array(':uniacid' => $_W['uniacid'], ':status' => $status);
        $searchfield = strtolower(trim($_GPC['searchfield']));
        $keyword = trim($_GPC['keyword']);
        if (!(empty($searchfield)) && !(empty($keyword))) {
            if ($searchfield == 'applyno') {
                $condition .= ' and a.applyno like :keyword';
            } else if ($searchfield == 'member') {
                $condition .= ' and (m.realname like :keyword or m.nickname like :keyword or m.mobile like :keyword)';
            }
            $params[':keyword'] = '%' . $keyword . '%';
        }
        if (empty($starttime) || empty($endtime)) {
            $starttime = strtotime('-1 month');
            $endtime = time();
        }
        $timetype = $_GPC['timetype'];
        if (!(empty($_GPC['timetype']))) {
            $starttime = strtotime($_GPC['time']['start']);
            $endtime = strtotime($_GPC['time']['end']);
            if (!(empty($timetype))) {
                $condition .= ' AND a.' . $timetype . ' >= :starttime AND a.' . $timetype . '  <= :endtime ';
                $params[':starttime'] = $starttime;
                $params[':endtime'] = $endtime;
            }
        }

        if (3 <= $status) {
            $orderby = 'paytime';
        } else if (2 <= $status) {
            $orderby = ' checktime';
        } else {
            $orderby = 'applytime';
        }
        $applytitle = '';
        if ($status == 1) {
            $applytitle = '待审核';
        } else if ($status == 2) {
            $applytitle = '待打款';
        } else if ($status == 3) {
            $applytitle = '已打款';
        } else if ($status == -1) {
            $applytitle = '已无效';
        }
        $sql = 'select a.*,m.nickname,m.avatar,m.realname,m.mobile from ' . tablename('ewei_shop_creditmanagement_apply') . ' a ' .
            ' left join ' . tablename('ewei_shop_member') . ' m on m.id = a.mid' .
            ' where 1 ' . $condition . ' ORDER BY ' . $orderby . ' desc ';
        if (empty($_GPC['export'])) {
            $sql .= '  limit ' . (($pindex - 1) * $psize) . ',' . $psize;
        }
        $list = pdo_fetchall($sql, $params);

        if ($status == 3) {
            $realmoney_total = (double)pdo_fetchcolumn('select sum(a.realmoney) from ' . tablename('ewei_shop_creditmanagement_apply') . ' a ' .
                ' left join ' . tablename('ewei_shop_member') . ' m on m.id = a.mid' .
                ' where 1 ' . $condition, $params);
        }

        foreach ($list as &$row) {

            $row['typestr'] = $apply_type[$row['type']];
        }
        unset($row);

        $total = pdo_fetchcolumn('select count(1) from' . tablename('ewei_shop_creditmanagement_apply') . ' a ' . ' left join ' . tablename('ewei_shop_member') . ' m on m.uid = a.mid' . ' left join ' . tablename('ewei_shop_commission_level') . ' l on l.id = m.agentlevel' . ' where 1 ' . $condition, $params);
        $pager = pagination($total, $pindex, $psize);
        include $this->template();
    }

    protected function applyData() {
        global $_W;
        global $_GPC;
        $id = intval($_GPC['id']);
        $apply = pdo_fetch('select * from ' . tablename('ewei_shop_creditmanagement_apply') . ' where uniacid=:uniacid and id=:id limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $id));
        if (empty($apply)) {
            if ($_W['isajax']) {
                show_json(0, '提现申请不存在!!');
            }
            $this->message('提现申请不存在!', '', 'error');
        }
        $status = intval($_GPC['status']);
        empty($status) && ($status = 1);
        if ($apply['status'] == -1) {
            ca('creditmanagement.apply.view_1');
        } else {
            ca('creditmanagement.apply.view' . $apply['status']);
        }

        $agentid = $apply['mid'];

        $member = p('commission')->getInfo($agentid, array('total', 'ok', 'apply', 'lock', 'check'));


        $orderids = iunserializer($apply['orderids']);
        if (!(is_array($orderids)) || (count($orderids) <= 0)) {
            $this->message('无任何订单，无法查看!', '', 'error');
        }

        $ids = array();
        foreach ($orderids as $o) {
            $ids[] = $o['id'];
        }
        
        $list = pdo_fetchall('select * from ' . tablename('ewei_shop_creditmanagement_log') . ' where  id in ( ' . implode(',', $ids) . ' );');

        $totalcommission = 0;
        $totalpay = 0;
        foreach ($list as &$row) {
            $totalmoney += $row['num'];
            $totalcommission += $row['num'];
            if($row['status']==2)$totalpay += $row['num'];
        }
        unset($row);

        $totalcount = $total = pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_creditmanagement_log') . ' o ' . ' left join ' . tablename('ewei_shop_member') . ' m on o.openid = m.openid '  . ' where o.id in ( ' . implode(',', $ids) . ' );');

        $set_array = array();
        $set_array['charge'] = $apply['charge'];
        $set_array['begin'] = $apply['beginmoney'];
        $set_array['end'] = $apply['endmoney'];
        $realmoney = $totalpay;
        $deductionmoney = 0;
        if (!(empty($set_array['charge']))) {
            $money_array = m('member')->getCalculateMoney($totalpay, $set_array);
            if ($money_array['flag']) {
                $realmoney = $money_array['realmoney'];
                $deductionmoney = $money_array['deductionmoney'];
            }
        }
        $apply_type = array('余额', '微信钱包', '支付宝', '银行卡');
        return array('id' => $id, 'status' => $status, 'apply' => $apply, 'list' => $list, 'totalcount' => $totalcount, 'totalmoney' => $totalmoney, 'member' => $member, 'totalpay' => $totalpay, 'totalcommission' => $totalcommission, 'realmoney' => $realmoney, 'deductionmoney' => $deductionmoney, 'charge' => $set_array['charge'], 'agentLevel' => $agentLevel, 'set_array' => $set_array, 'apply_type' => $apply_type);
    }

    public function detail() {
        global $_W;
        global $_GPC;

        $applyData = $this->applyData();
        extract($applyData);
        include $this->template();
    }

    public function check() {
        global $_W;
        global $_GPC;
        $applyData = $this->applyData();
        extract($applyData);
        if ($apply['status'] != 1) {
            show_json(0, '此申请无法审核!');
        }
        $paycommission = 0;

        $time = time();
        $isAllUncheck = true;
        foreach ($list as $log) {
            $update = array();
            $logid = $log['id'];

            if (isset($_GPC['status'][$logid])) {
                if (intval($_GPC['status'][$logid]) == 2) {
                    $paycommission += $log['num'];
                    $isAllUncheck = false;
                }
                $update = array('checktime' => $time, 'status' => intval($_GPC['status'][$logid]), 'content' => $_GPC['content'][$logid]);
            }
            if (!(empty($update))) {
                pdo_update('ewei_shop_creditmanagement_log', $update, array('id' => $logid));
            }
        }
        if ($isAllUncheck) {
            pdo_update('ewei_shop_creditmanagement_apply', array('status' => -1, 'invalidtime' => $time), array('id' => $id, 'uniacid' => $_W['uniacid']));
        } else {
            pdo_update('ewei_shop_creditmanagement_apply', array('status' => 2, 'checktime' => $time), array('id' => $id, 'uniacid' => $_W['uniacid']));
            $rmoney = $paycommission;
            $dmoney = 0;
            if (!(empty($set_array['charge']))) {
                $m_array = m('member')->getCalculateMoney($paycommission, $set_array);
                if ($m_array['flag']) {
                    $rmoney = $m_array['realmoney'];
                    $dmoney = $m_array['deductionmoney'];
                }
            }
            $mcommission = $paycommission;
            if (!(empty($dmoney))) {
                $mcommission .= ',实际到账金额:' . $rmoney . ',个人所得税金额:' . $dmoney;
            }
            //$this->model->sendMessage($member['openid'], array('creditmanagement' => $mcommission, 'type' => $apply_type[$apply['type']]), TM_COMMISSION_CHECK);
        }
        plog('creditmanagement.apply.check', '佣金审核 ID: ' . $id . ' 申请编号: ' . $apply['applyno'] . ' 总佣金: ' . $totalmoney . ' 审核通过佣金: ' . $paycommission . ' ');
        show_json(1, array('url' => webUrl('creditmanagement/apply', array('status' => $apply['status']))));
    }

    public function cancel() {
        global $_W;
        global $_GPC;

        $applyData = $this->applyData();
        extract($applyData);
        if (($apply['status'] != 2) && ($apply['status'] != -1)) {
            show_json(0, '此申请无法取消!');
        }
        $time = time();
        foreach ($list as $row) {
            $logid = $row['id'];
            pdo_update('ewei_shop_creditmanagement_log', array('checktime' => 0, 'status' => 1), array('id' => $logid));
        }
        pdo_update('ewei_shop_creditmanagement_apply', array('status' => 1, 'checktime' => 0, 'invalidtime' => 0), array('id' => $id, 'uniacid' => $_W['uniacid']));
        plog('creditmanagement.apply.cancel', '重新审核申请 ID: ' . $id . ' 申请编号: ' . $apply['applyno'] . ' ');
        show_json(1, array('url' => webUrl('creditmanagement/apply', array('status' => 1))));
    }

    public function refuse() {
        global $_W;
        global $_GPC;
        $applyData = $this->applyData();
        extract($applyData);
        if ($apply['status'] != 1) {
            show_json(0, '此申请无法拒绝!');
        }
        $time = time();
        foreach ($list as $row) {
            $logid = $row['id'];
            pdo_update('ewei_shop_creditmanagement_log', array('checktime' => 0, 'status' => 0), array('id' => $logid));
        }
        pdo_update('ewei_shop_creditmanagement_apply', array('status' => -2, 'checktime' => 0, 'invalidtime' => 0, 'refusetime' => time()), array('id' => $id, 'uniacid' => $_W['uniacid']));
        plog('creditmanagement.apply.refuse', '驳回申请 ID: ' . $id . ' 申请编号: ' . $apply['applyno'] . ' ');
        show_json(1, array('url' => webUrl('creditmanagement/apply', array('status' => 0))));
    }

    public function pay($params = array(), $mine = array()) {
        global $_W;
        global $_GPC;
        $applyData = $this->applyData();
        extract($applyData);
        $set = $this->getSet();
        if ($apply['status'] != 2) {
            show_json(0, '此申请不能打款!');
        }
        $time = time();
        $pay = round($realmoney, 2);
        if ($apply['type'] < 2) {
            if ($apply['type'] == 1) {
                $pay *= 100;
            }
            $data = m('common')->getSysset('pay');
            if (!(empty($data['paytype']['creditmanagement'])) && ($apply['type'] == 1)) {
                $result = m('finance')->payRedPack($member['openid'], $pay, $apply['applyno'], $apply, $set['texts']['creditmanagement'] . '打款', $data['paytype']);
                pdo_update('ewei_shop_creditmanagement_apply', array('sendmoney' => $result['sendmoney'], 'senddata' => json_encode($result['senddata'])), array('id' => $apply['id']));
                if ($result['sendmoney'] == $realmoney) {
                    $result = true;
                } else {
                    $result = $result['error'];
                }
            } else {
                $result = m('finance')->pay($member['openid'], $apply['type'], $pay, $apply['applyno'], $set['texts']['creditmanagement'] . '打款');
            }
            if (is_error($result)) {
                show_json(0, $result['message']);
            }
        }
        if ($apply['type'] == 2) {
            $sec = m('common')->getSec();
            $sec = iunserializer($sec['sec']);
            if (!(empty($sec['alipay_pay']['open']))) {
                $batch_no_money = $pay * 100;
                $batch_no = 'D' . date('Ymd') . 'CP' . $apply['id'] . 'MONEY' . $batch_no_money;
                $res = m('finance')->AliPay(array('account' => $apply['alipay'], 'name' => $apply['realname'], 'money' => $pay), $batch_no, $sec['alipay_pay'], $set['texts']['creditmanagement'] . '打款');
                if (is_error($res)) {
                    show_json(0, $res['message']);
                }
                show_json(1, array('url' => $res));
            }
        }
        foreach ($list as $row) {
            $logid = $row['id'];
            if ($row['status'] == 2){
                pdo_update('ewei_shop_creditmanagement_log', array('paytime' => $time, 'status' => 3), array('id' => $logid));
            }

        }

        pdo_update('ewei_shop_creditmanagement_apply', array('status' => 3, 'paytime' => $time, 'commission_pay' => $totalpay, 'realmoney' => $realmoney, 'deductionmoney' => $deductionmoney), array('id' => $id, 'uniacid' => $_W['uniacid']));
        $log = array('uniacid' => $_W['uniacid'], 'applyid' => $apply['id'], 'mid' => $member['id'], 'commission' => $totalcommission, 'commission_pay' => $totalpay, 'realmoney' => $realmoney, 'deductionmoney' => $deductionmoney, 'charge' => $charge, 'createtime' => $time, 'type' => $apply['type']);
        pdo_insert('ewei_shop_commission_log', $log);
        $mcommission = $totalpay;
        if (!(empty($deductionmoney))) {
            $mcommission .= ',实际到账金额:' . $realmoney . ',个人所得税金额:' . $deductionmoney;
        }
        //$this->model->sendMessage($member['openid'], array('creditmanagement' => $mcommission, 'type' => $apply_type[$apply['type']]), TM_COMMISSION_PAY);
        //$this->model->upgradeLevelByCommissionOK($member['openid']);

        plog('creditmanagement.apply.pay', '佣金打款 ID: ' . $id . ' 申请编号: ' . $apply['applyno'] . ' 打款方式: ' . $apply_type[$apply['type']] . ' 总佣金: ' . $totalcommission . ' 审核通过佣金: ' . $totalpay . ' 实际到账金额: ' . $realmoney . ' 个人所得税金额: ' . $deductionmoney . ' 个人所得税税率: ' . $charge . '%');
        show_json(1, array('url' => webUrl('creditmanagement/apply', array('status' => $apply['status']))));
    }

    public function payed($params = array(), $mine = array()) {
        global $_W;
        global $_GPC;
        $applyData = $this->applyData();
        extract($applyData);
        $set = $this->getSet();
        if ($apply['status'] != 2) {
            show_json(0, '此申请不能打款!');
        }
        $time = time();
        $pay = $realmoney;
        foreach ($list as $row) {
            $logid = $row['id'];
            if ($row['status'] == 2){
                pdo_update('ewei_shop_creditmanagement_log', array('paytime' => $time, 'status' => 3), array('id' => $logid));
            }

        }

        pdo_update('ewei_shop_creditmanagement_apply', array('status' => 3, 'paytime' => $time, 'commission_pay' => $totalpay, 'realmoney' => $realmoney, 'deductionmoney' => $deductionmoney), array('id' => $id, 'uniacid' => $_W['uniacid']));
        $log = array('uniacid' => $_W['uniacid'], 'applyid' => $apply['id'], 'mid' => $member['id'], 'commission' => $totalcommission, 'commission_pay' => $totalpay, 'realmoney' => $realmoney, 'deductionmoney' => $deductionmoney, 'charge' => $charge, 'createtime' => $time, 'type' => $apply['type']);
        pdo_insert('ewei_shop_commission_log', $log);
        $mcommission = $totalpay;
        if (!(empty($deductionmoney))) {
            $mcommission .= ',实际到账金额:' . $realmoney . ',个人所得税金额:' . $deductionmoney;
        }

        plog('creditmanagement.apply.pay', '佣金打款 ID: ' . $id . ' 申请编号: ' . $apply['applyno'] . ' 打款方式: 已经手动打款 总佣金: ' . $totalcommission . ' 审核通过佣金: ' . $totalpay . ' 实际到账金额: ' . $realmoney . ' 个人所得税金额: ' . $deductionmoney . ' 个人所得税税率: ' . $charge . '%');
        show_json(1, array('url' => webUrl('creditmanagement/apply', array('status' => $apply['status']))));
    }

}

?>
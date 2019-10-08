<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);

class Agent_EweiShopV2Page extends PluginWebPage {

    public function main() {
        global $_W, $_GPC;
        $no_left = false;
        $uniacid = $_W['uniacid'];

        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $params = array(':uniacid' => $uniacid);
        $condition = '';

        $searchfield = strtolower(trim($_GPC['searchfield']));
        $keyword = trim($_GPC['keyword']);
        if (!empty($keyword)) {
            $condition .= ' AND (dm.nickname like :keyword OR dm.realname like :keyword OR dm.mobile like :keyword)';
            $params[':keyword'] = "%{$keyword}%";

        }
        $sql = 'SELECT dm.*,a.point,p.nickname as parentname,p.avatar as parentavatar FROM ' . tablename('ewei_shop_member') . ' dm ' .
            " LEFT JOIN " . tablename('ewei_shop_member') . " p on p.id = dm.agentid " .
            ' LEFT JOIN ' . tablename('ewei_shop_jflc_agent') . ' a ON a.mid = dm.id' .
            ' WHERE dm.uniacid=:uniacid ' . $condition . ' ORDER BY dm.createtime DESC ';
        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }

        $list = pdo_fetchall($sql, $params);
        foreach ($list as &$row) {
            $row['credit1'] = m('member')->getCredit($row['openid'], 'credit1');
            $row['credit2'] = m('member')->getCredit($row['openid'], 'credit2');
            //$row['credit3'] = m('member')->getCredit($row['openid'], 'credit3');
            $row['point'] = round($row['point'], 2);

            $row['recent_credit'] = floatval(pdo_fetchcolumn('SELECT num FROM ' . tablename('ewei_shop_creditmanagement_log') .
                ' WHERE uniacid=:uniacid AND mid=:mid ORDER BY createtime DESC LIMIT 1', array(
                ':uniacid' => $_W['uniacid'],
                ':mid'     => $row['id']
            )));

            $row['total_credit'] = floatval(pdo_fetchcolumn('SELECT SUM(num) FROM ' . tablename('ewei_shop_creditmanagement_log') .
                ' WHERE uniacid=:uniacid AND mid=:mid ORDER BY createtime DESC LIMIT 1', array(
                ':uniacid' => $_W['uniacid'],
                ':mid'     => $row['id']
            )));

        }
        unset($row);

        $total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename('ewei_shop_member') . ' dm ' .
            ' LEFT JOIN ' . tablename('ewei_shop_jflc_agent') . ' a ON a.mid = dm.id' .
            ' WHERE dm.uniacid=:uniacid ' . $condition . ' LIMIT 1 ', $params
        );

        $pager = pagination($total, $pindex, $psize);
        include $this->template();

    }


}


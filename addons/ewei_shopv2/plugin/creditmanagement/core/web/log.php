<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);

class Log_EweiShopV2Page extends PluginWebPage {

    public function main() {
        global $_GPC, $_W;
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $params = array(':uniacid' => $_W['uniacid']);
        $condition = '';
        $searchfield = strtolower(trim($_GPC['searchfield']));
        $keyword = trim($_GPC['keyword']);

        if (!empty($keyword)) {
            $condition .= ' and ( dm.realname like :keyword or dm.nickname like :keyword or dm.mobile like :keyword)';
            $params[':keyword'] = "%{$keyword}%";
        }

        if (empty($starttime) || empty($endtime)) {
            $starttime = strtotime('-1 month');
            $endtime = time();
        }
        if (!empty($_GPC['time']['start']) && !empty($_GPC['time']['end'])) {
            $starttime = strtotime($_GPC['time']['start']);
            $endtime = strtotime($_GPC['time']['end']);
            $condition .= " AND dm.agenttime >= :starttime AND dm.agenttime <= :endtime ";
            $params[':starttime'] = $starttime;
            $params[':endtime'] = $endtime;
        }

        if ($_GPC['status'] != '') {
            //$condition.=' and dm.status=' . intval($_GPC['status']);
            switch (intval($_GPC['status'])) {
                case 0:
                    $condition .= 'AND dm.authtime>0 AND dm.activetime=0 AND dm.outtime=0 ';
                    break;
                case 1:
                    $condition .= 'AND dm.activetime>0 AND dm.outtime=0 ';
                    break;
                case 2:
                    $condition .= 'AND dm.outtime>0 ';
                    break;
            }
        }

        $sql = "SELECT l.*,dm.nickname,dm.avatar,dm.realname,dm.mobile FROM " . tablename('ewei_shop_creditmanagement_log') . ' l ' .
            ' LEFT JOIN ' . tablename('ewei_shop_member') . ' dm on dm.id=l.mid ' .
            ' WHERE l.uniacid=:uniacid ' . $condition . ' ORDER BY l.id DESC ';

        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }

        $list = pdo_fetchall($sql, $params);

        $total = pdo_fetchcolumn("select count(1) from" . tablename('ewei_shop_creditmanagement_log') . " l  "
            . " left join " . tablename('ewei_shop_member') . " dm on dm.id=l.mid "
            . " where l.uniacid =:uniacid ".$condition, $params);
        $pager = pagination($total, $pindex, $psize);
        include $this->template();
    }


}
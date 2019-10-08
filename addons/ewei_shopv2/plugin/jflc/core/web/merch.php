<?php
/**
 * Created by PhpStorm.
 * User: gwind
 * Date: 2017/10/10
 * Time: 上午 1:13
 */
if (!defined('IN_IA')) {
    exit('Access Denied');
}
//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);
class Merch_EweiShopV2Page extends PluginWebPage {

    public function log() {
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
            if ($searchfield == 'member') {
                $condition .= ' AND (dm.nickname like :keyword OR dm.realname like :keyword OR dm.mobile like :keyword)';
                $params[':keyword'] = "%{$keyword}%";
            } elseif ($searchfield == 'merch') {
                $condition .= ' AND merch.name LIKE :keyword ';
                $params[':keyword'] = "%{$keyword}%";
            } elseif ($searchfield == 'order') {
                $condition .= ' AND l.ordersn LIKE :keyword ';
                $params[':keyword'] = "%{$keyword}%";
            }
        }

        if (!empty($_GPC['time']) && !empty($_GPC['time']['start']) && !empty($_GPC['time']['end'])) {
            $start = strtotime($_GPC['time']['start']);
            $end = strtotime($_GPC['time']['end']);
            $condition .= ' AND l.createtime>=' . $start . ' AND l.createtime<=' . $end;
        }


        $sql = 'SELECT l.*,dm.avatar,dm.nickname,dm.realname,dm.mobile,merch.merchname FROM ' . tablename('ewei_shop_jflc_merch_order_log') . ' l ' .
            ' LEFT JOIN ' . tablename('ewei_shop_member') . ' dm ON dm.openid=l.openid ' .
            ' LEFT JOIN ' . tablename('ewei_shop_merch_user') . ' merch ON merch.id=l.merchid ' .
            ' WHERE l.uniacid=:uniacid ORDER BY l.id desc';

        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }

        $list = pdo_fetchall($sql, $params);

        $total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename('ewei_shop_jflc_merch_order_log') . ' l ' .
            ' LEFT JOIN ' . tablename('ewei_shop_member') . ' dm ON dm.openid=l.openid ' .
            ' LEFT JOIN ' . tablename('ewei_shop_merch_user') . ' merch ON merch.id=l.merchid ' .
            ' WHERE l.uniacid=:uniacid ' . $condition . ' LIMIT 1 ', $params
        );
        $pager = pagination($total, $pindex, $psize);

        include $this->template();

    }

    public function agent() {
        global $_W, $_GPC;
        $no_left = false;
        $uniacid = $_W['uniacid'];

        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $params = array(':uniacid' => $uniacid);
        $condition = '';

        $sql = 'SELECT distinct u.commissionopenid,dm.* FROM ' . tablename('ewei_shop_merch_user') . ' u ' .
            ' LEFT JOIN ' . tablename('ewei_shop_member') . ' dm ON dm.openid=u.commissionopenid ' .
            ' WHERE u.uniacid=:uniacid AND u.commissionopenid<>"" AND dm.id IS NOT NULL ORDER BY dm.createtime DESC ';

        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }

        $list = pdo_fetchall($sql, $params);
        $total = pdo_fetchcolumn('SELECT COUNT(distinct u.commissionopenid) FROM ' . tablename('ewei_shop_merch_user') . ' u ' .
            ' LEFT JOIN ' . tablename('ewei_shop_member') . ' dm ON dm.openid=u.commissionopenid ' .
            ' WHERE u.uniacid=:uniacid AND u.commissionopenid<>"" ' . $condition . ' LIMIT 1 ', $params
        );
        $pager = pagination($total, $pindex, $psize);

        foreach ($list as &$row) {
            $row['num'] = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename('ewei_shop_merch_user') . ' WHERE uniacid=:uniacid AND commissionopenid=:openid LIMIT 1', array(
                ':uniacid' => $_W['uniacid'],
                ':openid'  => $row['openid']
            ));

            $merch_list = pdo_fetchall('SELECT id FROM ' . tablename('ewei_shop_merch_user') . ' WHERE uniacid=:uniacid AND commissionopenid=:openid', array(
                ':uniacid' => $_W['uniacid'],
                ':openid'  => $row['openid']
            ));

            $merch_array = array();
            foreach ($merch_list as $_row) {
                $merch_array[] = $_row['id'];
            }

            $merch_ids = implode(',', $merch_array);

            if(!empty($merch_ids)) {
                $row['total_credit'] = floatval(pdo_fetchcolumn('SELECT SUM(commission) FROM ' . tablename('ewei_shop_jflc_merch_order_log') . ' WHERE uniacid=:uniacid AND merchid IN (' . $merch_ids . ') LIMIT 1', array(
                    ':uniacid' => $_W['uniacid']
                )));

                $orders = pdo_fetch('SELECT SUM(price) t_price,SUM(dispatchprice) t_dispatchprice FROM ' . tablename('ewei_shop_order') . ' WHERE uniacid=:uniacid AND merchid IN(' . $merch_ids . ') AND ismerch>0 AND price>=dispatchprice AND status=3 LIMIT 1', array(
                    ':uniacid' => $_W['uniacid']
                ));
                $row['all_credit'] = $orders['t_price'] - $orders['t_dispatchprice'];
            }


        }
        unset($row);
        include $this->template();
    }

    public function main() {
        global $_W, $_GPC;
        $openid = pdo_fetchcolumn('SELECT openid FROM ' . tablename('ewei_shop_member') . ' WHERE uniacid=:uniacid AND id=:id LIMIT 1', array(
            ':uniacid' => $_W['uniacid'],
            ':id'      => $_GPC['id']
        ));

        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $params = array(':uniacid' => $_W['uniacid']);
        $condition = ' AND u.commissionopenid=:openid ';

        $keyword = trim($_GPC['keyword']);
        if (!empty($keyword)) {
            $condition .= ' and ( u.merchname like :keyword or u.realname like :keyword or u.mobile like :keyword)';
            $params[':keyword'] = "%{$keyword}%";
        }
        $params[':openid'] = $openid;

        $sql = "select  u.*,g.groupname  from " . tablename('ewei_shop_merch_user') . "  u "
            . " left join  " . tablename('ewei_shop_merch_group') . " g on u.groupid = g.id "
            . " where u.uniacid=:uniacid {$condition} ORDER BY u.id desc";
        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }
        $list = pdo_fetchall($sql, $params);
        $total = pdo_fetchcolumn("select count(*) from" . tablename('ewei_shop_merch_user') . " u  "
            . " left join  " . tablename('ewei_shop_merch_group') . " g on u.groupid = g.id "
            . " where u.uniacid = :uniacid {$condition}", $params);

        $pager = pagination($total, $pindex, $psize);

        include $this->template();
    }

    /*
    public function delete(){
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        if (empty($id)) {
            $id = is_array($_GPC['ids']) ? implode(',', $_GPC['ids']) : 0;
        }
        $items = pdo_fetchall("SELECT id,mid FROM " . tablename('ewei_shop_gy_agent') . " WHERE id in( $id ) AND uniacid=" . $_W['uniacid']);
        foreach ($items as $item) {
            pdo_delete('ewei_shop_member', array('id' => $item['mid']));
            pdo_delete('ewei_shop_gy_agent', array('id' => $item['id']));
        }
        show_json(1, array('url' => referer()));
    }*/
}
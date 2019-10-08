<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);

class Index_EweiShopV2Page extends PluginWebPage {

    public function main() {
        global $_W, $_GPC;
        $no_left = false;
        $uniacid = $_W['uniacid'];
        header('location: ' . webUrl('jflc/agent'));
        exit;
    }

    public function test() {
        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);
        //p('jflc')->test();
        //$this->message('刷新数据完成');
        p('jflc')->calc_commisson(273431);
    }

    public function log1() {
        global $_W, $_GPC;

        $no_left = false;
        $uniacid = $_W['uniacid'];

        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $params = array(':uniacid' => $uniacid);
        $condition = '';

        $keyword = trim($_GPC['keyword']);
        if (!empty($keyword)) {
            $condition .= ' AND (dm.nickname like :keyword OR dm.realname like :keyword OR dm.mobile like :keyword)';
            $params[':keyword'] = "%{$keyword}%";
        }

        if (!empty($_GPC['time']) && !empty($_GPC['time']['start']) && !empty($_GPC['time']['end'])) {
            $start = strtotime($_GPC['time']['start']);
            $end = strtotime($_GPC['time']['end']);
            $condition .= ' AND l.createtime>=' . $start . ' AND l.createtime<=' . $end;
        }
        $sql = 'SELECT l.*,dm.avatar,dm.nickname,dm.realname,dm.mobile FROM ' . tablename('ewei_shop_jflc_point_log') . ' l ' .
            ' LEFT JOIN ' . tablename('ewei_shop_member') . ' dm ON dm.openid=l.openid ' .
            ' WHERE l.uniacid=:uniacid '.$condition.' ORDER BY l.id desc';
        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }

        $list = pdo_fetchall($sql, $params);
        $total = pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename('ewei_shop_jflc_point_log') . ' l ' .
            ' LEFT JOIN ' . tablename('ewei_shop_member') . ' dm ON dm.openid=l.openid ' .
            ' WHERE l.uniacid=:uniacid ' . $condition . ' LIMIT 1 ', $params
        );
        if ($_GPC['export'] == 1) {


            $list[] = array('data' => '日志总计', 'count' => $total);
            foreach ($list as &$row) {
                $row['createtime'] = date('Y-m-d H:i', $row['createtime']);
            }
            unset($row);

            m('excel')->export($list, array(
                "title" => "理财日志-" . date('Y-m-d-H-i', time()),
                "columns" => array(
                    array('title' => 'openid', 'field' => 'openid', 'width' => 24),
                    array('title' => '会员昵称', 'field' => 'nickname', 'width' => 12),
                    array('title' => '会员姓名', 'field' => 'realname', 'width' => 12),
                    array('title' => '手机号码', 'field' => 'mobile', 'width' => 12),
                    array('title' => '积分变化', 'field' => 'point', 'width' => 12),
                    array('title' => '备注', 'field' => 'remark', 'width' => 40),
                    array('title' => '成交时间', 'field' => 'createtime', 'width' => 24)
                )
            ));


        }


        $pager = pagination($total, $pindex, $psize);

        include $this->template();
    }
}


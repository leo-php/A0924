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
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $condition = " dm.uniacid=:uniacid";
        $params = array(':uniacid' => $uniacid);

        if (!empty($_GPC['mid'])) {
            $condition .= ' and dm.id=:mid';
            $params[':mid'] = intval($_GPC['mid']);
        }
        if (!empty($_GPC['realname'])) {
            $_GPC['realname'] = trim($_GPC['realname']);
            $condition .= ' and ( dm.realname like :realname or dm.nickname like :realname or dm.mobile like :realname or dm.id like :realname)';
            $params[':realname'] = "%{$_GPC['realname']}%";
        }

        if ($_GPC['status'] != '') {
            switch (intval($_GPC['status'])) {
                case 0:
                    $condition .= ' AND mu.id IS NULL ';
                    break;
                case 1:
                    $condition .= ' AND mu.status=1 AND mu.level=0 ';
                    break;
                case 2:
                    $condition .= ' AND mu.status=1 AND mu.level=1 ';
                    break;
                case 3:
                    $condition .= ' AND mu.status=1 AND mu.level=2 ';
                    break;
            }
        }

        $sql = "select dm.*,dm.nickname,dm.avatar,p.nickname as parentname,p.avatar as parentavatar,f.follow as followed, f.unfollowtime,mu.id as mu_id,mu.status as merchant,mu.level as mlv from " . tablename('ewei_shop_xfqf_member') . ' m ' . " LEFT JOIN " . tablename('ewei_shop_member') . " dm ON dm.id=m.id" . " LEFT JOIN " . tablename('ewei_shop_member') . " p on p.id = dm.agentid " //. " left join " . tablename('ewei_shop_jfqf_member') . " j on j.id = dm.id"
            . " LEFT JOIN " . tablename('ewei_shop_merch_user') . ' mu ON mu.openid = dm.openid ' . " left join " . tablename('mc_mapping_fans') . "f on f.openid=dm.openid and f.uniacid={$_W['uniacid']}" . " where m.status=1 AND dm.isagent =1 AND {$condition} ORDER BY m.createtime DESC";
        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }
        $list = pdo_fetchall($sql, $params);

        $total = pdo_fetchcolumn("select count(1) from" . tablename('ewei_shop_xfqf_member') . ' m ' . ' LEFT JOIN ' . tablename('ewei_shop_member') . " dm ON dm.id = m.id " . " LEFT JOIN " . tablename('ewei_shop_member') . " p on p.id = dm.agentid " . " LEFT JOIN " . tablename('ewei_shop_merch_user') . ' mu ON mu.openid = dm.openid ' . " LEFT JOIN " . tablename('mc_mapping_fans') . "f on f.openid=dm.openid" . " WHERE m.status=1 AND dm.isagent =1 AND {$condition}", $params);

        $pager = pagination($total, $pindex, $psize);
        foreach ($list as &$row) {
            $row['point_pay'] = pdo_fetchcolumn('SELECT SUM(point) FROM ' . tablename('ewei_shop_xfqf_history') . ' WHERE uniacid=:uniacid AND pid=:pid LIMIT 1', array(':uniacid' => $_W['uniacid'], ':pid' => $row['id']));
            $row['point_total'] = pdo_fetchcolumn('SELECT SUM(point) FROM ' . tablename('ewei_shop_xfqf_log') . ' WHERE uniacid=:uniacid AND mid=:mid LIMIT 1', array(':uniacid' => $_W['uniacid'], ':mid' => $row['id']));
        }
        unset($row);
        include $this->template();
    }

    public function log() {
        global $_W, $_GPC;
        $no_left = false;
        $uniacid = $_W['uniacid'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $condition = " l.uniacid=:uniacid";
        $params = array(':uniacid' => $uniacid);

        if (!empty($_GPC['keyword'])) {
            $_GPC['keyword'] = trim($_GPC['keyword']);
            $condition .= ' AND (m.realname LIKE "%' . $_GPC['keyword'] . '%" OR m.nickname LIKE "%' . $_GPC['keyword'] . '%" OR m.mobile LIKE "%' . $_GPC['keyword'] . '%")';
        }

        if (!empty($_GPC['time']) && !empty($_GPC['time']['start']) && !empty($_GPC['time']['end'])) {
            $start = strtotime($_GPC['time']['start']);
            $end = strtotime($_GPC['time']['end']);
            $condition .= ' AND l.applytime>=' . $start . ' AND l.applytime<=' . $end;
        }

        $sql = 'SELECT l.*,m.avatar,m.nickname FROM ' . tablename('ewei_shop_xfqf_log') . ' l ' . ' LEFT JOIN ' . tablename('ewei_shop_member') . ' m ON m.id=l.mid ' . ' WHERE ' . $condition . ' ORDER BY id DESC ';
        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }
        $list = pdo_fetchall($sql, $params);
        $total = pdo_fetchcolumn('SELECT COUNT(l.id) FROM ' . tablename('ewei_shop_xfqf_log') . ' l ' . ' LEFT JOIN ' . tablename('ewei_shop_member') . ' m ON m.id=l.mid ' . ' WHERE ' . $condition, $params);
        $pager = pagination($total, $pindex, $psize);
        include $this->template();
    }

    public function history() {
        global $_W, $_GPC;
        $no_left = false;
        $uniacid = $_W['uniacid'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $condition = " l.uniacid=:uniacid";
        $params = array(':uniacid' => $uniacid);

        if (!empty($_GPC['keyword'])) {
            $_GPC['keyword'] = trim($_GPC['keyword']);
            $condition .= ' AND (m.realname LIKE "%' . $_GPC['keyword'] . '%" OR m.nickname LIKE "%' . $_GPC['keyword'] . '%" OR m.mobile LIKE "%' . $_GPC['keyword'] . '%")';
        }

        if (!empty($_GPC['time']) && !empty($_GPC['time']['start']) && !empty($_GPC['time']['end'])) {
            $start = strtotime($_GPC['time']['start']);
            $end = strtotime($_GPC['time']['end']);
            $condition .= ' AND l.applytime>=' . $start . ' AND l.applytime<=' . $end;
        }

        $sql = 'SELECT l.*,m.avatar,m.nickname FROM ' . tablename('ewei_shop_xfqf_history') . ' l ' . ' LEFT JOIN ' . tablename('ewei_shop_member') . ' m ON m.id=l.mid ' . ' WHERE ' . $condition . ' ORDER BY id DESC ';
        if (empty($_GPC['export'])) {
            $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
        }
        $list = pdo_fetchall($sql, $params);
        $total = pdo_fetchcolumn('SELECT COUNT(l.id) FROM ' . tablename('ewei_shop_xfqf_history') . ' l ' . ' LEFT JOIN ' . tablename('ewei_shop_member') . ' m ON m.id=l.mid ' . ' WHERE ' . $condition, $params);

        include $this->template();
    }

    public function test() {
        // p('xfqf')->task_run();
        //        $num = 2;
        //        $i = 1;
        //        for($j=0;$j<100;$j++){
        //            echo $i,'<br/>';
        //            if($i++ >= $num){
        //                break;
        //            }
        //        }
        // message('数据刷新完成', webUrl('xfqf'));
        /**
         * $merch = m('member')->getInfo('oNaxJ1uhv8dwPzZC8agX94hV2pUk');
         * $money = 63;
         *
         * $parent = p('xfqf')->get_parent($merch); //m('member')->getMember($member['agentid']);
         * if ($parent) {
         * $order = array('id' => 0, 'openid' => $parent['openid'], 'goodsprice' => $money);
         * $goodsprice = floatval($order['goodsprice']);
         * if ($goodsprice > 0) {
         * $set = p('xfqf')->getSet();
         * $point = $goodsprice * 100 * $set['rebusine'] * 0.01;
         *
         * p('xfqf')->save_point($merch, $parent, $order, $point, 2);
         * }
         * }
         * */
        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime('+1 day',$start_time);
        var_dump(date('Y-m-d H:i:s',$start_time));
        var_dump(date('Y-m-d H:i:s',$end_time));
    }

    public function test1() {
        global $_W;
        $uniacid = $_W['uniacid'];
        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);
        //p('xfqf')->task_run();
        //message('数据刷新完成', webUrl('xfqf'));

        //$member = m('member')->getMember('ox6cWv6WAzBOBDigLYb5ByaJN8Us');

        /*
        var_dump(p('xfqf')->run(array(
            'id'         => 0,
            'goodsprice' => 999,
            'openid'     => $member['openid']
        )));
*/
        //var_dump(p('xfqf')->get_parent($member));
        //$order = pdo_fetch('SELECT * FROM '.tablename('ewei_shop_order').' WHERE id=5');

        //p('xfqf')->area_div($order);
        $set = p('xfqf')->getSet();
        $percentage = $set['percentage'] * 0.01;

        $members = pdo_fetchall('SELECT m.id as mid, m.openid,mc.credit1,mc.credit2 FROM ' . tablename('ewei_shop_member') . ' m ' . ' LEFT JOIN ' . tablename('mc_members') . ' mc ON mc.uid=m.uid AND mc.uniacid=m.uniacid ' . ' WHERE m.uniacid=:uniacid  AND m.isagent=1 AND m.status=1 AND mc.credit1>0 ', array(':uniacid' => $uniacid));
        foreach ($members as $member) {
            $point = $member['credit1'] * $percentage;
            var_dump($point * 0.01);
            $credit2 = round($point * 0.01, 2);
            var_dump($credit2);
        }
    }
}
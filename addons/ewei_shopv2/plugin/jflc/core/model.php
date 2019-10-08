<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}


class JflcModel extends PluginModel {

    public function getSet($uniacid = 0) {
        $set = parent::getSet($uniacid);
        //$data = m('common')->getPluginset('mt');
        return $set;
    }

    public function status() {
        $set = $this->getSet();
        return empty($set['status']) ? false : true;
    }

    /**
     * 测试用刷新数据
     */
    public function test() {
        $this->merch_commission();
        $this->point_commisson();
    }

    /**
     * @param $orderid
     * 计算订单招商员分红到日志。
     */
    public function calc_commisson($orderid) {
        global $_W;

        $order = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_order') . ' WHERE uniacid=:uniacid AND id=:orderid AND status=3 AND merchid>0 AND ismerch=1 LIMIT 1', array(
            ':uniacid' => $_W['uniacid'],
            ':orderid' => $orderid
        ));

        if (empty($order)) return;
        $log = pdo_fetch('SELECT id FROM ' . tablename('ewei_shop_jflc_merch_order_log') . ' WHERE uniacid=:uniacid AND orderid=:orderid LIMIT 1', array(
            ':uniacid' => $_W['uniacid'],
            ':orderid' => $order['id']
        ));

        if (!empty($log)) return;

        $set = $this->getSet();

        $merchday = max(intval($set['merchday']), 1);
        if (empty($order)) return;

        $merch = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_merch_user') . ' WHERE uniacid=:uniacid AND status=1 AND commissionopenid<>"" AND id=:merchid LIMIT 1', array(
            ':uniacid' => $_W['uniacid'],
            ':merchid' => intval($order['merchid'])
        ));

        $openid = $merch['commissionopenid'];
        if (empty($openid)) return;

        $price = max(0, $order['price'] - $order['dispatchprice']);
        $commission = round($price * (floatval($merch['commissionrate']) / 100), 2);

        $closetime = strtotime(date('Y-m-d 00:00:00', strtotime('+' . $merchday . ' day', TIMESTAMP)));
        pdo_insert('ewei_shop_jflc_merch_order_log', array(
            'orderid'    => $order['id'],
            'uniacid'    => $_W['uniacid'],
            'merchid'    => $order['merchid'],
            'openid'     => $openid,
            'ordersn'    => $order['ordersn'],
            'price'      => $price,
            'rate'       => $merch['commissionrate'],
            'commission' => $commission,
            'createtime' => TIMESTAMP,
            'closetime'  => $closetime
        ));
    }

    /**
     * 招商员分红
     */
    public function merch_commission() {
        global $_W;

        $merch_list = pdo_fetchall('SELECT * FROM ' . tablename('ewei_shop_merch_user') . ' WHERE uniacid=:uniacid AND status=1 AND commissionopenid<>"" ', array(
            ':uniacid' => $_W['uniacid']
        ));

        if ($merch_list) {
            foreach ($merch_list as $merch) {

                $openid = $merch['commissionopenid'];
                $list = pdo_fetchall('SELECT id,commission FROM ' . tablename('ewei_shop_jflc_merch_order_log') . ' WHERE uniacid=:uniacid AND openid=:openid AND status=0 AND applytime=0 AND closetime<=:closetime', array(
                    ':uniacid'   => $_W['uniacid'],
                    ':openid'    => $openid,
                    ':closetime' => TIMESTAMP
                ));

                $total_commission = 0;
                foreach ($list as $row) {
                    $total_commission = $row['commission'];
                    pdo_update('ewei_shop_jflc_merch_order_log', array(
                        'applytime' => TIMESTAMP,
                        'status'    => 1
                    ), array(
                        'id' => $row['id']
                    ));
                }

                if ($total_commission > 0) {
                    m('member')->setCredit($openid, 'credit2', $total_commission, array(
                        $_W['uid'],
                        '商户分红'
                    ));
                    $member = m('member')->getMember($openid);
                    $datas = array(
                        array(
                            "name"  => "商城名称",
                            "value" => $_W['shopset']['shop']['name']
                        ),
                        array(
                            "name"  => "粉丝昵称",
                            "value" => $member['nickname']
                        ),
                        array(
                            'name'  => '返点方式',
                            'value' => '商户分红'
                        ),
                        array(
                            'name'  => '商户分红',
                            'value' => $total_commission
                        )
                    );
                    $url = mobileUrl('', array(), true);
                    $remark = "\n" . $_W['shopset']['shop']['name'] . "感谢您的支持，如有疑问请联系在线客服或<a href='{$url}'>点击进入查看详情</a>";
                    $message = array(
                        'first'    => array(
                            'value' => "恭喜您获取商户分红。\n",
                            "color" => "#ff0000"
                        ),
                        'keyword1' => array(
                            'title' => '分红',
                            'value' => $total_commission,
                            "color" => "#000000"
                        ),
                        //'keyword2' => array('title' => '积分', 'value' =>   $point, "color" => "#000000"),
                        //'keyword3' => array('title' => '积分', 'value' =>   123, "color" => "#000000"),//$log_info['realmoeny']
                        'keyword4' => array(
                            'title' => '余额来源',
                            'value' => '商户分红',
                            "color" => "#0a4b9c"
                        ),
                        'remark'   => array(
                            'value' => $remark,
                            "color" => "#000000"
                        )
                    );
                    $time = date('Y-m-d H:i:s');
                    $text = "亲爱的[粉丝昵称]，您的余额发生变动，具体如下：\n\n增加余额：{$total_commission}\n充值时间：{$time}\n充值方式：商户分红\n" . $remark;
                    //$text = "积分全返详细信息";

                    m('notice')->sendNotice(array(
                        "openid"     => $member['openid'],
                        'tag'        => 'withdraw_ok',
                        'default'    => $message,
                        'cusdefault' => $text,
                        'url'        => $url,
                        'datas'      => $datas
                    ));

                }
            }
        }

    }

    public function _merch_commission() {
        global $_W;

        $start_time = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
        $end_time = strtotime(date('Y-m-d 00:00:00'));

        $merch_list = pdo_fetchall('SELECT * FROM ' . tablename('ewei_shop_merch_user') . ' WHERE uniacid=:uniacid AND status=1 AND commissionopenid<>"" ', array(
            ':uniacid' => $_W['uniacid']
        ));

        if ($merch_list) {
            foreach ($merch_list as $merch) {

                $list = pdo_fetchall('SELECT o.id,o.ordersn,o.price,o.dispatchprice,o.merchid FROM ' . tablename('ewei_shop_order') . ' o ' .
                    ' LEFT JOIN ' . tablename('ewei_shop_jflc_merch_order_log') . ' l ON l.orderid=o.id ' .
                    ' WHERE o.uniacid=:uniacid AND o.status=3 AND o.ismerch=1 AND o.finishtime>=:starttime AND o.finishtime<:endtime AND o.merchid=:merchid AND l.orderid IS NULL', array(
                    ':uniacid'   => $_W['uniacid'],
                    ':starttime' => $start_time,
                    ':endtime'   => $end_time,
                    ':merchid'   => $merch['id']
                ));
                /*
                $list = pdo_fetchall('SELECT o.id,o.ordersn,o.price,o.dispatchprice,o.merchid FROM ' . tablename('ewei_shop_order') . ' o ' .
                    ' LEFT JOIN ' . tablename('ewei_shop_jflc_merch_order_log') . ' l ON l.orderid=o.id ' .
                    ' WHERE o.uniacid=:uniacid AND o.status=3 AND l.orderid IS NULL', array(
                    ':uniacid'   => $_W['uniacid']

                ));*/

                $openid = $merch['commissionopenid'];
                $total_commission = 0.00;
                foreach ($list as $row) {
                    $price = max(0, $row['price'] - $row['dispatchprice']);
                    $commission = round($price * (floatval($merch['commissionrate']) / 100), 2);
                    $total_commission += $commission;

                    pdo_insert('ewei_shop_jflc_merch_order_log', array(
                        'orderid'    => $row['id'],
                        'uniacid'    => $_W['uniacid'],
                        'merchid'    => $row['merchid'],
                        'openid'     => $openid,
                        'ordersn'    => $row['ordersn'],
                        'price'      => $price,
                        'rate'       => $merch['commissionrate'],
                        'commission' => $commission,
                        'createtime' => TIMESTAMP
                    ));
                }
                if ($total_commission > 0) {
                    m('member')->setCredit($openid, 'credit2', $total_commission, array(
                        $_W['uid'],
                        '商户分红'
                    ));
                    $member = m('member')->getMember($openid);
                    $datas = array(
                        array(
                            "name"  => "商城名称",
                            "value" => $_W['shopset']['shop']['name']
                        ),
                        array(
                            "name"  => "粉丝昵称",
                            "value" => $member['nickname']
                        ),
                        array(
                            'name'  => '返点方式',
                            'value' => '商户分红'
                        ),
                        array(
                            'name'  => '商户分红',
                            'value' => $total_commission
                        )
                    );
                    $url = mobileUrl('', array(), true);
                    $remark = "\n" . $_W['shopset']['shop']['name'] . "感谢您的支持，如有疑问请联系在线客服或<a href='{$url}'>点击进入查看详情</a>";
                    $message = array(
                        'first'    => array(
                            'value' => "恭喜您获取商户分红。\n",
                            "color" => "#ff0000"
                        ),
                        'keyword1' => array(
                            'title' => '分红',
                            'value' => $total_commission,
                            "color" => "#000000"
                        ),
                        //'keyword2' => array('title' => '积分', 'value' =>   $point, "color" => "#000000"),
                        //'keyword3' => array('title' => '积分', 'value' =>   123, "color" => "#000000"),//$log_info['realmoeny']
                        'keyword4' => array(
                            'title' => '余额来源',
                            'value' => '商户分红',
                            "color" => "#0a4b9c"
                        ),
                        'remark'   => array(
                            'value' => $remark,
                            "color" => "#000000"
                        )
                    );
                    $time = date('Y-m-d H:i:s');
                    $text = "亲爱的[粉丝昵称]，您的余额发生变动，具体如下：\n\n增加余额：{$total_commission}\n充值时间：{$time}\n充值方式：商户分红\n" . $remark;
                    //$text = "积分全返详细信息";

                    m('notice')->sendNotice(array(
                        "openid"     => $member['openid'],
                        'tag'        => 'withdraw_ok',
                        'default'    => $message,
                        'cusdefault' => $text,
                        'url'        => $url,
                        'datas'      => $datas
                    ));

                }
            }
        }
    }

    /**
     * 投资理财
     */
    public function point_commisson() {
        global $_W;

        $list = pdo_fetchall('SELECT * FROM ' . tablename('ewei_shop_jflc_agent') . ' WHERE uniacid=:uniacid', array(
            ':uniacid' => $_W['uniacid']
        ));

        $proportion = $this->get_proportion();
        $start_time = strtotime(date('Y-m-d 00:00:00',strtotime('-1 day')));
        foreach ($list as $row) {
            $point = floatval($row['point']);

            $h_point = pdo_fetchcolumn('SELECT point FROM '.tablename('ewei_shop_jflc_point_log').' WHERE type=1 AND status=0 AND mid=:mid AND createtime<:time AND uniacid=:uniacid ',array(
               ':uniacid' => $_W['uniacid'],
               ':mid' => $row['mid'],
               ':time' => $start_time
            ));

            if(!empty($h_point)){
                $point += floatval($h_point);
                pdo_run('UPDATE '.tablename('ewei_shop_jflc_point_log').' SET status=1 WHERE type=1 AND status=0 AND mid='.$row['mid'].' AND uniacid='.$_W['uniacid'].' AND createtime<'.$start_time);
            }
            
            $money = round($point * $proportion, 2);

            if ($money > 0) {

                $member = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_member') . ' WHERE uniacid=:uniacid AND id=:id LIMIT 1', array(
                    ':uniacid' => $_W['uniacid'],
                    ':id'      => $row['mid']
                ));

                if (empty($member)) {
                    show_message('无效的会员');
                    exit;
                }

                pdo_update('ewei_shop_jflc_agent', array(
                    'point'      => $row['point'] - $money,
                    'updatetime' => TIMESTAMP
                ), array(
                    'mid' => $row['mid']
                ));
                pdo_insert('ewei_shop_creditmanagement_log', array(
                    'uniacid'    => $_W['uniacid'],
                    'mid'        => $row['mid'],
                    'openid'     => $member['openid'],
                    'num'        => $money,
                    'date'       => date('Y-m-d', TIMESTAMP),
                    'createtime' => TIMESTAMP,
                    'remark'     => '积分理财收益'
                ));
                pdo_insert('ewei_shop_jflc_point_log', array(
                    'uniacid'    => $_W['uniacid'],
                    'createtime' => TIMESTAMP,
                    'point'      => -$money,
                    'mid'        => $member['id'],
                    'openid'     => $member['openid'],
                    'remark'     => '积分投资收益',
                    'type'       => 0
                ));

                $datas = array(
                    array(
                        "name"  => "商城名称",
                        "value" => $_W['shopset']['shop']['name']
                    ),
                    array(
                        "name"  => "粉丝昵称",
                        "value" => $member['nickname']
                    ),
                    array(
                        "name"  => "姓名",
                        "value" => $member['realname']
                    ),
                    array(
                        "name"  => "手机号码",
                        "value" => $member['mobile']
                    ),
                    array(
                        'name'  => '收益金额',
                        'value' => $money
                    ),
                    array(
                        'name'  => '收益时间',
                        'value' => date('Y-m-d H:i:s')
                    )
                );
                $url = mobileUrl('', array(), true);
                $remark = "\n" . $_W['shopset']['shop']['name'] . "感谢您的支持，如有疑问请联系在线客服或<a href='{$url}'>点击进入查看详情</a>";
                $message = array(
                    'first'    => array(
                        'value' => "{$_W['shopset']['shop']['name']}积分理财\n",
                        "color" => "#ff0000"
                    ),
                    'keyword1' => array(
                        'title' => '粉丝昵称',
                        'value' => $member['nickname'],
                        "color" => "#000000"
                    ),
                    'keyword2' => array(
                        'title' => '姓名',
                        'value' => $member['realname'],
                        "color" => "#000000"
                    ),
                    'keyword3' => array(
                        'title' => '手机号码',
                        'value' => $member['mobile'],
                        "color" => "#000000"
                    ),
                    'keyword4' => array(
                        'title' => '收益金额',
                        'value' => $money,
                        "color" => "#000000"
                    ),
                    'keyword5' => array(
                        'title' => '收益时间',
                        'value' => date('Y-m-d H:i:s'),
                        "color" => "#000000"
                    ),
                    'remark'   => array(
                        'value' => $remark,
                        "color" => "#000000"
                    )
                );
                $text = "{$_W['shopset']['shop']['name']}积分理财:\n亲爱的{$member['nickname']}\n您于" . date('Y-m-d H:i:s') . "获取投资收益\n金额：{$money}\n姓名：{$member['realname']}\n手机：{$member['$mobile']}\n" . $remark;

                m('notice')->sendNotice(array(
                    "openid"     => $member['openid'],
                    'tag'        => 'jflc_point_commission',
                    'default'    => $message,
                    'cusdefault' => $text,
                    'url'        => $url,
                    'datas'      => $datas
                ));
            }
        }
    }

    public function get_proportion() {
        global $_W;
        $date = date('Y-m-d', strtotime('-1 day'));
        $set = $this->getSet();
        $proportion = $set['default'];

        $row = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_jflc_income') . ' WHERE uniacid=:uniacid AND createdate=:createdate LIMIT 1', array(
            ':uniacid'    => $_W['uniacid'],
            ':createdate' => $date
        ));

        if (!empty($row)) {
            $proportion = $row['proportion'];
        }

        $proportion = floatval($proportion) / 100;
        return $proportion;
    }

}
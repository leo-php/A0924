<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}
//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);

class XfqfModel extends PluginModel {

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
     * @param $order
     * 积分全返唯一人口
     */
    public function run($order) {
        global $_W;

        if (!$this->status())
            return;
        $set = $this->getSet();

        $openid = $order['openid'];
        $member = m('member')->getMember($openid);
        if (empty($member)) {
            return;
        }
        /*
        $isagent = ($member['isagent'] == 1) ? true : false;

        $parent = false;
        if (!empty($member['agentid'])) {
            $parent = m('member')->getMember($member['agentid']);
        }*/

        $is_become = false;

        /*
        $set = p('commission')->getset();
        if ($set['become'] == '4' && !empty($set['become_goodsid'])) {
            $order_goods = pdo_fetchall('select goodsid from ' . tablename('zm_shop_order_goods') . ' where orderid=:orderid and uniacid=:uniacid  ', array(':uniacid' => $_W['uniacid'], ':orderid' => $order['id']), "goodsid");
            if (in_array($set['become_goodsid'], array_keys($order_goods))) {
                $is_become = true;
            }
        }
        */
        if ($order['id'] == 0) {
            $is_become = true;
        }
        //show_json(0,$is_become?'true':'false');
        $parent = $this->get_parent($member);

        $goodsprice = floatval($order['goodsprice']);

        if ($goodsprice > 0) {
            if ($is_become) {
                // 如果不是会员，交费999成为会员
                //show_json(0,$member['openid']);
                //!$this->is_member($member)
                $this->up_case($member, $parent, $order);
            } else {
                $this->commissions($member, $parent, $order);
                $this->business_div($order); // 商家分红
                $this->area_div($order);
            }

        }

        //$this->post_point();
    }

    /**
     * @param $member
     * @return bool
     * 获取上级最近的非普通会员
     */
    public function get_parent($member) {
        global $_W;

        $agentid = intval($member['agentid']);

        if (empty($agentid))
            return false;
        $member_count = intval(pdo_fetchcolumn('SELECT COUNT(1) FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid', array(':uniacid' => $_W['uniacid'])));
        $i = 0;
        while ($agent = pdo_fetch('SELECT dm.*,m.id as mid FROM ' . tablename('zm_shop_member') . ' dm ' . ' LEFT JOIN ' . tablename('zm_shop_xfqf_member') . ' m ON m.id=dm.id ' . ' WHERE dm.uniacid=:uniacid AND dm.id=:id LIMIT 1', array(':id' => $agentid, ':uniacid' => $_W['uniacid']))) {

            if ($i++ > $member_count)
                return false;

            if (!empty($agent['mid']) && !empty($agent['isagent']) && !empty($agent['status']) && empty($agent['isblack'])) {
                return $agent;
            } else {
                $agentid = intval($agent['agentid']);
                if (empty($agentid)) {
                    return false;
                }


            }
        }
        return false;
    }


    /**
     * @param $mid
     * @return bool
     * 是否为全返会员
     */
    public function is_member($mid) {
        global $_W;
        $agent = pdo_fetch('SELECT dm.*,m.id as mid FROM ' . tablename('zm_shop_member') . ' dm ' . ' LEFT JOIN ' . tablename('zm_shop_xfqf_member') . ' m ON m.id=dm.id ' . ' WHERE m.uniacid=:uniacid AND dm.id=:id LIMIT 1', array(':id' => $mid, ':uniacid' => $_W['uniacid']));
        if (!empty($agent['mid'])) {
            return true;
        }
        return false;
    }

    /**
     * @param $openid
     * @return bool
     * 判断是否为商户会员
     */
    public function is_merch($openid) {
        global $_W;
        $merch = pdo_fetch('SELECT id,status FROM ' . tablename('zm_shop_merch_user') . ' WHERE uniacid=:uniacid AND openid=:openid LIMIT 1', array(':uniacid' => $_W['uniacid'], ':openid' => $openid));

        if (empty($merch)) {
            return -1;
        } else {
            return $merch['status'];
        }
    }

    /**
     * 消费全返计划任务人口
     */
    public function task_run() {
        global $_W;

        if (!$this->status())
            return;
        $set = $this->getSet();
        $date = date('Y-m-d', time());
        //$date = '2017-02-14';
        $uniacid = $_W['uniacid'];

        $percentage = $set['percentage'] * 0.01;
        if ($percentage > 0) {
            $members = pdo_fetchall('SELECT m.id as mid, m.openid,mc.credit1,mc.credit2 FROM ' . tablename('zm_shop_member') . ' m ' . ' LEFT JOIN ' . tablename('mc_members') . ' mc ON mc.uid=m.uid AND mc.uniacid=m.uniacid ' . ' WHERE m.uniacid=:uniacid  AND m.isagent=1 AND m.status=1 AND mc.credit1>0 ', array(':uniacid' => $uniacid));

            foreach ($members as $member) {
                $point = $member['credit1'] * $percentage;
                if ($point >= 1) {

                    $log = pdo_fetch('SELECT id FROM ' . tablename('zm_shop_xfqf_log') . ' WHERE uniacid=:uniacid AND mid=:mid AND date=:date', array(':uniacid' => $uniacid, ':mid' => $member['mid'], 'date' => $date));

                    if (empty($log)) {
                        $credit2 = $point * 0.01;

                        pdo_insert('zm_shop_xfqf_log', array('uniacid' => $_W['uniacid'], 'mid' => $member['mid'], 'openid' => $member['openid'], 'date' => $date, 'createtime' => time(), 'applytime' => time(), 'credit1' => $member['credit1'], 'credit2' => $member['credit2'], 'proportion' => $percentage, 'point' => $point));

                        $point = floatval($point * -1);
                        m('member')->setCredit($member['openid'], 'credit1', $point, array($_W['uid'], '消费全返'));
                        $logno = m('common')->createNO('member_log', 'logno', 'GD');
                        $data = array('openid' => $member['openid'], 'logno' => $logno, 'uniacid' => $_W['uniacid'], 'type' => 0, 'createtime' => time(), 'status' => '1', 'title' => '消费全返', 'money' => $point, 'rechargetype' => 'xfqf');
                        pdo_insert('zm_shop_member_log', $data);
                        $logid = pdo_insertid();
                        plog('xfqf.addPoint', '消费全返' . ':' . $point . ' <br/>会员信息: ID: ' . $member['mid'] . ' /  ' . $member['openid'] . '/' . $member['nickname'] . '/' . $member['realname'] . '/' . $member['mobile']);

                        m('member')->setCredit($member['openid'], 'credit2', $credit2, array($_W['uid'], '消费全返'));

                    }
                }
            }
        }
    }

    /**
     * @param $member
     * @param $parent
     * @param $order
     * 1.两级分佣
     */
    private function commissions($member, $parent, $order) {
        $set = $this->getSet();
        $level1 = $set['level1'] * 0.01;
        $level2 = $set['level2'] * 0.01;

        $point = floatval($order['goodsprice']) * 100;
        $lv1_point = $point * $level1; //一级10%返点
        $lv2_point = $point * $level2;//二级5%返点
        $mid = $member['id'];

        $this->save_point($member, $member, $order, $point, 0); //自己获取积分
        if ($parent && $parent['isagent'] == 1 && $parent['status'] == 1) {
            $pid = $parent['id'];
            $this->save_point($member, $parent, $order, $lv1_point, 1);
            $parent = $this->get_parent($parent);
            if ($parent && $parent['isagent'] == 1 && $parent['status'] == 1) {
                $pid = $parent['id'];
                $this->save_point($member, $parent, $order, $lv2_point, 1);
            }
        }
    }


    /**
     * @param $order
     * 2.推荐商家分红
     */
    private function business_div($order) {
        global $_W;
        if (empty($order['ordersn']))
            return;
        $order = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_order') . ' WHERE ordersn=:ordersn AND uniacid=:uniacid', array(':uniacid' => $_W['uniacid'], ':ordersn' => $order['ordersn']));

        $ismerch = intval($order['ismerch']);
        $merchid = intval($order['merchid']);

        if ($ismerch) {
            $merch_user = pdo_fetch('SELECT openid FROM ' . tablename('zm_shop_merch_user') . ' WHERE uniacid=:uniacid AND id=:id', array(':uniacid' => $_W['uniacid'], ':id' => $merchid));
            if (!empty($merch_user['openid'])) {
                $member = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE openid=:openid AND uniacid=:uniacid LIMIT 1', array(':uniacid' => $_W['uniacid'], ':openid' => $merch_user['openid']));
                //$isagent = $member['isagent'] == 1 && $member['status'] == 1;

                if ($member) {


                    $point = $order['goodsprice'] * 10;

                    $this->business_point($member, $point);
                    //$parent = false;
                    if (!empty($member['agentid'])) {
                        $parent = $this->get_parent($member); //m('member')->getMember($member['agentid']);
                        if ($parent) {
                            $goodsprice = floatval($order['goodsprice']);
                            if ($goodsprice > 0) {
                                $set = $this->getSet();
                                $point = $goodsprice * 100 * $set['rebusine'] * 0.01;
                                $this->save_point($member, $parent, $order, $point, 2);
                            }
                        }
                    }
                }
            }
        }
    }

    private function business_point($member, $point) {
        $mark = '商家购置积分';
        $time = TIMESTAMP;
        m('member')->setCredit($member['openid'], 'credit1', $point, array(0, '商家购置积分'));
        /*
        $parent = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid', array(
            ':uniacid' => $_W['uniacid'],
            ':openid' => $log['openid']
        ));*/
        plog('jfqf.addPoint', '商家购置积分' . ':' . $point . ' <br/>会员信息: ID: ' . $member['id'] . ' /  ' . $member['openid'] . '/' . $member['nickname'] . '/' . $member['realname'] . '/' . $member['mobile']);

        $datas = array(array("name" => "商城名称", "value" => $_W['shopset']['shop']['name']), array("name" => "粉丝昵称", "value" => $member['nickname']), array('name' => '返点方式', 'value' => '商家购置积分'), array('name' => '返积分点', 'value' => $point));
        $url = mobileUrl('', array(), true);
        $remark = "\n" . $_W['shopset']['shop']['name'] . "感谢您的支持，如有疑问请联系在线客服或<a href='{$url}'>点击进入查看详情</a>";
        $message = array('first'    => array('value' => "恭喜您获取商家购置积分。\n", "color" => "#ff0000"), 'keyword1' => array('title' => '积分', 'value' => $point, "color" => "#000000"), //'keyword2' => array('title' => '积分', 'value' =>   $point, "color" => "#000000"),
            //'keyword3' => array('title' => '积分', 'value' =>   123, "color" => "#000000"),//$log_info['realmoeny']
                         'keyword4' => array('title' => '积分来源', 'value' => $mark, "color" => "#0a4b9c"), 'remark' => array('value' => $remark, "color" => "#000000"));

        $text = "亲爱的[粉丝昵称]，您的积分发生变动，具体如下：\n\n增加积分：{$point}\n充值时间：{$time}\n充值方式：商家购置积分\n" . $remark;
        //$text = "积分全返详细信息";

        m('notice')->sendNotice(array("openid" => $member['openid'], 'tag' => 'withdraw_ok', 'default' => $message, 'cusdefault' => $text, 'url' => $url, 'datas' => $datas));

    }

    /**
     * 3.升级身份全返
     */
    private function up_case($member, $parent, $order) {
        global $_W;
        if (!$this->is_member($member['id'])) {
            pdo_insert('zm_shop_xfqf_member', array('id' => $member['id'], 'uniacid' => $_W['uniacid'], 'status' => 1, 'createtime' => time()));
        }
        if ($parent && $parent['isagent'] == 1 && $parent['status'] == 1) {
            m('member')->setCredit($parent['openid'], 'credit2', 200, array(0, '升级身份全返:wechatnotify:credit2:' . 200));
        }
        $up_point = empty($set['up_point']) ? 99900 : intval($set['up_point']);
        $this->save_point($member, $member, $order, $up_point, 3);
    }

    /**
     * @param $order
     * 4.区域分红
     */
    public function area_div($order) {
        global $_W;

        if (empty($order['ordersn']))
            return;

        $order = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_order') . ' WHERE ordersn=:ordersn AND uniacid=:uniacid', array(':uniacid' => $_W['uniacid'], ':ordersn' => $order['ordersn']));

        if (!empty($order['address'])) {
            $address = unserialize($order['address']);
            $province = $address['province'];
            $city = $address['city'];
            $area = $address['area'];


            $set = $this->getSet();
            $area_perc = $set['area'] * 0.01;
            $city_perc = $set['city'] * 0.01;

            $price = floatval($order['price']);

            $member = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid', array(':uniacid' => $_W['uniacid'], ':openid' => $order['openid']));

            $area_point = 0;
            $area_list = pdo_fetchall('SELECT openid FROM ' . tablename('zm_shop_merch_user') . ' WHERE uniacid=:uniacid AND level=1 AND status=1 AND province=:province AND city=:city AND area=:area', array(':uniacid' => $_W['uniacid'], ':province' => $province, ':city' => $city, ':area' => $area));

            $area_conut = count($area_list);

            if ($area_conut > 0) {
                $area_point = ($price * 100 * $area_perc) / $area_conut;
                foreach ($area_list as $area) {
                    if (!empty($area['openid'])) {
                        $parent = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid', array(':uniacid' => $_W['uniacid'], ':openid' => $area['openid']));
                        if ($parent) {
                            $this->save_point($member, $parent, $order, $area_point, 4);
                        }
                    }
                }
            }

            $city_list = pdo_fetchall('SELECT openid FROM ' . tablename('zm_shop_merch_user') . ' WHERE uniacid=:uniacid AND level=2 AND status=1 AND province=:province AND city=:city', array(':uniacid' => $_W['uniacid'], ':province' => $province, ':city' => $city));

            $city_conut = count($city_list);

            if ($city_conut > 0) {
                $city_point = ($price * 100 * $city_perc) / $city_conut;
                foreach ($city_list as $city) {
                    if (!empty($city['openid'])) {
                        $parent = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid', array(':uniacid' => $_W['uniacid'], ':openid' => $city['openid']));
                        if ($parent) {
                            $this->save_point($member, $parent, $order, $city_point, 4);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param     $member
     * @param     $parent
     * @param     $order
     * @param     $point
     * @param int $type
     * 保存返点到指定用户
     */
    public function save_point($member, $parent, $order, $point, $type = 0) {
        global $_W;
        if ($point > 0) {
            $time = date('Y-m-d H:i:s', TIMESTAMP);
            //$history = pdo_fetch('SELECT id FROM ' . tablename('zm_shop_xfqf_history') . ' WHERE uniacid=:uniacid AND orderid=:orderid AND pid=:pid AND mid=:mid AND type=:type LIMIT 1', array(':uniacid' => $_W['uniacid'], ':orderid' => $order['id'], ':mid' => $member['id'], ':pid' => $parent['id'], ':type' => $type));
            //if (empty($history)) {
            $mark = '消费全返';
            switch ($type) {
                case 1:
                    $mark = '两级分佣';
                    break;
                case 2:
                    $mark = '推荐商家分红';
                    break;
                case 3:
                    $mark = '升级身份全返';
                    break;
                case 4:
                    $mark = '区域分红';
                    break;
                case 5:
                    $mark = '商家购置';
                    break;
                default:
                    $mark = '消费全返';
            }

            pdo_insert('zm_shop_xfqf_history', array('uniacid' => $_W['uniacid'], 'orderid' => $order['id'], 'mid' => $member['id'], 'pid' => $parent['id'], 'point' => $point, 'type' => $type, 'remark' => $mark, 'create_at' => TIMESTAMP));

            m('member')->setCredit($parent['openid'], 'credit1', $point, array($_W['uid'], $mark));
            plog('jfqf.addPoint', '积分全返' . ':' . $point . ' <br/>会员信息: ID: ' . $parent['mid'] . ' /  ' . $parent['openid'] . '/' . $parent['nickname'] . '/' . $parent['realname'] . '/' . $parent['mobile']);

            $datas = array(array("name" => "商城名称", "value" => $_W['shopset']['shop']['name']), array("name" => "粉丝昵称", "value" => $parent['nickname']), array('name' => '返点方式', 'value' => $type), array('name' => '返积分点', 'value' => $point));
            $url = mobileUrl('', array(), true);
            $remark = "\n" . $_W['shopset']['shop']['name'] . "感谢您的支持，如有疑问请联系在线客服或<a href='{$url}'>点击进入查看详情</a>";
            $message = array('first'    => array('value' => "恭喜您已经成功获取全返积分。\n", "color" => "#ff0000"), 'keyword1' => array('title' => '积分', 'value' => $point, "color" => "#000000"), //'keyword2' => array('title' => '积分', 'value' =>   $point, "color" => "#000000"),
                //'keyword3' => array('title' => '积分', 'value' =>   123, "color" => "#000000"),//$log_info['realmoeny']
                             'keyword4' => array('title' => '积分来源', 'value' => $mark, "color" => "#0a4b9c"), 'remark' => array('value' => $remark, "color" => "#000000"));

            $text = "亲爱的" . $parent['nickname'] . "，您的积分发生变动，具体如下：\n\n增加积分：{$point}\n充值时间：{$time}\n充值方式：{$mark}\n" . $remark;
            //$text = "积分全返详细信息";

            m('notice')->sendNotice(array("openid" => $parent['openid'], 'tag' => 'withdraw_ok', 'default' => $message, 'cusdefault' => $text, 'url' => $url, 'datas' => $datas));
            //}

        }
    }

    /**
     * @param $merch
     * @param $member
     * @param $member
     * 商家购置积分返现及分佣
     */
    public function merchbuy($merch, $member, $money) {
        global $_W;
        $set = $this->getSet();
        $shopset = m('common')->getSysset('shop');
        $purchase = max(1,floatval($set['purchase']));
        if (!empty($merch)) {
            $point = $money * 100;

            $this->business_point($merch, $point);

            $parent = $this->get_parent($merch); //m('member')->getMember($member['agentid']);
            if ($parent) {
                $order = array('id' => 0, 'openid' => $parent['openid'], 'goodsprice' => $money);
                $goodsprice = floatval($order['goodsprice']);
                if ($goodsprice > 0) {
                    $point = $goodsprice * 100 * $set['rebusine'] * 0.01;
                    $this->save_point($merch, $parent, $order, $point, 2);
                }
            }
        }

        if (!empty($member)) {
            $realmoney = $money * $purchase;
            $order = array('id' => 0, 'openid' => $member['openid'], 'goodsprice' => $realmoney);
            $this->rechargelog($member['id'],$member['openid'],$realmoney);
            $parent = $this->get_parent($member);
            $this->commissions($member, $parent, $order);
        }

    }

    public function rechargelog($mid, $openid, $money) {
        global $_W;
        pdo_insert('zm_shop_xfqf_recharge', array(
            'uniacid' => $_W['uniacid'],
            'mid' => $mid,
            'openid' => $openid,
            'money' => $money,
            'createdate' => date('Y-m-d', TIMESTAMP),
            'createtime' => TIMESTAMP
            )
        );
    }

    /**
     * @param $openid
     * @param $money
     * @param int $mcrid
     * @return bool
     * 获取线下指定商户会员消费
     */
    public function getrecharge($openid,$money,$mcrid=0){
        global $_W;
        $set = $this->getSet();
        $max_price = floatval($set['max_price']);
        if(empty($max_price)) return false;

        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime('+1 day',$start_time);
        $recharge = floatval(pdo_fetchcolumn('SELECT SUM(money) FROM '.tablename('zm_shop_xfqf_recharge').' WHERE uniacid=:uniacid AND openid=:openid AND createtime>=:starttime AND createtime<:endtime',array(
            ':uniacid' => $_W['uniacid'],
            ':openid' => $openid,
            ':starttime' => $start_time,
            ':endtime' => $end_time
        )));
        if( ($recharge+$money) > $max_price) return true;
        return false;
    }

    /**
     * @param $openid
     * @param $money
     * @return bool
     * 判断是否超过每日购物上限
     */
    public function getOrderPrice($openid,$money){
        global $_W;
        $set = $this->getSet();
        $max_price = floatval($set['max_price']);
        if(empty($max_price)) return false;
        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime('+1 day',$start_time);

        $recharge = floatval(pdo_fetchcolumn('SELECT SUM(money) FROM '.tablename('zm_shop_xfqf_recharge').' WHERE uniacid=:uniacid AND openid=:openid AND createtime>=:starttime AND createtime<:endtime',array(
            ':uniacid' => $_W['uniacid'],
            ':openid' => $openid,
            ':starttime' => $start_time,
            ':endtime' => $end_time
        )));

        $grprice = floatval(pdo_fetchcolumn('SELECT SUM(grprice) FROM '.tablename('zm_shop_order').' WHERE uniacid=:uniacid AND openid=:openid AND paytime>0 AND paytime>=:starttime AND paytime<:endtime',array(
            ':uniacid' => $_W['uniacid'],
            ':openid' => $openid,
            ':starttime' => $start_time,
            ':endtime' => $end_time
        )));
        if( ($recharge+$grprice+$money) > $max_price) return true;
        return false;

    }
}
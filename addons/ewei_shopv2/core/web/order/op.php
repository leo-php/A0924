<?php

/*
 * 人人商城
 *
 * 青岛易联互动网络科技有限公司
 * http://www.we7shop.cn
 * TEL: 4000097827/18661772381/15865546761
 */
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Op_EweiShopV2Page extends WebPage {

    function delete() {

        global $_W, $_GPC;
        $status = intval($_GPC['status']);
        $orderid = intval($_GPC['id']);

        pdo_update('ewei_shop_order', array('deleted' => 1), array('id' => $orderid, 'uniacid' => $_W['uniacid']));
        plog('order.op.delete', "订单删除 ID: {$orderid}");
        show_json(1, webUrl('order', array('status' => $status)));
    }

    protected function opData() {

        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $item = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_order') . " WHERE id = :id and uniacid=:uniacid", array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if (empty($item)) {
            if ($_W['isajax']) {
                show_json(0, "未找到订单!");
            }
            $this->message('未找到订单!', '', 'error');
        }

        $order_goods = pdo_fetchall('select single_refundstate from ' . tablename('ewei_shop_order_goods'). ' where orderid=:orderid and uniacid=:uniacid', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));

        $is_singlerefund=false;//是否正在维权
        foreach ($order_goods as $og){
            if(!$is_singlerefund && ($og['single_refundstate']==1 ||$og['single_refundstate']==2)){
                $is_singlerefund=true;//存在维权申请，需要处理后再进行后续操作
                break;
            }
        }

        return array('id' => $id, 'item' => $item,'is_singlerefund'=>$is_singlerefund);
    }

    function changeprice() {

        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);


        //检查是否是代付,如果是拒绝改价
        $is_peerpay = false;
        $is_peerpay = m('order')->checkpeerpay($item['id']);
        if (!empty($is_peerpay)) show_json(0,'代付订单不能改价');

        if($item['ordersn2']>=100) $item['ordersn2'] = 0;
        if ($_W['ispost']) {
            $changegoodsprice = $_GPC['changegoodsprice'];
            if (!is_array($changegoodsprice)) {
                show_json(0, '未找到改价内容!');
            }

            if ($item['parentid'] > 0) {
                $parent_order = array();
                $parent_order['id'] = $item['parentid'];
            }


            $changeprice = 0;
            foreach ($changegoodsprice as $ogid => $change) {
                $changeprice+=floatval($change);
            }

            $dispatchprice = floatval($_GPC['changedispatchprice']);
            if ($dispatchprice < 0) {
                $dispatchprice = 0;
            }
            $orderprice = $item['price'] + $changeprice;
            $changedispatchprice = 0;
            if ($dispatchprice != $item['dispatchprice']) {
                //修改了运费
                $changedispatchprice = $dispatchprice - $item['dispatchprice'];
                $orderprice+=$changedispatchprice;
            }

            if ($orderprice < 0) {
                show_json(0, "订单实际支付价格不能小于0元!");
            }
            foreach ($changegoodsprice as $ogid => $change) {
                $og = pdo_fetch('select price,realprice from ' . tablename('ewei_shop_order_goods') . ' where id=:ogid and uniacid=:uniacid limit 1', array(':ogid' => $ogid, ':uniacid' => $_W['uniacid']));
                if (!empty($og)) {
                    $realprice = $og['realprice'] + $change;
                    if ($realprice < 0) {
                        show_json(0, '单个商品不能优惠到负数');
                    }
                }
            }
            $ordersn2 = $item['ordersn2'] + 1;
            if ($ordersn2 > 99) {
                show_json(0, '超过改价次数限额');
            }


            $orderupdate = array();
            if ($orderprice != $item['price']) {
                //订单价格变化
                $orderupdate['price'] = $orderprice;
                $orderupdate['ordersn2'] = $item['ordersn2'] + 1;

                if ($item['parentid'] > 0) {
                    $parent_order['price_change'] = $orderprice - $item['price'];
                }
            }
            //订单的价格变化值
            $orderupdate['changeprice'] = $item['changeprice'] + $changeprice;

            if ($dispatchprice != $item['dispatchprice']) {
                //运费变化
                $orderupdate['dispatchprice'] = $dispatchprice; //这次的运费变化
                $orderupdate['changedispatchprice']+=$changedispatchprice; //累计的运费变化

                if ($item['parentid'] > 0) {
                    $parent_order['dispatch_change'] = $changedispatchprice;
                }
            }
            if (!empty($orderupdate)) {
                pdo_update('ewei_shop_order', $orderupdate, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
            }

            if ($item['parentid'] > 0) {
                if (!empty($parent_order)) {
                    m('order')->changeParentOrderPrice($parent_order);
                }
            }

            //修改商品价
            foreach ($changegoodsprice as $ogid => $change) {
                $og = pdo_fetch('select price,realprice,changeprice from ' . tablename('ewei_shop_order_goods') . ' where id=:ogid and uniacid=:uniacid limit 1', array(':ogid' => $ogid, ':uniacid' => $_W['uniacid']));
                if (!empty($og)) {
                    $realprice = $og['realprice'] + $change; //这次的变化
                    $changeprice = $og['changeprice'] + $change; //累计的变化
                    pdo_update('ewei_shop_order_goods', array('realprice' => $realprice, 'changeprice' => $changeprice), array('id' => $ogid));
                }
            }

            //修改商品佣金
            $pluginc = p('commission');
            if ($pluginc) {
                $pluginc->calculate($item['id'], true);
            }
            plog('order.op.changeprice', "订单号： {$item['ordersn']} <br/> 价格： {$item['price']} -> {$orderprice}");

            m('notice')->sendOrderChangeMessage($item['openid'],array(
                'title'=>'订单金额',
                'orderid'=>$item['id'],
                'ordersn'=>$item['ordersn'],
                'olddata'=>$item['price'],
                'data'=> round($orderprice ,2),
                'type'=>1
            ),'orderstatus');
            show_json(1);
        }
        //订单商品
        $order_goods = pdo_fetchall('select og.id,g.title,g.thumb,g.goodssn,og.goodssn as option_goodssn, g.productsn,og.productsn as option_productsn, og.total,og.price,og.optionname as optiontitle, og.realprice,og.oldprice from ' . tablename('ewei_shop_order_goods') . ' og ' . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid ' . ' where og.uniacid=:uniacid and og.orderid=:orderid ', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));



        if (empty($item['addressid'])) {
            $user = unserialize($item['carrier']);
            $item['addressdata'] = array(
                'realname' => $user['carrier_realname'],
                'mobile' => $user['carrier_mobile']
            );
        }
        else {

            $user = iunserializer($item['address']);
            if (!is_array($user)) {
                $user = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_address') . " WHERE id = :id and uniacid=:uniacid", array(':id' => $item['addressid'], ':uniacid' => $_W['uniacid']));
            }
            $user['address'] = $user['province'] . ' ' . $user['city'] . ' ' . $user['area'] . ' ' . $user['address'];

            $item['addressdata'] = array(
                'realname' => $user['realname'],
                'mobile' => $user['mobile'],
                'address' => $user['address'],
            );
        }
        include $this->template();
    }

    function pay($a = array(), $b = array()) {

        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if ($item['status'] > 1) {
            show_json(0, '订单已付款，不需重复付款！');
        }
        if (!empty($item['virtual']) && com('virtual')) {
            //虚拟物品自动发货
            com('virtual')->pay($item);
        } else {


            $time = time();
            $updateorder = array(
                'status' => 1,
                'paytype' => 11,
                'paytime' => $time
            );
            $isonlyverifygoods = m('order')->checkisonlyverifygoods($item['id']);

            if($isonlyverifygoods){
                $updateorder['status']=2;
                $updateorder['sendtime']= $time;
            }
            //确认付款先改状态，再设置库存
            pdo_update('ewei_shop_order', $updateorder, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));

            //设置库存,增加积分
            m('order')->setStocksAndCredits($item['id'], 1);

            //模板消息
            m('notice')->sendOrderMessage($item['id']);

            //打印机打印
            com_run('printer::sendOrderMessage',$item['id']);

            //发送赠送优惠券
            if (com('coupon')) {
                com('coupon')->sendcouponsbytask($item['id']); //订单支付
            }

            //优惠券返利
            if (com('coupon') && !empty($item['couponid'])) {
                com('coupon')->backConsumeCoupon($item['id']); //后台确认付款
            }

            //分销检测
            if (p('commission')) {
                p('commission')->checkOrderPay($item['id']);
            }

            //抵消优惠券
            $plugincoupon = com('coupon');
            if ($plugincoupon) {
                $oid = $item['id'];
                $plugincoupon->useConsumeCoupon($oid);
            }
        }

        //创建记次时商品记录
        m('verifygoods')->createverifygoods($item['id']);

        plog('order.op.pay', "订单确认付款 ID: {$item['id']} 订单号: {$item['ordersn']}");
        show_json(1);
    }

    function close() {
        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if($is_singlerefund){
            show_json(0, '订单商品存在维权，无法关闭订单！');
        }

        if ($item['status'] == -1) {
            show_json(0, '订单已关闭，无需重复关闭！');
        } else if ($item['status'] >= 1) {
            show_json(0, '订单已付款，不能关闭！');
        }
        if ($_W['ispost']) {
            if (!empty($item['transid'])) {
                //changeWechatSend($item['ordersn'], 0, $_GPC['reson']);
            }
            $time = time();
            if ($item['refundstate'] > 0 && !empty($item['refundid'])) {

                $change_refund = array();
                $change_refund['status'] = -1;
                $change_refund['refundtime'] = $time;
                pdo_update('ewei_shop_order_refund', $change_refund, array('id' => $item['refundid'], 'uniacid' => $_W['uniacid']));
            }

            //返还抵扣积分
            if ($item['deductcredit'] > 0) {
                m('member')->setCredit($item['openid'], 'credit1', $item['deductcredit'], array('0', $_W['shopset']['shop']['name'] . "购物返还抵扣积分 积分: {$item['deductcredit']} 抵扣金额: {$item['deductprice']} 订单号: {$item['ordersn']}"));
            }

            //返还抵扣余额
            m('order')->setDeductCredit2($item);

            //退还优惠券
            if (com('coupon') && !empty($item['couponid'])) {
                com('coupon')->returnConsumeCoupon($item['id']); //后台关闭订单
            }

            m('order')->setStocksAndCredits($item['id'], 2);

            pdo_update('ewei_shop_order', array('status' => -1, 'refundstate' => 0, 'canceltime' => $time, 'remarkclose' => $_GPC['remark']), array('id' => $item['id'], 'uniacid' => $_W['uniacid']));

            if (!empty($item['virtual']) && $item['virtual'] != 0) {

                $goodsid = pdo_fetch('SELECT goodsid FROM '.tablename('ewei_shop_order_goods').' WHERE uniacid = '.$_W['uniacid'].' AND orderid = '.$item['id']);

                $typeid = $item['virtual'];
                $vkdata = ltrim($item['virtual_info'],'[');
                $vkdata = rtrim($vkdata,']');
                $arr = explode('}',$vkdata);
                foreach($arr as $k => $v){
                    if(!$v){
                        unset($arr[$k]);
                    }
                }
                $vkeynum = count($arr);

                //未付款卡密变为未使用
                pdo_query("update " . tablename('ewei_shop_virtual_data') . ' set openid="",usetime=0,orderid=0,ordersn="",price=0,merchid='.$item['merchid'].' where typeid=' . intval($typeid).' and orderid = '.$item["id"]);

                //模板减少使用数据
                pdo_query("update " . tablename('ewei_shop_virtual_type') . " set usedata=usedata-".$vkeynum." where id=" .intval($typeid));

            }

            plog('order.op.close', "订单关闭 ID: {$item['id']} 订单号: {$item['ordersn']}");
            show_json(1);
        }
        include $this->template();
    }

    function paycancel() {

        global $_W, $_GPC;

        $opdata = $this->opData();
        extract($opdata);

        if($is_singlerefund){
            show_json(0, '订单商品存在维权，无法取消付款！');
        }

        if ($item['status'] != 1) {
            show_json(0, '订单未付款，不需取消！');
        }
        if ($_W['ispost']) {

            //先设置库存，再更改状态,
            m('order')->setStocksAndCredits($item['id'], 2);

            pdo_update('ewei_shop_order', array(
                'status' => 0,
                'cancelpaytime' => time()
            ), array('id' => $item['id'], 'uniacid' => $_W['uniacid']));

            plog('order.op.paycancel', "订单取消付款 ID: {$item['id']} 订单号: {$item['ordersn']}");
            show_json(1);
        }
    }

    //普通商品收获
    function finish() {
        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if($is_singlerefund){
            show_json(0, '订单商品存在维权，无法确认收货！');
        }

        pdo_update('ewei_shop_order', array(
            'status' => 3,
            'finishtime' => time()
        ), array('id' => $item['id'], 'uniacid' => $_W['uniacid']));

        $order = pdo_fetch('select * from '.tablename("ewei_shop_order")." where id=:id", array(":id" => $item['id']));

        //判断是否升级
        $memberInfo = pdo_fetch("select * from ".tablename("ewei_shop_member")." where openid=:openid", array('openid' => $order['openid']));
        $memberNowLevel = pdo_fetch("select * from " . tablename("ewei_shop_member_level") . " where id=:id", array(':id' => $memberInfo['level']));
        if (!$memberNowLevel) {
            $memberNowLevel = array(
                'levelname' => empty($_S['shop']['levelname']) ? '普通会员' : $_S['shop']['levelname'],
                'discount' => empty($_S['shop']['leveldiscount']) ? 10 : $_S['shop']['leveldiscount'],
                'stock_money' => 0
            );
        }
        $levelAllList = pdo_fetchall("select * from " . tablename("ewei_shop_member_level") . " where stock_money > {$memberNowLevel['stock_money']} order by stock_money desc");
        foreach ($levelAllList as $k => $v) {
            if ($order['price'] >= $v['stock_money']) { //升级
                pdo_update('ewei_shop_member', array('level' => $v['id']), array('id' => $memberInfo['id']));
                com_run('wxcard::updateMemberCardByOpenid', $memberInfo['openid']);
                //模板消息
                m('notice')->sendMemberUpgradeMessage($memberInfo['openid'], $memberNowLevel, $v);

                //判断直推人数是否满足升级
                $myInviter = pdo_fetch("select id,level,inviter from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $memberInfo['inviter']));
                $nowInviterLevel = pdo_getcolumn("ewei_shop_member_level", array("id" => $myInviter['level']), 'level');
                $nowInviterLevel = $nowInviterLevel ?: 0;
                $newInviterLevelList = pdo_fetchall("select * from " . tablename("ewei_shop_member_level") . " 
    where level> {$nowInviterLevel} ORDER BY `level` desc");
                $sameLevelCount = 0;
                foreach ($newInviterLevelList as $key => $val) {
                    if (!empty($val['same_level_invite'])) {
                        //查询上级的同级以上人数
                        $myChildren = pdo_fetchall("select id,level,inviter from " . tablename("ewei_shop_member") . " where inviter=:id and inviter!=0", array(":id" => $myInviter['id']));
                        foreach ($myChildren as $i => $j) {
                            $levelLevel = pdo_getcolumn("ewei_shop_member_level", array("id" => $j['level']), 'level');
                            if (($val['level'] - 1) <= $levelLevel) {
                                $sameLevelCount += 1;
                            }
                        }
                        if ($val['same_level_invite'] <= $sameLevelCount) {
                            //升级直推
                            pdo_update("ewei_shop_member", array('level' => $val['id']), array('id' => $myInviter['id']));
                            //判断间推人数是否满足升级
                            $myTwoInviter = pdo_fetch("select id,level,inviter from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $myInviter['inviter']));
                            $nowTwoInviterLevel = pdo_getcolumn("ewei_shop_member_level", array("id" => $myTwoInviter['level']), 'level');
                            $nowTwoInviterLevel = $nowTwoInviterLevel ?: 0;
                            $newTwoInviterLevelList = pdo_fetchall("select * from " . tablename("ewei_shop_member_level") . " 
    where level > {$nowTwoInviterLevel} ORDER BY `level` desc");
                            $sameTwoLevelCount = 0;
                            foreach ($newTwoInviterLevelList as $ke => $vo) {
                                if (!empty($vo['same_level_invite'])) {
                                    //查询上级的同级以上人数
                                    $myTwoChildren = pdo_fetchall("select id,level,inviter from " . tablename("ewei_shop_member") . " where inviter=:id and inviter!=0", array(":id" => $myTwoInviter['id']));
                                    foreach ($myTwoChildren as $i => $j) {
                                        $levelLevel = pdo_getcolumn("ewei_shop_member_level", array("id" => $j['level']), 'level');
                                        if (($vo['level'] - 1) <= $levelLevel) {
                                            $sameTwoLevelCount += 1;
                                        }
                                    }
                                    if ($vo['same_level_invite'] <= $sameTwoLevelCount) {
                                        //升级间推
                                        pdo_update("ewei_shop_member", array('level' => $vo['id']), array('id' => $myTwoInviter['id']));
                                        break;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
                break;
            }
        }

        //发放卫贝及提成奖励
        $orderAddress = unserialize($order['address']);
        $isFirstStock = pdo_fetch("select count(*) as count from " . tablename("ewei_shop_linepay_record") . " where user_id={$memberInfo['id']} and status=2");
        $memberLevel = pdo_getcolumn("ewei_shop_member", array('id' => $memberInfo['id']), 'level');
        $memberNowLevel = pdo_fetch("select * from " . tablename("ewei_shop_member_level") . " where id=:id", array(':id' => $memberLevel));
        //判断是否首次进货
        if ($isFirstStock['count'] <= 1 && $memberNowLevel['double_credit'] == 1 && $order['paytype'] != 1) { //首次进货(因为目前此订单已支付成功，判断为小于等于1)
            //卫贝奖励
            $credits = $order['price'] * 2;
            m('member')->setCredit($memberInfo['openid'], 'credit1', $credits, array(0, '首次进货卫贝奖励'));
        } else if ($isFirstStock['count'] > 1 && $memberNowLevel['rebuy_double_credit'] == 1 && $order['paytype'] != 1) { //重复进货
            //卫贝奖励
            $credits = $order['price'] * 2;
            m('member')->setCredit($memberInfo['openid'], 'credit1', $credits, array(0, '重复进货卫贝奖励'));
            //重复购买加速奖励
//                if (!empty($memberNowLevel['rebuy_speed'])) {
//                    m('member')->setAddSpeed($memberInfo['id'], $memberNowLevel['rebuy_speed'], 3, $recordInfo['id'], '重复进货加速奖励');
//                    pdo_update("ewei_shop_linepay_record", array('speed' => $memberNowLevel['rebuy_speed']), array('id' => $recordInfo['id']));
//                }
        }
        //直推和间推奖励 (进货商品)
        if (!empty($memberInfo['inviter'])) {
            //直推
            $inviterInfo = pdo_fetch("select * from " . tablename('ewei_shop_member') . ' where id=:id', array(':id' => $memberInfo['inviter']));
            $inviterLevelLevel = pdo_fetch("select * from " . tablename('ewei_shop_member_level') . " where id=:id", array(":id" => $inviterInfo['level']));
            //加速奖励
            if (!empty($inviterLevelLevel['invite_buy_speed'] && $inviterLevelLevel['invite_buy_speed_unit'] > 0)) {
                $totalPrice = $order['price'];
                $nowInviterSpeed = 0;
                while (($totalPrice - $inviterLevelLevel['invite_buy_speed_unit'] >= 0) && ($nowInviterSpeed <= $inviterLevelLevel['invite_buy_max_speed'])) {
                    $totalPrice -= $inviterLevelLevel['invite_buy_speed_unit'];
                    $nowInviterSpeed += $inviterLevelLevel['invite_buy_speed'];
                    if ($nowInviterSpeed >= $inviterLevelLevel['invite_buy_max_speed']) {
                        $nowInviterSpeed = $inviterLevelLevel['invite_buy_max_speed'];
                        break;
                    }
                }
                m('member')->setAddSpeed($inviterInfo['id'], $nowInviterSpeed, 4, $order['id'], '直推用户进货加速奖励');
            }
            //直推提成奖励
            if (!empty($inviterLevelLevel['invite_buy_point'])) {
                $inviteCredit = $order['price'] * $inviterLevelLevel['invite_buy_point'] * 0.01;
                if (!empty($inviteCredit)) {
                    m('member')->setCredit($inviterInfo['openid'], 'credit2', $inviteCredit, array(0, '直推用户进货提成奖励'));
                }
            }
            //间推
            if (!empty($inviterInfo['inviter'])) {
                $inviterTwoInfo = pdo_fetch("select * from " . tablename('ewei_shop_member') . ' where id=:id', array(':id' => $inviterInfo['inviter']));
                $inviterTwoLevelLevel = pdo_fetch("select * from " . tablename('ewei_shop_member_level') . " where id=:id", array(":id" => $inviterTwoInfo['level']));
                if (!empty($inviterTwoLevelLevel['invite_two_buy_speed'] && $inviterLevelLevel['invite_two_buy_speed_unit'] > 0)) {
                    $totalPrice = $order['price'];
                    $nowInviterTwoSpeed = 0;
                    while (($totalPrice - $inviterTwoLevelLevel['invite_two_buy_speed_unit'] >= 0) && ($nowInviterTwoSpeed <= $inviterTwoLevelLevel['invite_two_buy_max_speed'])) {
                        $totalPrice -= $inviterTwoLevelLevel['invite_two_buy_speed_unit'];
                        $nowInviterTwoSpeed += $inviterTwoLevelLevel['invite_two_buy_speed'];
                        if ($nowInviterTwoSpeed >= $inviterTwoLevelLevel['invite_two_buy_max_speed']) {
                            $nowInviterTwoSpeed = $inviterTwoLevelLevel['invite_two_buy_max_speed'];
                            break;
                        }
                    }
                    m('member')->setAddSpeed($inviterTwoInfo['id'], $nowInviterTwoSpeed, 5, $order['id'], '间推用户进货加速奖励');
                }
            }
            //间推提成奖励
            if (!empty($inviterTwoLevelLevel['invite_two_buy_point'])) {
                $inviteTwoCredit = $order['price'] * $inviterTwoLevelLevel['invite_two_buy_point'] * 0.01;
                if (!empty($inviteTwoCredit)) {
                    m('member')->setCredit($inviterTwoInfo['openid'], 'credit2', $inviteTwoCredit, array(0, '间推用户进货提成奖励'));
                }
            }
        }

        //区域提成 (非进货商品)
        if (!empty($orderAddress)) {
            $province = $orderAddress['province'];
            $city = $orderAddress['province'] . $orderAddress['city'];
            $area = $orderAddress['province'] . $orderAddress['city'] . $orderAddress['area'];
            //查询下单用户是否区域代理，限制区域代理进货奖励
            $same_level=pdo_fetch(' select b.level from '.tablename('ewei_shop_member'). ' as a left join '.tablename('ewei_shop_member_level'). ' as b on a.level = b.id where a.uniacid=:uniacid and  a.openid=:openid ',array(':uniacid'=>$_W['uniacid'], ':openid'=>$order['openid']));
            if($same_level['level']<2) {
                //省级
                if (!empty($province)) {
                    $provinceAgent = pdo_fetch("select id,level,openid from " . tablename("ewei_shop_member") .
                        " where agent_province=:province", array(':province' => $province));
                    if ($provinceAgent) {
                        //查询用户等级是否关联省级区域代理
                        $provinceLevelInfo = pdo_fetch("select * from " . tablename("ewei_shop_member_level") .
                            " where id=:id", array(':id' => $provinceAgent['level']));
                        if ($provinceLevelInfo['area_type'] == 3) {
                            $agentCredit = $order['price'] * $provinceLevelInfo['area_buy_point'] * 0.01;
                            if (!empty($agentCredit)) {
                                m('member')->setCredit($provinceAgent['openid'], 'credit2', $agentCredit,
                                    array(0, '省级区域提成：订单' . $order['ordersn'] . ',获得卫卷' . $agentCredit));
                                pdo_update('ewei_shop_order', array('province_agent_id' => $provinceAgent['id'],
                                    'province_agent_price' => $agentCredit), array('id' => $order['id']));
                            }
                        }
                    }
                }
                //市级
                if (!empty($city)) {
                    $cityAgent = pdo_fetch("select id,level,openid from " . tablename("ewei_shop_member") .
                        " where agent_city=:city", array(':city' => $city));
                    if ($cityAgent) {
                        //查询用户等级是否关联市级区域代理
                        $cityLevelInfo = pdo_fetch("select * from " . tablename("ewei_shop_member_level") .
                            " where id=:id", array(':id' => $cityAgent['level']));
                        if ($cityLevelInfo['area_type'] == 2) {
                            $agentCredit = $order['price'] * $cityLevelInfo['area_buy_point'] * 0.01;
                            if (!empty($agentCredit)) {
                                m('member')->setCredit($cityAgent['openid'], 'credit2', $agentCredit,
                                    array(0, '市级区域提成：订单' . $order['ordersn'] . ',获得卫卷' . $agentCredit));
                                pdo_update('ewei_shop_order', array('city_agent_id' => $cityAgent['id'],
                                    'city_agent_price' => $agentCredit), array('id' => $order['id']));
                            }
                        }
                    }
                }
                //区级
                if (!empty($area)) {
                    $areaAgent = pdo_fetch("select id,level,openid from " . tablename("ewei_shop_member") .
                        " where agent_area=:area", array(':area' => $area));
                    if ($areaAgent) {
                        //查询用户等级是否关联区级区域代理
                        $areaLevelInfo = pdo_fetch("select * from " . tablename("ewei_shop_member_level") .
                            " where id=:id", array(':id' => $areaAgent['level']));
                        if ($areaLevelInfo['area_type'] == 1) {
                            $agentCredit = $order['price'] * $areaLevelInfo['area_buy_point'] * 0.01;
                            if (!empty($agentCredit)) {
                                m('member')->setCredit($areaAgent['openid'], 'credit2', $agentCredit,
                                    array(0, '区级区域提成：订单' . $order['ordersn'] . ',获得卫卷' . $agentCredit));
                                pdo_update('ewei_shop_order', array('area_agent_id' => $areaAgent['id'],
                                    'area_agent_price' => $agentCredit), array('id' => $order['id']));
                            }
                        }
                    }
                }
            }
        }

        //商品全返
        m('order')->fullback($item['id']);
        if (p('ccard') && !empty($item['ccardid'])) {
            p('ccard')->setBegin($item['id'], $item['ccardid']);
        }

        //会员升级
        m('member')->upgradeLevel($item['openid'],$item['id']);

        //处理积分
        m('order')->setStocksAndCredits($item['id'], 3);

        //余额赠送
        m('order')->setGiveBalance($item['id'], 1);

        //模板消息
        m('notice')->sendOrderMessage($item['id']);

        //打印机打印
        com_run('printer::sendOrderMessage',$item['id']);

        //发送赠送优惠券
        if (com('coupon')) {
            com('coupon')->sendcouponsbytask($item['id']); //订单支付
        }

        //优惠券返利
        if (!empty($item['couponid'])) {
            com('coupon')->backConsumeCoupon($item['id']); //后台收货
        }

        //排队全返
        if (p('lineup')) {
            p('lineup')->checkOrder($item);
        }

        //分销检测
        if (p('commission')) {
            p('commission')->checkOrderFinish($item['id']);
        }
        //抽奖
        if(p('lottery')){
            //type 1:消费 2:签到 3:任务 4:其他
            $res = p('lottery')->getLottery($item['openid'],1,array('money'=>$item['price'],'paytype'=>2));
            if($res){
                p('lottery')->getLotteryList($item['openid'],array('lottery_id'=>$res));
            }
        }

        //        任务中心单笔满额
        if(p('task')){
            p('task')->checkTaskProgress($item['price'],'order_full','',$item['openid']);
        }

        plog('order.op.finish', "订单完成 ID: {$item['id']} 订单号: {$item['ordersn']}");
        show_json(1);
    }

    function fetchcancel() {

        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if($is_singlerefund){
            show_json(0, '订单商品存在维权，无法取消取货！');
        }

        if ($item['status'] != 3) {
            show_json(0, '订单未取货，不需取消！');
        }
        pdo_update(
            'ewei_shop_order', array(
            'status' => 1,
            'finishtime' => 0
        ), array('id' => $item['id'], 'uniacid' => $_W['uniacid'])
        );
        plog('order.op.fetchcancel', "订单取消取货 ID: {$item['id']} 订单号: {$item['ordersn']}");
        show_json(1);
    }

    function sendcancel() {
        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if($is_singlerefund){
            show_json(0, '订单商品存在维权，无法取消发货！');
        }

        $sendtype = intval($_GPC['sendtype']);
        if ($item['status'] != 2 && $item['sendtype'] == 0) {
            show_json(0, '订单未发货，不需取消发货！');
        }

        if ($_W['ispost']) {

            if (!empty($item['transid'])) {
                //changeWechatSend($item['ordersn'], 0, $_GPC['cancelreson']);
            }
            $remark = trim($_GPC['remark']);
            if(!empty($item['remarksend'])){
                $remark = $item['remarksend']."\r\n".$remark;
            }
            $data = array(
                'sendtime' => 0,
                'remarksend'=>$remark
            );
            //是否为包裹
            if($item['sendtype']>0){
                if(empty($sendtype)){
                    if(empty($_GPC['bundle'])){
                        show_json(0, "请选择您要修改的包裹！");
                    }
                    $sendtype = intval($_GPC['bundle']);
                }
                $data['sendtype'] = 0;
                pdo_update('ewei_shop_order_goods', $data, array('orderid' => $item['id'],'sendtype'=>$sendtype, 'uniacid' => $_W['uniacid']));
                $order = pdo_fetch("select sendtype from ".tablename('ewei_shop_order')." where id = ".$item['id']." and uniacid = ".$_W['uniacid']." ");
                pdo_update('ewei_shop_order', array('sendtype'=>$order['sendtype']-1,'status'=>1), array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
            }else{
                $data['status'] = 1;
                pdo_update('ewei_shop_order', $data, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
            }

            if ($item['paytype'] == 3) {
                //处理订单库存
                m('order')->setStocksAndCredits($item['id'], 2);
            }

            plog('order.op.sendcancel', "订单取消发货 ID: {$item['id']} 订单号: {$item['ordersn']} 原因: {$remark}");
            show_json(1);
        }
        //是否存在包裹
        $sendgoods = array();
        $bundles = array();
        if($sendtype>0){
            $sendgoods = pdo_fetchall('select g.id,g.title,g.thumb,og.sendtype,g.ispresell from ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
                . ' where og.uniacid=:uniacid and og.orderid=:orderid and og.sendtype='.$sendtype.' ', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));
        }else{
            if($item['sendtype']>0){
                for($i=1;$i<=intval($item['sendtype']);$i++){
                    $bundles[$i]['goods'] = pdo_fetchall('select g.id,g.title,g.thumb,og.sendtype,g.ispresell from ' . tablename('ewei_shop_order_goods') . ' og '
                        . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
                        . ' where og.uniacid=:uniacid and og.orderid=:orderid and og.sendtype='.$i.' ', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));
                    $bundles[$i]['sendtype'] = $i;
                    if(empty($bundles[$i]['goods'])){
                        unset($bundles[$i]);
                    }
                }
            }
        }
        include $this->template();
    }

    //虚拟商品收获
    function fetch() {

        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if($is_singlerefund){
            show_json(0, '订单商品存在维权，无法确认取货！');
        }

        if ($item['status'] != 1) {
            show_json(0, '订单未付款，无法确认取货！');
        }
        $time = time();
        $d = array(
            'status' => 3,
            'sendtime' => $time,
            'finishtime' => $time
        );

        if ($item['isverify'] == 1) {
            $d['verified'] = 1;
            $d['verifytime'] = $time;
            $d['verifyopenid'] = "";
        }
        pdo_update(
            'ewei_shop_order', $d, array('id' => $item['id'], 'uniacid' => $_W['uniacid'])
        );

        //商品全返
        m('order')->fullback($item['id']);
        //发送赠送优惠券
        if (com('coupon')) {
            com('coupon')->sendcouponsbytask($item['id']); //订单支付
        }

        //优惠券返利
        if (!empty($item['couponid'])) {
            com('coupon')->backConsumeCoupon($item['id']); //后台收货
        }

        //取消退款状态
        if (!empty($item['refundid'])) {
            $refund = pdo_fetch('select * from ' . tablename('ewei_shop_order_refund') . ' where id=:id limit 1', array(':id' => $item['refundid']));
            if (!empty($refund)) {
                pdo_update('ewei_shop_order_refund', array('status' => -1), array('id' => $item['refundid']));
                pdo_update('ewei_shop_order', array('refundstate' => 0), array('id' => $item['id']));
            }
        }



        $log = "订单确认取货";
        if (p('ccard') && !empty($item['ccardid'])) {
            p('ccard')->setBegin($item['id'], $item['ccardid']);
            $log = "订单确认充值";
        }

        $log .=  " ID: {$item['id']} 订单号: {$item['ordersn']}";

        //余额赠送
        m('order')->setGiveBalance($item['id'], 1);

        //处理积分
        m('order')->setStocksAndCredits($item['id'], 3);

        //会员升级
        m('member')->upgradeLevel($item['openid'],$item['id']);

        //模板消息
        m('notice')->sendOrderMessage($item['id']);

        //分销佣金
        if (p('commission')) {
            p('commission')->checkOrderFinish($item['id']);
        }

        plog('order.op.fetch', $log);
        show_json(1);
    }

    function send() {

        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if($is_singlerefund){
            show_json(0, '订单商品存在维权，无法发货！');
        }

        if (empty($item['addressid'])) {
            show_json(0, '无收货地址，无法发货！');
        }
        if ($item['paytype'] != 3) {
            if ($item['status'] != 1) {
                show_json(0, '订单未付款，无法发货！');
            }
        }
        if ($_W['ispost']) {
            //如果不是同城配送订单
            if($item['city_express_state']==0){
                    if (!empty($_GPC['isexpress']) && empty($_GPC['expresssn'])) {
                        show_json(0, '请输入快递单号！');
                    }
                    if (!empty($item['transid'])) {
                        //changeWechatSend($item['ordersn'], 1);
                    }

                    $time = time();
                    $data = array(
                        'sendtype' => $item['sendtype'] > 0 ? $item['sendtype'] : intval($_GPC['sendtype']),
                        'express' => trim($_GPC['express']),
                        'expresscom' => trim($_GPC['expresscom']),
                        'expresssn' => trim($_GPC['expresssn']),
                        'sendtime' => $time
                    );
                    //判断是否为包裹发货
                    if(intval($_GPC['sendtype'])==1 || $item['sendtype'] > 0){
                        if(empty($_GPC['ordergoodsid'])){
                            show_json(0,"请选择发货商品！");
                        }
                        $ogoods = array();
                        $ogoods = pdo_fetchall("select sendtype from ".tablename('ewei_shop_order_goods')."
                        where orderid = ".$item['id']." and uniacid = ".$_W['uniacid']." order by sendtype desc ");

                        $senddata = array(
                            'sendtype'=>$ogoods[0]['sendtype']+1,
                            'sendtime'=>$data['sendtime']
                        );
                        $data['sendtype'] = $ogoods[0]['sendtype']+1;
                        $goodsid = $_GPC['ordergoodsid'];
                        foreach($goodsid as $key => $value){
                            pdo_update('ewei_shop_order_goods', $data, array('id'=>$value, 'uniacid' => $_W['uniacid']));
                        }
                        //查询是否有未发货包裹
                        $send_goods = pdo_fetch("select * from ".tablename('ewei_shop_order_goods')."
                        where orderid = ".$item['id']." and sendtype = 0 and uniacid = ".$_W['uniacid']." limit 1 ");
                        //如果包裹都发货，修改订单状态
                        if(empty($send_goods)){
                            $senddata['status'] = 2 ;
                        }
                        $senddata['refundid'] =0 ;
                        pdo_update('ewei_shop_order', $senddata, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
                    }else{
                        $data['status'] = 2;
                        $data['refundid'] = 0;
                        pdo_update('ewei_shop_order', $data, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
                    }
                }else{

                $cityexpress = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_city_express') . " WHERE uniacid=:uniacid AND merchid=:merchid",array(":uniacid"=>$_W['uniacid'],":merchid"=>0));
                if($cityexpress['express_type']==1){
                    $dada=m('order')->dada_send($item);
                    if($dada['state']==0){
                        show_json(0, $dada['result']);
                    }else{
                        $data['status'] = 2;
                        $data['refundid'] = 0;
                        $data['sendtime'] = time();
                        pdo_update('ewei_shop_order', $data, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
                    }
                }else{
                    $data['status'] = 2;
                    $data['refundid'] = 0;
                    $data['sendtime'] = time();
                    pdo_update('ewei_shop_order', $data, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
                }

            }
            //取消退款状态
            if (!empty($item['refundid'])) {
                $refund = pdo_fetch('select * from ' . tablename('ewei_shop_order_refund') . ' where id=:id limit 1', array(':id' => $item['refundid']));
                if (!empty($refund)) {
                    pdo_update('ewei_shop_order_refund', array('status' => -1, 'endtime' => $time), array('id' => $item['refundid']));
                    pdo_update('ewei_shop_order', array('refundstate' => 0), array('id' => $item['id']));
                }
            }

            if ($item['paytype'] == 3) {
                //处理订单库存
                m('order')->setStocksAndCredits($item['id'], 1);
            }

            //模板消息
            m('notice')->sendOrderMessage($item['id']);
            plog('order.op.send', "订单发货 ID: {$item['id']} 订单号: {$item['ordersn']} <br/>快递公司: {$_GPC['expresscom']} 快递单号: {$_GPC['expresssn']}");
            show_json(1);
        }
        //订单商品
        $noshipped = array();
        $shipped = array();
        if($item['sendtype']>0){
            //未发货商品
            $noshipped = pdo_fetchall('select og.id,g.title,g.thumb,og.sendtype,g.ispresell from ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
                . ' where og.uniacid=:uniacid and og.sendtype = 0 and og.orderid=:orderid ', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));
            //已发货商品
            for($i=1;$i<=$item['sendtype'];$i++){
                $shipped[$i]['sendtype'] = $i;
                $shipped[$i]['goods'] = pdo_fetchall('select g.id,g.title,g.thumb,og.sendtype,g.ispresell from ' . tablename('ewei_shop_order_goods') . ' og '
                    . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
                    . ' where og.uniacid=:uniacid and og.sendtype = '.$i.' and og.orderid=:orderid ', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));
            }


        }

        $order_goods = pdo_fetchall('select og.id,g.title,g.thumb,og.sendtype,g.ispresell from ' . tablename('ewei_shop_order_goods') . ' og '
            . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
            . ' where og.orderid=:orderid and og.uniacid=:uniacid and og.single_refundtime=0', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));
        $address = iunserializer($item['address']);
        if (!is_array($address)) {
            $address = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_address') . " WHERE id = :id and uniacid=:uniacid", array(':id' => $item['addressid'], ':uniacid' => $_W['uniacid']));
        }
        $express_list = m('express')->getExpressList();

        include $this->template();
    }

    function remarksaler() {
        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        if ($_W['ispost']) {
            pdo_update('ewei_shop_order', array('remarksaler' => $_GPC['remark']), array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
            plog('order.op.remarksaler', "订单备注 ID: {$item['id']} 订单号: {$item['ordersn']} 备注内容: " . $_GPC['remark']);
            show_json(1);
        }
        include $this->template();
    }

    function changeexpress() {
        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);
        $changeexpress=1;
        $sendtype = intval($_GPC['sendtype']);


        $edit_flag = 1;
        if ($_W['ispost']) {

            $express = $_GPC['express'];
            $expresscom = $_GPC['expresscom'];
            $expresssn = trim($_GPC['expresssn']);

            if (empty($id)) {
                $ret = "参数错误！";
                show_json(0, $ret);
            }

            if (!empty($expresssn)) {
                $change_data = array();
                $change_data['express'] = $express;
                $change_data['expresscom'] = $expresscom;
                $change_data['expresssn'] = $expresssn;
                //是否为包裹发货
                if($item['sendtype']>0){
                    if(empty($sendtype)){
                        if(empty($_GPC['bundle'])){
                            show_json(0, "请选择您要修改的包裹！");
                        }
                        $sendtype = intval($_GPC['bundle']);
                    }
                    pdo_update('ewei_shop_order_goods', $change_data, array('orderid' => $id,'sendtype'=>$sendtype, 'uniacid' => $_W['uniacid']));
                }else{
                    pdo_update('ewei_shop_order', $change_data, array('id' => $id, 'uniacid' => $_W['uniacid']));
                }
				plog('order.op.changeexpress', "修改快递状态 ID: {$item['id']} 订单号: {$item['ordersn']} 快递公司: {$expresscom} 快递单号: {$expresssn}");
				
                show_json(1);
            } else {
                show_json(0, "请填写快递单号！");
            }
        }
        //是否存在包裹
        $sendgoods = array();
        $bundles = array();
        if($sendtype>0){
            $sendgoods = pdo_fetchall('select g.id,g.title,g.thumb,og.sendtype,g.ispresell from ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
                . ' where og.uniacid=:uniacid and og.orderid=:orderid and og.sendtype='.$sendtype.' ', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));
        }else{
            if($item['sendtype']>0){
                for($i=1;$i<=intval($item['sendtype']);$i++){
                    $bundles[$i]['goods'] = pdo_fetchall('select g.id,g.title,g.thumb,og.sendtype,g.ispresell from ' . tablename('ewei_shop_order_goods') . ' og '
                        . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
                        . ' where og.uniacid=:uniacid and og.orderid=:orderid and og.sendtype='.$i.' ', array(':uniacid' => $_W['uniacid'], ':orderid' => $item['id']));
                    $bundles[$i]['sendtype'] = $i;
                    if(empty($bundles[$i]['goods'])){
                        unset($bundles[$i]);
                    }
                }
            }
        }

        $address = iunserializer($item['address']);
        if (!is_array($address)) {
            $address = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_address') . " WHERE id = :id and uniacid=:uniacid", array(':id' => $item['addressid'], ':uniacid' => $_W['uniacid']));
        }

        $express_list = m('express')->getExpressList();

        include $this->template('order/op/send');
    }

    function changeaddress() {
        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        $area_set = m('util')->get_area_config_set();
        $new_area = intval($area_set['new_area']);
        $address_street = intval($area_set['address_street']);

        if (empty($item['addressid'])) {
            $user = unserialize($item['carrier']);
        } else {
            $user = iunserializer($item['address']);

            if (!is_array($user)) {
                $user = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_address') . " WHERE id = :id and uniacid=:uniacid", array(':id' => $item['addressid'], ':uniacid' => $_W['uniacid']));
            }
            $address_info = $user['address'];
            $user_address = $user['address'];
            $user['address'] = $user['province'] . ' ' . $user['city'] . ' ' . $user['area'] . ' ' . $user['street'] . ' ' . $user['address'];
            $item['addressdata'] =$oldaddress = array(
                'realname' => $user['realname'],
                'mobile' => $user['mobile'],
                'address' => $user['address'],
            );
        }

        if ($_W['ispost']) {
            $realname = $_GPC['realname'];
            $mobile = $_GPC['mobile'];
            $province = $_GPC['province'];
            $city = $_GPC['city'];
            $area = $_GPC['area'];
            $street = $_GPC['street'];
            $changead = intval($_GPC['changead']);
            $address = trim($_GPC['address']);

            if (!empty($id)) {
                if (empty($realname)) {
                    $ret = "请填写收件人姓名！";
                    show_json(0, $ret);
                }

                if (empty($mobile)) {
                    $ret = "请填写收件人手机！";
                    show_json(0, $ret);
                }

                if ($changead) {
                    if ($province == '请选择省份') {
                        $ret = "请选择省份！";
                        show_json(0, $ret);
                    }

                    if (empty($address)) {
                        $ret = "请填写详细地址！";
                        show_json(0, $ret);
                    }
                }

                $item = pdo_fetch("SELECT id, ordersn, address,openid FROM " . tablename('ewei_shop_order') . " WHERE id = :id and uniacid=:uniacid", array(':id' => $id, ':uniacid' => $_W['uniacid']));
                $address_array = iunserializer($item['address']);

                $address_array['realname'] = $realname;
                $address_array['mobile'] = $mobile;

                if ($changead) {
                    $address_array['province'] = $province;
                    $address_array['city'] = $city;
                    $address_array['area'] = $area;
                    $address_array['street'] = $street;
                    $address_array['address'] = $address;
                } else {
                    $address_array['province'] = $user['province'];
                    $address_array['city'] = $user['city'];
                    $address_array['area'] = $user['area'];
                    $address_array['street'] = $user['street'];
                    $address_array['address'] = $user_address;
                }

                $address_array = iserializer($address_array);

                pdo_update('ewei_shop_order', array('address' => $address_array), array('id' => $id, 'uniacid' => $_W['uniacid']));

				plog('order.op.changeaddress', "修改收货地址 ID: {$item['id']} 订单号: {$item['ordersn']} <br>原地址: 收件人: {$oldaddress['realname']} 手机号: {$oldaddress['mobile']} 收件地址: {$oldaddress['address']}<br>新地址: 收件人: {$realname} 手机号: {$mobile} 收件地址: {$province} {$city} {$area} {$address}");

                m('notice')->sendOrderChangeMessage($item['openid'],array(
                    'title'=>'订单收货地址',
                    'orderid'=>$item['id'],
                    'ordersn'=>$item['ordersn'],
                    'olddata'=>$oldaddress['address'],
                    'data'=>"{$province}{$city}{$area}{$address}",
                    'type'=>0
                ),'orderstatus');
				
                show_json(1);
            }
        }
        include $this->template();
    }

    function upload_invoice(){
        global $_W,$_GPC;
        $order_id = $_GPC['order_id'];
        if (!$_W['ispost']){
            $invoice = pdo_fetch('select invoicename,invoice_img from '.tablename('ewei_shop_order').' where id = :order_id and uniacid = :uniacid limit 1',array(':order_id'=>$order_id, ':uniacid'=>$_W['uniacid']));
            $invoice_info = m('sale')->parseInvoiceInfo($invoice['invoicename']);
            if ($invoice_info['title']){
                if ($invoice_info['entity']){
                    show_json(0,'本单不支持电子发票');
                }
                include $this->template();
                return;
            }
            show_json(0,'本单不支持电子发票');
        }
        $invoice_img = $_GPC['invoice_img'];
        if (empty($invoice_img)){
            show_json(0,'请选择图片');
        }
        $invoice_img = save_media($invoice_img);
        $update_ret = pdo_update('ewei_shop_order', array('invoice_img'=>$invoice_img), array('id'=>$order_id));
        $update_ret ? show_json(1):show_json(0,'电子发票上传失败，请重试');
    }

    //订单代付
    function peerpay() {
        global $_W,$_GPC;
        $order_id = $_GPC['id'];
        if(empty($order_id)){
            show_json(0,'参数错误');
        }
        $peerpay = m('order')->checkpeerpay($order_id);
        if(!$peerpay){
            show_json(0,'非代付订单');
        }

        $sql = "SELECT * FROM ".tablename('ewei_shop_order_peerpay_payinfo')." where pid=:pid";
        $list  = pdo_fetchall($sql, array(':pid'=>$peerpay['id']));

        include $this->template();
    }
}

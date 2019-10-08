<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Single_Refund_EweiShopV2Page extends WebPage {

    protected function opData() {

        global $_W, $_GPC;
        $id = intval($_GPC['id']);

        //订单商品 关联 订单表
        $item = pdo_fetch('select o.*,og.id as ordergoodsid,og.single_refundid,og.single_refundstate,og.single_refundtime from ' . tablename('ewei_shop_order_goods') .' og '
            . ' left join ' . tablename('ewei_shop_order') . ' o on o.id=og.orderid'
            . ' where og.id=:ordergoodsid limit 1'
            , array(':ordergoodsid' => $id));

        if (empty($item)) {
            if ($_W['isajax']) {
                show_json(0, "未找到订单!");
            }
            $this->message('未找到订单!', '', 'error');
        }

        if (!empty($item['single_refundid'])) {
            $refund = pdo_fetch('select * from ' . tablename('ewei_shop_order_single_refund') . ' where id=:id limit 1', array(':id' => $item['single_refundid']));

            $refund['imgs'] = iunserializer($refund['imgs']);
        }
        $r_type = array( '0' => '退款', '1' => '退货退款', '2' => '换货');

        return array('id' => $id, 'item' => $item, 'refund' => $refund, 'r_type' => $r_type,);
    }

    function submit() {
        global $_W, $_GPC, $_S;
        $opdata = $this->opData();
        extract($opdata);

        if ($_W['ispost']) {

            $shopset = $_S['shop'];

            if (empty($item['single_refundstate'])) {
                show_json(0,'订单未申请维权，不需处理！');
            }

            if ($refund['status'] < 0 || $refund['status'] == 1) {
                //pdo_update('ewei_shop_order_goods', array('single_refundstate' => 0), array('id' => $item['ordergoodsid'], 'uniacid' => $_W['uniacid']));
                show_json(0,'未找到需要处理的维权申请，不需处理！');
            }

            if (empty($refund['refundno'])) { //退款单号
                $refund['refundno'] = m('common')->createNO('order_refund', 'refundno', 'SR');
                pdo_update('ewei_shop_order_single_refund', array('refundno' => $refund['refundno']), array('id' => $refund['id']));
            }

            //处理退款
            $refundstatus = intval($_GPC['refundstatus']);
            $refundcontent = trim($_GPC['refundcontent']);

            //0暂不处理 1通过申请 2手动退款 3完成 -1拒绝申请
            $time = time();
            $change_refund = array();
            $uniacid = $_W['uniacid'];

            if ($refundstatus == 0) {
                show_json(1);

            } else if ($refundstatus == 3) {
                //商家通过申请，等待客户发货

                $raid = $_GPC['raid'];
                $message = trim($_GPC['message']);

                if ($raid == 0) {
                    $raddress = pdo_fetch('select * from '.tablename('ewei_shop_refund_address').' where isdefault=1 and uniacid=:uniacid and merchid=0 limit 1',array(':uniacid'=>$uniacid));
                } else {
                    $raddress = pdo_fetch('select * from '.tablename('ewei_shop_refund_address').' where id=:id and uniacid=:uniacid and merchid=0 limit 1',array(':id'=>$raid,':uniacid'=>$uniacid));
                }

                if (empty($raddress)) {
                    $raddress = pdo_fetch('select * from '.tablename('ewei_shop_refund_address').' where uniacid=:uniacid and merchid=0 order by id desc limit 1',array(':uniacid'=>$uniacid));
                }

                unset($raddress['uniacid']);
                unset($raddress['openid']);
                unset($raddress['isdefault']);
                unset($raddress['deleted']);

                $raddress = iserializer($raddress);

                $change_refund['reply'] = '';
                $change_refund['refundaddress'] = $raddress;
                $change_refund['refundaddressid'] = $raid;
                $change_refund['message'] = $message;

                if (empty($refund['operatetime'])) {
                    $change_refund['operatetime'] = $time;
                }

                if ($refund['status'] != 4) {
                    $change_refund['status'] = 3;
                }

                pdo_update('ewei_shop_order_single_refund', $change_refund, array('id' => $item['single_refundid']));

                //模板消息
                m('notice')->sendOrderMessage($item['id'], true,$raid);

            } else if ($refundstatus == 5) {
                //商家确认发货

                $change_refund['rexpress'] = $_GPC['rexpress'];
                $change_refund['rexpresscom'] = $_GPC['rexpresscom'];
                $change_refund['rexpresssn'] = trim($_GPC['rexpresssn']);
                $change_refund['status'] = 5;

                if ($refund['status'] != 5 && empty($refund['returntime'])) {
                    $change_refund['returntime'] = $time;

                    if (empty($refund['operatetime'])) {
                        $change_refund['operatetime'] = $time;
                    }
                }


                pdo_update('ewei_shop_order_single_refund', $change_refund, array('id' => $item['single_refundid']));

                //模板消息
                m('notice')->sendOrderMessage($item['id'], true);

            } else if ($refundstatus == 10) {
                //确认换货成功，关闭申请

                $refund_data['status'] = 1;
                $refund_data['refundtime'] = $time;
                pdo_update('ewei_shop_order_single_refund', $refund_data, array('id'=>$item['single_refundid'], 'uniacid' => $uniacid));

                $order_data = array();
                $order_data['single_refundstate'] = 9;
                pdo_update('ewei_shop_order_goods', $order_data, array('id'=>$item['ordergoodsid'], 'uniacid' => $uniacid));

                //模板消息
                m('notice')->sendOrderMessage($item['id'], true);

            } else if ($refundstatus == 1) {
                //同意退款











                //订单号
                if ($item['parentid'] > 0) {
                    $parent_item = pdo_fetch("SELECT id,ordersn,ordersn2,price,transid,paytype,apppay FROM " . tablename('ewei_shop_order') . " WHERE id = :id and uniacid=:uniacid Limit 1", array(':id' => $item['parentid'], ':uniacid' => $_W['uniacid']));
                    if (empty($parent_item)) {
                        show_json(0, "未找到退款订单!");
                    }
                    $order_price = $parent_item['price'];
                    $ordersn = $parent_item['ordersn'];
                    $item['transid'] = $parent_item['transid'];
                    $item['paytype'] = $parent_item['paytype'];
                    $item['apppay'] = $parent_item['apppay'];
                    if(!empty($parent_item['ordersn2'])){
                        $var = sprintf("%02d", $parent_item['ordersn2']);
                        $ordersn.="GJ".$var;
                    }
                } else {
                    $borrowopenid = $item['borrowopenid'];
                    $ordersn = $item['ordersn'];
                    $order_price = $item['price'];
                    if (!strexists($borrowopenid,'2088') && !is_numeric($borrowopenid)){
                        if(!empty($item['ordersn2'])){
                            $var = sprintf("%02d", $item['ordersn2']);
                            $ordersn.="GJ".$var;
                        }
                    }
                }

                //退款金额
                $applyprice = $refund['applyprice'];

                $pay_refund_price = 0; //在线支付的退款金额
                $dededuct__refund_price = 0; //余额抵扣退款的金额

                if($applyprice<=$item['price']){
                    //申请金额小于实际支付金额
                    $pay_refund_price =$applyprice; //在线支付的退款金额
                    $dededuct__refund_price = 0; //余额抵扣退款的金额
                }elseif( $applyprice> $item['price'] && $applyprice<= $item['price'] +  $item['deductcredit2']){
                    $pay_refund_price = $item['price']; //在线支付的退款金额
                    $dededuct__refund_price =  $applyprice -$pay_refund_price ; //余额抵扣退款的金额
                }else {
                    //错误
                    $pay_refund_price = $item['price']; //在线支付的退款金额
                    $dededuct__refund_price =  $item['deductcredit2']; //余额抵扣退款的金额
                }

                $refundtype = 0;

                if (empty($item['transid']) && $item['paytype']==22 && empty($item['apppay'])){
                    $item['paytype'] = 23;
                }
                if (!empty($item['transid']) && $item['paytype']==22 && empty($item['apppay']) && strexists($item['borrowopenid'],'2088')){
                    $item['paytype'] = 23;
                }
                //检查是都是代付订单
                $ispeerpay = m('order')->checkpeerpay($item['id']);
                //如果是代付订单,微信退款
                if (!empty($ispeerpay)){
                    show_json(0, "代付订单不支持单品退换!");
                }

                if ($item['paytype'] == 1) {
                    //余额支付，直接返回余额
                    m('member')->setCredit($item['openid'], 'credit2', $pay_refund_price, array(0, $shopset['name'] . "退款: {$pay_refund_price}元 订单号: " . $item['ordersn']));
                    $result = true;

                    $refundtype = 0;
                } else if ($item['paytype'] == 21) {

                    if ($item['apppay']==2){
                        //如果是小程序的则走小程序配置
                        $result = m('finance')->wxapp_refund($item['openid'], $ordersn, $refund['refundno'], $order_price * 100, $pay_refund_price * 100, !empty($item['apppay']) ? true : false);
                    }else{
                        //微信支付，走退款 接口

                        if ($pay_refund_price > 0) {
                            if (empty($item['isborrow'])) {
                                $result = m('finance')->refund($item['openid'], $ordersn, $refund['refundno'], $order_price * 100, $pay_refund_price * 100, !empty($item['apppay']) ? true : false);
                            } else {
                                $result = m('finance')->refundBorrow($item['borrowopenid'], $ordersn, $refund['refundno'], $order_price * 100, $pay_refund_price * 100, !empty($item['ordersn2']) ? 1 : 0);
                            }
                        }

                    }
                    $refundtype = 2;
                }elseif($item['paytype']==22){
                    $sec = m('common')->getSec();
                    $sec =iunserializer($sec['sec']);
                    if(!empty($item['apppay'])){
                        if(!empty($sec['app_alipay']['private_key_rsa2'])){
                            $sign_type = 'RSA2';
                            $privatekey=$sec['app_alipay']['private_key_rsa2'];
                        }else{
                            $sign_type = 'RSA';
                            $privatekey=$sec['app_alipay']['private_key'];
                        }
                        // new & app
                        if(empty($privatekey) || empty($sec['app_alipay']['appid'])){
                            show_json(0,'支付参数错误，私钥为空或者APPID为空!');
                        }
                        $params = array('out_request_no'=>time(),'out_trade_no' => $ordersn,'refund_amount'=>$pay_refund_price,'refund_reason' => $shopset['name'] . "退款: {$pay_refund_price}元 订单号: " . $item['ordersn']);
                        $config = array('app_id' => $sec['app_alipay']['appid'], 'privatekey' =>$privatekey, 'publickey' => "", 'alipublickey' => "",'sign_type'=>$sign_type);
                        $result = m('finance')->newAlipayRefund($params, $config);
                    }else if(!empty($sec['alipay_pay'])){
                        //新版支付宝退款
                        if(empty($sec['alipay_pay']['private_key']) || empty($sec['alipay_pay']['appid'])){
                            show_json(0,'支付参数错误，私钥为空或者APPID为空!');
                        }
                        if($sec['alipay_pay']['alipay_sign_type'] == 1){
                            $sign_type = 'RSA2';
                        }else{
                            $sign_type = 'RSA';
                        }
                        $params = array('out_request_no'=>time(),'out_trade_no' => $item['ordersn'],'refund_amount'=>$pay_refund_price,'refund_reason' => $shopset['name'] . "退款: {$pay_refund_price}元 订单号: " . $item['ordersn']);
                        $config = array('app_id' => $sec['alipay_pay']['appid'], 'privatekey' => $sec['alipay_pay']['private_key'], 'publickey' => "", 'alipublickey' => "",'sign_type'=>$sign_type);
                        $result = m('finance')->newAlipayRefund($params, $config);
                    }else{
                        // old
                        if (empty($item['transid'])){
                            show_json(0,'仅支持 升级后此功能后退款的订单!');
                        }
                        $setting = uni_setting($_W['uniacid'], array('payment'));
                        if (!is_array($setting['payment'])) {
                            return error(1, '没有设定支付参数');
                        }
                        $alipay_config = $setting['payment']['alipay'];
                        $batch_no_money = $pay_refund_price*100;
                        $batch_no = date('Ymd').'RF'.$item['id'].'MONEY'.$batch_no_money;
                        $res = m('finance')->AlipayRefund(array(
                            'trade_no'=> $item['transid'],
                            'refund_price'=> $pay_refund_price,
                            'refund_reason'=> $shopset['name'] . "退款: {$pay_refund_price}元 订单号: " . $item['ordersn'],
                        ),$batch_no,$alipay_config);

                        if (is_error($res)) show_json(0,$res['message']);
                        show_json(1,array('url'=>$res));
                    }
                    $refundtype=3;
                }elseif($item['paytype']==23 && !empty($item['isborrow'])){
                    $result = m('finance')->refundBorrow($item['borrowopenid'], $ordersn, $refund['refundno'], $order_price * 100, $pay_refund_price * 100, !empty($item['ordersn2']) ? 1 : 0);
                    $refundtype=4;
                } else {
                    //其他支付方式，走微信企业付款
                    if ($pay_refund_price < 1) {
                        show_json(0,'退款金额必须大于1元，才能使用微信企业付款退款!');
                    }

                    if ($pay_refund_price > 0) {
                        $result = m('finance')->pay($item['openid'], 1, $pay_refund_price * 100, $refund['refundno'], $shopset['name'] . "退款: {$pay_refund_price}元 订单号: " . $item['ordersn']);
                    }
                    $refundtype = 1;
                }

                if (is_error($result)) {
                    show_json(0,$result['message']);
                }


                if ($dededuct__refund_price > 0) {
                    $item['deductcredit2'] = $dededuct__refund_price;
                    m('order')->setDeductCredit2($item);
                }

                $change_refund['reply'] = '';
                $change_refund['status'] = 1;
                $change_refund['refundtype'] = $refundtype;
                $change_refund['price'] = $applyprice;
                $change_refund['refundtime'] = $time;

                if (empty($refund['operatetime'])) {
                    $change_refund['operatetime'] = $time;
                }

                //同意
                pdo_update('ewei_shop_order_single_refund', $change_refund, array('id' => $item['single_refundid']));

                //处理赠送积分余额以及库存
                $this->refund_after($item,$time,$shopset);

                $log = "订单退款 ID: {$item['id']} 订单号: {$item['ordersn']}";

                if ($item['parentid'] > 0) {
                    $log .= " 父订单号:{$ordersn}";
                }

                plog('order.op.refund.submit', $log);

                //模板消息
                m('notice')->sendOrderMessage($item['id'], true);

            } else if ($refundstatus == -1) {
                //驳回申请

                pdo_update('ewei_shop_order_single_refund', array('reply' => $refundcontent, 'status' => -1, 'endtime' => $time), array('id' => $item['single_refundid']));

                plog('order.op.refund.submit', "订单退款拒绝 ID: {$item['id']} 订单号: {$item['ordersn']} 原因: {$refundcontent}");

                //更新订单商品退款状态
                pdo_update('ewei_shop_order_goods', array('single_refundstate' => 8), array('id' => $item['ordergoodsid'], 'uniacid' => $uniacid));

                //查询其它 订单商品 是否有正在维权中的
                $is_single_refund=pdo_fetchcolumn('select count(id) from '.tablename('ewei_shop_order_goods').'where orderid=:orderid and (single_refundstate=1 or single_refundstate=2)',array(':orderid'=>$item['id']));

                //如果其它 订单商品 没有正在维权中
                if(empty($is_single_refund)){
                    //更新订单维权时间
                    pdo_update('ewei_shop_order', array('refundtime' => $time), array('id' => $item['id'], 'uniacid' => $uniacid));
                }



                //模板消息
                m('notice')->sendOrderMessage($item['id'], true);
            } else if ($refundstatus == 2) {
                //手动退款

                //同意
                $refundtype = 2;

                $change_refund['reply'] = '';
                $change_refund['status'] = 1;
                $change_refund['refundtype'] = $refundtype;
                $change_refund['price'] = $refund['applyprice'];
                $change_refund['refundtime'] = $time;

                if (empty($refund['operatetime'])) {
                    $change_refund['operatetime'] = $time;
                }

                pdo_update('ewei_shop_order_single_refund', $change_refund, array('id' => $item['single_refundid']));

                //处理赠送积分余额以及库存
                $this->refund_after($item,$time,$shopset);

                //模板消息
                m('notice')->sendOrderMessage($item['id'], true);
            }
            show_json(1);
        }

        //查询退货地址
        $refund_address = pdo_fetchall('select * from '.tablename('ewei_shop_refund_address').' where uniacid=:uniacid and merchid=0',array(':uniacid'=>$_W['uniacid']));
        $express_list = m('express')->getExpressList();

        include $this->template();

    }


    //处理赠送积分余额以及库存
    function refund_after($item,$time,$shopset){
        global $_W, $_GPC;


        //订单商品
        $goods = pdo_fetch("SELECT og.goodsid,og.total,g.totalcnf,og.realprice,g.money,og.optionid,g.total as goodstotal,og.optionid,g.sales,g.salesreal,g.credit,og.seckill,og.consume FROM "
            . tablename('ewei_shop_order_goods').'og'
            . " left join " . tablename('ewei_shop_goods') . " g on og.goodsid=g.id "
            . " WHERE og.id=:ordergoodsid and og.uniacid=:uniacid limit 1", array(':ordergoodsid' => $item['ordergoodsid'], ':uniacid' => $_W['uniacid']));


        $consume=iunserializer($goods['consume']);


        //处理赠送积分情况
        $credit1=m('ordergoods')->getGoodsCredit1($goods);
        //减少积分，只有订单完成才减少
        if ($item['status'] == 3 && $credit1>0) {
            m('member')->setCredit($item['openid'], 'credit1', -$credit1, array(0, $shopset['name'] . '购物取消订单扣除积分 订单号: ' . $item['ordersn']));
            m('notice')->sendMemberPointChange($item['openid'],$credit1,1,3);
        }

        //处理赠送余额情况
        $credit2 = m('ordergoods')->getGoodsCredit2($goods);
        //用户余额
        if ($credit2 > 0) {
            //订单售后,扣除赠送的余额
            if ($item['status'] >= 1) {
                m('member')->setCredit($item['openid'], 'credit2', -$credit2, array(0, $shopset['name'] . '购物取消订单扣除赠送余额 订单号: ' . $item['ordersn']));
            }
        }

        if(!empty($consume)){
            //返还抵扣积分
            if(!empty($consume['consume_deduct'])){
                m('member')->setCredit($item['openid'], 'credit1', $consume['consume_deduct'], array(0, $shopset['name'] . '返还抵扣积分 订单号: ' . $item['ordersn']));
            }

            //返还抵扣余额
            if(!empty($consume['consume_deduct2'])){
                m('member')->setCredit($item['openid'], 'credit2', $consume['consume_deduct2'], array(0, $shopset['name'] . '返还抵扣余额 订单号: ' . $item['ordersn']));
            }
        }

        //更新库存
        m('ordergoods')->setStock($goods);

        //更新订单退款状态
        pdo_update('ewei_shop_order_goods', array('single_refundstate' => 9,'single_refundtime' => $time,'nocommission'=>1), array('id' => $item['ordergoodsid'], 'uniacid' => $_W['uniacid']));


        $order_goods=pdo_fetchall("select single_refundid,single_refundstate,single_refundtime from ".tablename('ewei_shop_order_goods')." where orderid=:orderid",array(':orderid'=>$item['id']));

        $refund_num=0;//退款过的订单商品数量
        $apply_refund_num=0;//申请维权中的订单商品数量
        foreach ($order_goods as $og){
            if($og['single_refundtime']>0){
                $refund_num++;
            }
            if($og['single_refundstate']==1 || $og['single_refundstate']==2){
                $apply_refund_num++;
            }
        }

        //如果退款数等于订单商品数则退还优惠券
        if($refund_num==count($order_goods)){
            //退还优惠券
            if (com('coupon') && !empty($item['couponid'])) {
                com('coupon')->returnConsumeCoupon($item['id']); //申请退款成功
            }

            pdo_update('ewei_shop_order', array('status' => -1,'canceltime' => $time,'refundid'=>0), array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
            plog('order.op.close', "订单关闭 ID: {$item['id']} 订单号: {$item['ordersn']}");
        }

        //如果没有正在维权中的订单商品
        if(empty($apply_refund_num)){
            //更新订单维权时间
            pdo_update('ewei_shop_order', array('refundtime' => $time,'refundid'=>0), array('id' => $item['id'], 'uniacid' => $_W['uniacid']));
        }



        //更新实际销量
        $salesreal = pdo_fetchcolumn('select ifnull(sum(total),0) from ' . tablename('ewei_shop_order_goods') . ' og '
            . ' left join ' . tablename('ewei_shop_order') . ' o on o.id = og.orderid '
            . ' where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1', array(':goodsid' => $item['ordergoodsid'], ':uniacid' => $_W['uniacid']));
        pdo_update('ewei_shop_goods', array('salesreal' => $salesreal), array('id' => $item['ordergoodsid']));
    }



    function main() {

        global $_W, $_GPC;
        $opdata = $this->opData();
        extract($opdata);

        $step_array = array();
        $step_array[1]['step'] = 1;
        $step_array[1]['title'] = '客户申请维权';
        $step_array[1]['time'] = $refund['createtime'];
        $step_array[1]['done'] = 1;

        $step_array[2]['step'] = 2;
        $step_array[2]['title'] = '商家处理维权申请';
        $step_array[2]['done'] = 1;

        $step_array[3]['step'] = 3;
        $step_array[3]['done'] = 0;

        if ($refund['status'] >= 0) {

            if ($refund['rtype'] == 0) {
                $step_array[3]['title'] = '退款完成';

            } else if ($refund['rtype'] == 1) {
                $step_array[3]['title'] = '客户退回物品';
                $step_array[4]['step'] = 4;
                $step_array[4]['title'] = '退款退货完成';

            } else if ($refund['rtype'] == 2) {
                $step_array[3]['title'] = '客户退回物品';
                $step_array[4]['step'] = 4;
                $step_array[4]['title'] = '商家重新发货';
                $step_array[5]['step'] = 5;
                $step_array[5]['title'] = '换货完成';
            }

            if ($refund['status'] == 0) {
                $step_array[2]['done'] = 0;
                $step_array[3]['done'] = 0;
            }

            if ($refund['rtype'] == 0) {
                if ($refund['status'] > 0) {
                    $step_array[2]['time'] = $refund['refundtime'];
                    $step_array[3]['done'] = 1;
                    $step_array[3]['time'] = $refund['refundtime'];
                }
            } else {
                $step_array[2]['time'] = $refund['operatetime'];

                if ($refund['status'] == 1 || $refund['status'] >= 4) {
                    $step_array[3]['done'] = 1;
                    $step_array[3]['time'] = $refund['sendtime'];
                }

                if ($refund['status'] == 1 || $refund['status'] == 5) {
                    $step_array[4]['done'] = 1;

                    if ($refund['rtype'] == 1) {
                        $step_array[4]['time'] = $refund['refundtime'];
                    } else if ($refund['rtype'] == 2) {
                        $step_array[4]['time'] = $refund['returntime'];

                        if ($refund['status'] == 1) {
                            $step_array[5]['done'] = 1;
                            $step_array[5]['time'] = $refund['refundtime'];
                        }
                    }
                }
            }

        } else if ($refund['status'] == -1) {
            //拒绝申请
            $step_array[2]['done'] = 1;
            $step_array[2]['time'] = $refund['endtime'];

            $step_array[3]['done'] = 1;
            $step_array[3]['title'] = '拒绝' . $r_type[$refund['rtype']];
            $step_array[3]['time'] = $refund['endtime'];

        } else if ($refund['status'] == -2) {
            //客户取消申请
            if (!empty($refund['operatetime'])) {
                $step_array[2]['done'] = 1;
                $step_array[2]['time'] = $refund['operatetime'];
            }

            $step_array[3]['done'] = 1;
            $step_array[3]['title'] = '客户取消' . $r_type[$refund['rtype']];
            $step_array[3]['time'] = $refund['refundtime'];
        }


        $goods = pdo_fetch("SELECT g.*, og.goodssn as option_goodssn, og.productsn as option_productsn,og.total,g.type,og.optionname,og.optionid,og.price as orderprice,og.realprice,og.changeprice,og.oldprice,og.commission1,og.commission2,og.commission3,og.commissions {$diyformfields} FROM "
            . tablename('ewei_shop_order_goods').'og'
            . " left join " . tablename('ewei_shop_goods') . " g on og.goodsid=g.id "
            . " WHERE og.id=:ordergoodsid and og.uniacid=:uniacid", array(':ordergoodsid' => $refund['ordergoodsid'], ':uniacid' => $_W['uniacid']));

        if (!empty($goods['option_goodssn'])) {
            $goods['goodssn'] = $goods['option_goodssn'];
        }
        if (!empty($goods['option_productsn'])) {
            $goods['productsn'] = $goods['option_productsn'];
        }
        if (p('diyform')) {
            $goods['diyformfields'] = iunserializer($goods['diyformfields']);
            $goods['diyformdata'] = iunserializer($goods['diyformdata']);
        }

        $member = m('member')->getMember($item['openid']);
        $express_list = m('express')->getExpressList();
        include $this->template();
    }

}

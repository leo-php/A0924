<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Virtual_EweiShopV2ComModel extends ComModel {

    public function updateGoodsStock($id = 0) {

        global $_W, $_GPC;
        $goods = pdo_fetch('select `virtual`,merchid from ' . tablename('ewei_shop_goods') . ' where id=:id and type=3 and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));

        if (empty($goods)) {
            return;
        }
        $merchid = $goods['merchid'];

        $stock = 0;
        //虚拟物品(卡密) 库存
        if (!empty($goods['virtual'])) {
            //单规格
            $stock = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_virtual_data') . " where typeid=:typeid and uniacid=:uniacid and merchid = :merchid and openid='' limit 1", array(':typeid' => $goods['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
        } else {
            $virtuals = array();
            $alloptions = pdo_fetchall("select id, `virtual` from " . tablename('ewei_shop_goods_option') . " where goodsid=$id");

            foreach ($alloptions as $opt) {
                if (empty($opt['virtual'])) {
                    continue;
                }

                $c = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_virtual_data') . " where typeid=:typeid and uniacid=:uniacid and merchid = :merchid and openid='' limit 1", array(':typeid' => $opt['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                pdo_update('ewei_shop_goods_option', array('stock' => $c), array('id' => $opt['id']));
                if (!in_array($opt['virtual'], $virtuals)) {
                    $virtuals[] = $opt['virtual'];
                    $stock+=$c;
                }
            }
        }
        pdo_update("ewei_shop_goods", array("total" => $stock), array("id" => $id));
    }

    public function updateStock($typeid = 0) {
        global $_W;
        $goodsids = array();

        //先找单规格的
        $goods = pdo_fetchall('select id from ' . tablename('ewei_shop_goods') . ' where `type`=3 and `virtual`=:virtual and uniacid=:uniacid', array(':virtual' => $typeid, ':uniacid' => $_W['uniacid']));
        foreach ($goods as $g) {
            $goodsids[] = $g['id'];
        }

        //多规格的
        $alloptions = pdo_fetchall("select id, goodsid from " . tablename('ewei_shop_goods_option') . " where `virtual`=:virtual and uniacid=:uniacid", array(':uniacid' => $_W['uniacid'], ':virtual' => $typeid));
        foreach ($alloptions as $opt) {
            if (!in_array($opt['goodsid'], $goodsids)) {
                $goodsids[] = $opt['goodsid'];
            }
        }
        foreach ($goodsids as $gid) {
            $this->updateGoodsStock($gid);
        }
    }

    //未付款分配虚拟商品
    public function pay_befo($order){
        global $_W, $_GPC;
        $orderid_cache = m("cache")->getString("orderid_".$order['id']);
        if(empty($orderid_cache))
        {
            m("cache")->set("orderid_".$order['id'],1);
        }else
        {
            return false;
        }


        $open_redis = function_exists('redis') && !is_error(redis());

        //虚拟物品直接发货
        $goods = pdo_fetch('select id,goodsid,total,realprice from ' . tablename('ewei_shop_order_goods') . ' where  orderid=:orderid and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':orderid' => $order['id']));
        $g = pdo_fetch('select id,credit,sales,salesreal from ' . tablename('ewei_shop_goods') . ' where  id=:id and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $goods['goodsid']));

        //如果redis可用，则取最后一个使用过的卡密id 拼接 sql
        $last_where='';
        if($open_redis) {
            $redis=redis();
            $last_id = $redis->get($_W['uniacid'].'_last_virtual_id_'.$order['virtual'].'_'.$order['merchid']);
            $last_used = pdo_fetch('select id,typeid,is_top,sort_time from ' . tablename('ewei_shop_virtual_data') . ' where  id=:id and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $last_id));
            //如果上一条不是被置顶的卡密数据 , 则下一条ID+1
            if (!empty($last_id) && !empty($last_used) && $last_used['is_top'] == 0) {
                $last_id=intval($last_id);
                $last_where=" and id>".$last_id;
            }
        }

        $sort_order = 'orderid ASC,is_top desc,sort_time desc,id ASC';
        $virtual_data = pdo_fetchall('SELECT id,typeid,fields FROM ' . tablename('ewei_shop_virtual_data') . ' WHERE typeid=:typeid and orderid=:orderid and uniacid=:uniacid and merchid = :merchid '.$last_where.' order by '.$sort_order.' limit ' . $goods['total'], array(':orderid' => 0, ':typeid' => $order['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $order['merchid']));
        if(count($virtual_data) < $goods['total']){
            return array('error'=>-1,'message'=>'库存不足');
        }

        //将最后一个将要使用的卡密id存到redis
        if(!empty($virtual_data)){
            $last_virtual_id = max(array_column($virtual_data, 'id'));
            if($open_redis && !empty($last_virtual_id)) {
                $redis=redis();
                $redis->set($_W['uniacid'].'_last_virtual_id_'.$order['virtual'].'_'.$order['merchid'], $last_virtual_id, 30);
            }
        }

        $type = pdo_fetch('select fields from ' . tablename('ewei_shop_virtual_type') . ' where id=:id and uniacid=:uniacid and merchid = :merchid limit 1 ', array(':id' => $order['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $order['merchid']));
        $fields = iunserializer($type['fields'], true);
        $virtual_info = array();
        $virtual_str = array();
        foreach ($virtual_data as $vd) {

            //数据
            $virtual_info[] = $vd['fields'];

            //显示
            $strs = array();
            $vddatas = iunserializer($vd['fields']);

            foreach ($vddatas as $vk => $vv) {
                $strs[] = $fields[$vk] . ": " . $vv;
            }
            $virtual_str[] = implode(" ", $strs);

            //更新虚拟物品数据
            pdo_update('ewei_shop_virtual_data', array(
                'openid' => $order['openid'],
                'orderid' => $order['id'],
                'ordersn' => $order['ordersn'],
                'price' => round($goods['realprice'] / $goods['total'], 2),
                'usetime' => time()
            ), array('id' => $vd['id']));
            pdo_update('ewei_shop_virtual_type', 'usedata=usedata+1', array('id' => $vd['typeid']));
            //更新库存
//            $this->updateStock($vd['typeid']); //弃用（解决一次购买N件商品时卡顿的问题） SuniW-2018.7.23
        }
        //更新库存
        $this->updateStock($order['virtual']);
        $virtual_str = implode("\n", $virtual_str);
        $virtual_info = "[" . implode(",", $virtual_info) . "]";

        //更新订单的状态
        $time = time();
        pdo_update('ewei_shop_order', array('virtual_info' => $virtual_info, 'virtual_str' => $virtual_str, 'sendtime' => $time), array('id' => $order['id']));

        //将卡密内容存到redis里面
        if($open_redis && !empty($virtual_str)) {
            $redis=redis();
            $redis->set($order['id'] . '_virtual_str', $virtual_str, 30);
        }
        return true;
    }

    public function pay($order,$ispeerpay=false) {
        global $_W, $_GPC;


        //虚拟物品直接发货
        $goods = pdo_fetch('select id,goodsid,total,realprice from ' . tablename('ewei_shop_order_goods') . ' where  orderid=:orderid and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':orderid' => $order['id']));
        $g = pdo_fetch('select id,credit,sales,salesreal from ' . tablename('ewei_shop_goods') . ' where  id=:id and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $goods['goodsid']));
        $virtual_data = pdo_fetchall('SELECT id,typeid,fields FROM ' . tablename('ewei_shop_virtual_data') . ' WHERE typeid=:typeid and openid=:openid and uniacid=:uniacid and merchid = :merchid order by id asc limit ' . $goods['total'], array(':openid' => '', ':typeid' => $order['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $order['merchid']));
        $type = pdo_fetch('select fields from ' . tablename('ewei_shop_virtual_type') . ' where id=:id and uniacid=:uniacid and merchid = :merchid limit 1 ', array(':id' => $order['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $order['merchid']));


        //更新订单的状态
        $time = time();
        pdo_update('ewei_shop_order', array('status' => '3', 'paytime' => $time, 'sendtime' => $time, 'finishtime' => $time), array('id' => $order['id']));

        //处理余额抵扣
        /*if ($order['deductcredit2'] > 0) {
            $shopset = m('common')->getSysset('shop');
            m('member')->setCredit($order['openid'], 'credit2', -$order['deductcredit2'], array(0, $shopset['name'] . "余额抵扣: {$order['deductcredit2']} 订单号: " . $order['ordersn']));
        }*/

        //购物积分
        //$credits = $goods['total'] * $g['credit'];
        $credits = 0;
        $gcredit = trim($g['credit']);
        if (!empty($gcredit)) {
            if (strexists($gcredit, '%')) {
                //按比例计算
                $credits += intval(floatval(str_replace('%', '', $gcredit)) / 100 * $goods['realprice']);
            } else {
                //按固定值计算
                $credits += intval($g['credit']) * $goods['total'];
            }
        }
        if ($credits > 0) {
            $shopset = m('common')->getSysset('shop');
            m('member')->setCredit($order['openid'], 'credit1', $credits, array(0, $shopset['name'] . '购物积分 订单号: ' . $order['ordersn']));
        }else{
                //积分活动订单送积分
                $money = com_run('sale::getCredit1', $order['openid'], (float)$order['price'], $order['paytype'], 1);
                if ($money > 0) {
                    m('notice')->sendMemberPointChange($order['openid'], $money, 0,3);
                }
            }
            
        //实际销量
        $salesreal = pdo_fetchcolumn('select ifnull(sum(total),0) from ' . tablename('ewei_shop_order_goods') . ' og '
            . ' left join ' . tablename('ewei_shop_order') . ' o on o.id = og.orderid '
            . ' where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1', array(':goodsid' => $g['id'], ':uniacid' => $_W['uniacid']));
        pdo_update('ewei_shop_goods', array('salesreal' => $salesreal), array('id' => $g['id']));

        //商品全返
        m('order')->fullback($order['id']);
        //会员升级
        m('member')->upgradeLevel($order['openid'], $order['id']);

        //模板消息
        m('notice')->sendOrderMessage($order['id']);

        //余额赠送
        m('order')->setGiveBalance($order['id'], 1);

        //小票打印
        com_run('printer::sendOrderMessage', $order['id']);

        //发送赠送优惠券
        if (com('coupon')) {
            com('coupon')->sendcouponsbytask($order['id']); //订单支付
        }

        //优惠券返利
        if (com('coupon') && !empty($order['couponid'])) {
            com('coupon')->backConsumeCoupon($order['id']); //虚拟物品支付
        }

        //分销
        if (p('commission')) {
            //付款后
            p('commission')->checkOrderPay($order['id']);

            //收货后
            p('commission')->checkOrderFinish($order['id']);
        }

        if (p('task')){

            //余额抵扣加入金额计算
            if($order['deductcredit2'] > 0 ){
                $order['price'] = floatval($order['price']) + floatval($order['deductcredit2']);
            }
            //积分抵扣加入金额计算
            if($order['deductcredit'] > 0){
                $order['price'] = floatval($order['price']) + floatval($order['deductprice']);
            }

            if ($order['agentid']){
                p('task')->checkTaskReward('commission_order',1);//分销订单
            }
            p('task')->checkTaskReward('cost_total',$order['price']);
            p('task')->checkTaskReward('cost_enough',$order['price']);
            p('task')->checkTaskReward('cost_count',1);
            $goodslist = pdo_fetchall("SELECT goodsid FROM ".tablename('ewei_shop_order_goods')." WHERE orderid = :orderid AND uniacid = :uniacid",array(':orderid'=>$order['id'], ':uniacid'=>$order['uniacid']));
            foreach($goodslist as $item) {
                p('task')->checkTaskReward('cost_goods'.$item['goodsid'],1,$order['openid']);
            }

            //余额抵扣加入金额计算
            if($order['deductcredit2'] > 0 ){
                $order['price'] = floatval($order['price']) + floatval($order['deductcredit2']);
            }
            //积分抵扣加入金额计算
            if($order['deductcredit'] > 0){
                $order['price'] = floatval($order['price']) + floatval($order['deductprice']);
            }

            //订单满额
            p('task')->checkTaskProgress($order['price'],'order_full');
            p('task')->checkTaskProgress($order['price'],'order_all');
            //购买指定商品
            $goodslist = pdo_fetchall("SELECT goodsid FROM ".tablename('ewei_shop_order_goods')." WHERE orderid = :orderid AND uniacid = :uniacid",array(':orderid'=>$order['id'], ':uniacid'=>$order['uniacid']));
            foreach($goodslist as $item) {
                p('task')->checkTaskProgress(1,'goods',0,'',$item['goodsid']);
            }
            //首次购物
            if (pdo_fetchcolumn("select count(*) from ".tablename('ewei_shop_order')." where openid = '{$order['openid']}' and uniacid = {$order['uniacid']}")==1){
                p('task')->checkTaskProgress(1,'order_first');
            }
        }

        //抽奖模块
        if(p('lottery')&&empty($ispeerpay)){
            //余额抵扣加入金额计算
            if($order['deductcredit2'] > 0 ){
                $order['price'] = floatval($order['price']) + floatval($order['deductcredit2']);
            }
            //积分抵扣加入金额计算
            if($order['deductcredit'] > 0){
                $order['price'] = floatval($order['price']) + floatval($order['deductprice']);
            }

            //type 1:消费 2:签到 3:任务 4:其他
            $res = p('lottery')->getLottery($order['openid'],1,array('money'=>$order['price'],'paytype'=>1));

            if($res){
                //发送模版消息
                p('lottery')->getLotteryList($order['openid'],array('lottery_id'=>$res));
            }
        }

        return true;
    }


//    public function pay($order) {
//
//        global $_W, $_GPC;
//
//        $orderid_cache = m("cache")->getString("orderid_".$order['id']);
//        if(empty($orderid_cache))
//        {
//            m("cache")->set("orderid_".$order['id'],1);
//        }else
//        {
//            return false;
//        }
//        //虚拟物品直接发货
//        $goods = pdo_fetch('select id,goodsid,total,realprice from ' . tablename('ewei_shop_order_goods') . ' where  orderid=:orderid and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':orderid' => $order['id']));
//        $g = pdo_fetch('select id,credit,sales,salesreal from ' . tablename('ewei_shop_goods') . ' where  id=:id and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $goods['goodsid']));
//        $virtual_data = pdo_fetchall('SELECT id,typeid,fields FROM ' . tablename('ewei_shop_virtual_data') . ' WHERE typeid=:typeid and openid=:openid and uniacid=:uniacid and merchid = :merchid order by id asc limit ' . $goods['total'], array(':openid' => '', ':typeid' => $order['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $order['merchid']));
//        $type = pdo_fetch('select fields from ' . tablename('ewei_shop_virtual_type') . ' where id=:id and uniacid=:uniacid and merchid = :merchid limit 1 ', array(':id' => $order['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $order['merchid']));
//        $fields = iunserializer($type['fields'], true);
//        $virtual_info = array();
//        $virtual_str = array();
//        foreach ($virtual_data as $vd) {
//
//            //数据
//            $virtual_info[] = $vd['fields'];
//
//            //显示
//            $strs = array();
//            $vddatas = iunserializer($vd['fields']);
//
//            foreach ($vddatas as $vk => $vv) {
//                $strs[] = $fields[$vk] . ": " . $vv;
//            }
//            $virtual_str[] = implode(" ", $strs);
//
//            //更新虚拟物品数据
//            pdo_update('ewei_shop_virtual_data', array(
//                'openid' => $order['openid'],
//                'orderid' => $order['id'],
//                'ordersn' => $order['ordersn'],
//                'price' => round($goods['realprice'] / $goods['total'], 2),
//                'usetime' => time()
//            ), array('id' => $vd['id']));
//            pdo_update('ewei_shop_virtual_type', 'usedata=usedata+1', array('id' => $vd['typeid']));
//            //更新库存
//            $this->updateStock($vd['typeid']);
//        }
//        $virtual_str = implode("\n", $virtual_str);
//        $virtual_info = "[" . implode(",", $virtual_info) . "]";
//
//        //更新订单的状态
//        $time = time();
//        pdo_update('ewei_shop_order', array('virtual_info' => $virtual_info, 'virtual_str' => $virtual_str, 'status' => '3', 'paytime' => $time, 'sendtime' => $time, 'finishtime' => $time), array('id' => $order['id']));
//
//        //处理余额抵扣
//        /*if ($order['deductcredit2'] > 0) {
//            $shopset = m('common')->getSysset('shop');
//            m('member')->setCredit($order['openid'], 'credit2', -$order['deductcredit2'], array(0, $shopset['name'] . "余额抵扣: {$order['deductcredit2']} 订单号: " . $order['ordersn']));
//        }*/
//
//        //购物积分
//        //$credits = $goods['total'] * $g['credit'];
//        $credits = 0;
//        $gcredit = trim($g['credit']);
//        if (!empty($gcredit)) {
//            if (strexists($gcredit, '%')) {
//                //按比例计算
//                $credits += intval(floatval(str_replace('%', '', $gcredit)) / 100 * $goods['realprice']);
//            } else {
//                //按固定值计算
//                $credits += intval($g['credit']) * $goods['total'];
//            }
//        }
//        if ($credits > 0) {
//            $shopset = m('common')->getSysset('shop');
//            m('member')->setCredit($order['openid'], 'credit1', $credits, array(0, $shopset['name'] . '购物积分 订单号: ' . $order['ordersn']));
//        }
//
//        //实际销量
//        $salesreal = pdo_fetchcolumn('select ifnull(sum(total),0) from ' . tablename('ewei_shop_order_goods') . ' og '
//            . ' left join ' . tablename('ewei_shop_order') . ' o on o.id = og.orderid '
//            . ' where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1', array(':goodsid' => $g['id'], ':uniacid' => $_W['uniacid']));
//        pdo_update('ewei_shop_goods', array('salesreal' => $salesreal), array('id' => $g['id']));
//
//        //商品全返
//        m('order')->fullback($order['id']);
//        //会员升级
//        m('member')->upgradeLevel($order['openid'], $order['id']);
//
//        //模板消息
//        m('notice')->sendOrderMessage($order['id']);
//
//        //余额赠送
//        m('order')->setGiveBalance($order['id'], 1);
//
//        //发送赠送优惠券
//        if (com('coupon')) {
//            com('coupon')->sendcouponsbytask($order['id']); //订单支付
//        }
//
//        //优惠券返利
//        if (com('coupon') && !empty($order['couponid'])) {
//            com('coupon')->backConsumeCoupon($order['id']); //虚拟物品支付
//        }
//
//        //分销
//        if (p('commission')) {
//            //付款后
//            p('commission')->checkOrderPay($order['id']);
//
//            //收货后
//            p('commission')->checkOrderFinish($order['id']);
//        }
//
//        return true;
//    }

}

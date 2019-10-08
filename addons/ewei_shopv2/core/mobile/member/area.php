<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Area_EweiShopV2Page extends MobileLoginPage
{
    public function main()
    {
        global $_W;
        global $_GPC;
        //判断目前会员等级是否有申请区域条件
        $member = m('member')->getMember($_W['openid']);
        $memberLevel = pdo_fetch("select * from " . tablename("ewei_shop_member_level") . " where id=:id", array(':id' => $member['level']));
        $canRegister = false;
        if (($memberLevel['area_type'] == 1 && empty($member['agent_area'])) || ($memberLevel['area_type'] == 2 && empty($member['agent_city'])) || ($memberLevel['area_type'] == 3 && empty($member['agent_province']))) { //申请区级
            $canRegister = true;
        }
        //累计提成
        $areaOrderList = pdo_fetchall("select og.* from " . tablename("ewei_shop_order_goods") . " og  
        left join ".tablename("ewei_shop_order")." o on og.orderid=o.id where o.status=3 and
         city_agent_id=:id or area_agent_id=:id or province_agent_id=:id", array(":id" => $member['id']));
        $totalPrice = 0;
        foreach ($areaOrderList as $k => $v) {
            if ($v['area_agent_id'] == $member['id']) {
                $totalPrice += $v['area_agent_price'];
            } else if ($v['city_agent_id'] == $member['id']) {
                $totalPrice += $v['city_agent_price'];
            } else if ($v['province_agent_id'] == $member['id']) {
                $totalPrice += $v['province_agent_price'];
            }
        }
        include $this->template();
    }

    //获取区域订单
    public function getAreaList()
    {
        global $_W;
        global $_GPC;
        $member = m('member')->getMember($_W['openid']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 50;
        $orderGoodsList = pdo_fetchall("select orderid from " . tablename("ewei_shop_order_goods") . " 
        where city_agent_id=:id or area_agent_id=:id or province_agent_id=:id group by orderid", array(":id" => $member['id']));
        $orderIds = array_column($orderGoodsList, 'orderid');
        $orderIds = implode(',', $orderIds);

        $list = pdo_fetchall("select * from " . tablename("ewei_shop_order") . " where status = 3 and 
        id in ({$orderIds}) order by 'createtime' desc limit " . ($pindex - 1) * $psize . ',' . $psize, array(':id' => $member['id']));
        foreach ($list as $k => $v) {
            $goodsList = pdo_fetchall("select * from " . tablename("ewei_shop_order_goods") . " where orderid={$v['id']}");
            $agentPrice = 0;
            $agentType = '';
            foreach ($goodsList as $key => $val) {
                if ($val['area_agent_id'] == $member['id']) {
                    $agentType = '区级提成';
                    $agentPrice += $val['area_agent_price'];
                } else if ($val['city_agent_id'] == $member['id']) {
                    $agentType = '市级提成';
                    $agentPrice += $val['city_agent_price'];
                } else if ($val['province_agent_id'] == $member['id']) {
                    $agentType = '省级提成';
                    $agentPrice += $val['province_agent_price'];
                }
            }
            $list[$k]['agent_type'] = $agentType;
            $list[$k]['agent_price'] = $agentPrice;
            $list[$k]['username'] = pdo_getcolumn('ewei_shop_member', array('openid' => $v['openid']), 'nickname');
            $list[$k]['createtime'] = date("Y-m-d H:i", $v['createtime']);
        }
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . " where status = 3 and 
        id in ({$orderIds})", array(':id' => $member['id']));
        show_json(1, array('list' => $list, 'pagesize' => $psize, 'total' => $total));
    }

    public function register()
    {
        global $_W;
        global $_GPC;
        $member = m('member')->getMember($_W['openid']);
        if ($_W["ispost"]) {
            //判断是否重复申请
            $isRegister = pdo_fetch("select * from " . tablename('ewei_shop_area_register') . ' 
            where user_id=:user_id and status=1 and type=:type', array(':user_id' => $member['id'], 'type' => $_GPC['type']));
            if (!empty($isRegister)) {
                show_json(0, '你已经提交过申请，请等待审核');
            }
            //判断该区域是否已有代理
            $address = trim(preg_replace('/\s+/', '', $_GPC['province'])).trim(preg_replace('/\s+/', '', $_GPC['city'])).trim(preg_replace('/\s+/', '', $_GPC['area']));
            if ($_GPC['type'] == 1) { //区级
                $isAgent = pdo_fetch("select * from ".tablename("ewei_shop_member")." where agent_area=:address", array(":address" => $address));
            } else if ($_GPC['type'] == 2) { //市级
                $isAgent = pdo_fetch("select * from ".tablename("ewei_shop_member")." where agent_city=:address", array(":address" => $address));
            } else if ($_GPC['type'] == 3) { //省级
                $isAgent = pdo_fetch("select * from ".tablename("ewei_shop_member")." where agent_province=:address", array(":address" => $address));
            }
            if (!empty($isAgent)) {
                show_json(0, '该区域已有一个区域代理，请重新选择其他区域');
            }
            $insertData = array(
                'create_time' => time(),
                'status' => 1,
                'user_id' => $member['id'],
                'type' => $_GPC['type'],
                'now_level' => $member['level'],
                'area' => trim(preg_replace('/\s+/', '', $_GPC['area'])),
                'city' => trim(preg_replace('/\s+/', '', $_GPC['city'])),
                'province' => trim(preg_replace('/\s+/', '', $_GPC['province'])),
            );
            pdo_insert("ewei_shop_area_register", $insertData);
            show_json(1, '添加审核成功，请等待结果');
        } else {
            //判断申请的区域等级
            $memberLevel = pdo_fetch('select * from ' . tablename("ewei_shop_member_level") . " where id=:id", array(':id' => $member['level']));
            if (empty($memberLevel['area_type'])) {
                show_message('你暂时还不满足条件');
            }
            include $this->template();
        }
    }
}

?>

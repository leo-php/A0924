<?php

if (!defined('IN_IA')) {
	exit('Access Denied');
}

class Op_EweiShopV2Page extends MobileLoginPage
{
	/**
     * 取消订单
     * @global type $_W
     * @global type $_GPC
     */
	public function cancel()
	{
		global $_W;
		global $_GPC;
		$orderid = intval($_GPC['id']);
		$order = pdo_fetch('select id,ordersn,openid,status,deductcredit,deductcredit2,deductprice,couponid,isparent,`virtual`,`virtual_info`,merchid from ' . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $_W['uniacid'], ':openid' => $_W['openid']));

		if (empty($order)) {
			show_json(0, '订单未找到');
		}

		if (0 < $order['status']) {
			show_json(0, '订单已支付，不能取消!');
		}

		if ($order['status'] < 0) {
			show_json(0, '订单已经取消!');
		}

		if (!empty($order['virtual']) && $order['virtual'] != 0) {
			$goodsid = pdo_fetch('SELECT goodsid FROM ' . tablename('ewei_shop_order_goods') . ' WHERE uniacid = ' . $_W['uniacid'] . ' AND orderid = ' . $order['id']);
			$typeid = $order['virtual'];
			$vkdata = ltrim($order['virtual_info'], '[');
			$vkdata = rtrim($vkdata, ']');
			$arr = explode('}', $vkdata);

			foreach ($arr as $k => $v) {
				if (!$v) {
					unset($arr[$k]);
				}
			}

			$vkeynum = count($arr);
			pdo_query('update ' . tablename('ewei_shop_virtual_data') . ' set openid="",usetime=0,orderid=0,ordersn="",price=0,merchid=' . $order['merchid'] . ' where typeid=' . intval($typeid) . ' and orderid = ' . $order['id']);
			pdo_query('update ' . tablename('ewei_shop_virtual_type') . ' set usedata=usedata-' . $vkeynum . ' where id=' . intval($typeid));
		}

		if (0 < $order['deductprice']) {
			m('member')->setCredit($order['openid'], 'credit1', $order['deductcredit'], array('0', $_W['shopset']['shop']['name'] . ('购物返还抵扣积分 积分: ' . $order['deductcredit'] . ' 抵扣金额: ' . $order['deductprice'] . ' 订单号: ' . $order['ordersn'])));
		}

		if (0 < $order['deductcredit2']) {
			m('member')->setCredit($order['openid'], 'credit2', $order['deductcredit2'], array('0', $_W['shopset']['shop']['name'] . ('购物返还抵扣余额 余额: ' . $order['deductcredit2'] . ' 订单号: ' . $order['ordersn'])));
		}

		m('order')->setStocksAndCredits($orderid, 2);
		if (com('coupon') && !empty($order['couponid'])) {
			$plugincoupon = com('coupon');

			if ($plugincoupon) {
				$coupondata = $plugincoupon->getCouponByDataID($order['couponid']);

				if ($coupondata['used'] != 1) {
					com('coupon')->returnConsumeCoupon($orderid);
				}
			}
		}

		pdo_update('ewei_shop_order', array('status' => -1, 'canceltime' => time(), 'closereason' => trim($_GPC['remark'])), array('id' => $order['id'], 'uniacid' => $_W['uniacid']));

		if (!empty($order['isparent'])) {
			pdo_update('ewei_shop_order', array('status' => -1, 'canceltime' => time(), 'closereason' => trim($_GPC['remark'])), array('parentid' => $order['id'], 'uniacid' => $_W['uniacid']));
		}

		m('notice')->sendOrderMessage($orderid);
		show_json(1);
	}

	/**
     * 确认收货
     * @global type $_W
     * @global type $_GPC
     */
	public function finish()
	{
		global $_W;
		global $_GPC,$_S;
		$orderid = intval($_GPC['id']);
		pdo_update('ewei_shop_order_goods', array('single_refundstate' => 0), array('orderid' => $orderid, 'uniacid' => $_W['uniacid']));
		$order = pdo_fetch('select id,status,openid,couponid,price,refundstate,refundid,ordersn,price,address,paytype from ' . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $_W['uniacid'], ':openid' => $_W['openid']));

		if (empty($order)) {
			show_json(0, '订单未找到');
		}

		if ($order['status'] != 2) {
			show_json(0, '订单不能确认收货');
		}

		if (0 < $order['refundstate'] && !empty($order['refundid'])) {
			$change_refund = array();
			$change_refund['status'] = -2;
			$change_refund['refundtime'] = time();
			pdo_update('ewei_shop_order_refund', $change_refund, array('id' => $order['refundid'], 'uniacid' => $_W['uniacid']));
		}

		pdo_update('ewei_shop_order', array('status' => 3, 'finishtime' => time(), 'refundstate' => 0), array('id' => $order['id'], 'uniacid' => $_W['uniacid']));

        if($order['paytype'] != 1) {
            //判断是否升级
            $memberInfo = pdo_fetch("select * from " . tablename("ewei_shop_member") . " where openid=:openid", array('openid' => $order['openid']));
            $memberNowLevel = pdo_fetch("select * from " . tablename("ewei_shop_member_level") . " where id=:id", array(':id' => $memberInfo['level']));
            if (!$memberNowLevel) {
                $memberNowLevel = array(
                    'levelname'   => empty($_S['shop']['levelname']) ? '普通会员' : $_S['shop']['levelname'],
                    'discount'    => empty($_S['shop']['leveldiscount']) ? 10 : $_S['shop']['leveldiscount'],
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
                    $myInviter = pdo_fetch("select id,level,agentid from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $memberInfo['agentid']));
                    $nowInviterLevel = pdo_getcolumn("ewei_shop_member_level", array("id" => $myInviter['level']), 'level');
                    $nowInviterLevel = $nowInviterLevel ?: 0;
                    $newInviterLevelList = pdo_fetchall("select * from " . tablename("ewei_shop_member_level") . " where level> {$nowInviterLevel} ORDER BY `level` desc");
                    $sameLevelCount = 0;
                    foreach ($newInviterLevelList as $key => $val) {
                        if (!empty($val['same_level_invite'])) {
                            //查询上级的同级以上人数
                            $myChildren = pdo_fetchall("select id,level,agentid from " . tablename("ewei_shop_member") . " where agentid=:id and agentid!=0", array(":id" => $myInviter['id']));
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
                                $myTwoInviter = pdo_fetch("select id,level,agentid from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $myInviter['agentid']));
                                $nowTwoInviterLevel = pdo_getcolumn("ewei_shop_member_level", array("id" => $myTwoInviter['level']), 'level');
                                $nowTwoInviterLevel = $nowTwoInviterLevel ?: 0;
                                $newTwoInviterLevelList = pdo_fetchall("select * from " . tablename("ewei_shop_member_level") . " where level > {$nowTwoInviterLevel} ORDER BY `level` desc");
                                $sameTwoLevelCount = 0;
                                foreach ($newTwoInviterLevelList as $ke => $vo) {
                                    if (!empty($vo['same_level_invite'])) {
                                        //查询上级的同级以上人数
                                        $myTwoChildren = pdo_fetchall("select id,level,agentid from " . tablename("ewei_shop_member") . " where agentid=:id and agentid!=0", array(":id" => $myTwoInviter['id']));
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

            //成为店长的产品不进行任何提成返利
            $order_goods=pdo_fetchall(' select goodsid,price from '.tablename('ewei_shop_order_goods'). ' where orderid='.$order['id'].' and uniacid='.$_W['uniacid']);
            $set=p('globonus')->getSet();
            foreach ( $order_goods as $value){
                if (in_array($value['goodsid'],iunserializer($set['become_goodsid']))) {
                    $order['price']=$order['price']-$value['price'];
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
            }
            //直推和间推奖励 (进货商品)
            if (!empty($memberInfo['agentid'])) {
                //直推
                $inviterInfo = pdo_fetch("select * from " . tablename('ewei_shop_member') . ' where id=:id', array(':id' => $memberInfo['agentid']));
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
                if (!empty($inviterInfo['agentid'])) {
                    $inviterTwoInfo = pdo_fetch("select * from " . tablename('ewei_shop_member') . ' where id=:id', array(':id' => $inviterInfo['agentid']));
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
        }

		m('order')->setStocksAndCredits($orderid, 3, true);
		m('order')->fullback($orderid);
		m('member')->upgradeLevel($order['openid'], $orderid);
		m('order')->setGiveBalance($orderid, 1);

		if (com('coupon')) {
			$refurnid = com('coupon')->sendcouponsbytask($orderid);
		}

		if (com('coupon') && !empty($order['couponid'])) {
			com('coupon')->backConsumeCoupon($orderid);
		}

		m('notice')->sendOrderMessage($orderid);
		com_run('printer::sendOrderMessage', $orderid);

		if (p('lineup')) {
			p('lineup')->checkOrder($order);
		}

		if (p('commission')) {
			p('commission')->checkOrderFinish($orderid);
		}

		if (p('lottery')) {
			$res = p('lottery')->getLottery($_W['openid'], 1, array('money' => $order['price'], 'paytype' => 2));

			if ($res) {
				p('lottery')->getLotteryList($_W['openid'], array('lottery_id' => $res));
			}
		}

		if (p('task')) {
			p('task')->checkTaskProgress($order['price'], 'order_full', '', $order['openid']);
		}

		show_json(1, array('url' => mobileUrl('order', array('status' => 3))));
	}

	/**
     * 删除或恢复订单
     * @global type $_W
     * @global type $_GPC
     */
	public function delete()
	{
		global $_W;
		global $_GPC;
		$orderid = intval($_GPC['id']);
		$userdeleted = intval($_GPC['userdeleted']);
		$order = pdo_fetch('select id,status,refundstate,refundid from ' . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $_W['uniacid'], ':openid' => $_W['openid']));

		if (empty($order)) {
			show_json(0, '订单未找到!');
		}

		if ($userdeleted == 0) {
			if ($order['status'] != 3) {
				show_json(0, '无法恢复');
			}
		}
		else {
			if ($order['status'] != 3 && $order['status'] != -1) {
				show_json(0, '无法删除');
			}

			if (0 < $order['refundstate'] && !empty($order['refundid'])) {
				$change_refund = array();
				$change_refund['status'] = -2;
				$change_refund['refundtime'] = time();
				pdo_update('ewei_shop_order_refund', $change_refund, array('id' => $order['refundid'], 'uniacid' => $_W['uniacid']));
			}
		}

		pdo_update('ewei_shop_order', array('userdeleted' => $userdeleted, 'refundstate' => 0), array('id' => $order['id'], 'uniacid' => $_W['uniacid']));
		show_json(1);
	}
}

?>

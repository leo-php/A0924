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
class Create_EweiShopV2Page extends PluginMobileLoginPage {
	
	function main(){
		global $_W, $_GPC;
		//获取购买数量
        $num = max(1,$_GPC['num']);

        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        $id = intval($_GPC['id']);
        //多商户
        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $is_openmerch = 1;
        } else {
            $is_openmerch = 0;
        }
        $merchid = intval($_GPC['merchid']);
        $optionid = intval($_GPC['optionid']);
        $shop = m('common')->getSysset('shop');
        $member = m('member')->getMember($openid);
        $goods = $this->model->getGoods($id, $member,$optionid,$num);
        if (empty($goods)) {
            $this->message("商品已下架或被删除!", mobileUrl('creditshop'), 'error');
        }
        $pay = m('common')->getSysset('pay');
        $pay['weixin'] = !empty($pay['weixin_sub']) ? 1 : $pay['weixin'];
        $pay['weixin_jie'] = !empty($pay['weixin_jie_sub']) ? 1 : $pay['weixin_jie'];
        $pay['app_alipay'] = 0;//积分商城未支持支付宝支付
        $goods['jie'] = intval($pay['weixin_jie']);
        $set = m('common')->getPluginset('creditshop');
        $goods['followed'] = m('user')->followed($openid);
        $chance = empty($goods['chance'])?0:$goods['chance'];

        if($goods['goodstype']==0){
            //如果线下兑换，读取门店
            $stores = array();
            if(!empty($goods['isverify'])){
                $storeids = array();
                if (!empty($goods['storeids'])) {
                    $storeids = array_merge(explode(',', $goods['storeids']), $storeids);
                }
                if (empty($storeids)) {
                    //全部门店
                    if ($merchid > 0) {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where  uniacid=:uniacid and merchid=:merchid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                    } else {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where  uniacid=:uniacid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid']));
                    }
                } else {
                    if ($merchid > 0) {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and merchid=:merchid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                    } else {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid']));
                    }
                }
            }
        }

        //h5app支付参数 by lgt
        $sysSet = m('common')->getSysset();
        $sec = m('common')->getSec();
        $sec =iunserializer($sec['sec']);

        $payinfo = array(
            'wechat' => !empty($sec['app_wechat']['merchname']) && !empty($sysSet['pay']['app_wechat']) && !empty($sec['app_wechat']['appid']) && !empty($sec['app_wechat']['appsecret']) && !empty($sec['app_wechat']['merchid']) && !empty($sec['app_wechat']['apikey']) ? true : false,
            'alipay' => false,
            'mcname' => $sec['app_wechat']['merchname'],
            'aliname' => empty($_W['shopset']['shop']['name']) ? $sec['app_wechat']['merchname'] : $_W['shopset']['shop']['name'],
            'attach' => $_W['uniacid'] . ":2",
            'type' => 7
        );

        //为改变数量提供数据
        if ($_W['ispost']){
            show_json(1,$goods);
        }
		include $this->template();
	}
    function dispatch(){
        global $_W, $_GPC;
        $area_set = m('util')->get_area_config_set();
        $new_area = intval($area_set['new_area']);

        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        $goodsid = intval($_GPC['goodsid']);
        //获取购买数量
        $num = max(1,$_GPC['num']);
        $addressid = intval($_GPC['addressid']);
        $optionid = intval($_GPC['optionid']);
        $member = m('member')->getMember($openid);
        $goods = $this->model->getGoods($goodsid, $member,$optionid);
        $merchid = $goods['merchid'];
        $dispatch = 0;
        $dispatch_array = array();
        $address = pdo_fetch('select id,realname,mobile,address,province,city,area,datavalue,street from ' . tablename('ewei_shop_member_address') . '
        where id=:id and uniacid=:uniacid limit 1', array(':id' => $addressid, ':uniacid' => $_W['uniacid']));

        $user_city = '';
        $user_city_code = '';

        if (empty($new_area)) {
            if (!empty($address)) {
                $user_city = $user_city_code = $address['city'];
            } else if (!empty($member['city'])) {
                $user_city = $user_city_code = $member['city'];
            }
        } else {
            if (!empty($address)) {
                $user_city = $address['city'];
                $user_city_code = $address['datavalue'];
            }
        }

        //读取快递信息
        if ($goods['dispatchtype']==0) {
            if (empty($goods['dispatchid'])) {
                //默认快递
                $dispatch_data = m('dispatch')->getDefaultDispatch($merchid);
            } else {
                $dispatch_data = m('dispatch')->getOneDispatch($goods['dispatchid']);
            }

            if (empty($dispatch_data)) {
                //最新的一条快递信息
                $dispatch_data = m('dispatch')->getNewDispatch($merchid);
            }
            //使用统一邮费
            //是否设置了不配送城市
            if (!empty($dispatch_data)) {
                $isnoarea = 0;
                $dkey = $dispatch_data['id'];
                $isdispatcharea = intval($dispatch_data['isdispatcharea']);

                if (!empty($user_city)) {

                    if (empty($isdispatcharea)) {
                        if (empty($new_area)) {
                            $citys = m('dispatch')->getAllNoDispatchAreas($dispatch_data['nodispatchareas']);
                        } else {
                            $citys = m('dispatch')->getAllNoDispatchAreas($dispatch_data['nodispatchareas_code'], 1);
                        }
                        if (!empty($citys)) {
                            if (in_array($user_city_code, $citys)) {
                                //如果此条包含不配送城市
                                $isnoarea = 1;
                            }
                        }
                    } else {
                        if (empty($new_area)) {
                            $citys = m('dispatch')->getAllNoDispatchAreas();
                        } else {
                            $citys = m('dispatch')->getAllNoDispatchAreas('', 1);
                        }
                        if (!empty($citys)) {
                            if (in_array($user_city_code, $citys)) {
                                //如果此条包含不配送城市
                                $isnoarea = 1;
                            }
                        }

                        if (empty($isnoarea)) {
                            $isnoarea = m('dispatch')->checkOnlyDispatchAreas($user_city_code, $dispatch_data);
                        }
                    }

                    if (!empty($isnoarea)) {
                        //包含不配送城市
                        $isnodispatch = 1;

                        $has_goodsid = 0;
                        if (!empty($nodispatch_array['goodid'])) {
                            if (in_array($goods['goodsid'], $nodispatch_array['goodid'])) {
                                $has_goodsid = 1;
                            }
                        }

                        if ($has_goodsid == 0) {
                            $nodispatch_array['goodid'][] = $goods['goodsid'];
                            $nodispatch_array['title'][] = $goods['title'];
                            $nodispatch_array['city'] = $user_city;
                        }
                    }
                }
            }
            $dprice = $goods['dispatch'];

        } else if ($goods['dispatchtype'] > 0) {
            //使用快递模板

            if (empty($goods['dispatchid'])) {
                //默认快递
                $dispatch_data = m('dispatch')->getDefaultDispatch($merchid);
            } else {
                $dispatch_data = m('dispatch')->getOneDispatch($goods['dispatchid']);
            }

            if (empty($dispatch_data)) {
                //最新的一条快递信息
                $dispatch_data = m('dispatch')->getNewDispatch($merchid);
            }

            //是否设置了不配送城市
            if (!empty($dispatch_data)) {
                $isnoarea = 0;

                $dkey = $dispatch_data['id'];
                $isdispatcharea = intval($dispatch_data['isdispatcharea']);

//                    print_r($isdispatcharea);exit;

                if (!empty($user_city)) {

                    if (empty($isdispatcharea)) {
                        if (empty($new_area)) {
                            $citys = m('dispatch')->getAllNoDispatchAreas($dispatch_data['nodispatchareas']);
                        } else {
                            $citys = m('dispatch')->getAllNoDispatchAreas($dispatch_data['nodispatchareas_code'], 1);
                        }
                        if (!empty($citys)) {
                            if (in_array($user_city_code, $citys)) {
                                //如果此条包含不配送城市
                                $isnoarea = 1;
                            }
                        }
                    } else {
                        if (empty($new_area)) {
                            $citys = m('dispatch')->getAllNoDispatchAreas();
                        } else {
                            $citys = m('dispatch')->getAllNoDispatchAreas('', 1);
                        }
                        if (!empty($citys)) {
                            if (in_array($user_city_code, $citys)) {
                                //如果此条包含不配送城市
                                $isnoarea = 1;
                            }
                        }

                        if (empty($isnoarea)) {
                            $isnoarea = m('dispatch')->checkOnlyDispatchAreas($user_city_code, $dispatch_data);
                        }
                    }

                    if (!empty($isnoarea)) {
                        //包含不配送城市
                        $isnodispatch = 1;

                        $has_goodsid = 0;
                        if (!empty($nodispatch_array['goodid'])) {
                            if (in_array($goods['goodsid'], $nodispatch_array['goodid'])) {
                                $has_goodsid = 1;
                            }
                        }

                        if ($has_goodsid == 0) {
                            $nodispatch_array['goodid'][] = $goods['goodsid'];
                            $nodispatch_array['title'][] = $goods['title'];
                            $nodispatch_array['city'] = $user_city;
                        }
                    }
                }

                if ($isnodispatch == 0) {
                    //配送区域
                    $areas = unserialize($dispatch_data['areas']);
                    if ($dispatch_data['calculatetype'] == 1) {
                        //按件计费
                        $param = $num;
                    } else {
                        //按重量计费
                        $param = floatval($goods['weight']) * $num;
                    }

                    if (array_key_exists($dkey, $dispatch_array)) {
                        $dispatch_array[$dkey]['param'] += $param;
                    } else {
                        $dispatch_array[$dkey]['data'] = $dispatch_data;
                        $dispatch_array[$dkey]['param'] = $param;
                    }
                }

                if (!empty($dispatch_array)) {
                    foreach ($dispatch_array as $k => $v) {
                        $dispatch_data = $dispatch_array[$k]['data'];
                        $param = $dispatch_array[$k]['param'];
                        $areas = unserialize($dispatch_data['areas']);

                        if (!empty($address)) {
                            //用户有默认地址
                            $dprice = m('dispatch')->getCityDispatchPrice($areas, $address, $param, $dispatch_data);
                        } else if (!empty($member['city'])) {
                            //设置了城市需要判断区域设置
                            $dprice = m('dispatch')->getCityDispatchPrice($areas, $member, $param, $dispatch_data);
                        } else {
                            //如果会员还未设置城市 ，默认邮费
                            $dprice = m('dispatch')->getDispatchPrice($param, $dispatch_data);
                        }
                    }
                }
            }
        }
        if (!empty($nodispatch_array)) {
            $nodispatch = '商品';
            foreach ($nodispatch_array['title'] as $k => $v) {
                $nodispatch .= $v . ',';
            }
            $nodispatch = trim($nodispatch, ',');
            $nodispatch .= '不支持配送到' . $nodispatch_array['city'];
            $nodispatch_array['nodispatch'] = $nodispatch;
            $nodispatch_array['isnodispatch'] = 1;
            show_json(0,$nodispatch_array);
        }

        show_json(1,array('dispatch'=>$dprice));
    }
}

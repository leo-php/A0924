<?php

/*
 * 9CIT SHOP
 * 
 * @author ZXF
 */
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Buy_ZmShopPage extends PluginMobileLoginPage
{

    function main()
    {
        global $_W, $_GPC;
        if ($_W['ispost']) {
            $mid = $_GPC['post_id'];
            $openid = $_W['openid'];
            $uniacid = $_W['uniacid'];

            $price = floatval($_GPC['post_price']);

            if ($price <= 0) $this->message('金额不可以为0', '', 'error');

            $member = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND id=:id LIMIT 1', array(
                ':uniacid' => $uniacid,
                ':id' => $mid
            ));

            if (empty($member)) $this->message('无效的会员', '', 'error');

            //参数
            $set = $_W['shopset'];
            $set['pay']['weixin'] = !empty($set['pay']['weixin_sub']) ? 1 : $set['pay']['weixin'];
            $set['pay']['weixin_jie'] = !empty($set['pay']['weixin_jie_sub']) ? 1 : $set['pay']['weixin_jie'];

            $sec = m('common')->getSec();
            $sec = iunserializer($sec['sec']);

            $status = 1;
            if (!empty($set['trade']['closerecharge'])) {
                $this->message('系统未开启充值!', '', 'error');
            }

            if (empty($set['trade']['minimumcharge'])) {
                $minimumcharge = 0;
            } else {
                $minimumcharge = $set['trade']['minimumcharge'];
            }

            //微信支付
            $wechat = array('success' => false);
            if (is_weixin()) {
                //微信环境
                if (isset($set['pay']) && $set['pay']['weixin'] == 1) {
                    load()->model('payment');
                    $setting = uni_setting($_W['uniacid'], array('payment'));
                    if (is_array($setting['payment']['wechat']) && $setting['payment']['wechat']['switch']) {
                        $wechat['success'] = true;
                    } elseif ($set['pay']['weixin']) {
                        $wechat['success'] = true;
                    }
                }

                if (isset($set['pay']) && $set['pay']['weixin_jie'] == 1 && !$wechat['success']) {
                    if (!empty($set['pay']['weixin_jie_sub']) && !empty($sec['sub_secret_jie_sub'])) {
                        m('member')->wxuser($sec['sub_appid_jie_sub'], $sec['sub_secret_jie_sub']);
                    } elseif (!empty($sec['secret'])) {
                        m('member')->wxuser($sec['appid'], $sec['secret']);
                    }
                    $wechat['success'] = true;
                }
            }

            //支付宝
            $alipay = array('success' => false);
            if (isset($set['pay']['alipay']) && $set['pay']['alipay'] == 1) {
                //如果开启支付宝
                load()->model('payment');
                $setting = uni_setting($_W['uniacid'], array('payment'));
                if (is_array($setting['payment']['alipay']) && $setting['payment']['alipay']['switch']) {
                    $alipay['success'] = true;
                }
            }

            $credit['success'] = false;

            //是否可以余额支付
            $credit = array('success' => false);
            if (isset($set['pay']) && $set['pay']['credit'] == 1) {
                $credit = array(
                    'success' => true
                );
            }

            if (is_h5app()) {
                $payinfo = array(
                    'wechat' => !empty($sec['app_wechat']['merchname']) && !empty($set['pay']['app_wechat']) && !empty($sec['app_wechat']['appid']) && !empty($sec['app_wechat']['appsecret']) && !empty($sec['app_wechat']['merchid']) && !empty($sec['app_wechat']['apikey']) ? true : false,
                    'alipay' => !empty($set['pay']['app_alipay']) && !empty($sec['app_alipay']['public_key']) ? true : false,
                    'mcname' => $sec['app_wechat']['merchname'],
                    'aliname' => empty($_W['shopset']['shop']['name']) ? $sec['app_wechat']['merchname'] : $_W['shopset']['shop']['name'],
                    'logno' => null,
                    'money' => null,
                    'attach' => $_W['uniacid'] . ":1",
                    'type' => 1
                );
            }


            include $this->template('xfqf/recharge');
            exit;
        }
        include $this->template();
    }

    function member()
    {
        global $_W, $_GPC;
        $mid = $_GPC['mid'];
        $openid = $_W['openid'];

        $member = pdo_fetch('SELECT id,openid FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND id=:id LIMIT 1', array(
            ':uniacid' => $_W['uniacid'],
            ':id' => $mid
        ));

        if (empty($member))
            show_json(0, '无效的会员ID');

        if ($member['openid'] == $openid)
            show_json(0, '不可以为自己购买');

        $price = floatval($_GPC['price']);

        if ($price <= 0)
            show_json(0, '金额不可以为0');

        /*
        $xfqf_set = p('xfqf')->getSet();
        $max_price = floatval($xfqf_set['max_price']);
        if ($max_price > 0) {
            if ($price > $max_price)
                show_json(0, '金额不可以超过' . $max_price . '元!');
        }*/
        show_json(1);
    }

    function submit()
    {
        global $_W, $_GPC;
        $set = $_W['shopset'];

        if (empty($set['trade']['minimumcharge'])) {
            $minimumcharge = 0;
        } else {
            $minimumcharge = $set['trade']['minimumcharge'];
        }

        $money = floatval($_GPC['money']);
        if ($money <= 0) {
            show_json(0, '充值金额必须大于0!');
        }
        if ($money < $minimumcharge && $minimumcharge > 0) {
            show_json(0, '最低充值金额为' . $minimumcharge . '元!');
        }
        if (empty($money)) {
            show_json(0, '请填写充值金额!');
        }

        if($this->model->getrecharge($_W['openid'],$money)){
            show_json(0, '此会员消费金额已超过当日限额!');
        }

        pdo_delete('zm_shop_member_log', array('openid' => $_W['openid'], 'status' => 0, 'type' => 0, 'uniacid' => $_W['uniacid']));
        $logno = m('common')->createNO('member_log', 'logno', 'RC');

        $senddata = array(
            'mid' => intval($_GPC['mid'])
        );

        //日志
        $log = array(
            'uniacid' => $_W['uniacid'],
            'logno' => $logno,
            'title' => $set['shop']['name'] . "购置积分",
            'openid' => $_W['openid'],
            'money' => $money,
            'type' => 0,
            'createtime' => time(),
            'status' => 0,
            'couponid' => intval($_GPC['couponid']),
            'senddata' => json_encode($senddata)
        );
        pdo_insert('zm_shop_member_log', $log);
        $logid = pdo_insertid();
        $type = $_GPC['type'];


        if (is_h5app()) {
            show_json(1, array(
                'logno' => $logno,
                'money' => $money
            ));
        }

        //参数
        $set = m('common')->getSysset(array('shop', 'pay'));
        $set['pay']['weixin'] = !empty($set['pay']['weixin_sub']) ? 1 : $set['pay']['weixin'];
        $set['pay']['weixin_jie'] = !empty($set['pay']['weixin_jie_sub']) ? 1 : $set['pay']['weixin_jie'];
        if ($type == 'wechat') {
            //微信支付
            if (!is_weixin()) {
                show_json(0, '非微信环境!');
            }
            //微信环境
            if (empty($set['pay']['weixin']) && empty($set['pay']['weixin_jie'])) {
                show_json(0, '未开启微信支付!');
            }
            $wechat = array('success' => false);
            $jie = intval($_GPC['jie']);
            //如果开启微信支付
            $params = array();
            $params['tid'] = $log['logno'];
            $params['user'] = $_W['openid'];
            $params['fee'] = $money;
            $params['title'] = $log['title'];

            if (isset($set['pay']) && $set['pay']['weixin'] == 1 && $jie !== 1) {
                load()->model('payment');
                $setting = uni_setting($_W['uniacid'], array('payment'));
                $options = array();
                if (is_array($setting['payment'])) {
                    $options = $setting['payment']['wechat'];
                    $options['appid'] = $_W['account']['key'];
                    $options['secret'] = $_W['account']['secret'];
                }
                $wechat = m('common')->wechat_build($params, $options, 1);
                if (!is_error($wechat)) {
                    $wechat['weixin'] = true;
                    $wechat['success'] = true;
                }
            }
            if ((isset($set['pay']) && $set['pay']['weixin_jie'] == 1 && !$wechat['success']) || $jie === 1) {
                $params['tid'] = $params['tid'] . '_borrow';
                $sec = m('common')->getSec();
                $sec = iunserializer($sec['sec']);
                $options = array();
                $options['appid'] = $sec['appid'];
                $options['mchid'] = $sec['mchid'];
                $options['apikey'] = $sec['apikey'];
                if (!empty($set['pay']['weixin_jie_sub']) && !empty($sec['sub_secret_jie_sub'])) {
                    $wxuser = m('member')->wxuser($sec['sub_appid_jie_sub'], $sec['sub_secret_jie_sub']);
                    $params['openid'] = $wxuser['openid'];
                } elseif (!empty($sec['secret'])) {
                    $wxuser = m('member')->wxuser($sec['appid'], $sec['secret']);
                    $params['openid'] = $wxuser['openid'];
                }

                $wechat = m('common')->wechat_native_build($params, $options, 1);
                if (!is_error($wechat)) {
                    $wechat['success'] = true;
                    if (!empty($params['openid'])) {
                        $wechat['weixin'] = true;
                    } else {
                        $wechat['weixin_jie'] = true;
                    }
                }
            }
            $wechat['jie'] = $jie;
            if (!$wechat['success']) {
                show_json(0, '微信支付参数错误!');
            }
            show_json(1, array(
                'wechat' => $wechat,
                'logid' => $logid
            ));
        } else if ($type == 'alipay') {

            //支付宝
            $alipay = array('success' => false);
            //如果开启支付宝
            $params = array();
            $params['tid'] = $log['logno'];
            $params['user'] = $_W['openid'];
            $params['fee'] = $money;
            $params['title'] = $log['title'];

            load()->func('communication');
            load()->model('payment');
            $setting = uni_setting($_W['uniacid'], array('payment'));
            if (is_array($setting['payment'])) {
                $options = $setting['payment']['alipay'];
                $alipay = m('common')->alipay_build($params, $options, 1, $_W['openid']);
                if (!empty($alipay['url'])) {
                    $alipay['url'] = urlencode($alipay['url']);
                    $alipay['success'] = true;
                }
            }
            show_json(1, array('alipay' => $alipay, 'logid' => $logid, 'logno' => $logno));
        } else if ($type == 'credit') {
            //余额支付
            $credit = array('success' => false);
            $openid = $_W['openid'];
            $ps = array();
            $ps['tid'] = $log['tid'];
            $ps['user'] = $openid;
            $ps['fee'] = $money;
            $ps['title'] = $log['title'];

            //判断是否开启余额支付
            if (empty($set['pay']['credit']) && $ps['fee'] > 0) {
                if ($_W['ispost']) {
                    show_json(0, '未开启余额支付!');
                } else {
                    $this->message("未开启余额支付", mobileUrl('order'));
                }
            }

            if ($ps['fee'] < 0) {
                if ($_W['ispost']) {
                    show_json(0, "金额错误");
                } else {
                    $this->message("金额错误", mobileUrl('order'));
                }
            }

            $credits = m('member')->getCredit($_W['openid'], 'credit2');
            if ($credits < $ps['fee']) {
                if ($_W['ispost']) {
                    show_json(0, "余额不足,请充值");
                } else {
                    $this->message("余额不足,请充值", mobileUrl('order'));
                }
            }
            $fee = floatval($ps['fee']);

            $member = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE id=:id AND uniacid=:uniacid LIMIT 1', array(
                ':id' => intval($_GPC['mid']),
                ':uniacid' => $_W['uniacid']
            ));
            if (!empty($member)) {
                $result = m('member')->setCredit($openid, 'credit2', -$fee, array($_W['member']['uid'], $_W['shopset']['shop']['name'] . '商家购买积分' . $fee));
                if (is_error($result)) {
                    if ($_W['ispost']) {
                        show_json(0, $result['message']);
                    } else {
                        $this->message($result['message'], mobileUrl('order'));
                    }
                } else {
                    pdo_update('zm_shop_member_log', array('status' => 1, 'rechargetype' => 'credit', 'apppay' => is_h5app() ? 1 : 0), array('id' => $logid));
                    //m('member')->setRechargeCredit($log['openid'], $log['money'] * 100);
                    //m('member')->setRechargeCredit($member['openid'], $log['money'] * 1000);
                    $merch = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid LIMIT 1', array(
                        ':uniacid' => $_W['uniacid'],
                        ':openid' => $log['openid']
                    ));

                    p('xfqf')->merchbuy($merch, $member, $log['money']);
                    show_json(1);
                }
            }
        }

        show_json(0, '未找到支付方式');
    }

    function wechat_complete()
    {
        global $_W, $_GPC;

        $logid = intval($_GPC['logid']);
        $log = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member_log') . ' WHERE `id`=:id and `uniacid`=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $logid));
        if (empty($log)) {
            $logno = intval($_GPC['logno']);
            $log = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member_log') . ' WHERE `logno`=:logno and `uniacid`=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':logno' => $logno));
        }

        if (!empty($log)) {
            $payquery = m('finance')->isWeixinPay($log['logno'], $log['money'], is_h5app() ? true : false);
            $payqueryborrow = m('finance')->isWeixinPayBorrow($log['logno'], $log['money']);
            $senddata = json_decode($log['senddata'], true);


            if (!is_error($payquery) || !is_error($payqueryborrow)) {

                if (empty($log['status'])) {

                    $member = pdo_fetch('SELECT id,openid FROM ' . tablename('zm_shop_member') . ' WHERE id=:id AND uniacid=:uniacid LIMIT 1', array(
                        ':id' => intval($senddata['mid']),
                        ':uniacid' => $_W['uniacid']
                    ));
                    if (!empty($member)) {
                        //充值状态
                        pdo_update('zm_shop_member_log', array('status' => 1, 'rechargetype' => 'wechat', 'apppay' => is_h5app() ? 1 : 0), array('id' => $logid));
                        //充值积分
                        m('member')->setRechargeCredit($log['openid'], $log['money'] * 100);
                        m('member')->setRechargeCredit($member['openid'], $log['money'] * 1000);
                        //模板消息
                        //m('notice')->sendMemberLogMessage($logid);
                    }
                }

                if ($_W['ispost']) {
                    show_json(1);
                } else {
                    header('location: ' . mobileUrl('member'));
                }
            }
        }
        if ($_W['ispost']) {
            show_json(0);
        } else {
            header('location: ' . mobileUrl('member'));
        }
    }

    function alipay_complete()
    {
        global $_W, $_GPC;
        $logno = trim($_GPC['out_trade_no']);
        $notify_id = trim($_GPC['notify_id']); //支付宝通知ID
        $sign = trim($_GPC['sign']);


        if (is_h5app()) {
            $set = m('common')->getSysset(array('shop', 'pay'));
            $sec = m('common')->getSec();
            $sec = iunserializer($sec['sec']);
            $public_key = $sec['app_alipay']['public_key'];

            if (empty($_GET['alidata'])) {
                $this->message('支付出现错误，请重试(1)!', mobileUrl('member'));
            }

            if (empty($set['pay']['app_alipay']) || empty($public_key)) {
                $this->message('支付出现错误，请重试(2)!', mobileUrl('order'));
            }

            $alidata = base64_decode($_GET['alidata']);

            $alidata = json_decode($alidata, true);
            $alisign = m('finance')->RSAVerify($alidata, $public_key, false);

            $logno = $this->str($alidata['out_trade_no']);

            if ($alisign == 0) {
                $this->message('支付出现错误，请重试(3)!', mobileUrl('member'));
            }

        } else {

            if (empty($logno)) {
                die('[ERR1]充值出现错误，请重试!');
            }

            if (empty($set['pay']['alipay']) && is_weixin()) {
                $this->message('未开启支付宝支付!', mobileUrl('order'));
            }

            if (!m('finance')->isAlipayNotify($_GET)) {
                die('[ERR2]充值出现错误，请重试!');
            }

        }

        $log = pdo_fetch('SELECT * FROM ' . tablename('zm_shop_member_log') . ' WHERE `logno`=:logno and `uniacid`=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':logno' => $logno));
        if (!empty($log) && empty($log['status'])) {

            //充值状态
            pdo_update('zm_shop_member_log', array('status' => 1, 'rechargetype' => 'alipay', 'apppay' => is_h5app() ? 1 : 0), array('id' => $log['id']));
            //增加会员余额
            m('member')->setCredit($log['openid'], 'credit2', $log['money'], array(0, $_W['shopset']['shop']['name'] . '会员充值:alipayreturn:credit2:' . $log['money']));
            //充值积分
            m('member')->setRechargeCredit($log['openid'], $log['money']);
            //充值活动
            com_run('sale::setRechargeActivity', $log);

            //优惠券
            com_run('coupon::useRechargeCoupon', $log);

            //模板消息
            m('notice')->sendMemberLogMessage($log['id']);
        }
        $url = mobileUrl('member', null, true);
        die("<script>top.window.location.href='{$url}'</script>");
    }
}


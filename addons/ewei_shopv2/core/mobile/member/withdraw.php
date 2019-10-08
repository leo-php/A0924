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

class Withdraw_EweiShopV2Page extends MobileLoginPage
{

    function main()
    {

        global $_W, $_GPC;
        //参数
        $set = $_W['shopset']['trade'];
        $status = 1;

        $openid = $_W['openid'];

        if (empty($set['withdraw'])) {
            $this->message('系统未开启提现!', '', 'error');

        }

        $withdrawcharge = $set['withdrawcharge'];
        $withdrawbegin = floatval($set['withdrawbegin']);
        $withdrawend = floatval($set['withdrawend']);
        $withdrawmoney = 0;
        if (floatval($set['withdrawmoney']) > 0) $withdrawmoney = floatval($set['withdrawmoney']);
        $withdrawmoneyMax = $set['withdrawmoneymax'];
        //当前余额
        $credit = m('member')->getCredit($_W['openid'], 'credit2');

        $last_data = $this->getLastApply($openid);

        // 判断openid是否是微信公众号的
        $canusewechat = !strexists($openid, 'wap_user_') && !strexists($openid, 'sns_qq_') && !strexists($openid, 'sns_wx_') && !strexists($openid, 'sns_wa_');

        //提现方式
        $type_array = array();

        if ($set['withdrawcashweixin'] == 1 && $canusewechat) {
            $type_array[0]['title'] = '提现到微信钱包';
        }

        if ($set['withdrawcashalipay'] == 1) {
            $type_array[2]['title'] = '提现到支付宝';

            if (!empty($last_data)) {
                if ($last_data['applytype'] != 2) {
                    $type_last = $this->getLastApply($openid, 2);

                    if (!empty($type_last)) {
                        $last_data['alipay'] = $type_last['alipay'];
                    }
                }
            }
        }

        if ($set['withdrawcashcard'] == 1) {
            $type_array[3]['title'] = '提现到银行卡';

            if (!empty($last_data)) {
                if ($last_data['applytype'] != 3) {
                    $type_last = $this->getLastApply($openid, 3);
                    if (!empty($type_last)) {
                        $last_data['bankname'] = $type_last['bankname'];
                        $last_data['bankcard'] = $type_last['bankcard'];
                    }
                }
            }

            $condition = " and uniacid=:uniacid";
            $params = array(':uniacid' => $_W['uniacid']);
            $banklist = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_commission_bank') . " WHERE 1 {$condition}  ORDER BY displayorder DESC", $params);
        }

        if (!empty($last_data)) {
            if (array_key_exists($last_data['applytype'], $type_array)) {
                $type_array[$last_data['applytype']]['checked'] = 1;
            }
        }

        //提现时间
        $withdrawStr = '';
        $set = $_W['shopset']['trade'];
        if (isset($set['withdrawday']) && $set['withdrawday'] != '99') {
            $dayArray = array('周日','周一','周二','周三','周四','周五','周六');
            $withdrawStr .= '每周'.$dayArray[$set['withdrawday']];
        }
        if (isset($set['withdrawstarthour']) && $set['withdrawstarthour'] != '99') {
            $set['withdrawstartmin'] = $set['withdrawstartmin'] ?: '00';
            $set['withdrawendmin'] = $set['withdrawstartmin'] ?: '00';
            $withdrawStr .= $set['withdrawstarthour'].':'.$set['withdrawstartmin'].'至'.$set['withdrawendhour'].':'.$set['withdrawendmin'];
        }
        $maxMoney = $set['withdrawmoneymax'] > $credit ? $credit : $set['withdrawmoneymax'];
        //判断提现时间
        $canGetMoney = true;
        //判断日期
        $nowDay = date("w", time());
        if (isset($set['withdrawday']) && $set['withdrawday'] != '99') {
            if ($nowDay != $set['withdrawday']) {
                $canGetMoney = false;
            }
        }
        //判断时分
        if (isset($set['withdrawstarthour']) && $set['withdrawstarthour'] != '99') {
            $set['withdrawstartmin'] = $set['withdrawstartmin'] ?: '00';
            $set['withdrawendmin'] = $set['withdrawstartmin'] ?: '00';
            $startTime = strtotime(date("Y-m-d", time()) . $set['withdrawstarthour'] .':'. $set['withdrawstartmin']);
            $endTime = strtotime(date("Y-m-d", time()) . $set['withdrawendhour'] .':'. $set['withdrawendmin']);
            if (!(time() >= $startTime && time() <= $endTime)) {
                $canGetMoney = false;
            }
        }
        //判断提现间隔
        $lastWithdrawTime = pdo_fetch("select createtime from ".tablename("ewei_shop_member_log")." where status!=-1 and openid='{$_W['openid']}' and type = 1");
        if ((!empty($lastWithdrawTime['createtime'])) && (!empty($set['withdraw_less_day']))) {
            if (strtotime("+{$set['withdraw_less_day']} day", $lastWithdrawTime['createtime']) > time()) {
                $canGetMoney = false;
            }
        }
        include $this->template();
    }

    function submit()
    {
        global $_W, $_GPC;

        $set = $_W['shopset']['trade'];
        if (empty($set['withdraw'])) {
            show_json(0, '系统未开启提现!');
        }

        //判断提现时间
        //判断日期
        $nowDay = date("w", time());
        if (isset($set['withdrawday']) && $set['withdrawday'] != '99') {
            if ($nowDay != $set['withdrawday']) {
                show_json(0, '请在规定的时间内进行提现');
            }
        }
        //判断时分
        if (isset($set['withdrawstarthour']) && $set['withdrawstarthour'] != '99') {
            $set['withdrawstartmin'] = $set['withdrawstartmin'] ?: '00';
            $set['withdrawendmin'] = $set['withdrawstartmin'] ?: '00';
            $startTime = strtotime(date("Y-m-d", time()) . $set['withdrawstarthour'] .':'. $set['withdrawstartmin']);
            $endTime = strtotime(date("Y-m-d", time()) . $set['withdrawendhour'] .':'. $set['withdrawendmin']);
            if (!(time() >= $startTime && time() <= $endTime)) {
                show_json(0, '请在规定的时间内进行提现');
            }
        }
        //判断提现间隔
        $lastWithdrawTime = pdo_fetch("select createtime from ".tablename("ewei_shop_member_log")." where status!=-1 and openid='{$_W['openid']}' and type = 1");
        if ((!empty($lastWithdrawTime['createtime'])) && (!empty($set['withdraw_less_day']))) {
            if (strtotime("+{$set['withdraw_less_day']} day", $lastWithdrawTime['createtime']) > time()) {
                show_json(0, '请在距离你上次提现的'.$set['withdraw_less_day'].'天后再申请提现');
            }
        }
        //判断金额
        $minMoney = $set['withdrawmoney'] ?: 0;
        $maxMoney = $set['withdrawmoneymax'] ?:0;
        if (!empty($minMoney)) {
            if ($_GPC['money'] < $minMoney) {
                show_json(0, '最低提现金额为'.$minMoney);
            }
        }
        if (!empty($maxMoney)) {
            if ($_GPC['money'] > $maxMoney) {
                show_json(0, '最大提现金额为'.$maxMoney);
            }
        }
        $set_array = array();
        $set_array['charge'] = $set['withdrawcharge'];
        $set_array['begin'] = floatval($set['withdrawbegin']);
        $set_array['end'] = floatval($set['withdrawend']);

        $money = floatval($_GPC['money']);
        $credit = m('member')->getCredit($_W['openid'], 'credit2');

        if ($money <= 0) {
            show_json(0, '提现金额错误!');
        }

        if ($money > $credit) {
            show_json(0, '提现金额过大!');
        }

        //高并发下单支付库款多次的问题
        $open_redis = function_exists('redis') && !is_error(redis());
        if ($open_redis) {
            $redis_key = "{$_W['uniacid']}_member_withdraw__pay_{$_W['openid']}";
            $redis = redis();
            if (!is_error($redis)) {
                if ($redis->get($redis_key)) {
                    show_json(0, '请勿重复点击');
                }
                $redis->setex($redis_key, 1, time());
            }
        }

        //生成申请
        $apply = array();

        //提现方式
        $type_array = array();

        if ($set['withdrawcashweixin'] == 1) {
            $type_array[0]['title'] = '提现到微信钱包';
        }

        if ($set['withdrawcashalipay'] == 1) {
            $type_array[2]['title'] = '提现到支付宝';
        }

        if ($set['withdrawcashcard'] == 1) {
            $type_array[3]['title'] = '提现到银行卡';
            $condition = " and uniacid=:uniacid";
            $params = array(':uniacid' => $_W['uniacid']);
            $banklist = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_commission_bank') . " WHERE 1 {$condition}  ORDER BY displayorder DESC", $params);
        }

        $applytype = intval($_GPC['applytype']);
        if (!array_key_exists($applytype, $type_array)) {
            show_json(0, '未选择提现方式，请您选择提现方式后重试!');
        }

        if ($applytype == 2) {
            //支付宝
            $realname = trim($_GPC['realname']);
            $alipay = trim($_GPC['alipay']);
            $alipay1 = trim($_GPC['alipay1']);
            if (empty($realname)) {
                show_json(0, '请填写姓名!');
            }
            if (empty($alipay)) {
                show_json(0, '请填写支付宝帐号!');
            }
            if (empty($alipay1)) {
                show_json(0, '请填写确认帐号!');
            }
            if ($alipay != $alipay1) {
                show_json(0, '支付宝帐号与确认帐号不一致!');
            }
            $apply['realname'] = $realname;
            $apply['alipay'] = $alipay;
        } else if ($applytype == 3) {
            //银行卡号
            $realname = trim($_GPC['realname']);
            $bankname = trim($_GPC['bankname']);
            $bankcard = trim($_GPC['bankcard']);
            $bankcard1 = trim($_GPC['bankcard1']);
            if (empty($realname)) {
                show_json(0, '请填写姓名!');
            }
            if (empty($bankname)) {
                show_json(0, '请选择银行!');
            }
            if (empty($bankcard)) {
                show_json(0, '请填写银行卡号!');
            }
            if (empty($bankcard1)) {
                show_json(0, '请填写确认卡号!');
            }
            if ($bankcard != $bankcard1) {
                show_json(0, '银行卡号与确认卡号不一致!');
            }
            $apply['realname'] = $realname;
            $apply['bankname'] = $bankname;
            $apply['bankcard'] = $bankcard;
        }

        $realmoney = $money;
        if (!empty($set_array['charge'])) {
            $money_array = m('member')->getCalculateMoney($money, $set_array);

            if ($money_array['flag']) {
                $realmoney = $money_array['realmoney'];
                $deductionmoney = $money_array['deductionmoney'];
            }
        }

        m('member')->setCredit($_W['openid'], 'credit2', -$money, array(0, $_W['shopset']['set'][''] . '余额提现预扣除: ' . $money . ',实际到账金额:' . $realmoney . ',手续费金额:' . $deductionmoney));
        $logno = m('common')->createNO('member_log', 'logno', 'RW');

        $apply['uniacid'] = $_W['uniacid'];
        $apply['logno'] = $logno;
        $apply['openid'] = $_W['openid'];
        $apply['title'] = '余额提现';
        $apply['type'] = 1;
        $apply['createtime'] = time();
        $apply['status'] = 0;
        $apply['money'] = $money;
        $apply['realmoney'] = $realmoney;
        $apply['deductionmoney'] = $deductionmoney;
        $apply['charge'] = $set_array['charge'];
        $apply['applytype'] = $applytype;

        pdo_insert('ewei_shop_member_log', $apply);
        $logid = pdo_insertid();

        //模板消息
        m('notice')->sendMemberLogMessage($logid);

        show_json(1);
    }

    function getLastApply($openid, $applytype = -1)
    {
        global $_W;

        $params = array(':uniacid' => $_W['uniacid'], ':openid' => $openid);
        $sql = "select applytype,alipay,bankname,bankcard,realname from " . tablename('ewei_shop_member_log') . " where openid=:openid and uniacid=:uniacid";

        if ($applytype > -1) {
            $sql .= " and applytype=:applytype";
            $params[':applytype'] = $applytype;
        }
        $sql .= " order by id desc Limit 1";
        $data = pdo_fetch($sql, $params);

        return $data;
    }

}

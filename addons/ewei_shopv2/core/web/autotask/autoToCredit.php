<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class AutoToCredit_EweiShopV2Page extends Page
{

//    public function main()
//    {
//        ignore_user_abort();
//        set_time_limit(0);
//
//        $allWxUsers = pdo_fetchall("select uid,credit1,credit2 from " . tablename("mc_members"));
//        $sysset = m('common')->getSysset();
//        $toCreditPoint = $sysset['shop']['to_money_basic_point'] ?: 100; //每一卫贝等于多少卫卷
//        //绑定微信用户
//        foreach ($allWxUsers as $k => $v) {
//            $userInfo = pdo_fetch("select id,exchange_speed,openid from ".tablename("ewei_shop_member")." where uid=:uid", array(":uid" => $v['uid']));
//            $credit = round($v['credit1'] * ($userInfo['exchange_speed'] * 0.001), 0);
//            $realCredit = round($credit / $toCreditPoint, 2);
//            if ($credit > 0) {
//                //卫贝记录
//                m('member')->setCredit($userInfo['openid'], 'credit1', -$credit, array(0, '卫贝*转换速度值:' . $credit));
//                //卫卷记录
//                m('member')->setCredit($userInfo['openid'], 'credit2', $realCredit, array(0, '卫贝*转换速度值:' . $realCredit));
//                $insertData = array(
//                    'user_id' => $userInfo['id'],
//                    'create_time' => time(),
//                    'now_speed' => $userInfo['exchange_speed'],
//                    'now_credit1' => $v['credit1'],
//                    'now_credit2' => $v['credit2'],
//                    'credit' => $credit,
//                    'real_credit' => $realCredit,
//                    'status' => 1,
//                );
//                pdo_insert('ewei_shop_auto_credit', $insertData);
//            }
//        }
//        //未绑定微信用户
//        $allUsers = pdo_fetchall("select id,openid,credit1,credit2,exchange_speed from ".tablename("ewei_shop_member")." where uid=0 and credit1 > 0");
//        foreach ($allUsers as $k => $v) {
//            $credit = round($v['credit1'] * ($v['exchange_speed'] * 0.001), 0);
//            $realCredit = round($credit / $toCreditPoint, 2);
//            if ($credit > 0) {
//                //卫贝记录
//                m('member')->setCredit($v['openid'], 'credit1', -$credit, array(0, '卫贝*转换速度值:' . $credit));
//                //卫卷记录
//                m('member')->setCredit($v['openid'], 'credit2', $realCredit, array(0, '卫贝*转换速度值:' . $realCredit));
//                $insertData = array(
//                    'user_id' => $v['id'],
//                    'create_time' => time(),
//                    'now_speed' => $v['exchange_speed'],
//                    'now_credit1' => $v['credit1'],
//                    'now_credit2' => $v['credit2'],
//                    'credit' => $credit,
//                    'real_credit' => $realCredit,
//                    'status' => 1,
//                );
//                pdo_insert('ewei_shop_auto_credit', $insertData);
//            }
//        }
//        echo 'finish';
//    }

}


?>

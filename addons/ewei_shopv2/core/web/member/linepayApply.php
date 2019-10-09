<?php
//dezend by http://www.efwww.com/
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class LinepayApply_EweiShopV2Page extends WebPage
{
    public function main()
    {
        global $_W;
        global $_GPC;
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;

        $sql = "select * from " . tablename("ewei_shop_linepay_record") . " WHERE uniacid=:uniacid order by create_time desc";

        $total = pdo_fetchcolumn('SELECT COUNT(1) FROM '.tablename("ewei_shop_linepay_record").' WHERE uniacid=:uniacid',[':uniacid'=>$_W['uniacid']]);

        $sql .= ' limit ' . ($pindex - 1) * $psize . ',' . $psize;
        $list = pdo_fetchall($sql,[':uniacid'=>$_W['uniacid']]);

        foreach ($list as $k => $v) {
            $list[$k]['user'] = pdo_fetch("select level,nickname from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $v['user_id']));
            $list[$k]['levelname'] = pdo_getcolumn('ewei_shop_member_level', array("id" => $list[$k]['user']['level']), 'levelname');
        }

        $pager = pagination2($total, $pindex, $psize);
        include $this->template();
    }

    public function successApply()
    {
        global $_W, $_GPC;
        $recordInfo = pdo_fetch("select * from " . tablename("ewei_shop_linepay_record") . " where id=:id", array(':id' => $_GPC['id']));
        $member = pdo_fetch("select * from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $recordInfo['user_id']));
        $set = m('common')->getSysset();
        $shopset = $set['shop'];
        $default = array('id' => 'default', 'levelname' => empty($set['shop']['levelname']) ? '普通等级' : $set['shop']['levelname'], 'discount' => $set['shop']['leveldiscount'], 'ordermoney' => 0, 'ordercount' => 0, 'membercount' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_member') . ' where uniacid=:uniacid and level=0 limit 1', array(':uniacid' => $_W['uniacid'])));
        $others = pdo_fetchall('SELECT * FROM ' . tablename('ewei_shop_member_level'));
        $levelList = array_merge(array($default), $others);
        include $this->template('member/successApply');
    }

    public function successApplyStore()
    {
        global $_W, $_GPC;
        $recordInfo = pdo_fetch("select * from " . tablename("ewei_shop_linepay_record") . " where id=:id", array(":id" => $_GPC['id']));
        if ($recordInfo['status'] != 1) {
            show_json(0, '该申请已审核');
        }
        if ($_GPC['apply_result'] == 2) { //成功
            $updateData = array(
                'status' => 2,
                'end_time' => time(),
            );
            pdo_update("ewei_shop_linepay_record", $updateData, array('id' => $_GPC['id']));
            $memberInfo = pdo_fetch("select * from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $recordInfo['user_id']));


            //判断是否升级
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
                if ($recordInfo['total_price'] >= $v['stock_money']) { //升级
                    pdo_update('ewei_shop_member', array('level' => $v['id']), array('id' => $memberInfo['id']));
                    com_run('wxcard::updateMemberCardByOpenid', $memberInfo['openid']);
                    //模板消息
                    m('notice')->sendMemberUpgradeMessage($memberInfo['openid'], $memberNowLevel, $v);
                    pdo_update("ewei_shop_linepay_record", array('level' => $v['id']), array('id' => $recordInfo['id']));

                    //判断直推人数是否满足升级
                    $myInviter = pdo_fetch("select id,level,agentid from " . tablename("ewei_shop_member") . " where id=:id", array(":id" => $memberInfo['agentid']));
                    $nowInviterLevel = pdo_getcolumn("ewei_shop_member_level", array("id" => $myInviter['level']), 'level');
                    $nowInviterLevel = $nowInviterLevel ?: 0;
                    $newInviterLevelList = pdo_fetchall("select * from " . tablename("ewei_shop_member_level") . " 
    where level> {$nowInviterLevel} ORDER BY `level` desc");
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
                                $newTwoInviterLevelList = pdo_fetchall("select * from " . tablename("ewei_shop_member_level") . " 
    where level > {$nowTwoInviterLevel} ORDER BY `level` desc");
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
            //进货奖励
            $isFirstStock = pdo_fetch("select count(*) as count from " . tablename("ewei_shop_linepay_record") . " where user_id={$memberInfo['id']} and status=2");
            $memberLevel = pdo_getcolumn("ewei_shop_member", array('id' => $memberInfo['id']), 'level');
            $memberNowLevel = pdo_fetch("select * from " . tablename("ewei_shop_member_level") . " where id=:id", array(':id' => $memberLevel));
            if ($isFirstStock['count'] <= 1 && $memberNowLevel['double_credit'] == 1) { //首次进货(因为目前此订单已支付成功，判断为小于等于1)
                //卫贝奖励
                $credits = $recordInfo['total_price'] * 2;
                m('member')->setCredit($memberInfo['openid'], 'credit1', $credits, array(0, '首次进货卫贝奖励'));
                pdo_update("ewei_shop_linepay_record", array('credit' => $credits), array('id' => $recordInfo['id']));
            } else if ($isFirstStock['count'] > 1 && $memberNowLevel['rebuy_double_credit'] == 1) { //重复进货
                //卫贝奖励
                $credits = $recordInfo['total_price'] * 2;
                m('member')->setCredit($memberInfo['openid'], 'credit1', $credits, array(0, '重复进货卫贝奖励'));
                pdo_update("ewei_shop_linepay_record", array('credit' => $credits), array('id' => $recordInfo['id']));
                //重复购买加速奖励
//                if (!empty($memberNowLevel['rebuy_speed'])) {
//                    m('member')->setAddSpeed($memberInfo['id'], $memberNowLevel['rebuy_speed'], 3, $recordInfo['id'], '重复进货加速奖励');
//                    pdo_update("ewei_shop_linepay_record", array('speed' => $memberNowLevel['rebuy_speed']), array('id' => $recordInfo['id']));
//                }
            }
            //直推和间推奖励 (进货商品)
            if (!empty($memberInfo['agentid'])) {
                //直推
                $inviterInfo = pdo_fetch("select * from " . tablename('ewei_shop_member') . ' where id=:id', array(':id' => $memberInfo['agentid']));
                $inviterLevelLevel = pdo_fetch("select * from " . tablename('ewei_shop_member_level') . " where id=:id", array(":id" => $inviterInfo['level']));

                //加速奖励
                if (!empty($inviterLevelLevel['invite_buy_speed'] && $inviterLevelLevel['invite_buy_speed_unit'] > 0)) {
                    $totalPrice = $recordInfo['total_price'];
                    $nowInviterSpeed = 0;
                    while (($totalPrice - $inviterLevelLevel['invite_buy_speed_unit'] >= 0) && ($nowInviterSpeed <= $inviterLevelLevel['invite_buy_max_speed'])) {
                        $totalPrice -= $inviterLevelLevel['invite_buy_speed_unit'];
                        $nowInviterSpeed += $inviterLevelLevel['invite_buy_speed'];
                        if ($nowInviterSpeed >= $inviterLevelLevel['invite_buy_max_speed']) {
                            $nowInviterSpeed = $inviterLevelLevel['invite_buy_max_speed'];
                            break;
                        }
                    }
                    m('member')->setAddSpeed($inviterInfo['id'], $nowInviterSpeed, 4, $recordInfo['id'], '直推用户进货加速奖励');
                    pdo_update("ewei_shop_linepay_record", array('inviter_speed' => $nowInviterSpeed), array('id' => $recordInfo['id']));
                }
                //直推提成奖励
                if (!empty($inviterLevelLevel['invite_buy_point'])) {
                    $inviteCredit = $recordInfo['total_price'] * $inviterLevelLevel['invite_buy_point'] * 0.01;
                    if (!empty($inviteCredit)) {
                        m('member')->setCredit($inviterInfo['openid'], 'credit2', $inviteCredit, array(0, '直推用户进货提成奖励'));
                        pdo_update("ewei_shop_linepay_record", array('inviter_point' => $inviteCredit), array('id' => $recordInfo['id']));
                    }
                }

                //间推
                if (!empty($inviterInfo['agentid'])) {
                    $inviterTwoInfo = pdo_fetch("select * from " . tablename('ewei_shop_member') . ' where id=:id', array(':id' => $inviterInfo['agentid']));
                    $inviterTwoLevelLevel = pdo_fetch("select * from " . tablename('ewei_shop_member_level') . " where id=:id", array(":id" => $inviterTwoInfo['level']));
                    if (!empty($inviterTwoLevelLevel['invite_two_buy_speed'] && $inviterLevelLevel['invite_two_buy_speed_unit'] > 0)) {
                        $totalPrice = $recordInfo['total_price'];
                        $nowInviterTwoSpeed = 0;
                        while (($totalPrice - $inviterTwoLevelLevel['invite_two_buy_speed_unit'] >= 0) && ($nowInviterTwoSpeed <= $inviterTwoLevelLevel['invite_two_buy_max_speed'])) {
                            $totalPrice -= $inviterTwoLevelLevel['invite_two_buy_speed_unit'];
                            $nowInviterTwoSpeed += $inviterTwoLevelLevel['invite_two_buy_speed'];
                            if ($nowInviterTwoSpeed >= $inviterTwoLevelLevel['invite_two_buy_max_speed']) {
                                $nowInviterTwoSpeed = $inviterTwoLevelLevel['invite_two_buy_max_speed'];
                                break;
                            }
                        }
                        m('member')->setAddSpeed($inviterTwoInfo['id'], $nowInviterTwoSpeed, 5, $recordInfo['id'], '间推用户进货加速奖励');
                        pdo_update("ewei_shop_linepay_record", array('inviter_two_speed' => $nowInviterTwoSpeed), array('id' => $recordInfo['id']));
                    }
                    //间推提成奖励
                    if (!empty($inviterTwoLevelLevel['invite_two_buy_point'])) {
                        $inviteTwoCredit = $recordInfo['total_price'] * $inviterTwoLevelLevel['invite_two_buy_point'] * 0.01;
                        if (!empty($inviteTwoCredit)) {
                            m('member')->setCredit($inviterTwoInfo['openid'], 'credit2', $inviteTwoCredit, array(0, '间推用户进货提成奖励'));
                            pdo_update("ewei_shop_linepay_record", array('inviter_two_point' => $inviteTwoCredit), array('id' => $recordInfo['id']));
                        }
                    }
                }
            }
            show_json(1, '该申请已成功通过');
        } else if ($_GPC['apply_result'] == 3) { //拒绝
            $updateData = array(
                'status' => 3,
                'reject_reason' => $_GPC['reject_reason'],
                'end_time' => time()
            );
            pdo_update("ewei_shop_linepay_record", $updateData, array('id' => $_GPC['id']));
            show_json(1, '该申请已拒绝成功');
        } else {
            show_json(0, '参数错误');
        }
    }
}

?>

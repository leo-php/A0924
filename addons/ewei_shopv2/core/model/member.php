<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Member_EweiShopV2Model {

    /**
     * 获取会员资料
     */
    public function getInfo($openid = '') {
        global $_W;
        $uid = intval($openid);
        if ($uid == 0) {
            $info = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where openid=:openid and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            if (empty($info)) {
                if (strexists($openid, 'sns_qq_')) {
                    $openid = str_replace('sns_qq_', '', $openid);
                    $condition = " openid_qq=:openid ";
                    $bindsns = 'qq';
                } elseif (strexists($openid, 'sns_wx_')) {
                    $openid = str_replace('sns_wx_', '', $openid);
                    $condition = " openid_wx=:openid ";
                    $bindsns = 'wx';
                } elseif (strexists($openid, 'sns_wa_')) {
                    $openid = str_replace('sns_wa_', '', $openid);
                    $condition = " openid_wa=:openid ";
                    $bindsns = 'wa';
                }
                if (!empty($condition)) {
                    $info = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where ' . $condition . '  and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
                    if (!empty($info)) {
                        $info['bindsns'] = $bindsns;
                    }
                }
            }
        } else {
            $info = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where id=:id  and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $uid));
        }
        if (!empty($info['uid'])) {
            //会员余额积分信息
            load()->model('mc');
            $uid = mc_openid2uid($info['openid']);
            $fans = mc_fetch($uid, array('credit1', 'credit2', 'birthyear', 'birthmonth', 'birthday', 'gender', 'avatar', 'resideprovince', 'residecity', 'nickname'));
            $info['credit1'] = $fans['credit1'];
            $info['credit2'] = $fans['credit2'];
            $info['birthyear'] = empty($info['birthyear']) ? $fans['birthyear'] : $info['birthyear'];
            $info['birthmonth'] = empty($info['birthmonth']) ? $fans['birthmonth'] : $info['birthmonth'];
            $info['birthday'] = empty($info['birthday']) ? $fans['birthday'] : $info['birthday'];
            $info['nickname'] = empty($info['nickname']) ? $fans['nickname'] : $info['nickname'];
            $info['gender'] = empty($info['gender']) ? $fans['gender'] : $info['gender'];
            $info['sex'] = $info['gender'];
            $info['avatar'] = empty($info['avatar']) ? $fans['avatar'] : $info['avatar'];
            $info['headimgurl'] = $info['avatar'];
            $info['province'] = empty($info['province']) ? $fans['resideprovince'] : $info['province'];
            $info['city'] = empty($info['city']) ? $fans['residecity'] : $info['city'];
        }
        if (!empty($info['birthyear']) && !empty($info['birthmonth']) && !empty($info['birthday'])) {
            $info['birthday'] = $info['birthyear'] . '-' . (strlen($info['birthmonth']) <= 1 ? '0' . $info['birthmonth'] : $info['birthmonth']) . '-' . (strlen($info['birthday']) <= 1 ? '0' . $info['birthday'] : $info['birthday']);
        }
        if (empty($info['birthday'])) {
            $info['birthday'] = '';
        }
        if (!empty($info)) {
            if (!strexists($info['avatar'], 'http://') && !strexists($info['avatar'], 'https://')) {
                $info['avatar'] = tomedia($info['avatar']);
            }
            if ($_W['ishttps']) {
                $info['avatar'] = str_replace('http://', 'https://', $info['avatar']);
            }
        }
        return $info;
    }

    /**
     * 获取会员信息
     */
    public function getMember($openid = '') {
        global $_W;
        $uid = (int)$openid;
        if ($uid == 0) {
            $info = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where  openid=:openid and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            if (empty($info)) {
                if (strexists($openid, 'sns_qq_')) {
                    $openid = str_replace('sns_qq_', '', $openid);
                    $condition = " openid_qq=:openid ";
                    $bindsns = 'qq';
                } elseif (strexists($openid, 'sns_wx_')) {
                    $openid = str_replace('sns_wx_', '', $openid);
                    $condition = " openid_wx=:openid ";
                    $bindsns = 'wx';
                } elseif (strexists($openid, 'sns_wa_')) {
                    $openid = str_replace('sns_wa_', '', $openid);
                    $condition = " openid_wa=:openid ";
                    $bindsns = 'wa';
                }
                if (!empty($condition)) {
                    $info = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where ' . $condition . '  and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
                    if (!empty($info)) {
                        $info['bindsns'] = $bindsns;
                    }
                }
            }
        } else {
            $info = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where id=:id and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $openid));
        }

        if (!empty($info)) {
            if (!strexists($info['avatar'], 'http://') && !strexists($info['avatar'], 'https://')) {
                $info['avatar'] = tomedia($info['avatar']);
            }
            if ($_W['ishttps']) {
                $info['avatar'] = str_replace('http://', 'https://', $info['avatar']);
            }

            if (strpos($info['avatar'], '132132')) {
                $upgrade2 = array();
                $upgrade2['avatar'] = str_replace('132132', '132', $info['avatar']);
                pdo_update('ewei_shop_member', $upgrade2, array('id' => $info['id']));
            }

            $info = $this->updateCredits($info);
        }
        return $info;
    }

    public function updateCredits($info) {
        global $_W;
        $openid = $info['openid'];
        //自动检测UID情况
        if (empty($info['uid'])) {
            $followed = m('user')->followed($openid);

            if ($followed) {
                load()->model('mc');
                $uid = mc_openid2uid($openid);
                if (!empty($uid)) {
                    $info['uid'] = $uid;
                    $upgrade = array('uid' => $uid);
                    if ($info['credit1'] > 0) {
                        //同步积分
                        mc_credit_update($uid, 'credit1', $info['credit1']);
                        $upgrade['credit1'] = 0;
                    }
                    if ($info['credit2'] > 0) {
                        //同步余额
                        mc_credit_update($uid, 'credit2', $info['credit2']);
                        $upgrade['credit2'] = 0;
                    }
                    if (!empty($upgrade)) {
                        pdo_update('ewei_shop_member', $upgrade, array('id' => $info['id']));
                    }
                }
            }
        }
        $credits = $this->getCredits($openid);
        $info['credit1'] = $credits['credit1'];
        $info['credit2'] = $credits['credit2'];
        return $info;
    }

    /**
     * 通过手机号获取用户信息
     * @param $mobile
     */
    public function getMobileMember($mobile) {
        global $_W;
        $info = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where mobile=:mobile and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':mobile' => $mobile));
        if (!empty($info)) {
            $info = $this->updateCredits($info);
        }
        return $info;
    }

    public function getMid() {
        global $_W;
        $openid = $_W['openid'];
        $member = $this->getMember($openid);
        return $member['id'];
    }

    //处理积分或余额
    public function setCredit($openid = '', $credittype = 'credit1', $credits = 0, $log = array()) {

        global $_W;
        load()->model('mc');
        $uid = mc_openid2uid($openid);

        $member = $this->getMember($openid);
        if (empty($uid)) {
            $uid = intval($member['uid']);
        }

        if (empty($log)) {
            $log = array($uid, '未记录');
        } elseif (!is_array($log)) {
            $log = array(0, $log);
        }

        if ($credittype == 'credit1' && empty($log[0]) && $credits > 0) {
            // 系统充值 判断是否达到积分上限
            $shopset = m('common')->getSysset('trade');

            if (empty($member['diymaxcredit'])) {
                // 系统设置
                if ($shopset['maxcredit'] > 0) {
                    if ($member['credit1'] >= $shopset['maxcredit']) {
                        return error(-1, "用户积分已达上限");
                    } elseif ($member['credit1'] + $credits > $shopset['maxcredit']) {
                        $credits = $shopset['maxcredit'] - $member['credit1'];
                    }
                }
            } else {
                // 用户设置
                if ($member['maxcredit'] > 0) {
                    if ($member['credit1'] >= $member['maxcredit']) {
                        return error(-1, "用户积分已达上限");
                    } elseif ($member['credit1'] + $credits > $member['maxcredit']) {
                        $credits = $member['maxcredit'] - $member['credit1'];
                    }
                }
            }
        }

        if (empty($log)) {
            $log = array($uid, '未记录');
        } elseif (!is_array($log)) {
            $log = array(0, $log);
        }

        $log_data = array(
            'uid'        => intval($uid),
            'credittype' => $credittype,
            'uniacid'    => $_W['uniacid'],
            'num'        => $credits,
            'createtime' => TIMESTAMP,
            'module'     => 'ewei_shopv2',
            'operator'   => intval($log[0]),
            'remark'     => $log[1],
        );
        if (!empty($uid)) {
            //如果已关注
            $value = pdo_fetchcolumn("SELECT {$credittype} FROM " . tablename('mc_members') . " WHERE `uid` = :uid", array(':uid' => $uid));
            $newcredit = $credits + $value;
            if ($newcredit <= 0) {
                $newcredit = 0;
            }
            $log_data['remark'] = $log_data['remark'] . " 剩余: " . $newcredit;
            pdo_update('mc_members', array($credittype => $newcredit), array('uid' => $uid));
        } else {
            //如果未关注
            $value = pdo_fetchcolumn("SELECT {$credittype} FROM " . tablename('ewei_shop_member') . " WHERE  uniacid=:uniacid and openid=:openid limit 1", array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            $newcredit = $credits + $value;
            if ($newcredit <= 0) {
                $newcredit = 0;
            }
            pdo_update('ewei_shop_member', array($credittype => $newcredit), array('uniacid' => $_W['uniacid'], 'openid' => $openid));
            $log_data['remark'] = $log_data['remark'] . " OPENID: " . $openid;
            $log_data['remark'] = $log_data['remark'] . " 剩余: " . $newcredit;
        }
        pdo_insert('mc_credits_record', $log_data);
        //新增商城积分(余额)记录表
        $member_log_table_flag = pdo_tableexists('ewei_shop_member_credit_record');
        if ($member_log_table_flag) {
            $log_data['openid'] = $openid;
            pdo_insert('ewei_shop_member_credit_record', $log_data);
        }

        if (p('task')) {//##任务中心
            if ($credittype == 'credit1') {

            } else {
                p('task')->checkTaskReward('cost_rechargeenough', $credits, $openid);
                p('task')->checkTaskReward('cost_rechargetotal', $credits, $openid);
            }
        }

        if (p('task')) {//##任务中心
            p('task')->checkTaskProgress($credits, 'recharge_full', 0, $openid);
            p('task')->checkTaskProgress($credits, 'recharge_count', 0, $openid);
        }

        //com('wxcard')->updateMemberCardByOpenid($openid);
        com_run('wxcard::updateMemberCardByOpenid', $openid);
    }

    //获取积分或余额
    public function getCredit($openid = '', $credittype = 'credit1') {

        global $_W;
        $openid = str_replace('sns_wa_', '', $openid);

        load()->model('mc');
        $uid = mc_openid2uid($openid);

        if (!empty($uid)) {
            //如果已关注
            return pdo_fetchcolumn("SELECT {$credittype} FROM " . tablename('mc_members') . " WHERE `uid` = :uid", array(':uid' => $uid));

        } else {
            //如果未关注
            $item = pdo_fetch("SELECT {$credittype} FROM " . tablename('ewei_shop_member') . " WHERE openid=:openid and uniacid=:uniacid limit 1", array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            if (empty($item)) {
                $item = pdo_fetch("SELECT {$credittype} FROM " . tablename('ewei_shop_member') . " WHERE openid_wa=:openid and uniacid=:uniacid limit 1", array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            }

            return empty($item[$credittype]) ? 0 : $item[$credittype];
        }
    }

    //获取积分或余额
    public function getCredits($openid = '', $credittypes = array('credit1', 'credit2')) {

        global $_W;
        $openid = str_replace('sns_wa_', '', $openid);
        load()->model('mc');
        $uid = mc_openid2uid($openid);

        $types = implode(',', $credittypes);
        if (!empty($uid)) {
            //如果已关注
            return pdo_fetch("SELECT {$types} FROM " . tablename('mc_members') . " WHERE `uid` = :uid limit 1", array(':uid' => $uid));
        } else {
            $item = pdo_fetch("SELECT {$types} FROM " . tablename('ewei_shop_member') . " WHERE openid=:openid and uniacid=:uniacid limit 1", array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            if (empty($item)) {
                $item = pdo_fetch("SELECT {$types} FROM " . tablename('ewei_shop_member') . " WHERE openid_wa=:openid and uniacid=:uniacid limit 1", array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            }
            //如果未关注
//            return pdo_fetch("SELECT {$types} FROM " . tablename('ewei_shop_member') . " WHERE  (openid=:openid or openid_wa=:openid) and uniacid=:uniacid limit 1", array(':uniacid' => $_W['uniacid'], ':openid' => $openid));
            if (empty($item)) {
                return array('credit1' => 0, 'credit2' => 0);
            }
            return $item;
        }
    }

    /**
     * 检测用户,注册用户
     */
    public function checkMember($inviterId = '') {

        global $_W;

        $member = array();
        $shopset = m('common')->getSysset(array('shop', 'wap'));
        $openid = $_W['openid'];

        if ($_W['routes'] == 'order.pay_alipay' || $_W['routes'] == 'creditshop.log.dispatch_complete' || $_W['routes'] == 'threen.register.threen_complete' || $_W['routes'] == 'creditshop.detail.creditshop_complete' || $_W['routes'] == 'order.pay_alipay.recharge_complete' || $_W['routes'] == 'order.pay_alipay.complete' || $_W['routes'] == 'newmr.alipay' || $_W['routes'] == 'newmr.callback.gprs' || $_W['routes'] == 'newmr.callback.bill' || $_W['routes'] == 'account.sns' || $_W['plugin'] == 'mmanage' || $_W['routes'] == 'live.send.credit' || $_W['routes'] == 'live.send.coupon' || $_W['routes'] == 'index.share_url') {
            return;
        }

        if ($shopset['wap']['open']) {
            if ($shopset['wap']['inh5app'] && is_h5app() || (empty($shopset['wap']['inh5app']) && empty($openid))) {
                return;
            }
        }

        //如果你把下面这个if注释了,你就倒霉了
        if (empty($openid) && !EWEI_SHOPV2_DEBUG) {
            $diemsg = is_h5app() ? "APP正在维护, 请到公众号中访问" : "请在微信客户端打开链接";
            die("<!DOCTYPE html>
                <html>
                    <head>
                        <meta name='viewport' content='width=device-width, initial-scale=1, user-scalable=0'>
                        <title>抱歉，出错了</title><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1, user-scalable=0'><link rel='stylesheet' type='text/css' href='https://res.wx.qq.com/connect/zh_CN/htmledition/style/wap_err1a9853.css'>
                    </head>
                    <body>
                    <div class='page_msg'><div class='inner'><span class='msg_icon_wrp'><i class='icon80_smile'></i></span><div class='msg_content'><h4>" . $diemsg . "</h4></div></div></div>
                    </body>
                </html>");
        }
        $member = $this->getMember($openid);
        $followed = m('user')->followed($openid);
        $uid = 0;
        $mc = array();
        load()->model('mc');
        if ($followed || empty($shopset['shop']['getinfo']) || $shopset['shop']['getinfo'] == 1) {
            $uid = mc_openid2uid($openid);
            if (!EWEI_SHOPV2_DEBUG) {
                $userinfo = mc_oauth_userinfo();
            } else {
                $userinfo = array(
                    'openid'     => $member['openid'],
                    'nickname'   => $member['nickname'],
                    'headimgurl' => $member['avatar'],
                    'gender'     => $member['gender'],
                    'province'   => $member['province'],
                    'city'       => $member['city']
                );
            }

            $mc = array();
            $mc['nickname'] = $userinfo['nickname'];
            $mc['avatar'] = $userinfo['headimgurl'];
            $mc['gender'] = $userinfo['sex'];
            $mc['resideprovince'] = $userinfo['province'];
            $mc['residecity'] = $userinfo['city'];
        }

        if (empty($member) && !empty($openid)) {
            $member = array(
                'uniacid'         => $_W['uniacid'],
                'uid'             => $uid,
                'openid'          => $openid,
                'realname'        => !empty($mc['realname']) ? $mc['realname'] : '',
                'mobile'          => !empty($mc['mobile']) ? $mc['mobile'] : '',
                'nickname'        => !empty($mc['nickname']) ? $mc['nickname'] : '',
                'nickname_wechat' => !empty($mc['nickname']) ? $mc['nickname'] : '',
                'avatar'          => !empty($mc['avatar']) ? $mc['avatar'] : '',
                'avatar_wechat'   => !empty($mc['avatar']) ? $mc['avatar'] : '',
                'gender'          => !empty($mc['gender']) ? $mc['gender'] : '-1',
                'province'        => !empty($mc['resideprovince']) ? $mc['resideprovince'] : '',
                'city'            => !empty($mc['residecity']) ? $mc['residecity'] : '',
                'area'            => !empty($mc['residedist']) ? $mc['residedist'] : '',
                'createtime'      => time(),
                'status'          => 0,
                'inviter'         => $inviterId,
            );
            pdo_insert('ewei_shop_member', $member);
            if (method_exists(m('member'), 'memberRadisCountDelete')) {
                m('member')->memberRadisCountDelete(); //清除会员统计radis缓存
            }
            $member['id'] = pdo_insertid();
            //首次注册赠送积分和加速度
            $sysset = m('common')->getSysset();
            $registerIntegral = $sysset['shop']['register_integral'];


            if ($registerIntegral && $registerIntegral > 0) {
                m('member')->setCredit($openid, 'credit1', $registerIntegral, array(0, '注册赠送' . $registerIntegral . '卫贝'));
                m('member')->setAddSpeed($member['id'], $sysset['shop']['to_money_basic_speed'], 1, '', '用户注册获取基础加速值');
            }

            $recommend_integral = $sysset['shop']['recommend_integral'];

            if (!empty($recommend_integral) && !empty($inviterId)) {
                $agent = pdo_fetch('SELECT id,openid FROM ' . tablename('ewei_shop_member') . ' WHERE uniacid=:uniacid AND id=:id LIMIT 1', [
                    ':uniacid' => $_W['uniacid'],
                    ':id'      => $inviterId
                ]);
                if (!empty($agent)) {
                    m('member')->setCredit($agent['openid'], 'credit1', $recommend_integral, array(0, '推荐注册赠送' . $registerIntegral . '卫贝'));
                }
            }

            //直推下载注册奖励
            if (!empty($inviterId)) {
                $inviterLevel = pdo_getcolumn("ewei_shop_member", array('id' => $inviterId), 'level');
                if (!$inviterLevel) {
                    $inviteSpeed = pdo_getcolumn("ewei_shop_member_level", array('level' => 1), 'invite_speed');
                } else {
                    $inviteSpeed = pdo_getcolumn("ewei_shop_member_level", array('id' => $inviterLevel), 'invite_speed');
                }
                m('member')->setAddSpeed($inviterId, $inviteSpeed, 2, $member['id'], '邀请用户' . $member['id'] . '下载注册获取');
            }
        } else {
            if ($member['isblack'] == 1) {
                //是黑名单
                show_message("暂时无法访问，请稍后再试!");
            }
            $upgrade = array('uid' => $uid);
            if (isset($mc['nickname']) && $member['nickname_wechat'] != $mc['nickname']) {
                $upgrade['nickname_wechat'] = $mc['nickname'];
            }
            if (isset($mc['nickname']) && empty($member['nickname'])) {
                $upgrade['nickname'] = $mc['nickname'];
            }
            if (isset($mc['avatar']) && $member['avatar_wechat'] != $mc['avatar']) {
                $upgrade['avatar_wechat'] = $mc['avatar'];
            }
            if (isset($mc['avatar']) && empty($member['avatar'])) {
                $upgrade['avatar'] = $mc['avatar'];
            }

            if (isset($mc['gender']) && $member['gender'] != $mc['gender']) {
                $upgrade['gender'] = $mc['gender'];
            }
            if (!empty($upgrade)) {
                pdo_update('ewei_shop_member', $upgrade, array('id' => $member['id']));
            }
        }
        //分销商
        if (p('commission')) {
            p('commission')->checkAgent($openid);
        }

        //海报图扫描
        if (p('poster')) {
            p('poster')->checkScan($openid);
        }
        if (empty($member)) {
            return false;
        }

        return array(
            'id'     => $member['id'],
            'openid' => $member['openid']
        );
    }

    /**
     * 获取所有会员等级
     * @return type
     * @global type $_W
     */
    function getLevels($all = true) {
        global $_W;

        $condition = '';
        if (!$all) {
            $condition = " and enabled=1";
        }
        return pdo_fetchall('select * from ' . tablename('ewei_shop_member_level') . ' where uniacid=:uniacid' . $condition . ' order by level asc', array(':uniacid' => $_W['uniacid']));
    }

    //获取会员等级
    function getLevel($openid) {
        global $_W, $_S;
        if (empty($openid)) {
            return false;
        }
        $member = m('member')->getMember($openid);
        if (!empty($member) && !empty($member['level'])) {
            $level = pdo_fetch('select * from ' . tablename('ewei_shop_member_level') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $member['level'], ':uniacid' => $_W['uniacid']));
            if (!empty($level)) {
                return $level;
            }
        }
        return array(
            'level' => 0,
            'levelname' => empty($_S['shop']['levelname']) ? '普通会员' : $_S['shop']['levelname'],
            'discount'  => empty($_S['shop']['leveldiscount']) ? 10 : $_S['shop']['leveldiscount'],
        );
    }

    function getOneGoodsLevel($openid, $goodsid) {
        global $_W;

        $uniacid = $_W['uniacid'];

        $level_info = $this->getLevel($openid);
        $level = intval($level_info['level']);

        $data = array();
        $levels = pdo_fetchall('select * from ' . tablename('ewei_shop_member_level') . ' where uniacid=:uniacid and buygoods=1 and level and level > :level order by level asc', array(':uniacid' => $uniacid, ':level' => $level));

        if (!empty($levels)) {

            foreach ($levels as $k => $v) {
                $goodsids = iunserializer($v['goodsids']);

                if (!empty($goodsids)) {
                    if (in_array($goodsid, $goodsids)) {
                        $data = $v;
                    }
                }
            }
        }
        return $data;
    }

    function getGoodsLevel($openid, $orderid) {
        global $_W;

        $uniacid = $_W['uniacid'];

        $order_goods = pdo_fetchall("select goodsid from " . tablename('ewei_shop_order_goods') . " where orderid=:orderid and uniacid=:uniacid", array(':uniacid' => $uniacid, ':orderid' => $orderid));

        $levels = array();
        $data = array();
        if (!empty($order_goods)) {
            foreach ($order_goods as $k => $v) {
                $item = $this->getOneGoodsLevel($openid, $v['goodsid']);
                if (!empty($item)) {
                    $levels[$item['level']] = $item;
                }
            }
        }

        if (!empty($levels)) {
            $level = max(array_keys($levels));
            $data = $levels[$level];
        }
        return $data;
    }

    /**
     * 会员升级
     * @param type $mid
     */
    function upgradeLevel($openid, $orderid = 0) {
        global $_W;
        if (empty($openid)) {
            return;
        }

        $shopset = m('common')->getSysset('shop');
        $leveltype = intval($shopset['leveltype']);
        $member = m('member')->getMember($openid);
        if (empty($member)) {
            return;
        }

        //查找符合条件的新等级
        $level = false;
        if (empty($leveltype)) {
            /*$ordermoney = pdo_fetchcolumn('select ifnull( sum(og.realprice),0) from ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_order') . ' o on o.id=og.orderid '
                . ' where o.openid=:openid and o.status=3 and o.uniacid=:uniacid ', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));*/
            //根据订单支付金额来判断   -- yqj --
            $ordermoney = pdo_fetchcolumn('select sum(price) from ' . tablename('ewei_shop_order') . " where uniacid=:uniacid and openid=:openid and status=3 limit 1", array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));
            $ordermoney = floatval($ordermoney);
            $level = pdo_fetch('select * from ' . tablename('ewei_shop_member_level') . " where uniacid=:uniacid  and enabled=1 and {$ordermoney} >= ordermoney and ordermoney>0  order by level desc limit 1", array(':uniacid' => $_W['uniacid']));
        } else if ($leveltype == 1) {
            $ordercount = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . ' where openid=:openid and status=3 and uniacid=:uniacid ', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));
            $level = pdo_fetch('select * from ' . tablename('ewei_shop_member_level') . " where uniacid=:uniacid and enabled=1 and {$ordercount} >= ordercount and ordercount>0  order by level desc limit 1", array(':uniacid' => $_W['uniacid']));
        }

        //购买特定商品达到的会员级别
        if (!empty($orderid)) {
            $goods_level = $this->getGoodsLevel($openid, $orderid);
            if (empty($level)) {
                $level = $goods_level;
            } else {
                if (!empty($goods_level)) {
                    if ($goods_level['level'] > $level['level']) {
                        $level = $goods_level;
                    }
                }
            }
        }

        if (empty($level)) {
            return;
        }
        if ($level['id'] == $member['level']) {
            return;
        }

        //旧等级
        $oldlevel = $this->getLevel($openid);
        $canupgrade = false;  //是否可以升级

        if (empty($oldlevel['id'])) {
            //用户没有等级
            $canupgrade = true;
        } else {
            if ($level['level'] > $oldlevel['level']) {
                //新等级权重较大
                $canupgrade = true;
            }
        }
        if ($canupgrade) {
            //会员升级
            pdo_update('ewei_shop_member', array('level' => $level['id']), array('id' => $member['id']));

            //com('wxcard')->updateMemberCardByOpenid($openid);
            com_run('wxcard::updateMemberCardByOpenid', $openid);

            //模板消息
            m('notice')->sendMemberUpgradeMessage($openid, $oldlevel, $level);

            //判断直推人数是否满足升级
            $myInviter = pdo_fetch("select id,level,inviter from " . tablename("ewei_shop_member") . " where openid=:id", array(":id" => $openid));
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
        }
    }

    /**
     * 根据会员等级ID升级会员
     * @param type $mid
     */
    function upgradeLevelByLevelId($openid, $LevelID) {
        global $_W;
        if (empty($openid)) {
            return;
        }

        $member = m('member')->getMember($openid);
        if (empty($member)) {
            return;
        }

        $level = pdo_fetch('select *  from ' . tablename('ewei_shop_member_level') . " where uniacid=:uniacid and enabled=1 and id=:id", array(':uniacid' => $_W['uniacid'], ':id' => $LevelID));

        if (empty($level)) {
            return;
        }
        if ($level['id'] == $member['level']) {
            return;
        }

        //旧等级
        $oldlevel = $this->getLevel($openid);
        $canupgrade = false;  //是否可以升级

        if (empty($oldlevel['id'])) {
            //用户没有等级
            $canupgrade = true;
        } else {
            if ($level['level'] > $oldlevel['level']) {
                //新等级权重较大
                $canupgrade = true;
            }
        }
        if ($canupgrade) {
            //会员升级
            pdo_update('ewei_shop_member', array('level' => $level['id']), array('id' => $member['id']));

            //com('wxcard')->updateMemberCardByOpenid($openid);
            com_run('wxcard::updateMemberCardByOpenid', $openid);

            //模板消息
            m('notice')->sendMemberUpgradeMessage($openid, $oldlevel, $level);
        }
    }

    /**
     * 获取所有会员分组
     * @return type
     * @global type $_W
     */
    function getGroups() {
        global $_W;
        return pdo_fetchall('select * from ' . tablename('ewei_shop_member_group') . ' where uniacid=:uniacid order by id asc', array(':uniacid' => $_W['uniacid']));
    }

    //获取会员分组
    function getGroup($openid) {
        if (empty($openid)) {
            return false;
        }

        $member = m('member')->getMember($openid);
        return $member['groupid'];
    }

    //给会员设置分组
    function setGroups($openid, $group_ids, $reason = '') {
        $is_id = false;
        if (intval($openid) > 0) {//id
            $openid = m('member')->getInfo($openid);
            if (empty($openid['openid'])) return false;
            $openid = $openid['openid'];
        }
        $condition = array('openid' => $openid);
        if (is_array($group_ids)) {
            $group_arr = $group_ids;
            $group_ids = implode(',', $group_ids);
        } elseif (is_string($group_ids) || is_numeric($group_ids)) {
            $group_arr = explode(',', $group_ids);
        } else {
            return false;
        }
        $old_group_ids = pdo_getcolumn('ewei_shop_member', $condition, 'groupid');
        $diff_ids = explode(',', $group_ids);
        if (!empty($old_group_ids)) {
            $old_group_ids = explode(',', $old_group_ids);
            $group_ids = array_merge($old_group_ids, $diff_ids);
            $group_ids = array_flip(array_flip($group_ids));
            $group_ids = implode(',', $group_ids);
            $diff_ids = array_diff($diff_ids, $old_group_ids);
        }
        pdo_update('ewei_shop_member', array('groupid' => $group_ids), $condition);
        foreach ($diff_ids as $groupid) {
            pdo_insert('ewei_shop_member_group_log',
                array('add_time' => date('Y-m-d H:i:s'), 'group_id' => $groupid, 'content' => $reason, 'mid' => intval($openid), 'openid' => $is_id ? '' : $openid));
        }
        return true;
    }

    //充值积分
    function setRechargeCredit($openid = '', $money = 0) {
        if (empty($openid)) {
            return;
        }
        global $_W;
        $credit = 0;
        $set = m('common')->getSysset(array('trade', 'shop'));

        if ($set['trade']) {
            $tmoney = floatval($set['trade']['money']);
            if (!empty($tmoney)) {
                $tcredit = intval($set['trade']['credit']);
                if ($money >= $tmoney) {
                    if ($money % $tmoney == 0) {
                        $credit = intval($money / $tmoney) * $tcredit;
                    } else {
                        $credit = (intval($money / $tmoney) + 1) * $tcredit;
                    }
                }
            }
        }
        if ($credit > 0) {
            $this->setCredit($openid, 'credit1', $credit, array(0, $set['shop']['name'] . '会员充值积分:credit2:' . $credit));
        }
    }

    //计算税费等相关费用
    function getCalculateMoney($money, $set_array) {

        $charge = $set_array['charge'];
        $begin = $set_array['begin'];
        $end = $set_array['end'];

        $array = array();
        $array['deductionmoney'] = round($money * $charge / 100, 2);
        if ($array['deductionmoney'] >= $begin && $array['deductionmoney'] <= $end) {
            $array['deductionmoney'] = 0;
        }
        $array['realmoney'] = round($money - $array['deductionmoney'], 2);
        if ($money == $array['realmoney']) {
            $array['flag'] = 0;
        } else {
            $array['flag'] = 1;
        }
        return $array;
    }

    public function checkMemberFromPlatform($openid = '', $acc = '') {
        global $_W;
        if (empty($acc)) {
            $acc = WeiXinAccount::create();
        }
        $userinfo = $acc->fansQueryInfo($openid);
        $userinfo['avatar'] = $userinfo['headimgurl'];

        $redis = redis();
        if (!is_error($redis)) {
            $member = $redis->get($openid . '_checkMemberFromPlatform');
            if (!empty($member)) {
                return json_decode($member, true);
            }
        }

        load()->model('mc');
        $uid = mc_openid2uid($openid);
        if (!empty($uid)) {
            pdo_update('mc_members', array(
                'nickname'       => $userinfo['nickname'],
                'gender'         => $userinfo['sex'],
                'nationality'    => $userinfo['country'],
                'resideprovince' => $userinfo['province'],
                'residecity'     => $userinfo['city'],
                'avatar'         => $userinfo['headimgurl']), array('uid' => $uid)
            );
        }
        pdo_update('mc_mapping_fans', array(
            'nickname' => $userinfo['nickname']
        ), array('uniacid' => $_W['uniacid'], 'openid' => $openid));

        $member = $this->getMember($openid);
        if (empty($member)) {
            $mc = mc_fetch($uid, array('realname', 'nickname', 'mobile', 'avatar', 'resideprovince', 'residecity', 'residedist'));
            $member = array(
                'uniacid'    => $_W['uniacid'],
                'uid'        => $uid,
                'openid'     => $openid,
                'realname'   => $mc['realname'],
                'mobile'     => $mc['mobile'],
                'nickname'   => !empty($mc['nickname']) ? $mc['nickname'] : $userinfo['nickname'],
                'avatar'     => !empty($mc['avatar']) ? $mc['avatar'] : $userinfo['avatar'],
                'gender'     => !empty($mc['gender']) ? $mc['gender'] : $userinfo['sex'],
                'province'   => !empty($mc['resideprovince']) ? $mc['resideprovince'] : $userinfo['province'],
                'city'       => !empty($mc['residecity']) ? $mc['residecity'] : $userinfo['city'],
                'area'       => $mc['residedist'],
                'createtime' => time(),
                'status'     => 0
            );
            pdo_insert('ewei_shop_member', $member);
            if (method_exists(m('member'), 'memberRadisCountDelete')) {
                m('member')->memberRadisCountDelete(); //清除会员统计radis缓存
            }
            $member['id'] = pdo_insertid();
            $member['isnew'] = true;
        } else {
            $member['nickname'] = $userinfo['nickname'];
            $member['avatar'] = $userinfo['headimgurl'];
            $member['province'] = $userinfo['province'];
            $member['city'] = $userinfo['city'];
            pdo_update('ewei_shop_member', $member, array('id' => $member['id']));
            if ((time() - $member['createtime']) < 60) {
                $member['isnew'] = true;
            } else {
                $member['isnew'] = false;
            }
        }
        if (!is_error($redis)) {
            $redis->set($openid . '_checkMemberFromPlatform', json_encode($member), 20);
        }
        return $member;
    }

    public function mc_update($mid, $data) {
        global $_W;

        if (empty($mid) || empty($data)) {
            return;
        }
        $wapset = m('common')->getSysset('wap');
        $member = $this->getMember($mid);
        if (!empty($wapset['open']) && isset($data['mobile']) && $data['mobile'] != $member['mobile']) {
            unset($data['mobile']);
        }
        load()->model('mc');
        mc_update($this->member['uid'], $data);
    }

    public function checkMemberSNS($sns) {
        global $_W, $_GPC;

        if (empty($sns)) {
            $sns = $_GPC['sns'];
        }
        if (empty($sns)) {
            return;
        } elseif ($sns == 'wx') {
            load()->func('communication');

            $token = trim($_GPC['token']);
            $openid = trim($_GPC['openid']);

            $appid = 'wxc3d9d8efae0ae858';
            $secret = '93d4f6085f301c405b5812217e6d5025';

            if (empty($token) && !empty($_GPC['code'])) {
                $codeurl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appid . "&secret=" . $secret . "&code=" . trim($_GPC['code']) . "&grant_type=authorization_code";
                $coderesult = $userinfo = ihttp_request($codeurl);
                $coderesult = json_decode($coderesult['content'], true);
                if (empty($coderesult['access_token']) || empty($coderesult['openid'])) {
                    return;
                }
                $token = $coderesult['access_token'];
                $openid = $coderesult['openid'];
            }

            if (empty($token) || empty($openid)) {
                return;
            }
            $snsurl = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $token . "&openid=" . $openid . "&lang=zh_CN";
            $userinfo = ihttp_request($snsurl);
            $userinfo = json_decode($userinfo['content'], true);
            if (empty($userinfo['openid'])) {
                return;
            }
            $userinfo['openid'] = 'sns_wx_' . $userinfo['openid'];
        } elseif ($sns == 'qq') {
            $userinfo = htmlspecialchars_decode($_GPC['userinfo']);
            $userinfo = json_decode($userinfo, true);
            $userinfo['openid'] = 'sns_qq_' . $_GPC['openid'];
            $userinfo['headimgurl'] = $userinfo['figureurl_qq_2'];
            $userinfo['gender'] = $userinfo['gender'] == '男' ? 1 : 2;
        }

        $data = array(
            'nickname' => $userinfo['nickname'],
            'avatar'   => $userinfo['headimgurl'],
            'province' => $userinfo['province'],
            'city'     => $userinfo['city'],
            'gender'   => $userinfo['sex'],
            'comefrom' => 'h5app_sns_' . $sns
        );

        $openid = trim($_GPC['openid']);
        if ($sns == 'qq') {
            $data['openid_qq'] = trim($_GPC['openid']);
            $openid = 'sns_qq_' . trim($_GPC['openid']);
        }
        if ($sns == 'wx') {
            $data['openid_wx'] = trim($_GPC['openid']);
            $openid = 'sns_wx_' . trim($_GPC['openid']);
        }

        $member = $this->getMember($openid);

        if (empty($member)) {
            $data['openid'] = $userinfo['openid'];
            $data['uniacid'] = $_W['uniacid'];
            $data['comefrom'] = 'sns_' . $sns;
            $data['createtime'] = time();
            $data['salt'] = m('account')->getSalt();
            $data['pwd'] = rand(10000, 99999) . $data['salt'];
            pdo_insert('ewei_shop_member', $data);
            if (method_exists(m('member'), 'memberRadisCountDelete')) {
                m('member')->memberRadisCountDelete(); //清除会员统计radis缓存
            }
            return pdo_insertid();
        } elseif (empty($member['bindsns']) || $member['bindsns'] == $sns) {
            pdo_update('ewei_shop_member', $data, array('id' => $member['id'], 'uniacid' => $_W['uniacid']));
            return $member['id'];
        }
    }

    /**
     * @param array $level 数组中有两个参数 一个是等级,一个是新等级
     * @param array $levels 分销商等级数组
     * @return bool 如果新等级大于 原等级 则返回true 否则 false
     */
    public function compareLevel(array $level, array $levels = array()) {
        global $_W;
        $levels = !empty($levels) ? $levels : $this->getLevels();
        $old_key = -1;
        $new_key = -1;

        foreach ($levels as $kk => $vv) {
            if ($vv['id'] == $level[0]) {
                $old_key = $vv['level'];
            }
            if ($vv['id'] == $level[1]) {
                $new_key = $vv['level'];
            }
        }

        return $new_key > $old_key;
    }

    /**
     * 微信获取网页openid整合
     * @param $appid APPID;
     * @param $secret SECRET;
     * @param $snsapi 类型有snsapi_userinfo,snsapi_base;
     * @param $expired 本地cookie缓存过期时间 默认600秒
     * @return array(openid,nickname,sex,province,city,country,headimgurl,privilege,[unionid])
     */
    public function wxuser($appid, $secret, $snsapi = 'snsapi_base', $expired = '600') {
        global $_W;
        $wxuser = $_COOKIE[$_W['config']['cookie']['pre'] . $appid];
        if ($wxuser === null) {
            $http = 'http://';
            if (isset($_W['config']['setting']['https']) && !empty($_W['config']['setting']['https'])) {
                $http = 'https://';
            }
            $code = isset($_GET['code']) ? $_GET['code'] : '';

            if (!$code) {
                $url = $http . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $oauth_url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $appid . '&redirect_uri=' . urlencode($url) . '&response_type=code&scope=' . $snsapi . '&state=wxbase#wechat_redirect';
                header('Location: ' . $oauth_url);
                exit;
            }
            load()->func('communication');
            $getOauthAccessToken = ihttp_get('https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $secret . '&code=' . $code . '&grant_type=authorization_code');
            $json = json_decode($getOauthAccessToken['content'], true);

            if (!empty($json['errcode']) && ($json['errcode'] == '40029' || $json['errcode'] == '40163')) {
                $url = $http . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '' : '?');
                $parse = parse_url($url);
                if (isset($parse['query'])) {
                    parse_str($parse['query'], $params);
                    unset($params['code']);
                    unset($params['state']);
                    $url = $http . $_SERVER['HTTP_HOST'] . $parse['path'] . '?' . http_build_query($params);
                }
                $oauth_url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $appid . '&redirect_uri=' . urlencode($url) . '&response_type=code&scope=' . $snsapi . '&state=wxbase#wechat_redirect';
                header('Location: ' . $oauth_url);
                exit;
            }
            if ($snsapi == "snsapi_userinfo") {
                $userinfo = ihttp_get('https://api.weixin.qq.com/sns/userinfo?access_token=' . $json['access_token'] . '&openid=' . $json['openid'] . '&lang=zh_CN');
                $userinfo = $userinfo['content'];
            } elseif ($snsapi == "snsapi_base") {
                $userinfo = array();
                $userinfo['openid'] = $json['openid'];
            }
            $userinfostr = json_encode($userinfo);
            isetcookie($appid, $userinfostr, $expired);
            return $userinfo;
        } else {
            return json_decode($wxuser, true);
        }
    }

    /**
     * 会员统计缓存创建
     */
    public function memberRadisCount($key, $value = false) {
        global $_W;
        $redis = redis();
        if (!is_error($redis)) {
            if (empty($value)) {
                if ($redis->get($key) != false) {  //判断key值所对应的值是否存在
                    return $redis->get($key);
                } else {
                    return false;
                }
            } else {
                $redis->set($key, $value, array('nx', 'ex' => '3600'));  //设置缓存  过期时间默认为1小时
            }
        }
    }

    /**
     * 会员统计缓存清空
     */
    public function memberRadisCountDelete() {
        global $_W;
        $open_redis = function_exists('redis') && !is_error(redis());
        if ($open_redis) { //判断redisshi
            $redis = redis();
            $keysArr = $redis->keys("ewei_{$_W['uniacid']}_member*"); //查询出所有会员统计的缓存的key值
            if (!empty($keysArr) && is_array($keysArr)) {
                foreach ($keysArr as $k => $v) {
                    $redis->del($v);  //清除所有会员统计的缓存
                }
            }
        }
    }

    /**
     * 增加加速记录
     */
    public function setAddSpeed($userId, $point, $type, $typeId = '', $remark = '') {
        //目前会员等级
        if ($type == 2 && empty($point)) {
            $point = 3;
        }
        if (!empty($point)) {
            $sysset = m('common')->getSysset();
            $sysMaxSpeed = $sysset['shop']['to_money_speed_max'];
            $userNowSpeed = pdo_getcolumn("ewei_shop_member", array('id' => $userId), 'exchange_speed');
            if ($userNowSpeed + $point > $sysMaxSpeed) {
                if ($userNowSpeed == $sysMaxSpeed) {
                    return;
                } else {
                    $addPoint = $sysMaxSpeed - $userNowSpeed;
                }
            } else {
                $addPoint = $point;
            }
            $nowLevel = pdo_getcolumn('ewei_shop_member_level', array('id' => $userId), 'level');
            $insertData = array(
                'user_id'     => $userId,
                'create_time' => time(),
                'end_time'    => '999999999',
                'point'       => $addPoint,
                'type'        => $type, //1-注册基础值 2-推荐下载加速 3-重复进货加速 4-直推他人进货 5-间推他人进货 6-线下进货奖励 7-线下直推进货 8-线下间推进货
                'type_id'     => $typeId,
                'now_level'   => $nowLevel ?: 0,
                'remark'      => $remark
            );
            pdo_insert('ewei_shop_speed_record', $insertData);
            $nowUserSpeed = pdo_getcolumn('ewei_shop_member', array('id' => $userId), 'exchange_speed');
            pdo_update('ewei_shop_member', array('exchange_speed' => round($nowUserSpeed + $addPoint, 2)), array('id' => $userId));
        }
    }

    public function autoToCredit() {
        ignore_user_abort(TRUE);
        set_time_limit(0);
        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);
        global $_W;
        //$allWxUsers = pdo_fetchall("select uid,credit1,credit2 from " . tablename("mc_members"));
        $allWxUsers = pdo_fetchall('SELECT id,exchange_speed,openid FROM ' . tablename('ewei_shop_member') . ' WHERE uniacid=:uniacid AND isblack=0 ', [':uniacid' => $_W['uniacid']]);

        $sysset = m('common')->getSysset();
        $toCreditPoint = round($sysset['shop']['to_money_basic_point'], 4) ?: 100; //每一卫贝等于多少卫卷

        if (empty($toCreditPoint)) {
            echo '无效的转换';
            exit;
        }

        $sysMaxSpeed = $sysset['shop']['to_money_speed_max'];
        $nowDay = date("Y-m-d", time());
        $i = 0;
        //绑定微信用户
        foreach ($allWxUsers as $k => $userInfo) {

            $userInfo['credit1'] = m('member')->getCredit($userInfo['openid'], 'credit1');
            $userInfo['credit2'] = m('member')->getCredit($userInfo['openid'], 'credit2');
            //判断该用户今天是否已转换
            $isAutoCredit = pdo_getcolumn("ewei_shop_auto_credit", array('user_id' => $userInfo['id'], 'now_day' => $nowDay), 'id');
            if (!empty($isAutoCredit)) {
                continue;
            }
            //判断用户速度有否封顶
            if ($sysMaxSpeed && $userInfo['exchange_speed'] > $sysMaxSpeed) {
                $userInfo['exchange_speed'] = $sysMaxSpeed;
            }
            $credit = floatval($userInfo['credit1'] * ($userInfo['exchange_speed'] * 0.001));

            if($credit == 0) continue;

            if ($credit < 1) {
                $credit = 1;
            } else {
                $credit = round($credit,0);
            }

            $realCredit = round($credit / $toCreditPoint, 2);
            //echo $i,'  ---  ',$userInfo['credit1'] * ($userInfo['exchange_speed'] * 0.001),'  ---  ',$userInfo['exchange_speed'],'  ---  ',$credit,'  ---  ',$realCredit,"\n";

            if ($realCredit > 0) {

                //卫贝记录
                m('member')->setCredit($userInfo['openid'], 'credit1', -$credit, array(0, '卫贝*转换速度值:' . $credit));
                //卫卷记录
                m('member')->setCredit($userInfo['openid'], 'credit2', $realCredit, array(0, '卫贝*转换速度值:' . $realCredit));
                $insertData = array(
                    'user_id'     => $userInfo['id'],
                    'create_time' => time(),
                    'now_speed'   => $userInfo['exchange_speed'],
                    'now_credit1' => $userInfo['credit1'],
                    'now_credit2' => $userInfo['credit2'],
                    'credit'      => $credit,
                    'real_credit' => $realCredit,
                    'status'      => 1,
                    'now_day'     => $nowDay
                );
                pdo_insert('ewei_shop_auto_credit', $insertData);

                $i++;

                if($i>1000) break;
            }
        }
        //$f = fopen('./auto_log.txt', 'a');
        $text = date("Y-m-d H:i:s") . "执行完成了" . $i . "条\r\n";
        //fwrite($f, $text);
        //fclose($f);

        echo $text;
    }
}

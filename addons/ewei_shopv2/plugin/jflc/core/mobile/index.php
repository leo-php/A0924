<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/6/17
 * Time: 11:51
 */

if (!(defined('IN_IA'))) {
    exit('Access Denied');
}


class Index_EweiShopV2Page extends PluginMobileLoginPage {
    public function main() {
        global $_W;
        if ($_W['ispost']) {
            $this->log();
        } else {
            include $this->template();
        }

    }

    public function inpoint() {
        global $_W, $_GPC;
        $no_left = false;
        $uniacid = $_W['uniacid'];
        $member = m('member')->getMember($_W['openid']);

        if (!empty($_GPC['post_num'])) {
            $num = round(floatval($_GPC['post_num']), 2);
            $credit = $member['credit1'];

            if ($num > $credit)
                show_json(0, '积分不足,无法转入');
            if ($num > 0) {

                $set = $this->getSet();
                $userlimit = intval($set['userlimit']);
                $maxlimit = intval($set['maxlimit']);

                if (!empty($userlimit)) {
                    $user_point = pdo_fetchcolumn('SELECT SUM(point) FROM ' . tablename('ewei_shop_jflc_point_log') . ' WHERE uniacid=:uniacid AND mid=:mid AND (type=1 OR type=2) LIMIT 1', array(
                        ':uniacid' => $_W['uniacid'],
                        ':mid'     => $member['id']
                    ));
                    if ((floatval($user_point) + $num) > $userlimit) {
                        show_json(0, '投资额已超过个人限额！');
                        exit;
                    }
                }

                if (!empty($maxlimit)) {
                    $all_point = pdo_fetchcolumn('SELECT SUM(point) FROM ' . tablename('ewei_shop_jflc_point_log') . ' WHERE uniacid=:uniacid AND(type=1 OR type=2) LIMIT 1', array(
                        ':uniacid' => $_W['uniacid']
                    ));

                    if ((floatval($all_point) + $num) > $maxlimit) {
                        show_json(0, '投资额已超过系统限额！');
                        exit;
                    }
                }

                m('member')->setCredit($member['openid'], 'credit1', -$num, array(
                    $_W['uid'],
                    '积分投资'
                ));
                $logno = m('common')->createNO('member_log', 'logno', 'JFLC');
                $data = array(
                    'openid'       => $member['openid'],
                    'logno'        => $logno,
                    'uniacid'      => $_W['uniacid'],
                    'type'         => '0',
                    'createtime'   => time(),
                    'status'       => '1',
                    'title'        => '积分投资',
                    'money'        => -$num,
                    'rechargetype' => 'JFLC'
                );
                pdo_insert('ewei_shop_member_log', $data);
                $logid = pdo_insertid();
                plog('jflc.transfer', '积分投资' . ':' . -$num . ' <br/>会员信息: ID: ' . $member['id'] . ' /  ' . $member['openid'] . '/' . $member['nickname'] . '/' . $member['realname'] . '/' . $member['mobile']);

                pdo_insert('ewei_shop_jflc_point_log', array(
                    'uniacid'    => $_W['uniacid'],
                    'createtime' => TIMESTAMP,
                    'point'      => $num,
                    'mid'        => $member['id'],
                    'openid'     => $member['openid'],
                    'remark'     => '转入积分投资',
                    'type'       => 1,
                    'status'     => 0
                ));

                $agent = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_jflc_agent') . ' WHERE mid=:mid', array(
                    ':mid' => $member['id']
                ));

                if (empty($agent)) {
                    pdo_insert('ewei_shop_jflc_agent', array(
                        'mid'        => $member['id'],
                        'uniacid'    => $_W['uniacid'],
                        'point'      => 0,//$num,
                        'createtime' => TIMESTAMP,
                        'updatetime' => TIMESTAMP
                    ));
                } /*else {
                    pdo_update('ewei_shop_jflc_agent', array(
                        'point'      => $agent['point'] + $num,
                        'updatetime' => TIMESTAMP
                    ), array(
                        'mid' => $member['id']
                    ));
                }*/
                show_json(1);
            } else {
                show_json(0, '您输入的积分有误');
            }
            show_json(1);
        }

        $set = $this->getSet();
        include $this->template();
    }

    public function outpoint() {
        global $_W, $_GPC;
        $no_left = false;
        $uniacid = $_W['uniacid'];
        $member = m('member')->getMember($_W['openid']);
        $agent = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_jflc_agent') . ' WHERE uniacid=:uniacid AND mid=:mid LIMIT 1', array(
            ':uniacid' => $_W['uniacid'],
            ':mid'     => $member['id']
        ));
        $point = empty($agent['point']) ? 0.00 : floatval($agent['point']);
        if (!empty($_GPC['post_num'])) {
            $num = round(floatval($_GPC['post_num']), 2);

            if ($num > $point)
                show_json(0, '积分不足,无法转出');

            if ($num > 0 && !empty($agent)) {
                m('member')->setCredit($member['openid'], 'credit1', $num, array(
                    $_W['uid'],
                    '转出积分'
                ));

                $logno = m('common')->createNO('member_log', 'logno', 'JFLC');
                $data = array(
                    'openid'       => $member['openid'],
                    'logno'        => $logno,
                    'uniacid'      => $_W['uniacid'],
                    'type'         => '0',
                    'createtime'   => time(),
                    'status'       => '1',
                    'title'        => '转出积分',
                    'money'        => $num,
                    'rechargetype' => 'JFLC'
                );
                pdo_insert('ewei_shop_member_log', $data);
                $logid = pdo_insertid();
                plog('jflc.transfer', '转出积分' . ':' . $num . ' <br/>会员信息: ID: ' . $member['id'] . ' /  ' . $member['openid'] . '/' . $member['nickname'] . '/' . $member['realname'] . '/' . $member['mobile']);
                pdo_insert('ewei_shop_jflc_point_log', array(
                    'uniacid'    => $_W['uniacid'],
                    'createtime' => TIMESTAMP,
                    'point'      => -$num,
                    'mid'        => $member['id'],
                    'openid'     => $member['openid'],
                    'remark'     => '转出积分投资',
                    'type'       => 2
                ));

                pdo_update('ewei_shop_jflc_agent', array(
                    'point'      => $agent['point'] - $num,
                    'updatetime' => TIMESTAMP
                ), array(
                    'mid' => $member['id']
                ));
                show_json(1);
            }
        }

        include $this->template();
    }

}
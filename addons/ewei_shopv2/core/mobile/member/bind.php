<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Bind_EweiShopV2Page extends MobileLoginPage
{

    protected $member;


    function __construct()
    {
        global $_W, $_GPC;
        parent::__construct();
    }

    /*
     * ***用户绑定逻辑
     *
     * 1. GPC到新手机号
     * 2. 查询此手机号是否存在用户
     * 2.1. 如果不存在 则直接绑定
     * 2.2. 如果已存在 得到member2 判断是否是自己
     * 2.2.1. 如果是自己 则返回
     * 2.2.2. 如果不是自己 则判断 member2 是否是微信用户
     * 2.2.2.1. 如果 member2 不是微信用户 则进行合并
     * 2.2.2.2. 如果 member2 是微信用户 则判断自己 member 是否是微信用户
     * 2.2.2.2.1. 如果自己 member 也是微信用户 则执行解绑、绑定
     * 2.2.2.2.2. 如果 自己 member 不是微信用户则合并
     *
     */

    public function main(){
        global $_W, $_GPC;

        @session_start();

        $member = m('member')->getMember($_W['openid']);
        if(empty($member['id'])){
            $this->message("会员数据出错");
        }

        $wapset = m('common')->getSysset('wap');
        $appset = m('common')->getSysset('app');
        if(!p('threen')) {
            if (empty($wapset['open']) && !empty($appset['isclose'])) {
                $this->message("未开启绑定设置");
            }
        }

        $bind = !empty($member['mobile']) && !empty($member['mobileverify']) ? 1 : 0;

        if($_W['ispost']){
            $mobile = trim($_GPC['mobile']);
            $verifycode = trim($_GPC['verifycode']);
            $pwd = trim($_GPC['pwd']);
            $confirm = intval($_GPC['confirm']);

            $key = '__ewei_shopv2_member_verifycodesession_' . $_W['uniacid'] . '_' . $mobile;
//            if( !isset($_SESSION[$key]) ||  $_SESSION[$key]!==$verifycode || !isset($_SESSION['verifycodesendtime']) || $_SESSION['verifycodesendtime']+600<time()){
//                show_json(0, '验证码错误或已过期');
//            }

            
            $member2 = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where mobile=:mobile and uniacid=:uniacid and mobileverify=1 limit 1', array(':mobile' => $mobile, ':uniacid' => $_W['uniacid']));

            if(empty($member2)){
                $salt = m('account')->getSalt();
                $data = array(
                    'mobile'=>$mobile,
                    'pwd'=>md5($pwd.$salt),
                    'salt'=>$salt,
                    'mobileverify'=>1
                );

                if(!empty($_GPC['realname']))
                {
                    $data['realname'] = trim($_GPC['realname']);
                }
                if(!empty($_GPC['birthyear']))
                {
                    $data['birthyear'] = trim($_GPC['birthyear']);
                    $data['birthmonth'] = trim($_GPC['birthmonth']);
                    $data['birthday'] = trim($_GPC['birthday']);
                }
                if(!empty($_GPC['idnumber']))
                {
                    $data['idnumber'] = trim($_GPC['idnumber']);
                }

                if(!empty($_GPC['bindwechat']))
                {
                    $data['weixin'] = trim($_GPC['bindwechat']);
                }

                m('bind')->update($member['id'],$data);

                unset($_SESSION[$key]);
                m('account')->setLogin($member['id']);

                // 绑定手机号送积分
                if($bind == 0){
                    m('bind')->sendCredit($member);
                }

                if (p('task')){//##任务中心
                    p('task')->checkTaskReward('member_info',1,$_W['openid']);
                }
                if (p('task')){//##任务中心
                    p('task')->checkTaskProgress(1,'info_phone');
                }
                show_json(1, "bind success (0)");
            }

            // member 不等于空 判断是否是自己
            if($member['id']==$member2['id']){
                show_json(0 ,"此手机号已与当前账号绑定");
            }
            
            // 如果 两用户都是 微信用户
            if(m('bind')->iswxm($member) && m('bind')->iswxm($member2)){
                if($confirm){
                    $salt = m('account')->getSalt();
                    m('bind')->update($member['id'], array('mobile'=>$mobile, 'pwd'=>md5($pwd.$salt), 'salt'=>$salt, 'mobileverify'=>1));
                    m('bind')->update($member2['id'], array('mobileverify'=>0));

                    unset($_SESSION[$key]);
                    m('account')->setLogin($member['id']);
                    if (p('task')){//##任务中心
                        p('task')->checkTaskReward('member_info',1,$_W['openid']);
                    }
                    if (p('task')){//##任务中心
                        p('task')->checkTaskProgress(1,'info_phone');
                    }
                    show_json(1, "bind success (1)");
                }else{
                    show_json(-1, "<center>此手机号已与其他帐号绑定<br>如果继续将会解绑之前帐号<br>确定继续吗？</center>");
                }
            }

            // 判断 member2 不是是微信用户
            if(!m('bind')->iswxm($member2)){
                if($confirm) {
                    // member 不是微信用户 则进行合并
                    $result = m('bind')->merge($member2, $member);
                    if (empty($result['errno'])) {
                        show_json(0, $result['message']);
                    }

                    $salt = m('account')->getSalt();
                    m('bind')->update($member['id'], array('mobile' => $mobile, 'pwd' => md5($pwd . $salt), 'salt' => $salt, 'mobileverify' => 1));

                    unset($_SESSION[$key]);
                    m('account')->setLogin($member['id']);
                    if (p('task')){//##任务中心
                        p('task')->checkTaskReward('member_info',1,$_W['openid']);
                    }
                    if (p('task')){//##任务中心
                        p('task')->checkTaskProgress(1,'info_phone');
                    }
                    show_json(1, "bind success (2)");
                }else{
                    show_json(-1, "<center>此手机号已通过其他方式注册<br>如果继续将会合并账号信息<br>确定继续吗？</center>");
                }
            }

            // 判断 member 不是微信用户
            if(!m('bind')->iswxm($member)){
                if($confirm) {
                    // 合并用户
                    $result = m('bind')->merge($member, $member2);
                    if (empty($result['errno'])) {
                        show_json(0, $result['message']);
                    }

                    $salt = m('account')->getSalt();
                    m('bind')->update($member2['id'], array('mobile' => $mobile, 'pwd' => md5($pwd . $salt), 'salt' => $salt, 'mobileverify' => 1));

                    unset($_SESSION[$key]);
                    m('account')->setLogin($member2['id']);
                    if (p('task')){##任务中心
                        p('task')->checkTaskReward('member_info',1,$_W['openid']);
                    }
                    if (p('task')){//##任务中心
                        p('task')->checkTaskProgress(1,'info_phone');
                    }
                    show_json(1, "bind success (3)");
                }else{
                    show_json(-1, "<center>此手机号已通过其他方式注册<br>如果继续将会合并账号信息<br>确定继续吗？</center>");
                }
            }

        }

        $sendtime = $_SESSION['verifycodesendtime'];
        if(empty($sendtime) || $sendtime+60<time()){
            $endtime = 0;
        }else{
            $endtime = 60 - (time() - $sendtime);
        }

        include $this->template();
    }

    public function getbindinfo()
    {
        $wap = m('common')->getSysset('wap');

        $nohasbindinfo = 0;
        if(empty($wap['bindrealname'])&&empty($wap['bindbirthday'])&&empty($wap['bindidnumber'])&&empty($wap['bindwechat']))
        {
            $nohasbindinfo = 1;
        }

        show_json(1, array(
            'nohasbindinfo'=>$nohasbindinfo,
            'bindrealname'=>empty($wap['bindrealname'])?0:1,
            'bindbirthday'=>empty($wap['bindbirthday'])?0:1,
            'bindidnumber'=>empty($wap['bindidnumber'])?0:1,
            'bindwechat'=>empty($wap['bindwechat'])?0:1
        ));

    }
}

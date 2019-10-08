<?php

/*
 * ICE SHOP
 * 
 * @author ICE ZXF
 */
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Index_EweiShopV2Page extends PluginMobilePage
{

    function main()
    {
        global $_W, $_GPC;

        include $this->template();
    }

    function transfer()
    {
        global $_W, $_GPC;
        $member = m('member')->getMember($_W['openid']);
        include $this->template();
    }

    function info(){
        global $_W, $_GPC;
        $post_id = intval($_GPC['post_id']);
        $openid = pdo_fetchcolumn('SELECT openid FROM '.tablename('ewei_shop_member').' WHERE id=:id AND uniacid=:uniacid',array(
            ':id'=>$post_id,
            ':uniacid'=>$_W['uniacid']
        ));
        if(empty($openid)){
            show_json(0,'不存在的用户ID');
        }
        if($openid == $_W['openid']) show_json(0,'不可以转赠给自己');
        $profile = m('member')->getMember($openid);
        if($profile){
            show_json(1,array('nickname'=>$profile['nickname']));
        }else{
            show_json(0,'不存在的用户ID');
        }
    }

    function post()
    {
        global $_W, $_GPC;

        $money = floatval($_GPC['post_num']);
        $time = date('Y-m-d H:i:s',time());
        if($money<=0) show_json(0,'输入的积分有误');

        $post_id = trim($_GPC['post_id']);
				/*
        if(is_mobile($post_id)){
            $openid = pdo_fetchcolumn('SELECT openid FROM ' . tablename('ewei_shop_member') . ' WHERE mobile=:mobile AND mobileverify=1 AND uniacid=:uniacid LIMIT 1', array(
                ':mobile'      => $post_id,
                ':uniacid' => $_W['uniacid']
            ));
        }else {*/
            $post_id = intval($_GPC['post_id']);
            $openid = pdo_fetchcolumn('SELECT openid FROM ' . tablename('ewei_shop_member') . ' WHERE id=:id AND uniacid=:uniacid', array(
                ':id'      => $post_id,
                ':uniacid' => $_W['uniacid']
            ));
        //}
        if(empty($openid)){
            show_json(0,'不存在的用户ID');
        }

        $credit = m('member')->getCredit($_W['openid'],'credit1');

        if($money>$credit) show_json(0,'积分不足');

        $profile = m('member')->getMember($openid);
        if($openid == $_W['openid']) show_json(0,'不可以转赠给自己');
        if(empty($profile))show_json(0,'不存在的用户ID');

        $logno = m('common')->createNO('member_log', 'logno', 'YET');
        pdo_insert('ewei_shop_jy_yet_log', array(
            'uniacid'       =>  $_W['uniacid'],
            'openid'        =>  $_W['openid'],
            'openid2'       =>  $openid,
            'credit'        =>  $credit,
            'credit2'       =>  m('member')->getCredit($openid,'credit1'),
            'send_sn'       =>  $logno,
            'send_money'    =>  $money,
            'created_at'    =>  $time
        ));
        m('member')->setCredit($profile['openid'], 'credit1', $money, array($_W['uid'], '会员转赠'));


        plog('yet.transfer', '会员转赠' . ':' . $money . ' <br/>会员信息: ID: ' . $profile['id'] . ' /  ' . $profile['openid'] . '/' . $profile['nickname'] . '/' . $profile['realname'] . '/' . $profile['mobile']);

        $member = m('member')->getMember($_W['openid']);
        m('member')->setCredit($member['openid'], 'credit1', -1*$money, array($_W['uid'], '会员转赠'));
        $data = array(
            'openid' => $member['openid'],
            'logno' => $logno,
            'uniacid' => $_W['uniacid'],
            'type' => '0',
            'createtime' => time(),
            'status' => '1',
            'title' => '会员转赠',
            'money' => -1*$money,
            'rechargetype' => 'yet');
        pdo_insert('ewei_shop_member_log', $data);
        $logid = pdo_insertid();
        plog('yet.transfer', '会员转赠' . ':' . -1*$money . ' <br/>会员信息: ID: ' . $member['id'] . ' /  ' . $member['openid'] . '/' . $member['nickname'] . '/' . $member['realname'] . '/' . $member['mobile']);

        //$this->sendMessage('成功转赠积分:'.$money.'元给:'.$profile['nickname'].',ID:'.$profile['id'],$_W['openid']);
        //$this->sendMessage($member['nickname'].',ID:'.$member['id'].'成功转赠积分:'.$money.'元:',$openid);

        $datas = array(
            array("name" => "商城名称", "value" => $_W['shopset']['shop']['name']),
            array("name" => "粉丝昵称", "value" => $member['nickname'])
        );

        $message = array(
            'first' => array('value' => "转赠积分给:".$profile['nickname'].',ID:'.$profile['id'], "color" => "#4a5077"),
            'money' => array('title' => '转赠积分', 'value' => $money . '积分', "color" => "#4a5077"),
            'timet' => array('title' => '转赠时间', 'value' => $time, "color" => "#4a5077"),
            'remark' => array('value' => "\r\n感谢您的支持！", "color" => "#4a5077")
        );

        m('notice')->sendNotice(array(
            "openid" => $_W['openid'],
            'tag' => 'withdraw_ok',
            'default' => $message,
            'cusdefault' => $time."\r\n转赠积分:".$money."\r\n".$profile['nickname'].' [ID:'.$profile['id'].']',
            'url' => mobileUrl('member',null,true),
            'datas' => $datas
        ));


        $message = array(
            'first' => array('value' => "收到会员:".$member['nickname'].',ID:'.$member['id'].'转赠积分', "color" => "#4a5077"),
            'money' => array('title' => '到账积分', 'value' => $money . '积分', "color" => "#4a5077"),
            'timet' => array('title' => '到账时间', 'value' => $time, "color" => "#4a5077"),
            'remark' => array('value' => "\r\n感谢您的支持！", "color" => "#4a5077")
        );
        m('notice')->sendNotice(array(
            "openid" => $openid,
            'tag' => 'withdraw_ok',
            'default' => $message,
            'cusdefault' => $time."\r\n收到积分:".$money."\r\n".$member['nickname'].' [ID:'.$member['id'].']',
            'url' => mobileUrl('member',null,true),
            'datas' => $datas
        ));

        show_json(1);
    }

    function sendMessage($text,$openid){
        global $_W, $_GPC;
        load()->func('communication');
        $access_token = WeAccount::token();
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$access_token}";
        $text=urlencode($text);
        $pic2=$_W['siteroot'].'/addons/ewei_shopv2/plugin/yet/static/message.jpg';
        $pic=urlencode($pic2);
        $url2=mobileUrl('member',null,true);
        $url2=urlencode($url2);
        $data = array(
            "touser"=>$openid,
            "msgtype"=>"news",
            "news"=>array("articles"=>array(0=>(array("title"=>$text,"url"=>$url2,'picurl'=>$pic))))
        );
        $response = ihttp_request($url, urldecode(json_encode($data)));
    }


}


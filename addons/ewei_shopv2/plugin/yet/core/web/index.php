<?php

/*
 * ICE SHOP
 *
 * @author ICE ZXF
 */
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Index_EweiShopV2Page extends PluginWebPage
{
    function main()
    {
        global $_W, $_GPC;
        $no_left = false;

        header('location: ' . webUrl('yet/log'));
        exit;
        //include $this->template();
    }

    function send()
    {
        global $_W, $_GPC;
        //默认的
        $default = array(
            'id' => 'default',
            "levelname" => '默认等级'
        );

        $commission_level = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_commission_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY commission1 asc");
        $list1 = array_merge(array($default), $commission_level);

        $globonus_level = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_globonus_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY bonus asc");
        $list2 = array_merge(array($default), $globonus_level);

        $abonus_level = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_abonus_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY id asc");
        $list3 = array_merge(array($default), $abonus_level);

        include $this->template();
    }

    function send_json()
    {
        global $_W, $_GPC;
        $send1              = $_GPC['send1'];
        $send_sn            = $_GPC['send_sn'];
        $send_money         = $_GPC['send_money'];
        $send_commission    = $_GPC['send_commission'];
        $send_globonus      = $_GPC['send_globonus'];
        $send_abonus        = $_GPC['send_abonus'];

        //pdo_run('DELETE FROM '.tablename('ewei_shop_jy_yet_log').' WHERE uniacid='.$_W['uniacid'].' AND send_at IS NULL');
	
        $log = pdo_fetch('SELECT id FROM '.tablename('ewei_shop_jy_yet_log').' WHERE uniacid=:uniacid AND send_sn=:send_sn',array(':uniacid'=>$_W['uniacid'],':send_sn'=>$send_sn));

        if($log){
            show_json(0, '不可以重复提交批号！');
            exit();
        }

        switch (intval($send1)) {
            case 1:
                $level = ($send_commission == 'default') ? 0 : intval($send_commission);
                $members = pdo_fetchall('SELECT m.id,m.nickname,m.realname,m.mobile,m.openid,mc.credit2 as credit FROM ' . tablename('ewei_shop_member') . ' m LEFT JOIN ' . tablename('mc_members') . ' mc ON mc.uid=m.uid WHERE m.uniacid=:uniacid AND m.agentlevel=:level_id AND m.isagent=1 ', array(
                    ':uniacid' => $_W['uniacid'],
                    ':level_id' => $level
                ));
                break;
            case 2:
                $level = ($send_globonus == 'default') ? 0 : intval($send_globonus);
                $members = pdo_fetchall('SELECT m.id,m.nickname,m.realname,m.mobile,m.openid,mc.credit2 as credit FROM ' . tablename('ewei_shop_member') . ' m LEFT JOIN ' . tablename('mc_members') . ' mc ON mc.uid=m.uid WHERE m.uniacid=:uniacid AND m.partnerlevel=:level_id AND m.ispartner=1 AND m.partnerstatus=1', array(
                    ':uniacid' => $_W['uniacid'],
                    ':level_id' => $level
                ));
                break;
            case 3:
                $level = ($send_abonus == 'default') ? 0 : intval($send_abonus);
                $members = pdo_fetchall('SELECT m.id,m.nickname,m.realname,m.mobile,m.openid,mc.credit2 as credit FROM ' . tablename('ewei_shop_member') . ' m LEFT JOIN ' . tablename('mc_members') . ' mc ON mc.uid=m.uid WHERE m.uniacid=:uniacid AND m.aagentlevel=:level_id AND m.isaagent=1 ', array(
                    ':uniacid' => $_W['uniacid'],
                    ':level_id' => $level
                ));
                break;
            default:
                show_json(0, '无效的数据提交！');
                exit();
        }
        $i = 0;
        if ($members) {
            foreach ($members as $member) {
                $i++;
                $point = floatval($send_money);
                m('member')->setCredit($member['openid'], 'credit2', $point, array($_W['uid'], '批量充值:'.$send_sn));
                $logno = m('common')->createNO('member_log', 'logno', 'YET');
                $data = array(
                    'openid' => $member['openid'],
                    'logno' => $send_sn,
                    'uniacid' => $_W['uniacid'],
                    'type' => '0',
                    'createtime' => time(),
                    'status' => '1',
                    'title' => '批量充值',
                    'money' => $point,
                    'rechargetype' => 'yet');
                pdo_insert('ewei_shop_member_log', $data);
                $logid = pdo_insertid();
                plog('yet.transfer', '批量充值' . ':' . $point . ' <br/>会员信息: ID: ' . $member['id'] . ' /  ' . $member['openid'] . '/' . $member['nickname'] . '/' . $member['realname'] . '/' . $member['mobile']);

                $time = date('Y-m-d H:i:s',time());
                pdo_insert('ewei_shop_jy_yet_log', array(
                    'type'          =>  intval($send1),
                    'uniacid'       =>  $_W['uniacid'],
                    'level'         =>  intval($level),
                    'openid'        =>  $member['openid'],
                    'credit'        =>  $member['credit'],
                    'send_sn'       =>  $send_sn,
                    'send_money'    =>  $send_money,
                    'created_at'    =>  date('Y-m-d H:i:s',time())
                ));

                //$this->sendMessage('系统批量充值 ¥'. $point . '元',$member['openid']);
                $datas = array(
                    array("name" => "商城名称", "value" => $_W['shopset']['shop']['name']),
                    array("name" => "粉丝昵称", "value" => $member['nickname'])
                );

                $message = array(
                    'first' => array('value' => "系统批量充值!", "color" => "#4a5077"),
                    'money' => array('title' => '到账金额', 'value' => '¥' . $point . '元', "color" => "#4a5077"),
                    'timet' => array('title' => '充值时间', 'value' => $time, "color" => "#4a5077"),
                    'remark' => array('value' => "\r\n感谢您的支持！", "color" => "#4a5077")
                );
                m('notice')->sendNotice(array(
                    "openid" => $member['openid'],
                    'tag' => 'withdraw_ok',
                    'default' => $message,
                    'url' => mobileUrl('member',null,true),
                    'datas' => $datas
                ));

            }
        }else{
            show_json(0, '当前等级无会员！');
            exit();
        }
        show_json(1, array('lenght'=>$i));
    }

    function ylist(){
        global $_W, $_GPC;

        $condition = ' AND l.type<>0 ';
        $params = array(':uniacid' => $_W['uniacid']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;

        if (!empty($_GPC['keyword'])) {
            $keyword = trim($_GPC['keyword']);
            $condition .=' and ( m.realname like :keyword or m.nickname like :keyword or m.mobile like :keyword or m.id like :keyword)';
            $params[':keyword'] = "%{$keyword}%";
        }

        if ($_GPC['category'] != '') {
            $condition.=' and l.type=' . intval($_GPC['category']);
        }

        $total = pdo_fetchcolumn('SELECT COUNT(l.id) FROM '.tablename('ewei_shop_jy_yet_log').' l LEFT JOIN '.tablename('ewei_shop_member').' m ON m.openid=l.openid AND m.uniacid=l.uniacid WHERE l.uniacid=:uniacid'.$condition,$params);
        $sql = 'SELECT l.*,m.avatar,m.nickname FROM '.tablename('ewei_shop_jy_yet_log').' l LEFT JOIN '.tablename('ewei_shop_member').' m ON m.openid=l.openid AND m.uniacid=l.uniacid WHERE l.uniacid=:uniacid'.$condition;

        if (empty($_GPC['export'])) {
            $sql .= ' ORDER BY l.id DESC limit ' . (($pindex - 1) * $psize) . ',' . $psize;
        }

        $list = pdo_fetchall($sql,$params);
        $pager = pagination($total, $pindex, $psize);

        $cat_list=array(
            1   =>  '分销商',
            2   =>  '全民股东',
            3   =>  '区域代理'
        );

        $commission_level = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_commission_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY commission1 asc");
        $globonus_level = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_globonus_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY bonus asc");
        $abonus_level = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_abonus_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY id asc");

        $commission_arr = array();
        foreach($commission_level as $level){
            $commission_arr[$level['id']] = $level['levelname'];
        }

        $globonus_arr = array();
        foreach($globonus_level as $level){
            $globonus_arr[$level['id']] = $level['levelname'];
        }
        $abonus_arr = array();
        foreach($abonus_level as $level){
            $abonus_arr[$level['id']] = $level['levelname'];
        }
        $level = array(
            1   =>  $commission_arr,
            2   =>  $globonus_arr,
            3   =>  $abonus_arr
        );

        include $this->template();
    }

    function log(){
        global $_W, $_GPC;

        $condition = '';
        $params = array(':uniacid' => $_W['uniacid']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;

        if (!empty($_GPC['keyword'])) {
            $keyword = trim($_GPC['keyword']);
            $condition.=' and ( m.realname like :keyword or m.nickname like :keyword or m.mobile like :keyword or m.id like :keyword OR p.realname like :keyword or p.nickname like :keyword or p.mobile like :keyword or p.id like :keyword)';
            $params[':keyword'] = "%{$keyword}%";
        }

        $total = pdo_fetchcolumn('SELECT COUNT(l.id) FROM '.tablename('ewei_shop_jy_yet_log').' l LEFT JOIN '.tablename('ewei_shop_member').' m ON m.openid=l.openid AND m.uniacid=l.uniacid LEFT JOIN '.tablename('ewei_shop_member').' p ON p.openid=l.openid2 AND p.uniacid=l.uniacid WHERE l.uniacid=:uniacid AND l.type=0 '.$condition,$params);
        $sql = 'SELECT l.*,m.avatar,m.nickname,p.avatar as avatar2,p.nickname AS nickname2 FROM '.tablename('ewei_shop_jy_yet_log').' l LEFT JOIN '.tablename('ewei_shop_member').' m ON m.openid=l.openid AND m.uniacid=l.uniacid LEFT JOIN '.tablename('ewei_shop_member').' p ON p.openid=l.openid2 AND p.uniacid=l.uniacid WHERE l.uniacid=:uniacid AND l.type=0 '.$condition;

        if (empty($_GPC['export'])) {
            $sql .= ' ORDER BY l.id DESC limit ' . (($pindex - 1) * $psize) . ',' . $psize;
        }

        $list = pdo_fetchall($sql,$params);
        $pager = pagination($total, $pindex, $psize);

        include $this->template();
    }

    function sendMessage($text,$openid){
        global $_W, $_GPC;
        load()->func('communication');
        $access_token = WeAccount::token();
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$access_token}";
        $text=urlencode($text);
        $pic2=$_W['siteroot'].'/addons/ewei_shop/plugin/yet/static/message.jpg';
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

    function test(){
        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);
        global $_W, $_GPC;
        //$this->sendMessage('TEST','ohqADwLSrfpLDRsk-Lt4iCrD_Rmc');
        $member = m('member')->getInfo('ohqADwLSrfpLDRsk-Lt4iCrD_Rmc');
        $money = 333;
        $time = time();
        $datas = array(
            array("name" => "商城名称", "value" => $_W['shopset']['shop']['name']),
            array("name" => "粉丝昵称", "value" => $member['nickname'])
        );

        $message = array(
            'first' => array('value' => "转赠积分给:".$member['nickname'].',ID:'.$member['id'], "color" => "#4a5077"),
            'money' => array('title' => '转赠积分', 'value' => '¥' . $money . '元', "color" => "#4a5077"),
            'timet' => array('title' => '转赠时间', 'value' => $time, "color" => "#4a5077"),
            'remark' => array('value' => "\r\n感谢您的支持！", "color" => "#4a5077")
        );
        m('notice')->sendNotice(array(
            "openid" => $member['openid'],
            'tag' => 'withdraw_ok',
            'default' => $message,
            'cusdefault' => '积分互转',
            'url' => mobileUrl('member',null,true),
            'datas' => $datas
        ));

    }

    public function set() {
        global $_W, $_GPC;
        $no_left       = false;
        if ($_W['ispost']) {
            $data = is_array($_GPC['data']) ? $_GPC['data'] : array();
            m('common')->updatePluginset(array('yet' => $data));
            show_json(1, array('url' => webUrl('yet/set', array('tab' => str_replace("#tab_", "", $_GPC['tab'])))));
        }
        $data  = m('common')->getPluginset('yet');
        include $this->template();
    }
}
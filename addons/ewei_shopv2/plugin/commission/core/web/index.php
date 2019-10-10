<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Index_EweiShopV2Page extends PluginWebPage {

    function main() {
        global $_W;
        if (cv('commission.agent')) {
            header('location: ' . webUrl('commission/agent'));
            exit;
        } else if (cv('commission.apply.view1')) {
            header('location: ' . webUrl('commission/apply', array('status' => 1)));
            exit;
        } else if (cv('commission.apply.view2')) {
            header('location: ' . webUrl('commission/apply', array('status' => 2)));
            exit;
        } else if (cv('commission.apply.view3')) {
            header('location: ' . webUrl('commission/apply', array('status' => 3)));
            exit;
        } else if (cv('commission.apply.view_1')) {
            header('location: ' . webUrl('commission/apply', array('status' => -1)));
            exit;
        } else if (cv('commission.increase')) {
            header('location: ' . webUrl('commission/increase'));
            exit;
        } else if (cv('commission.notice')) {
            header('location: ' . webUrl('commission/notice'));
            exit;
        } else if (cv('commission.cover')) {
            header('location: ' . webUrl('commission/cover'));
            exit;
        } else if (cv('commission.level')) {
            header('location: ' . webUrl('commission/level'));
            exit;
        } else if (cv('commission.set')) {
            header('location: ' . webUrl('commission/set'));
            exit;
        }
    }

    function notice() {

        global $_W, $_GPC;

        $data = m('common')->getPluginset('commission', false);
        $data = $data['tm'];
        //分销商提现通知商家高级
        $salers1 = array();
        if (isset($data['openid1'])) {
            if (!empty($data['openid1'])) {
                $openids1 = array();
                $strsopenids = explode(",", $data['openid1']);
                foreach ($strsopenids as $openid) {
                    $openids1[] = "'" . $openid . "'";
                }
                @$salers1 = pdo_fetchall("select id,nickname,avatar,openid from " . tablename('ewei_shop_member') . ' where openid in (' . implode(",", $openids1) . ") and uniacid={$_W['uniacid']}");
            }
        }

        //分销商提现通知商家低级
        $salers2 = array();
        if (isset($data['openid2'])) {
            if (!empty($data['openid2'])) {
                $openids2 = array();
                $strsopenids2 = explode(",", $data['openid2']);
                foreach ($strsopenids2 as $openid2) {
                    $openids2[] = "'" . $openid2 . "'";
                }
                @$salers2 = pdo_fetchall("select id,nickname,avatar,openid from " . tablename('ewei_shop_member') . ' where openid in (' . implode(",", $openids2) . ") and uniacid={$_W['uniacid']}");
            }
        }
        if ($_W['ispost']) {
//
            //分销商提现通知商家
            $post_data = is_array($_GPC['data']) ? $_GPC['data'] : array();
            if($post_data['is_advanced']==0){
                //低级
                if (is_array($_GPC['openids2'])) {
                    $post_data['openid2'] = implode(",", $_GPC['openids2']);
                }else{
                    $post_data['openid2'] = '';
                }
                $post_data['openid'] =  $post_data['openid2'];
                if(!empty($data['openid1'])){
                    $post_data['openid1'] =  $data['openid1'];
                }
            }elseif($post_data['is_advanced']==1){
//                高级

                if (is_array($_GPC['openids1'])) {
                    $post_data['openid1'] = implode(",", $_GPC['openids1']);
                }else{
                    $post_data['openid1'] = '';
                }
                $post_data['openid'] =  $post_data['openid1'];
                if(!empty($data['openid2'])){
                    $post_data['openid2'] =  $data['openid2'];
                }
            }

            m('common')->updatePluginset(array('commission'=>array('tm'=>$post_data)));
            plog('commission.notice.edit', '修改通知设置');
            show_json(1);
        }
        $data = m('common')->getPluginset('commission');
        $template_lists= pdo_fetchall('SELECT id,title,typecode FROM ' . tablename('ewei_shop_member_message_template') . ' WHERE uniacid=:uniacid ', array(':uniacid' => $_W['uniacid']));

        $templatetype_list = pdo_fetchall('SELECT * FROM  ' . tablename('ewei_shop_member_message_template_type'));

        $template_group=array();

        foreach($templatetype_list as $type)
        {
            $templates=array();

            foreach($template_lists as $template)
            {
                if($template['typecode']==$type['typecode'])
                {
                    $templates[]=$template;
                }
            }
            $template_group[$type['typecode']]=$templates;

        }

        $template_list = $template_group;


        //        $template_sms = com('sms')->sms_temp();
        //        $opensms = com('sms');
        include $this->template();
    }

    function set() {
        global $_W, $_GPC;


        if ($_W['ispost']) {
            $data = is_array($_GPC['data']) ? $_GPC['data'] : array();

            if($data['cansee'] ==1 && empty($data['seetitle'])){
                show_json(0,'请选择佣金显示文字');
            }

            $data['cashcredit'] = intval($data['cashcredit']);
            $data['cashweixin'] = intval($data['cashweixin']);
            $data['cashother'] = intval($data['cashother']);
            $data['cashalipay'] = intval($data['cashalipay']);
            $data['cashcard'] = intval($data['cashcard']);

            if(!empty($data['withdrawcharge'])) {
                $data['withdrawcharge'] = trim($data['withdrawcharge']);
                $data['withdrawcharge'] = floatval(trim($data['withdrawcharge'], '%'));
            }

            $data['withdrawbegin'] = floatval(trim($data['withdrawbegin']));
            $data['withdrawend'] = floatval(trim($data['withdrawend']));

            $data['register_bottom_content'] = m('common')->html_images($data['register_bottom_content']);
            $data['applycontent'] = m('common')->html_images($data['applycontent']);
            $data['regbg'] = save_media($data['regbg']);
            $data['become_goodsid'] = $_GPC['become_goodsid'];
            $data['texts'] = is_array($_GPC['texts']) ? $_GPC['texts'] : array();
            if($data['become'] ==4 && empty($data['become_goodsid'])){
                show_json(0,'请选择商品');
            }

            if(!empty($data['become_goodsid'])){
                $cont = count($_GPC['become_goodsid']);
                if($cont>6){
                    show_json(0,'商品最多添加六个');
                }
                $data['become_goodsid'] = iserializer($data['become_goodsid']);
            }

             m('common')->updatePluginset(array('commission'=>$data));
            //模板缓存
             m('cache')->set('template_' . $this->pluginname, $data['style']);

            $selfbuy = $data['selfbuy']?'开启':'关闭';
            $become_child = $data['become_child']?($data['become_child'] == 1?'首次下单':'首次付款'):'首次点击分享连接';
            switch ($data['become'])
            {
                case '0':
                    $become = '无条件';break;
                case '1':
                    $become = '申请';break;
                case '2':
                    $become = '消费次数';break;
                case '3':
                    $become = '消费金额';break;
                case '4':
                    $become = '购买商品';break;
            }

            plog('commission.set.edit', '修改基本设置<br>'.'分销内购 -- '.$selfbuy.'<br>成为下线条件 -- '.$become_child.'<br>成为分销商条件 -- '.$become);
            show_json(1,array('url'=>webUrl('commission/set', array('tab'=>str_replace("#tab_","",$_GPC['tab'])))));
        }

        $styles = array();
        $dir = IA_ROOT . "/addons/ewei_shopv2/plugin/" . $this->pluginname . "/template/mobile/";
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file != ".." && $file != ".") {
                    if (is_dir($dir . "/" . $file)) {
                        $styles[] = $file;
                    }
                }
            }
            closedir($handle);
        }

        $data = m('common')->getPluginset('commission');
        $goods = false;
        $become_goodsid = iunserializer($data['become_goodsid']);
        if (!empty($data['become_goodsid'])) {
            $goods = pdo_fetchall("SELECT id,uniacid,title,thumb FROM ".tablename('ewei_shop_goods')." WHERE uniacid=:uniacid AND id IN (".implode(',',$become_goodsid).")",array(':uniacid'=>$_W['uniacid']));
        }

        include $this->template();
    }
    function goodsquery(){

        global $_W, $_GPC;
        $kwd = trim($_GPC['keyword']);
        $type = intval($_GPC['type']);

        $live = intval($_GPC['live']);

        $params = array();
        $params[':uniacid'] = $_W['uniacid'];
        //砍价和各种营销活动都没有
        $condition=" and status=1 and deleted=0 and uniacid=:uniacid and type = 1 
                    and isdiscount = 0 and istime = 0 and  ifnull(bargain,0)=0 and ispresell = 0 ";
        if (!empty($kwd)) {
            $condition.=" AND (`title` LIKE :keywords OR `keywords` LIKE :keywords)";

            $params[':keywords'] = "%{$kwd}%";
        }
        if (empty($type)) {
            $condition.=" AND `type` != 10 ";
        }else{
            $condition.=" AND `type` = :type ";
            $params[':type'] = $type;
        }
        $goodsids = pdo_fetchall('SELECT goodsids FROM ' . tablename('ewei_shop_commission_level') . " WHERE uniacid=:uniacid", array('uniacid'=>$_W['uniacid']));
        $newarray = array();
        if(!empty($goodsids) && is_array($goodsids)){
            foreach($goodsids as $key =>$val){
                $newarray =  array_merge($newarray,iunserializer($val['goodsids']));
            }
            if(!empty($newarray) && is_array($newarray)){
                $condition .="AND id NOT IN (".implode(',',$newarray).")";
            }
        }

        $ds = pdo_fetchall('SELECT id,title,thumb,marketprice,productprice,share_title,share_icon,description,minprice,costprice,total,sales,islive,liveprice FROM ' . tablename('ewei_shop_goods') . " WHERE 1 {$condition} order by createtime desc", $params);

        foreach($ds as &$value){
            $value['share_title'] = htmlspecialchars_decode($value['share_title']);
            unset($value);
        }
        $ds = set_medias($ds, array('thumb','share_icon'));
        if($_GPC['suggest']){
            die(json_encode(array('value'=>$ds)));
        }
        include $this->template();

    }
    public function test(){
        global $_W;
        //成为店长
//        $member=pdo_fetchall(' select id,agentid,inviter,isagent,status from '.tablename('ewei_shop_member').' where uniacid='.$_W['uniacid'] .' and isagent=0 ');
//        //dddd($member);
//        foreach($member as $value){
//            pdo_update('ewei_shop_member', array('isagent' => 1, 'status' => 1, 'agenttime' => time(), 'agentblack' => 0), array('uniacid' => $_W['uniacid'], 'id' => $value['id']));
//        }
        $level=pdo_fetch(' select id,level from '.tablename('ewei_shop_member_level').' where uniacid='.$_W['uniacid'] .' and level=0 ');
        //老会员成为店长
        $member2=pdo_fetchall(' select id from '.tablename('ewei_shop_member').' where uniacid='.$_W['uniacid'] .' and level='.$level['id']);
        $level2=pdo_fetch(' select id,level from '.tablename('ewei_shop_member_level').' where uniacid='.$_W['uniacid'] .' and level=1 ');
        foreach($member2 as $value){
            pdo_update('ewei_shop_member', array('level' => $level2['id']), array('uniacid' => $_W['uniacid'], 'id' => $value['id']));
        }

        //转移邀请人id至分销商上级ID
//        $member3=pdo_fetchall(' select id,agentid,inviter,isagent,status from '.tablename('ewei_shop_member').' where uniacid='.$_W['uniacid'] .' and inviter!=0 ');
//        //dddd($member2);
//        foreach($member3 as $value){
//            pdo_update('ewei_shop_member', array('agentid' => $value['inviter']), array('uniacid' => $_W['uniacid'], 'id' => $value['id']));
//        }
    }

}

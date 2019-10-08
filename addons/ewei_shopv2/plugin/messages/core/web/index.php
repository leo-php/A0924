<?php

if (!defined('IN_IA')) {
	exit('Access Denied');
}

class Index_EweiShopV2Page extends PluginWebPage {

    function main() {
        global $_W, $_GPC;

        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $condition = " and uniacid=:uniacid";
        $params = array(':uniacid' => $_W['uniacid']);

        if (!empty($_GPC['keyword'])) {
            $_GPC['keyword'] = trim($_GPC['keyword']);
            $condition.=' and title  like :keyword';
            $params[':keyword'] = "%{$_GPC['keyword']}%";
        }

        $list = pdo_fetchall("SELECT * FROM " . tablename('ewei_message_mass_task') . " WHERE 1 {$condition}  ORDER BY id asc limit " . ($pindex - 1) * $psize . ',' . $psize, $params);
        $total = pdo_fetchcolumn("SELECT count(*) FROM " . tablename('ewei_message_mass_task') . " WHERE 1 {$condition}", $params);
        $pager = pagination2($total, $pindex, $psize);

        foreach($list as &$item)
        {
            switch ($item['status']){
                case 0:
                    $item['statustext']='未生成发送列表';
                    break;
                case 1:
                    $item['statustext']='发送未开始';
                      break;
                case 2:
                    $item['statustext']='发送未完成';
                    break;
                case 3:
                    $item['statustext']='发送完成';
                    break;
            }

            $counts = pdo_fetchall("select `status`,COUNT(*) as num from " . tablename('ewei_message_mass_sign') . " WHERE taskid=".$item['id']."  GROUP BY `status`");
            $num=0;
            $item['nosend']=0;
            $item['sendsuccess']=0;
            $item['sendfail']=0;

            foreach($counts as $count)
            {
                $num+=$count['num'];

                if($count['status']==1)
                {
                    $item['nosend']=$count['num'];
                }
                if($count['status']==2)
                {
                    $item['nosend']+=$count['num'];
                }
                if($count['status']==3)
                {
                    $item['sendsuccess']=$count['num'];
                }
                if($count['status']==4)
                {
                    $item['sendfail']=$count['num'];
                }
            }
            $item['num']=$num;
        }

        unset($item);

        include $this->template();
    }


    function showsign() {
        global $_W, $_GPC;

        $id = intval($_GPC['id']);
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
        $condition = " and uniacid=:uniacid and taskid =:taskid";
        $params = array(':uniacid' => $_W['uniacid'],':taskid'=>$id);

        if (!empty($_GPC['status'])) {
            $condition.=' and status= :status';
            $params[':status'] = $_GPC['status'];
        }
        if (!empty($_GPC['keyword'])) {
            $_GPC['keyword'] = trim($_GPC['keyword']);
            $condition.=' and openid  like :keyword';
            $params[':keyword'] = "%{$_GPC['keyword']}%";
        }

        $list = pdo_fetchall("SELECT *  FROM " . tablename('ewei_message_mass_sign') . " WHERE 1 {$condition}  ORDER BY id asc limit " . ($pindex - 1) * $psize . ',' . $psize, $params);
        $total = pdo_fetchcolumn("SELECT count(*) FROM " . tablename('ewei_message_mass_sign') . " WHERE 1 {$condition}", $params);
        $pager = pagination2($total, $pindex, $psize);

        include $this->template();
    }


    function add() {
        $this->post();
    }

    function edit() {
        $this->post();
    }

    protected function post() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);

        if (!empty($id)) {
            $item = pdo_fetch('SELECT * FROM ' . tablename('ewei_message_mass_task') . ' WHERE id=:id and uniacid=:uniacid ', array(':id' => $_GPC['id'], ':uniacid' => $_W['uniacid']));

            if(!empty($item['templateid']))
            {
                $send = pdo_fetch('SELECT *  FROM ' . tablename('ewei_message_mass_template') . ' WHERE id=:id and uniacid=:uniacid ', array(':id' =>$item['templateid'], ':uniacid' => $_W['uniacid']));
            }
            if(intval($item['sendlimittype'])==1)
            {
                $send_openid1=$item['send_openid'];
            }
            if ($item['sendlimittype'] == 2) {
                $salers = array();
                if (!empty($item['send_openid'])) {
                    $openids = array();
                    $strsopenids = explode(",", $item['send_openid']);
                    foreach ($strsopenids as $openid) {
                        $openids[] = "'" . $openid . "'";
                    }

                    $salers = pdo_fetchall("select id,nickname,avatar,openid from " . tablename('ewei_shop_member') . ' where openid in (' . implode(",", $openids) . ") and uniacid={$_W['uniacid']}");
                }
            }
        }
        $list = pdo_fetchall("SELECT *  FROM " . tablename('ewei_shop_member_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY level asc");
        $list2 = pdo_fetchall("SELECT *  FROM " . tablename('ewei_shop_member_group') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY id asc");
        $list3 = pdo_fetchall("SELECT *  FROM " . tablename('ewei_shop_commission_level') . " WHERE uniacid = '{$_W['uniacid']}' ORDER BY id asc");


        if ($_W['ispost']) {
            $data = array(
                'uniacid' => $_W['uniacid'],
                'title' => trim($_GPC['title']),
                'messagetype' => intval($_GPC['messagetype']),
                'sendlimittype' => intval($_GPC['sendlimittype']),
                'status' => 0,
                'pagecount' => 30
            );

            if(empty($_GPC['messagetype']))
            {
                $data['templateid'] = intval($_GPC['templateid']);
            }
            else if($_GPC['messagetype']==1)
            {
                if(empty($_GPC['customertype']))
                {
                    $data['customertype'] = intval($_GPC['customertype']);
                    $data['resptitle'] = trim($_GPC['send_title']);
                    $data['respthumb'] = trim($_GPC['send_thumb']);
                    $data['respdesc'] = trim($_GPC['send_desc']);
                    $data['respurl'] = trim($_GPC['send_url']);
                }
                else
                {
                    $data['customertype'] = intval($_GPC['customertype']);
                    $data['resdesc2'] = html_entity_decode($_GPC['send_desc2']);
                }
            }else  if($_GPC['messagetype']==2)
            {
                $data['templateid'] = intval($_GPC['templateid']);
                if(empty($_GPC['customertype']))
                {
                    $data['customertype'] = intval($_GPC['customertype']);
                    $data['resptitle'] = trim($_GPC['send_title']);
                    $data['respthumb'] = trim($_GPC['send_thumb']);
                    $data['respdesc'] = trim($_GPC['send_desc']);
                    $data['respurl'] = trim($_GPC['send_url']);
                }
                else
                {
                    $data['customertype'] = intval($_GPC['customertype']);
                    $data['resdesc2'] = html_entity_decode($_GPC['send_desc2']);
                }
            }

            //分销商限制
            $plugin_com = p('commission');
            if ($plugin_com) {
                $plugin_com_set = $plugin_com->getSet();
            }

            if(intval($_GPC['sendlimittype'])==1)
            {
                $data['send_openid'] = trim($_GPC['send_openid1']);

            }else if(intval($_GPC['sendlimittype'])==2)
            {
                $data['send_openid'] = implode(",", $_GPC['send_openid2']);

            }else if(intval($_GPC['sendlimittype'])==3)
            {
                $data['send_level'] = intval($_GPC['send_level']);

            }else if(intval($_GPC['sendlimittype'])==4)
            {
                $data['send_group'] = intval($_GPC['send_group']);

            }else if(intval($_GPC['sendlimittype'])==5)
            {
                $data['send_agentlevel'] = intval($_GPC['send_agentlevel']);
            }

            if (empty($id)) {
                pdo_insert('ewei_message_mass_task', $data);
                $id = pdo_insertid();
                plog('messages.template.add', "添加群发模板 ID: {$id} 标题: {$data['title']} ");
            } else {
                pdo_update('ewei_message_mass_task', $data, array('id' => $id));
                plog('messages.template.edit', "编辑群发模板 ID: {$id} 标题: {$data['title']} ");
            }
            show_json(1, array('url' => webUrl('messages')));
        }
        include $this->template();
    }

    function build() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $item = pdo_fetch("SELECT id,sendlimittype,send_openid,send_level,send_group,send_agentlevel FROM " . tablename('ewei_message_mass_task') . " WHERE id =:id AND uniacid=:uniacid"
            ,array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if(!empty($item)){

            $params = array(':taskid'=>$item['id'],':uniacid'=> $_W['uniacid']);

            $sql = 'insert into  '. tablename('ewei_message_mass_sign') .'  (uniacid,openid,nickname,taskid,`status`) ';
            $sql .= ' SELECT  :uniacid,sm.openid,sm.nickname,:taskid,1 from '. tablename('ewei_shop_member') .' sm  inner join '
                . tablename('mc_mapping_fans') .' mf  on sm.openid=mf.openid and mf.follow=1 where sm.uniacid=:uniacid ';

            if(intval($item['sendlimittype'])==1||intval($item['sendlimittype'])==2)
            {
                if(!empty($item['send_openid']))
                {
                    $openids[]=array();

                    $openids = explode(',',$item['send_openid']);

                    foreach ($openids as $key => $openid) {
                        $openids[$key] = "'" . $openid . "'";
                    }

                    $sql .= ' and sm.openid in('.implode(',', $openids).')';
                }
                else
                {
                    $sql .= ' and 1<>1';
                }
            }
            elseif(intval($item['sendlimittype'])==3)
            {
                if (!empty($item['send_level'])) {
                    $sql.= " and sm.level =:send_level";
                    $params[':send_level']=$item['send_level'];
                }
                if ($item['send_level'] ==-1) {
                    $sql.= " and sm.level =:send_level";
                    $params[':send_level']=0;
                }
            }
            elseif(intval($item['sendlimittype'])==4)
            {
                if (!empty($item['send_group'])) {
                    $sql.= " and sm.groupid =:send_group";
                    $params[':send_group']=$item['send_group'];
                }
            }
            elseif(intval($item['sendlimittype'])==5)
            {
                $sql.= " and  sm.status =1 and sm.isagent=1 ";

                if($item['send_agentlevel'] =='-1')
                {
                    $sql.= " and  sm.agentlevel =0";
                }else if(!empty($item['send_agentlevel'])) {
                    $sql.= " and  sm.agentlevel =:send_agentlevel";
                    $params[':send_agentlevel']=$item['send_agentlevel'];
                }
            }

            pdo_delete('ewei_message_mass_sign', array('taskid' => $id, 'uniacid' => $_W['uniacid']));

            pdo_query($sql,$params);

            $total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('ewei_message_mass_sign') . "WHERE taskid ='{$id}' and uniacid = '{$_W['uniacid']}'"); //记录总数

            $data = array(
                'sendnum' => intval($total),
                'successnum' => 0,
                'failnum' => 0,
                'status' =>1
            );
            pdo_update('ewei_message_mass_task', $data,array('id' => $id, 'uniacid' => $_W['uniacid']));
        }

        show_json(1, array('url' => referer()));
    }

    function reset() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $item = pdo_fetch("SELECT id,title FROM " . tablename('ewei_message_mass_task') . " WHERE id =:id AND uniacid=:uniacid"
            ,array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if(!empty($item)){
            $data = array(
                'status' =>1
            );
            pdo_update('ewei_message_mass_task', $data,array('id' => $id, 'uniacid' => $_W['uniacid']));
            $data['log']='';
            pdo_update('ewei_message_mass_sign', $data, array('taskid' => $id, 'uniacid' => $_W['uniacid']));
            plog('messages.delete', "重置群发任务 ID: {$item['id']} 标题: {$item['title']} ");
        }

        show_json(1, array('url' => referer()));
    }

    function delete() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $item = pdo_fetch("SELECT id,title FROM " . tablename('ewei_message_mass_task') . " WHERE id =:id AND uniacid=:uniacid"
            ,array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if(!empty($item)){
            pdo_delete('ewei_message_mass_task', array('id' => $id, 'uniacid' => $_W['uniacid']));
            pdo_delete('ewei_message_mass_sign', array('taskid' => $id, 'uniacid' => $_W['uniacid']));
            plog('messages.delete', "删除群发任务 ID: {$item['id']} 标题: {$item['title']} ");
        }
        show_json(1, array('url' => referer()));
    }

    function run() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);

        $successnum = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('ewei_message_mass_sign') . "WHERE taskid ='{$id}'  and status =3  and uniacid = '{$_W['uniacid']}'"); //记录总数
        $failnum = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('ewei_message_mass_sign') . "WHERE taskid ='{$id}'  and status =4  and uniacid = '{$_W['uniacid']}'"); //记录总数

        $sendnum = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('ewei_message_mass_sign') . "WHERE taskid ='{$id}'   and uniacid = '{$_W['uniacid']}'"); //记录总数

        $data = array(
            'sendnum' => intval($sendnum),
            'successnum' => intval($successnum),
            'failnum' => intval($failnum)
        );

        pdo_update('ewei_message_mass_task', $data,array('id' => $id, 'uniacid' => $_W['uniacid']));

        $item = pdo_fetch("SELECT  *   FROM " . tablename('ewei_message_mass_task') . " WHERE id =:id AND uniacid=:uniacid"
            ,array(':id' => $id, ':uniacid' => $_W['uniacid']));

        $remainnum= intval($item['sendnum']) -intval($item['successnum'])   - intval($item['failnum']);

        $jindu = ((float)$item['sendnum']-(float)$remainnum)/(float)$item['sendnum']*100;
        $jindu  = round($jindu,2);
        $status = intval($item['status'])==0?'未生成发送列表,请返回任务列表页生成发送列表然后进行消息群发操作':'准备开始';

        include $this->template();
    }

    function fetch() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $pagecount =empty($_GPC['pagecount'])?30:intval($_GPC['pagecount']);

        if(empty($id))
        {
            show_json(0,'发送任务ID错误!');
        }

        /*$items = pdo_fetchall("SELECT s.id,s.taskid,s.openid,s.nickname,t.messagetype,t.templateid,t.resptitle,t.respthumb,t.respdesc,t.respurl,t.customertype,t.resdesc2  FROM " . tablename('ewei_message_mass_sign') . " s inner join " . tablename('ewei_message_mass_task') . " t
          on s.taskid = t.id  WHERE s.taskid =:id AND s.uniacid=:uniacid and s.status =1 order by id limit  {$pagecount}"
            ,array(':id' => $id, ':uniacid' => $_W['uniacid']));*/
        $mess_task = pdo_fetch("SELECT messagetype,templateid,resptitle,respthumb,respdesc,respurl,customertype,resdesc2 FROM ". tablename('ewei_message_mass_task') ."WHERE id =:id AND uniacid=:uniacid limit 1",array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if(empty($mess_task)){
            show_json(0,'发送任务未找到');
        }
        $items = pdo_fetchall("SELECT id,taskid,openid  FROM " . tablename('ewei_message_mass_sign') . " WHERE taskid =:id AND uniacid=:uniacid and status =1 order by id limit  {$pagecount}"
            ,array(':id' => $id, ':uniacid' => $_W['uniacid']));
        foreach ($items as $key => $val){
            $items[$key]['messagetype'] = $mess_task['messagetype'];
            $items[$key]['templateid'] = $mess_task['templateid'];
            $items[$key]['resptitle'] = $mess_task['resptitle'];
            $items[$key]['respthumb'] = $mess_task['respthumb'];
            $items[$key]['respdesc'] = $mess_task['respdesc'];
            $items[$key]['respurl'] = $mess_task['respurl'];
            $items[$key]['customertype'] = $mess_task['customertype'];
            $items[$key]['resdesc2'] = $mess_task['resdesc2'];
        }

        $successnum=0;
        $failnum=0;
        $count=count($items);
        $hasnext=1;

        foreach($items as $item)
        {
            $data=array();
            if($item['messagetype']==0)
            {
                $resp =$this->sendTplNotice($item['openid'],$item['templateid'],$item['nickname']);
            }
            elseif($item['messagetype']==1)
            {
                if(empty($item['customertype']))
                {
                    $resp =$this->sendNews($item['openid'],$item['resptitle'],$item['respdesc'],$item['respurl'],$item['respthumb']);
                }else
                {
                    $resp = m('message')->sendCustomNotice($item['openid'], $item['resdesc2']);
                }
            }
            elseif($item['messagetype']==2)
            {
                if(empty($item['customertype']))
                {
                    $resp =$this->sendNews($item['openid'],$item['resptitle'],$item['respdesc'],$item['respurl'],$item['respthumb']);
                }else
                {
                    $resp = m('message')->sendCustomNotice($item['openid'], $item['resdesc2']);
                }

                if (is_error($resp)) {
                    $resp = $this->sendTplNotice($item['openid'],$item['templateid'],$item['nickname']);
                }
            }

            if(is_error($resp))
            {
                $data['status'] = 4;
                $data['log'] = $resp['message'];
                $failnum++;
            }
            else
            {
                $data['status'] = 3;
                $successnum++;
            }
            pdo_update('ewei_message_mass_sign', $data, array('id' => $item['id'], 'uniacid' => $_W['uniacid']));

        }

        if($count<$pagecount)
        {
            $hasnext=0;
        }

        show_json(1,array("successnum"=>$successnum,'failnum'=>$failnum,'count'=>$count,'hasnext'=>$hasnext));

    }


    function sendTplNotice($openid,$templateid,$nickname)
    {
        global  $_W;
        $member = m('member')->getMember($openid);
        $result =false;
        if (!empty($templateid)) {

            $template = pdo_fetch('SELECT * FROM ' . tablename('ewei_message_mass_template') . ' WHERE id=:id and uniacid=:uniacid ', array(':id' => $templateid, ':uniacid' => $_W['uniacid']));
            $data = iunserializer($template['data']);

            $template['first'] = str_replace('[商城名称]',$_W['shopset']['shop']['name'],$template['first']);
            $template['first'] = str_replace('[粉丝昵称]',$member['nickname'],$template['first']);
            $template['remark'] = str_replace('[商城名称]',$_W['shopset']['shop']['name'],$template['remark']);
            $template['remark'] = str_replace('[粉丝昵称]',$member['nickname'],$template['remark']);

            $msg = array(
                'first' => array('value' => $template['first'], 'color' => $template['firstcolor']),
                'remark' => array('value' => "\n".$template['remark'], 'color' => $template['remarkcolor'])
            );
            for ($i = 0; $i < count($data); $i++) {

                if(stripos($data[$i]['value'],'[商城名称]')!==false)
                {
                    $data[$i]['value'] = str_replace('[商城名称]',$_W['shopset']['shop']['name'],$data[$i]['value']);
                }
                if(stripos($data[$i]['value'],'[粉丝昵称]')!==false)
                {
                    $data[$i]['value'] = str_replace('[粉丝昵称]',$nickname,$data[$i]['value']);
                }

                $msg[$data[$i]['keywords']] = array('value' => $data[$i]['value'], 'color' => $data[$i]['color']);
            }

            $miniprogram=array();
            if($template['miniprogram']==1)
            {
                $miniprogram['appid']=$template['appid'];
                $miniprogram['pagepath']=$template['pagepath'];
            }

            $result = m('message')->sendTplNotice($openid, $template['template_id'], $msg, $template['url'],null,$miniprogram);
        }
        return $result;
    }

    function sendNews($openid,$title,$desc,$url,$picurl, $account = null)
    {
        global  $_W;
        $result =false;
        $articles[] = array(
            "title" => urlencode($title),
            "description" => urlencode($desc),
            "url" => $url,
            "picurl" => tomedia($picurl)
        );
        $result = m('message')->sendNews($openid, $articles, $account);
        return $result;
    }
/*
    function sendmessage( $send_total, $member, $templateid = '', $account = null) {
        global $_GPC, $_W;
        $id = intval($_GPC['id']);
        $template = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_member_message_template') . ' WHERE id=:id and uniacid=:uniacid ', array(':id' => $id, ':uniacid' => $_W['uniacid']));
        $openid = $_GPC['openid'];
        $data = iunserializer($template['data']);

        $msg = array(
            'first' => array('value' => $template['first'], 'color' => $template['firstcolor']),
            'remark' => array('value' => $template['remark'], 'color' => $template['remarkcolor'])
        );
        for ($i = 0; $i < count($data); $i++) {
            $msg[$data[$i]['keywords']] = array('value' => $data[$i]['value'], 'color' => $data[$i]['color']);
        }
        $result = m('message')->sendTplNotice($openid, $template['template_id'], $msg, $template['url']);

        $url = empty($coupon['respurl']) ? mobileUrl('sale/coupon/my', null, true) : $coupon['respurl'];
        if (!empty($coupon['resptitle'])) {
            $articles[] = array(
                "title" => urlencode($title),
                "description" => urlencode($desc),
                "url" => $url,
                "picurl" => tomedia($coupon['respthumb'])
            );
        }
        if (!empty($articles)) {
            $resp = m('message')->sendNews($member['openid'], $articles, $account);
            if (is_error($resp)) {
                //如果客服不能发送再使用模板消息
                $msg = array(
                    'keyword1' => array('value' => $title, "color" => "#73a68d"),
                    'keyword2' => array('value' => $desc, "color" => "#73a68d")
                );
                if (!empty($templateid)) {
                    m('message')->sendTplNotice($member['openid'], $templateid, $msg, $url);
                }
            }
        }
    }*/

}

<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class index_EweiShopV2Page extends PluginMobilePage {

    private $new = false;//新版

    function __construct()
    {
        parent::__construct();
        $this->new = $this->model->isnew();
    }
    //设为已读
    public function set_read(){
        global $_W;
        pdo_update('ewei_shop_task_record',array('read'=>1),array('openid'=>$_W['openid'],'uniacid'=>$_W['uniacid']));
    }

    function main() {
        if ($this->new){
            $this->main_new();exit;
        }
        global $_W,$_GPC;
        $member = m('member')->getMember($_W['openid']);
        $list1 = $this->model->getUserTaskList(1);
        $list2 = $this->model->getUserTaskList(2);
        $poster = $this->taskposter();
        $bgimg = pdo_get('ewei_shop_task_default',array('uniacid'=>$_W['uniacid']),array('bgimg'));
        include $this->template();
    }

    function newtask(){
        global $_W,$_GPC;
        $id = $_GPC['id'];
        $res = $this->model->getNewTask($id);
        if (is_string($res)){
            show_json(0,$res);
        }
        show_json(1,$res);
    }

    function mytask(){
        global $_W;
        $dolist = $this->model->getMyTaskList();
        $donelist = $this->model->getMyTaskList('>');
        $poster = $this->taskposter();
        $fail = $this->model->failTask();
        include $this->template();
    }

    function detail(){
        if ($this->new){
            $this->detail_new();exit;
        }
        global $_W,$_GPC;
        $id = intval($_GPC['id']);
        $poster = intval($_GPC['poster']);
        if ($poster){
            $sql = "SELECT * FROM ".tablename('ewei_shop_task_poster')." WHERE id = :id";
            $detail = pdo_fetch($sql,array(':id'=>$id));
            $reward_data = unserialize($detail['reward_data']);
        }else{
            $sql = "SELECT * FROM ".tablename('ewei_shop_task')." WHERE id = :id";
            $detail = pdo_fetch($sql,array(':id'=>$id));
            $reward_data = unserialize($detail['reward_data']);
            $require_data = unserialize($detail['require_data']);
        }
        include $this->template();
    }

    function taskposter() {
        //判断参与人是否已经参加了其他任务【注意这里是其他任务不包括本任务】，如果有则不能生成海报
        global $_W, $_GPC;
        $tabpage = $_GPC['tabpage'];
        $openid = trim($_W['openid']);
        $is_menu = $this->model->getdefault('menu_state');
        $member = m('member')->getMember($openid);
        $now_time = time();
        $task_sql = 'SELECT * FROM '.tablename('ewei_shop_task_poster').' WHERE timestart<='.$now_time.' AND timeend>'.$now_time.' AND uniacid='.$_W['uniacid'].' AND `status`=1 AND `is_delete`=0 ORDER BY `createtime` DESC';
        $task_list = pdo_fetchall($task_sql);
        foreach ($task_list as $key=>$val){
            if($val['poster_type']==1){
                $val['reward_data'] = unserialize($val['reward_data']);
                $recward = $val['reward_data']['rec'];
                if(isset($recward['credit'])&&$recward['credit']>0){
                    $task_list[$key]['is_credit'] = 1;
                }
                if(isset($recward['money']['num'])&&$recward['money']['num']>0){
                    $task_list[$key]['is_money'] = 1;
                }
                if(isset($recward['bribery'])&&$recward['bribery']>0){
                    $task_list[$key]['is_bribery'] = 1;
                }
                if(isset($recward['goods'])&&count($recward['goods'])){
                    $task_list[$key]['is_goods'] = 1;
                }
                if(isset($recward['coupon']['total'])&&$recward['coupon']['total']>0){
                    $task_list[$key]['is_coupon'] = 1;
                }
            }elseif ($val['poster_type']==2){
                $val['reward_data'] = unserialize($val['reward_data']);
                $recward = $val['reward_data']['rec'];
                foreach ($recward as $k=>$v){
                    if(isset($v['credit'])&&$v['credit']>0){
                        $task_list[$key]['is_credit'] = 1;
                    }
                    if(isset($v['money']['num'])&&$v['money']['num']>0){
                        $task_list[$key]['is_money'] = 1;
                    }
                    if(isset($v['bribery'])&&$v['bribery']>0){
                        $task_list[$key]['is_bribery'] = 1;
                    }
                    if(isset($v['goods'])&&count($v['goods'])){
                        $task_list[$key]['is_goods'] = 1;
                    }
                    if(isset($v['coupon']['total'])&&$v['coupon']['total']>0){
                        $task_list[$key]['is_coupon'] = 1;
                    }
                }
            }
        }
        //正在进行
        $running_sql = 'SELECT `join`.*,`task`.title,`task`.reward_data AS `poster_reward`,`task`.titleicon,`task`.poster_type FROM '.tablename('ewei_shop_task_join').' AS `join` LEFT JOIN '.tablename('ewei_shop_task_poster').' AS `task` ON `join`.task_id=`task`.`id` WHERE `join`.`failtime`>'.$now_time.' AND `join`.`join_user`="'.$openid.'" AND `join`.uniacid='.$_W['uniacid'].' AND `join`.`is_reward` = 0 AND `task`.`is_delete` = 0 ORDER BY `join`.`addtime` DESC LIMIT 0,15';
        $task_running = pdo_fetchall($running_sql);
        foreach ($task_running as $key=>$val){
            if($val['poster_type']==1){
                $val['reward_data'] = unserialize($val['poster_reward']);
                $recward = $val['reward_data']['rec'];
                if(isset($recward['credit'])&&$recward['credit']>0){
                    $task_running[$key]['is_credit'] = 1;
                }
                if(isset($recward['money']['num'])&&$recward['money']['num']>0){
                    $task_running[$key]['is_money'] = 1;
                }
                if(isset($recward['bribery'])&&$recward['bribery']>0){
                    $task_running[$key]['is_bribery'] = 1;
                }
                if(isset($recward['goods'])&&count($recward['goods'])){
                    $task_running[$key]['is_goods'] = 1;
                }
                if(isset($recward['coupon']['total'])&&$recward['coupon']['total']>0){
                    $task_running[$key]['is_coupon'] = 1;
                }
            }elseif ($val['poster_type']==2){
                $val['reward_data'] = unserialize($val['poster_reward']);
                $recward = $val['reward_data']['rec'];
                foreach ($recward as $k=>$v){
                    if(isset($v['credit'])&&$v['credit']>0){
                        $task_running[$key]['is_credit'] = 1;
                    }
                    if(isset($v['money']['num'])&&$v['money']['num']>0){
                        $task_running[$key]['is_money'] = 1;
                    }
                    if(isset($v['bribery'])&&$v['bribery']>0){
                        $task_running[$key]['is_bribery'] = 1;
                    }
                    if(isset($v['goods'])&&count($v['goods'])){
                        $task_running[$key]['is_goods'] = 1;
                    }
                    if(isset($v['coupon']['total'])&&$v['coupon']['total']>0){
                        $task_running[$key]['is_coupon'] = 1;
                    }
                }
            }

        }
        //已完成
        $complete_sql = 'SELECT `join`.*,`task`.title,`task`.titleicon,`task`.poster_type FROM '.tablename('ewei_shop_task_join').' AS `join` LEFT JOIN '.tablename('ewei_shop_task_poster').' AS `task` ON `join`.task_id=`task`.`id` WHERE `join`.uniacid='.$_W['uniacid'].' AND `join`.`join_user`="'.$openid.'" AND `join`.`is_reward`=1 AND `task`.`is_delete` = 0 ORDER BY `join`.`addtime` DESC LIMIT 0,15';
        $task_complete = pdo_fetchall($complete_sql);
        foreach ($task_complete as $key=>$val){
            if($val['poster_type']==1){
                $task_complete[$key]['reward_data'] = unserialize($val['reward_data']);
                $val['reward_data'] = unserialize($val['reward_data']);
                $recward = $val['reward_data'];
                if(isset($recward['credit'])&&$recward['credit']>0){
                    $task_complete[$key]['is_credit'] = 1;
                }
                if(isset($recward['money']['num'])&&$recward['money']['num']>0){
                    $task_complete[$key]['is_money'] = 1;
                }
                if(isset($recward['bribery'])&&$recward['bribery']>0){
                    $task_complete[$key]['is_bribery'] = 1;
                }
                if(isset($recward['goods'])&&count($recward['goods'])){
                    $task_complete[$key]['is_goods'] = 1;
                }
                if(isset($recward['coupon']['total'])&&$recward['coupon']['total']>0){
                    $task_complete[$key]['is_coupon'] = 1;
                }
            }elseif ($val['poster_type']==2){
                $val['reward_data'] = unserialize($val['reward_data']);
                $recward = $val['reward_data'];
                foreach ($recward as $k=>$v){
                    if(isset($v['credit'])&&$v['credit']>0){
                        $task_complete[$key]['is_credit'] = 1;
                    }
                    if(isset($v['money']['num'])&&$v['money']['num']>0){
                        $task_complete[$key]['is_money'] = 1;
                    }
                    if(isset($v['bribery'])&&$v['bribery']>0){
                        $task_complete[$key]['is_bribery'] = 1;
                    }
                    if(isset($v['goods'])&&count($v['goods'])){
                        $task_complete[$key]['is_goods'] = 1;
                    }
                    if(isset($v['coupon']['total'])&&$v['coupon']['total']>0){
                        $task_complete[$key]['is_coupon'] = 1;
                    }
                }
            }

        }
        //已失效
        $faile_sql = 'SELECT `join`.*,`task`.title,`task`.reward_data AS `poster_reward`,`task`.titleicon,`task`.poster_type FROM '.tablename('ewei_shop_task_join').' AS `join` LEFT JOIN '.tablename('ewei_shop_task_poster').' AS `task` ON `join`.task_id=`task`.`id` WHERE `join`.`failtime`<='.$now_time.' AND `join`.`join_user`="'.$openid.'" AND `join`.uniacid='.$_W['uniacid'].' AND `join`.`is_reward`=0 AND `task`.`is_delete` = 0 ORDER BY `join`.`addtime` DESC LIMIT 0,15';
        $faile_complete = pdo_fetchall($faile_sql);
        foreach ($faile_complete as $key=>$val){
            if($val['poster_type']==1){
                $val['reward_data'] = unserialize($val['poster_reward']);
                $recward = $val['reward_data']['rec'];
                if(isset($recward['credit'])&&$recward['credit']>0){
                    $faile_complete[$key]['is_credit'] = 1;
                }
                if(isset($recward['money']['num'])&&$recward['money']['num']>0){
                    $faile_complete[$key]['is_money'] = 1;
                }
                if(isset($recward['bribery'])&&$recward['bribery']>0){
                    $faile_complete[$key]['is_bribery'] = 1;
                }
                if(isset($recward['goods'])&&count($recward['goods'])){
                    $faile_complete[$key]['is_goods'] = 1;
                }
                if(isset($recward['coupon']['total'])&&$recward['coupon']['total']>0){
                    $faile_complete[$key]['is_coupon'] = 1;
                }
            }elseif ($val['poster_type']==2){
                $val['reward_data'] = unserialize($val['poster_reward']);
                $recward = $val['reward_data']['rec'];
                foreach ($recward as $k=>$v){
                    if(isset($v['credit'])&&$v['credit']>0){
                        $faile_complete[$key]['is_credit'] = 1;
                    }
                    if(isset($v['money']['num'])&&$v['money']['num']>0){
                        $faile_complete[$key]['is_credit'] = 1;
                    }
                    if(isset($v['bribery'])&&$v['bribery']>0){
                        $faile_complete[$key]['is_money'] = 1;
                    }
                    if(isset($v['goods'])&&count($v['goods'])){
                        $faile_complete[$key]['is_goods'] = 1;
                    }
                    if(isset($v['coupon']['total'])&&$v['coupon']['total']>0){
                        $faile_complete[$key]['is_coupon'] = 1;
                    }
                }
            }

        }
        return array($task_list,$task_running,$task_complete,$faile_complete);
    }

    public function gettask(){
        global $_W, $_GPC;
        $content = trim($_GPC['content']);
        $timeout = 10;
        $url = mobileUrl('task/build',array('timestamp'=>TIMESTAMP),true);
        ihttp_request($url, array('openid' => $_W['openid'], 'content' => urlencode($content)), array(), $timeout);
        show_json(1);
    }

    public function getreward(){
        global $_W,$_GPC;
        $id = intval($_GPC['id']);
        $rewarded = pdo_get("ewei_shop_task_extension_join",array('uniacid'=>$_W['uniacid'], 'id'=>$_GPC['id']));
        $rewarded = $rewarded['rewarded'];
        $rewarded = unserialize($rewarded['rewarded']);
        if (empty($rewarded)) show_json(0,'奖励发放失败');
        $this->model->sendReward($rewarded,1,0,$rewarded['id']);
        show_json(1,'奖励已发放');
    }

    private function getpostericon($id){
        global $_W,$_GPC;
        return pdo_fetchcolumn("SELECT titleicon FROM ".tablename('ewei_shop_task_poster')." WHERE id = :id AND uniacid = :uniacid",array(':id'=>$id,':uniacid'=>$_W['uniacid']));
    }

    private function checkJoined($taskid){
        global $_W;
        $sql = "SELECT COUNT(*) FROM ".tablename('ewei_shop_task_extension_join')." WHERE openid = :openid AND taskid = :taskid";
        return pdo_fetchcolumn($sql, array(':openid'=>$_W['openid'], ':taskid'=>$taskid));//参加过
    }

    private function getDesc(){
        global $_W;
        $sql = "SELECT `data` FROM ".tablename('ewei_shop_task_default')." WHERE uniacid = :uniacid";
        $data = pdo_fetchcolumn($sql, array(':uniacid'=>$_W['uniacid']));
        $arr = unserialize($data);
        return unserialize($arr['taskinfo']);
    }


    /**
     * 任务中心首页
     */
    public function main_new(){
        global $_W;
        //$my = m('member')->getMember($_W['openid']);
        $info = m('member')->getMember($_W['openid']);
        $tableList = tablename('ewei_shop_task_list');//list表
        $tableRecord = tablename('ewei_shop_task_record');//record表
        $now = date('Y-m-d H:i:s');
        $sql = "select li.*,re.task_demand,re.task_progress,re.id as rid from {$tableList} li left join 
                (select *,max(id) from {$tableRecord} where (stoptime>'{$now}' or stoptime = '0000-00-00 00:00:00') and openid = '{$_W['openid']}' and finishtime = 0
                group by taskid order by id desc) re on li.id = re.taskid 
                where li.starttime < '{$now}' and li.endtime >'{$now}' and li.uniacid = :uniacid 
                order by li.displayorder desc,li.id desc";
        $params = array(':uniacid'=>$_W['uniacid']);
        $list = pdo_fetchall($sql,$params);
        $set = pdo_fetchcolumn("select bg_img from ".tablename('ewei_shop_task_set')." where uniacid = {$_W['uniacid']}");
        foreach ( $list as $k => $v ){
            if( $v['type'] == 'info_phone' && !empty($info['mobile']) ){
                unset( $list[$k] );
            }else{
                $list[$k]['info'] = p('task')->checkRewardStock($v);
            }
            if($v['type'] == 'goods'){
                $list[$k]['getinfo'] = p('task')->checkGoodsock($v);
            }else{
                $list[$k]['getinfo']['enough'] =1;
            }
            if(!empty($list[$k]['info']['getmesg'])){
                $list[$k]['info']['getmesg'] = implode(',',$list[$k]['info']['getmesg']);
            }
        }

        foreach($list as $key => $val){
            if(!$val['info']['enough']){
                unset($list[$key]);
            }
            if($val['type'] =='goods'){
                if(!$val['info']['enough']){
                    unset($list[$key]);
                }
            }
        }
        include $this->template('task/index_new');
    }

    /**
     * 奖励明细
     */
    public function reward(){
        global $_W;
        $list = $this->rewardlist();
        $tradeSet = m('common')->getSysset('trade');
        include $this->template();
    }

    public function rewardlist(){
        global $_W,$_GPC;
        $page = intval($_GPC['page']);
        $page = max(1,$page);
        $psize = 100;
        $pstart = ( $page -1 ) * $psize;
        $sql = "select * from ".tablename('ewei_shop_task_reward')." where openid = :openid and `get` = 1 and uniacid = :uniacid order by gettime desc limit {$pstart},{$psize}";
        $list = pdo_fetchall($sql,array(':openid'=>$_W['openid'],':uniacid'=>$_W['uniacid']));
        return $list;
        include $this->template();
    }

    /**
     * 我的任务
     */
    public function mine(){
        global $_W,$_GPC;
        $status = intval($_GPC['status']);
        $condition = '';
        $time0 = "'0000-00-00 00:00:00'";
        switch ($status){
            case 1://进行中
                $condition = ' and (stoptime > "' . date('Y-m-d H:i:s') . '" or stoptime = '.$time0.') and finishtime = '.$time0;
                break;
            case 2://已完成
                $condition = ' and finishtime > '.$time0.'';
                break;
            case 3://已失败
                $condition = ' and stoptime != "0000-00-00 00:00:00" and stoptime < \'' . date('Y-m-d H:i:s') . '\' and finishtime = '.$time0;
                break;
            default:
                header('location:'.mobileUrl('task.mine',array('status'=>1)));
                exit;
        }
        $sql = "select * from " . tablename('ewei_shop_task_record') . " where openid = :openid and uniacid = :uniacid {$condition} order by id desc";
        $list = pdo_fetchall($sql,array(':openid'=>$_W['openid'], ':uniacid'=>$_W['uniacid']));
        include $this->template();
    }

    /**
     * 任务详情
     */
    public function detail_new(){
        global $_W,$_GPC;
        $id = intval($_GPC['id']);
        $rid = intval($_GPC['rid']);
        if (!empty($rid)){
            $sql = "select * from ".tablename('ewei_shop_task_record')." where id = :id and uniacid = :uniacid";
            //任务详情
            $detail = pdo_fetch($sql, array(':id'=>$rid,':uniacid'=>$_W['uniacid']));
            //任务奖励
            $reward = json_decode($detail['reward_data'],true);
            $goods = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = :recordid and openid = :openid and uniacid = :uniacid and isjoiner = 0 and reward_type = 'goods' and `level` = 0 group by reward_title",array(':recordid'=>$detail['id'],':openid'=>trim($_W['openid']),':uniacid'=>intval($_W['uniacid'])));
            $reward_goods = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = :recordid and openid = :openid and uniacid = :uniacid and isjoiner = 0 and reward_type = 'goods' and `level` = 0",array(':recordid'=>$detail['id'],':openid'=>trim($_W['openid']),':uniacid'=>intval($_W['uniacid'])));

            foreach ($goods as $k => $g) {
                unset($g['id']);
                foreach ($reward_goods as $key=>&$value){
                    $num = empty($goods[$k]['num'])?0:$goods[$k]['num'];
                    unset($g['num'],$value['id']);
                    if ($value === $g) {
                        $num++;
                        $goods[$k]['num']=$num;
                    }
                }
            }
            $reward_goods = $goods;
            unset($goods);

            //123级海报
            $reward1 = $reward2 = $reward3 = array();
            $reward_goods1 = $reward_goods2 = $reward_goods3 = array();
            if ($detail['tasktype'] == 'poster'){
                if ($detail['level2'] == 0){//只有一级
                    $detail['level1'] = $detail['task_demand'];
                    $reward1 = $reward;
                    $reward_goods1 = $reward_goods;
                }elseif($detail['task_demand']>$detail['level2'] && $detail['task_demand']>$detail['level1']){//三级
                    $reward1 = json_decode($detail['reward_data1'],true);
                    $goods1 = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = {$detail['id']} and openid = '{$_W['openid']}' and uniacid = {$_W['uniacid']} and isjoiner = 0 and reward_type = 'goods' and `level` = 1 group by reward_title");
                    $reward_goods1 = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = {$detail['id']} and openid = '{$_W['openid']}' and uniacid = {$_W['uniacid']} and isjoiner = 0 and reward_type = 'goods' and `level` = 1");
                    foreach ($goods1 as $k => $g) {
                        unset($g['id']);
                        foreach ($reward_goods as $key=>&$value){
                            $num = empty($goods1[$k]['num'])?0:$goods1[$k]['num'];
                            unset($g['num'],$value['id']);
                            if ($value === $g) {
                                $num++;
                                $goods1[$k]['num']=$num;
                            }
                        }
                    }
                    $reward_goods1 = $goods1;
                    unset($goods1);

                    $reward2 = json_decode($detail['reward_data2'],true);
                    $goods2 = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = {$detail['id']} and openid = '{$_W['openid']}' and uniacid = {$_W['uniacid']} and isjoiner = 0 and reward_type = 'goods' and `level` = 2 group by reward_title");
                    $reward_goods2 = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = {$detail['id']} and openid = '{$_W['openid']}' and uniacid = {$_W['uniacid']} and isjoiner = 0 and reward_type = 'goods' and `level` = 2");
                    foreach ($goods2 as $k => $g) {
                        unset($g['id']);
                        foreach ($reward_goods as $key=>&$value){
                            $num = empty($goods2[$k]['num'])?0:$goods2[$k]['num'];
                            unset($g['num'],$value['id']);
                            if ($value === $g) {
                                $num++;
                                $goods2[$k]['num']=$num;
                            }
                        }
                    }
                    $reward_goods2 = $goods2;
                    unset($goods2);
                    $reward3 = $reward;
                    $reward_goods3 = $reward_goods;
                    $detail['level3'] = $detail['task_demand'];
                }else{//二级
                    $reward1 = json_decode($detail['reward_data1'],true);
                    $goods1 = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = {$detail['id']} and openid = '{$_W['openid']}' and uniacid = {$_W['uniacid']} and isjoiner = 0 and reward_type = 'goods' and `level` = 1 group by reward_title");
                    $reward_goods1 = pdo_fetchall("select * from ".tablename('ewei_shop_task_reward')." where recordid = {$detail['id']} and openid = '{$_W['openid']}' and uniacid = {$_W['uniacid']} and isjoiner = 0 and reward_type = 'goods' and `level` = 1");
                    foreach ($goods1 as $k => $g) {
                        unset($g['id']);
                        foreach ($reward_goods as $key=>&$value){
                            $num = empty($goods1[$k]['num'])?0:$goods1[$k]['num'];
                            unset($g['num'],$value['id']);
                            if ($value === $g) {
                                $num++;
                                $goods1[$k]['num']=$num;
                            }
                        }
                    }
                    $reward_goods1 = $goods1;
                    unset($goods1);
                    $reward2 = $reward;
                    $reward_goods2 = $reward_goods;
                }
            }

            //关注奖励
            $followreward = json_decode($detail['followreward_data'],true);
            //参与记录
            $joiner = pdo_fetchall("select DISTINCT openid,headimg,nickname,gettime from ".tablename('ewei_shop_task_reward')." where isjoiner = 1 and recordid = {$rid} and `get`=1 and uniacid = {$_W['uniacid']}");
        }
        if(empty($detail) && !empty($id)){//未接取任务
            $sql = "select * from ".tablename('ewei_shop_task_list')." where id = :id and uniacid = :uniacid";
            $detail = pdo_fetch($sql, array(':id'=>$id,':uniacid'=>$_W['uniacid']));
            $reward = json_decode($detail['reward'],true);
            $cangettask = false;
            $goodsenough = false;
            if(!empty($reward['goods'])){
                $reward= p('task')->getRewardStock($reward);
              }
            if($reward['credit']>0||$reward['balance']>0||$reward['redpacket']>0||!empty($reward['coupon'])){
                $cangettask = true;
            }
            if($detail['typr'] =='goods'){
                $enough = p('task')->checkGoodsock($detail);
                if($enough['enough']){
                    $goodsenough = true;
                }
            }else{
                $goodsenough = true;
            }
            if(is_array($reward['goods'])){
                foreach($reward['goods'] as $key=> $val){
                    if($val['all'] >0){
                        $cangettask = true;
                    }
                }
            }
            if ($detail['type'] == 'poster'){
                $detail['tasktype'] = 'poster';
                $detail['level1'] = $detail['demand'];
                $detail['demand'] = max($detail['demand'] ,$detail['level2'] , $detail['level3']);
                if ($detail['level2'] == 0){//只有一级
                    $reward1 = $reward;
                }elseif($detail['level3'] > 0){//三级
                    $reward1 = $reward;
                    $reward2 = json_decode($detail['reward2'],true);
                    $reward3 = json_decode($detail['reward3'],true);
                }elseif($detail['level2'] > 0){//二级
                    $reward1 = $reward;
                    $reward2 = json_decode($detail['reward2'],true);
                }
            }
            $followreward = json_decode($detail['followreward'],true);
        }
        if (empty($detail))
            $this->message('任务不存在');
        !empty($detail['tasktype']) && $type = $detail['tasktype'];
        !empty($detail['type']) && $type = $detail['type'];

        $postfix = pdo_fetch('SELECT verb,unit FROM '.tablename('ewei_shop_task_list').' WHERE id=:id and uniacid=:uniacid',array(':uniacid'=>$_W['uniacid'],':id'=>$detail['taskid']));
        $taskType = $this->model->getTaskType($type);

        //任务需求文字描述
        if($detail['tasktype'] == 'poster'){
            if(empty($detail['verb'])){
                $postfix['verb'] = $postfix['verb'];
                if(empty($postfix['verb'])){
                    $postfix['verb'] = $taskType['verb'];
                }
            }else{
                $postfix['verb'] = $detail['verb'];
            }
            if(empty($detail['unit'])){
                $postfix['unit'] = $postfix['unit'];
                if(empty($postfix['unit'])){
                    $postfix['unit'] = $taskType['unit'];
                }
            }else{
                $postfix['unit'] = $detail['unit'];
            }
            $desc = $postfix['verb'] ;
            $desc .= $detail['task_demand'].$detail['demand'] . $postfix['unit'];
        }else{
            $desc = $taskType['verb'] ;
            if (!empty($taskType['unit'])) $desc .= $detail['task_demand'].$detail['demand'] . $taskType['unit'];
        }

        if (isset($detail['tasktype']) && $detail['tasktype'] == 'poster'){
            $poster = $this->model->create_poster(array(
                'id'=>$detail['id'],
                'design_data'=>$detail['design_data'],
                'design_bg'=>$detail['design_bg'],
                'stoptime'=>$detail['stoptime'],
            ));
        }
        $tradeSet = m('common')->getSysset('trade');

        $tradeSet['credittext'] = empty($tradeSet['credittext']) ? '积分': $tradeSet['credittext'];
        $tradeSet['moneytext'] = empty($tradeSet['moneytext']) ? '余额': $tradeSet['moneytext'];

        include $this->template('task/detail_new');
    }

    /**
     * 预览海报
     */
    public function viewposter(){
        global $_W,$_GPC;
        $id = intval($_GPC['id']);
    }

    /**
     * 接任务
     */
    public function picktask(){
        global $_W,$_GPC;
        if ($_W['ispost']){
            $openid = $_W['openid'];
            $taskid = intval($_GPC['id']);
            empty($taskid) && show_json(0,'任务不存在');
            $ret = $this->model->pickTask($taskid,$openid);
            if (is_error($ret)) show_json(0,$ret['message']);
            logg('id',$ret);
            show_json(1,$ret);
        }
    }
    /**
     * 推送海报到微信
     */
    public function sendtowechat(){
        global $_GPC;
        $recordid = intval($_GPC['recordid']);
        $this->model->send2wechat($recordid);
    }

    /**
     * 发送红包
     */
    public function getred(){
        global $_W,$_GPC;
        $rewardid = intval($_GPC['id']);
        $money = pdo_fetchcolumn("select reward_data from ".tablename('ewei_shop_task_reward')." where id = {$rewardid} and `get` = 1 and sent = 0 and openid = '{$_W['openid']}'");
        if (empty($money)) show_json(0,'任务不存在');
        $params = array(
            'openid' => $_W['openid'], //openid
            'tid' => time(),//订单编号
            'send_name' => '任务中心', //发送红包的人
            'money' => floatval($money), //发送红包金额 最低 1元
            'wishing' => '恭喜您获得了任务奖励', //祝福语
            'act_name' => '任务中心', //参与活动名称
            'remark' => '任务中心完成奖励', //备注信息
        );
        $result = m('common')->sendredpack($params);
        if (is_error($result)){
            show_json(0,$result['message']);
        }
        pdo_update('ewei_shop_task_reward',array('sent'=>1,'senttime'=>time()),array('id'=>$rewardid));
        show_json(1);
    }

    /* 购买指定商品*/
    public  function buygoods(){
        global $_W,$_GPC;

        $sql = 'SELECT id,design_data FROM '.tablename('ewei_shop_task_record').' WHERE id = :id AND uniacid = :uniacid';
        $params = array(
            ':id' => $_GPC['id'],
            ':uniacid' => $_W['uniacid'],
        );
        $goodsInfo = pdo_fetch($sql,$params);
        $goodsData = json_decode($goodsInfo['design_data'],true);
        include $this->template('task/buygoods');
    }
}
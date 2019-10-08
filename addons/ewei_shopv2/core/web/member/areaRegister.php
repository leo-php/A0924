<?php
//dezend by http://www.efwww.com/
if (!defined('IN_IA')) {
	exit('Access Denied');
}

class AreaRegister_EweiShopV2Page extends WebPage
{
	public function main()
	{
		global $_W;
		global $_GPC;
		$pindex = max(1, intval($_GPC['page']));
		$psize = 20;

		$sql = "select * from ".tablename("ewei_shop_area_register")." WHERE uniacid=:uniacid order by create_time desc";

		$total = pdo_fetchcolumn('SELECT COUNT(1) FROM '.tablename("ewei_shop_area_register").' WHERE uniacid=:uniacid',[':uniacid'=>$_W['uniacid']]);

		$sql .= ' limit ' . ($pindex - 1) * $psize . ',' . $psize;
		$list = pdo_fetchall($sql,[':uniacid'=>$_W['uniacid']]);
		foreach ($list as $k => $v) {
			$list[$k]['user'] = pdo_fetch("select level,nickname from ".tablename("ewei_shop_member")." where id=:id", array(":id" => $v['user_id']));
			$list[$k]['levelname'] = pdo_getcolumn('ewei_shop_member_level', array("id" => $list[$k]['user']['level']), 'levelname');
		}

		$pager = pagination2($total, $pindex, $psize);
		include $this->template();
	}

	public function successApply()
	{
		global $_W;
		global $_GPC;
		if (empty($_GPC['id'])) {
			show_json(0, '参数有误');
		}
		//判断是否有同区域并已成功的申请
		$registerInfo = pdo_fetch("select * from ".tablename("ewei_shop_area_register")." where id=:id", array(":id" => $_GPC['id']));
		if ($registerInfo['type'] == 1) { //区级
			$isSame = pdo_fetch("select * from ".tablename("ewei_shop_member")." where agent_area=:address", array(":address" => $registerInfo['area']));
		} else if($registerInfo['type'] == 2) { //市级
			$isSame = pdo_fetch("select * from ".tablename("ewei_shop_member")." where agent_city=:address", array(":address" => $registerInfo['city']));
		} else if($registerInfo['type'] == 3) { //省级
			$isSame = pdo_fetch("select * from ".tablename("ewei_shop_member")." where agent_province=:address", array(":address" => $registerInfo['province']));
		}
		if (!empty($isSame)) {
			show_json(0, '该区域已有区域代理');
		}
		pdo_update("ewei_shop_area_register", array('end_time' => time(), 'status' => 2), array('id' => $_GPC['id']));
		switch ($registerInfo['type']) {
			case 1:
				pdo_update("ewei_shop_member", array('agent_area' => $registerInfo['area'], 'agent_city' => '', 'agent_province' => ''), array('id' => $registerInfo['user_id']));
				break;
			case 2:
                pdo_update("ewei_shop_member", array('agent_city' => $registerInfo['city'], 'agent_area' => '', 'agent_province' => ''), array('id' => $registerInfo['user_id']));
                break;
			case 3:
                pdo_update("ewei_shop_member", array('agent_province' => $registerInfo['province'], 'agent_area' => '', 'agent_city' => ''), array('id' => $registerInfo['user_id']));
                break;
			default:
				true;
		}
		show_json(1, '已通过审核');
	}

	public function rejectApply()
	{
        global $_W;
        global $_GPC;
        if (empty($_GPC['id'])) {
            show_json(0, '参数有误');
        }
        pdo_update("ewei_shop_area_register", array('end_time' => time(), 'status' => 3,'reason' => $_GPC['reason']), array('id' => $_GPC['id']));
        show_json(1, '已拒绝审核');
	}
}

?>

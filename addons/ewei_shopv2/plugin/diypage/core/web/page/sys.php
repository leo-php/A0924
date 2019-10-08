<?php

if (!defined('IN_IA')) {
	exit('Access Denied');
}
class Sys_EweiShopV2Page extends PluginWebPage {

	function main() {
		global $_W, $_GPC;
		
        empty($_GPC['page']) && $_GPC['page'] = 1;
        
		$pagetype = 'sys';

        $condition = "";

		if (!empty($_GPC['keyword'])) {
			$keyword = '%' . trim($_GPC['keyword']) . '%';
			$condition .= " and name like '{$keyword}' ";
		}

		$limittype = $pagetype;
		if(!empty($_GPC['type'])){
            $condition .= " and type=".intval($_GPC['type'])." ";
            $limittype = null;
        }
		$result = $this->model->getPageList($limittype, $condition,  intval($_GPC['page']));
		extract($result);

		if(!empty($list)){
			foreach($list as $key => &$value){
				$url = mobileUrl('diypage', array('id'=>$value['id']), true);
				$value['qrcode'] = m('qrcode')->createQrcode($url);
			}
		}

		$diypagedata = m('common')->getPluginset('diypage');
		$diypagedata = $diypagedata['page'];

		include $this->template('diypage/page/list');
	}

	function edit() {
		$this->post('edit');
	}

	function  add(){
		$this->post('add');
	}

	protected function post($do){
		global $_W, $_GPC;

		$result = $this->model->verify($do, 'sys');
		extract($result);

		if($template && $do=='add') {
			$template['data'] = base64_decode($template['data']);
			$template['data'] = json_decode($template['data'], true);
			$page = $template;
		}

		$allpagetype = $this->model->getPageType();
		$typename = $allpagetype[$type]['name'];

        $diymenu = pdo_fetchall('select id, `name` from ' . tablename('ewei_shop_diypage_menu') . ' where merch=:merch and uniacid=:uniacid  order by id desc', array(':merch'=>intval($_W['merchid']), ':uniacid' => $_W['uniacid']));
        $diyadvs = pdo_fetchall('select id, `name` from ' . tablename('ewei_shop_diypage_plu') . ' where merch=:merch and `type`=1 and status=1 and uniacid=:uniacid  order by id desc', array(':merch'=>intval($_W['merchid']), ':uniacid' => $_W['uniacid']));

        $category = pdo_fetchall("SELECT id, name FROM " . tablename('ewei_shop_diypage_template_category') . " WHERE merch=:merch and uniacid=:uniacid order by id desc ", array(':merch'=>intval($_W['merchid']), ':uniacid'=>$_W['uniacid']));

		if($_W['ispost']) {
			$data = $_GPC['data'];
//			if(!empty($data)){
//			    foreach($data['items'] as $k => $v){
//                    if($v['id'] == 'topmenu' || $v['id'] == 'tabbar'){
//                        foreach($v['data'] as $vk => $vv){
//                            if(empty($vv['linkurl'])){
//                                show_json(0,'请把顶部菜单或选项卡的数据填写完整');
//                            }
//                        }
//                    }
//                }
//            }

            //替换自定义页面描述中的回车换行
            $data['page']['desc'] = str_replace(array("\r\n", "\r", "\n"), "",  trim($data['page']['desc']));
			$this->model->savePage($id, $data);
		}

        $hasplugins = json_encode(array(
            'creditshop' => p('creditshop') ? 1 : 0,
            'merch' => p('merch') ? 1 : 0,
            'seckill' => p('seckill') ? 1 : 0,
            'exchange' => p('exchange') ? 1 : 0
        ));

		include $this->template('diypage/page/post');
	}

	function delete(){
		global $_W, $_GPC;

		$id = intval($_GPC['id']);
		if(empty($id)) {
			$id = is_array($_GPC['ids']) ? implode(',', $_GPC['ids']) : 0;
		}
		$this->model->delPage($id);
	}

	function savetemp() {
		global $_W, $_GPC;

		$temp = array(
			'type'=>intval($_GPC['type']),
			'cate'=>intval($_GPC['cate']),
			'name'=>trim($_GPC['name']),
			'preview'=>trim($_GPC['preview']),
			'data'=>$_GPC['data']
		);
		$this->model->saveTemp($temp);
	}
}
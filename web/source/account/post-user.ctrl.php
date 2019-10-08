<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
load()->model('module');
load()->model('system');

$dos = array('delete', 'edit', 'set_permission', 'set_manager', 'module');
$do = in_array($do, $dos) ? $do : 'edit';

$uniacid = intval($_GPC['uniacid']);
$acid = intval($_GPC['acid']);
if (empty($uniacid) || empty($acid)) {
	itoast('请选择要编辑的平台账号', referer(), 'error');
}
$state = permission_account_user_role($_W['uid'], $uniacid);
$role_permission = in_array($state, array(ACCOUNT_MANAGE_NAME_FOUNDER, ACCOUNT_MANAGE_NAME_OWNER, ACCOUNT_MANAGE_NAME_MANAGER, ACCOUNT_MANAGE_NAME_VICE_FOUNDER));
if (!$role_permission) {
	itoast('无权限操作！', referer(), 'error');
}
$founders = explode(',', $_W['config']['setting']['founder']);
$founder_info = pdo_getcolumn('users', array('uid' => current($founders)), 'username');
$headimgsrc = tomedia('headimg_'.$acid.'.jpg');
$account = account_fetch($acid);
if (is_error($account)) {
	itoast($account['message']);
}

if ($do == 'edit') {
	$permissions = pdo_fetchall("SELECT id, uid, role FROM ".tablename('uni_account_users')." WHERE uniacid = '$uniacid' ORDER BY uid ASC, role DESC", array(), 'uid');
	if (!empty($permissions)) {
		$member = pdo_fetchall("SELECT username, uid FROM ".tablename('users')." WHERE uid IN (".implode(',', array_keys($permissions)).")", array(), 'uid');
		if (!empty($member)) {
			$operator = $manager = $owner = $vice_founder = array();
			foreach ($permissions as $key => $per_val) {
				$per_val['isfounder'] = in_array($member[$key]['uid'], $founders) ? 1 : 0;
				$per_val['username'] = $member[$key]['username'] ? $member[$key]['username'] : '';
				switch($per_val['role']) {
					case 'vice_founder':
						$vice_founder = $per_val;
						break;
					case 'owner':
						$owner = $per_val;
						break;
					case 'manager':
						$manager[] = $per_val;
						break;
					case 'operator':
						$operator[] = $per_val;
						break;
					default:
						break;
				}
			}
		}
	}

	template('account/manage-users');
}

if ($do == 'delete') {
	if (!$_W['isajax'] || !$_W['ispost']) {
		itoast('非法操作！', referer(), 'error');
	}
	$uid = is_array($_GPC['uid']) ? 0 : intval($_GPC['uid']);
	if (empty($uid)) {
		itoast('请选择要删除的用户！', referer(), 'error');
	}
	$data = array(
		'uniacid' => $uniacid,
		'uid' => $uid,
	);
	$exists = pdo_get('uni_account_users', array('uniacid' => $uniacid, 'uid' => $uid));
	if (empty($exists)) {
		itoast('该平台账号下不存在该用户！', referer(), 'error');
	}

	if ($state == ACCOUNT_MANAGE_NAME_MANAGER && ($exists['role'] == ACCOUNT_MANAGE_NAME_OWNER || $exists['role'] == ACCOUNT_MANAGE_NAME_MANAGER)) {
		itoast('管理员不可操作其他管理员', referer(), 'error');
	}
	$result = pdo_delete('uni_account_users', $data);
	if ($result) {
		if ($exists['role'] == ACCOUNT_MANAGE_NAME_OPERATOR) {
			pdo_delete('users_permission', $data);
		}
		pdo_delete('users_lastuse', $data);
		pdo_delete('system_stat_visit', $data);
		itoast('删除成功！', referer(), 'success');
	} else {
		itoast('删除失败，请重试！', referer(), 'error');
	}

}

if ($do == 'set_manager') {
	$username = trim($_GPC['username']);
	$user = user_single(array('username' => $username));
	if (!empty($user)) {
		if ($user['status'] != 2) {
			iajax(3, '用户未通过审核或不存在！', '');
		}
		$user_end_time = user_end_time($user['uid']);
		$account_end_time = $user_end_time == 0 ? 0 : strtotime($user_end_time);
		pdo_update('account', array('endtime' => $account_end_time), array('uniacid' => $uniacid));
		$addtype = intval($_GPC['addtype']);
		if (!in_array($addtype, array(ACCOUNT_MANAGE_TYPE_OPERATOR, ACCOUNT_MANAGE_TYPE_MANAGER, ACCOUNT_MANAGE_TYPE_OWNER, ACCOUNT_MANAGE_TYPE_VICE_FOUNDER))) {
			iajax(-1, '添加使用者类型有误，只能添加操作员/管理员/主管理员/副创始人！');
		}
		if (in_array($user['uid'], $founders)) {
			if ($addtype == ACCOUNT_MANAGE_TYPE_OWNER) {
				pdo_delete('uni_account_users', array('uniacid' => $uniacid, 'role' => ACCOUNT_MANAGE_NAME_OWNER));
				iajax(0, '修改成功！', '');
			}
			iajax(1, '不可操作网站创始人！', '');
		}
				if ($addtype == ACCOUNT_MANAGE_TYPE_OWNER && !in_array($_W['uid'], $founders)) {
			$creat_limit = permission_user_account_num($user['uid']);
			if ($creat_limit[TYPE_SIGN . '_limit'] <= 0) {
				itoast(error(5, ACCOUNT_TYPE_NAME . '创建数量已达上限'), '', 'error');
			}
		}

		$data = array(
			'uniacid' => $uniacid,
			'uid' => $user['uid'],
		);

		$exists = pdo_get('uni_account_users', $data);
		if (empty($exists)) {
			
				if ($addtype == ACCOUNT_MANAGE_TYPE_VICE_FOUNDER) {
					if ($user['founder_groupid'] != ACCOUNT_MANAGE_GROUP_VICE_FOUNDER) {
						iajax(6, '副创始人不存在！', '');
					}
					pdo_delete('uni_account_users', array('uniacid' => $uniacid, 'role' => ACCOUNT_MANAGE_NAME_VICE_FOUNDER));
					$data['role'] = ACCOUNT_MANAGE_NAME_VICE_FOUNDER;
				}
			
			if ($addtype == ACCOUNT_MANAGE_TYPE_OWNER) {
				if ($state == ACCOUNT_MANAGE_NAME_MANAGER) {
					iajax(4, '管理员不可操作主管理员', '');
				}
				$owner = pdo_get('uni_account_users', array('uniacid' => $uniacid, 'role' => 'owner'));
				if (empty($owner)) {
					$data['role'] = ACCOUNT_MANAGE_NAME_OWNER;
				} else  {
					$result = pdo_update('uni_account_users', $data, array('id' => $owner['id']));
					if ($result) {
												pdo_delete('users_permission', array('uniacid' => $uniacid, 'uid' => $user['uid']));
						cache_clean(cache_system_key("user_accounts"));
						cache_build_account_modules($uniacid);
						iajax(0, '修改成功！', '');
					} else  {
						iajax(1, '修改失败！', '');
					}
					exit;
				}
			} else if ($addtype == ACCOUNT_MANAGE_TYPE_MANAGER) {
				if ($state == ACCOUNT_MANAGE_NAME_MANAGER) {
					iajax(4, '管理员不可操作管理员', '');
				}
				$data['role'] = ACCOUNT_MANAGE_NAME_MANAGER;
			} else if ($addtype == ACCOUNT_MANAGE_TYPE_OPERATOR) {
				$data['role'] = ACCOUNT_MANAGE_NAME_OPERATOR;
			}

			pdo_delete('uni_account_users', array('uniacid' => $uniacid,'uid' => $user['uid']));
			$result = pdo_insert('uni_account_users', $data);

			if ($result) {
				if ($addtype == ACCOUNT_MANAGE_TYPE_OWNER) {
					pdo_delete('users_permission', array('uniacid' => $uniacid, 'uid' => $user['uid']));
				}
				cache_clean(cache_system_key("user_accounts"));
				cache_build_account_modules($uniacid);
				iajax(0, '添加成功！', '');
			} else  {
				iajax(1, '添加失败！', '');
			}
		} else {
			$permissions = table('users_permission')->getAllUserPermission($user['uid'], $uniacid);
			$modulelist = uni_modules();
			if (!empty($permissions)) {
				$i = 0;
				foreach ($permissions as $permission) {
					if ($i >= 3) {
						break;
					}
					if (!in_array($permission['type'], array_keys($modulelist))) {
						continue;
					}
					$module_info = module_fetch($permission['type']);
					$modules_str .= $module_info['title'] . ',';
					$i ++;
				}
			}

			if (empty($permissions) && !empty($exists)) {
				if ($exists['role'] == ACCOUNT_MANAGE_NAME_OPERATOR) {
					$role_name = '操作员';
				} elseif ($exists['role'] == ACCOUNT_MANAGE_NAME_MANAGER) {
					$role_name = '管理员';
				} elseif ($exists['role'] == ACCOUNT_MANAGE_NAME_OWNER) {
					$role_name = '主管理员';
				}

				iajax(2, "添加平台使用者失败, <a style='color:#3296fa'>" . $username . "<a/>, 已经是公众号的<a style='color:#3296fa'>" .$role_name. "<a/> ，请勿重复添加！", '');
			} else {
				iajax(2, "添加平台使用者失败, <a style='color:#3296fa'>" . $username . "<a/>, 已经是 <a style='color:#3296fa'>" . rtrim($modules_str, ',') . "</a>的应用操作员，请先取消其应用内操作员身份后再添加平台使用者！", '');
			}
		}
	} else  {
		iajax(-1, '用户不存在!', '');
	}
}

if ($do == 'set_permission') {

	$uid = intval($_GPC['uid']);
	$user = user_single(array('uid' => $uid));
	if (empty($user)) {
		itoast('您操作的用户不存在或是已经被删除！', '', '');
	}
	$role = permission_account_user_role($_W['uid'], $uniacid);
	if (empty($role)) {
		itoast('此用户没有操作该统一公众号的权限，请选指派“管理员”或是“操作员”权限！', '', '');
	}

	$module_permission = permission_account_user_menu($uid, $uniacid, 'modules');
	if (is_error($module_permission)) {
		itoast('参数错误！');
	}
	$module_permission_keys = current($module_permission) == 'all' ? array() : array_keys($module_permission);
	$module = uni_modules_by_uniacid($uniacid);
	if (!empty($module)) {
		foreach ($module as $key => $value) {
			if (in_array($account['type'], array(ACCOUNT_TYPE_OFFCIAL_NORMAL, ACCOUNT_TYPE_OFFCIAL_AUTH)) && $value[MODULE_SUPPORT_ACCOUNT_NAME] != MODULE_SUPPORT_ACCOUNT) {
				unset($module[$key]);
			}
			if ($account['type'] == ACCOUNT_TYPE_APP_NORMAL && $value['wxapp_support'] != MODULE_SUPPORT_WXAPP) {
				unset($module[$key]);
			}
		}
	}

	foreach ($account_all_type_sign as $account_type_sign => $account_type_info) {
		if (in_array($account['type'], $account_type_info['contain_type'])) {
			$account_type = $account_type_sign == 'account' ? 'system' : $account_type_sign;
		}
	}
	$user_menu_permissions = permission_account_user_menu($uid, $uniacid, $account_type);
	$menus = system_menu_permission_list($role);

	if (in_array($account['type_sign'], array(ACCOUNT_TYPE_SIGN, XZAPP_TYPE_SIGN, WEBAPP_TYPE_SIGN))) {
		$account_type_menu = ACCOUNT_TYPE_SIGN;
	} elseif (in_array($account['type_sign'], array(ALIAPP_TYPE_SIGN, BAIDUAPP_TYPE_SIGN, TOUTIAOAPP_TYPE_SIGN))){
		$account_type_menu = WXAPP_TYPE_SIGN;
	} else {
		$account_type_menu = $account['type_sign'];
	}
	if (checksubmit('submit')) {
		$all_menu_permission = permission_menu_name();
		$user_menu_permission_new = array();

		if (!empty($_GPC['menus'])) {
			foreach ($_GPC['menus'] as $permission_name) {
				if (in_array($permission_name, $all_menu_permission)) {
					$user_menu_permission_new[] = $permission_name;
				}
			}
			$data = array(
				'type' => $account_type,
				'permission' => implode('|', $user_menu_permission_new),
			);
			$result = permission_update_account_user($uid, $uniacid, $data);
			if (is_error($result)) {
				itoast($result['message']);
			}
		} else {
			pdo_delete('users_permission', array('uniacid' => $uniacid, 'uid' => $uid, 'type' => $account_type));
		}
		if (is_error($result)) {
			itoast($result['message']);
		}


				
		$permission_names = "'" . implode(array(PERMISSION_ACCOUNT, PERMISSION_WXAPP, PERMISSION_WEBAPP, PERMISSION_PHONEAPP, PERMISSION_XZAPP, PERMISSION_ALIAPP, PERMISSION_BAIDUAPP, PERMISSION_TOUTIAOAPP, PERMISSION_SYSTEM),"','") . "'";
		pdo_query("DELETE FROM " . tablename('users_permission') . " WHERE uniacid = :uniacid AND uid = :uid AND type not in (" .$permission_names . ")", array(':uniacid' => $uniacid, ':uid' => $uid));

		if (!empty($_GPC['module'])) {
			foreach($_GPC['module'] as $module_val) {
				$insert = array(
					'uniacid' => $uniacid,
					'uid' => $uid,
					'type' => $module_val,
				);
				if(empty($_GPC['module_'. $module_val]) || $_GPC[$module_val . '_select'] == 1) {
					$insert['permission'] = 'all';
					pdo_insert('users_permission', $insert);
					continue;
				} else {
					$data = array();
					foreach($_GPC['module_'. $module_val] as $v) {
						$data[] = $v;
					}
					if(!empty($data)) {
						$insert['permission'] = implode('|', $data);
						pdo_insert('users_permission', $insert);
					}
				}
			}
		}
		cache_delete(cache_system_key('permission', array('uniacid' => $uniacid, 'uid' => $uid)));
		itoast('操作菜单权限成功！', referer(), 'success');
	}
	template('account/set-permission');
}

if($do == 'module' && $_W['isajax']) {
	$uid = intval($_GPC['uid']);
	$user = user_single($uid);
	if(empty($user)) {
		iajax(1, '访问错误, 未找到指定操作用户.', '');
	}
	$founders = explode(',', $_W['config']['setting']['founder']);
	$isfounder = in_array($user['uid'], $founders);
	if($isfounder) {
		iajax(2, '访问错误, 无法编辑站长.', '');
	}
	$module_name = trim($_GPC['m']);
	$module = pdo_fetch('SELECT * FROM ' . tablename('modules') . ' WHERE name = :m', array(':m' => $module_name));
		$purview = pdo_fetch('SELECT * FROM ' . tablename('users_permission') . ' WHERE uniacid = :aid AND uid = :uid AND type = :type', array(':aid' => $uniacid, ':uid' => $uid, ':type' => $module_name));
	if(!empty($purview['permission'])) {
		$purview['permission'] = explode('|', $purview['permission']);
	} else {
		$purview['permission'] = array();
	}

	$mineurl = array();
	$all = 0;
	if(!empty($mods)) {
		foreach($mods as $mod) {
			if($mod['url'] == 'all') {
				$all = 1;
				break;
			} else {
				$mineurl[] = $mod['url'];
			}
		}
	}
	$data = array();
	if($module['settings']) {
		$data[] = array('title' => '参数设置', 'permission' => $module_name.'_settings');
	}
	if($module['isrulefields']) {
		$data[] = array('title' => '回复规则列表', 'permission' => $module_name.'_rule');
	}
	$entries = module_entries($module_name);
	if(!empty($entries['home'])) {
		$data[] = array('title' => '微站首页导航', 'permission' => $module_name.'_home');
	}
	if(!empty($entries['profile'])) {
		$data[] = array('title' => '个人中心导航', 'permission' => $module_name.'_profile');
	}
	if(!empty($entries['shortcut'])) {
		$data[] = array('title' => '快捷菜单', 'permission' => $module_name.'_shortcut');
	}
	if(!empty($entries['cover'])) {
		foreach($entries['cover'] as $cover) {
			$data[] = array('title' => $cover['title'], 'permission' => $module_name.'_cover_'.$cover['do']);
		}
	}
	if(!empty($entries['menu'])) {
		foreach($entries['menu'] as $menu) {
			if (!empty($menu['multilevel'])) {
				continue;
			}
			$data[] = array('title' => $menu['title'], 'permission' => $module_name.'_menu_'.$menu['do']);
		}
	}
	unset($entries);
	if(!empty($module['permissions'])) {
		$module['permissions'] = (array)iunserializer($module['permissions']);
		$data = array_merge($data, $module['permissions']);
	}
	foreach($data as &$data_val) {
		$data_val['checked'] = 0;
		if(in_array($data_val['permission'], $purview['permission']) || in_array('all', $purview['permission'])) {
			$data_val['checked'] = 1;
		}
	}
	unset($data_val);
	if (empty($data)) {
		iajax(3, '无子权限！', '');
	} else {
		iajax(0, $data, '');
	}
}
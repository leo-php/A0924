<?php


if (!defined('IN_IA')) {
    exit('Access Denied');
}
require_once IA_ROOT . '/addons/ewei_shopv2/defines.php';
require_once EWEI_SHOPV2_INC . 'plugin_processor.php';
require_once EWEI_SHOPV2_INC . 'receiver.php';

class GyProcessor extends PluginProcessor {

    public function __construct() {
        parent::__construct('gy');
    }

    public function respond($obj = null) {
        global $_W;
        $message = $obj->message;
        $msgtype = strtolower($message['msgtype']);
        $event = strtolower($message['event']);
        $content = $message['content'];
        $rule = pdo_fetch("select * from " . tablename('rule') . ' where uniacid=:uniacid and module=:module and name=:name limit 1', array(':uniacid' => $_W['uniacid'], ':module' => 'ewei_shopv2', ':name' => "ewei_shopv2:gy"));
        if (!empty($rule)) {
            $keyword = pdo_fetch("select * from " . tablename('rule_keyword') . ' where uniacid=:uniacid and rid=:rid limit 1', array(':uniacid' => $_W['uniacid'], ':rid' => $rule['id']));
            $pattern = '/^' . $keyword['words'] . '([0-9]+)/i';

            if (preg_match($pattern, $content, $matches)) {
                if (empty($matches[1])) {
                    return $obj->respText('绑定信息有误');
                } else {
                    $id = intval($matches[1]);

                    $openid = $obj->message['from'];
                    if (empty($openid)) return $obj->respText('OPENID为空');

                    $member = pdo_fetch('SELECT id,openid FROM ' . tablename('ewei_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid', array(
                        ':uniacid' => $_W['uniacid'],
                        ':openid'  => $openid
                    ));

                    $agent = pdo_fetch('SELECT id,realname,mobile FROM ' . tablename('ewei_shop_gy_member') . ' WHERE uniacid=:uniacid AND id=:id LIMIT 1', array(
                        ':uniacid' => $_W['uniacid'],
                        ':id'      => $id
                    ));

                    if (empty($agent)) return $obj->respText('无效的会员');

                    $bind = pdo_fetch('SELECT mid FROM ' . tablename('ewei_shop_gy_bind') . ' WHERE uniacid=:uniacid AND mid=:mid LIMIT 1', array(
                        ':uniacid' => $_W['uniacid'],
                        ':mid'     => $member['id']
                    ));

                    if (empty($bind)) {
                        pdo_insert('ewei_shop_gy_bind', array(
                            'mid'        => $member['id'],
                            'openid'     => $member['openid'],
                            'uniacid'    => $_W['uniacid'],
                            'agentid'    => $agent['id'],
                            'createtime' => TIMESTAMP
                        ));
                        return $obj->respText($agent['realname'].' 已经作为您的荐酒人，神秘茅台，醉美酒谷欢迎您。'  );
                    }
                    return $obj->respText('您已绑定荐酒人，不可再次绑定');
                }
            } else {
                return $obj->respText('无效的绑定信息');
            }
        } else {
            return $obj->respText('无效的绑定信息!!!');
        }


    }
}
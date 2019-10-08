<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

//ini_set("display_errors", "On");
//error_reporting(E_ALL | E_STRICT);

class Install_EweiShopV2Page extends PluginWebPage {
    public function main() {
        global $_W, $_GPC;
        $no_left = false;
        $plugin = pdo_fetch('SELECT id FROM ' . tablename('ewei_shop_plugin') . ' WHERE identity="xfqf" ');
        if (!($plugin)) {
            pdo_insert('ewei_shop_plugin', array(
                'identity'   => 'xfqf',
                'name'       => '消费全返',
                'version'    => '1.0',
                'author'     => '9CIT',
                'status'     => 1,
                'category'   => 'biz',
                'thumb'      => '../addons/ewei_shopv2/static/images/creditshop.jpg',
                'iscom'      => 0,
                'deprecated' => 0,
                'isv2'       => 1
            ));
        }

        header('location: ' . webUrl('system/plugin'));
        exit;
    }

    public function install() {
        pdo_query("
DROP TABLE IF EXISTS `ims_zm_shop_xfqf_history`;
CREATE TABLE `ims_ewei_shop_xfqf_history` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `orderid` int(11) unsigned NOT NULL,
  `mid` int(11) unsigned NOT NULL,
  `pid` int(11) unsigned NOT NULL,
  `point` decimal(10,2) unsigned NOT NULL,
  `create_at` int(11) unsigned NOT NULL,
  `apply_at` int(11) unsigned NOT NULL DEFAULT '0',
  `type` int(11) unsigned NOT NULL DEFAULT '0',
  `remark` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;


DROP TABLE IF EXISTS `ims_ewei_shop_xfqf_log`;
CREATE TABLE `ims_ewei_shop_xfqf_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `mid` int(11) unsigned NOT NULL,
  `openid` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `createtime` int(11) unsigned NOT NULL,
  `applytime` int(11) unsigned NOT NULL DEFAULT '0',
  `credit1` decimal(10,2) unsigned NOT NULL DEFAULT '0.00',
  `credit2` decimal(10,3) unsigned NOT NULL DEFAULT '0.000',
  `proportion` float unsigned NOT NULL DEFAULT '0',
  `point` decimal(10,2) unsigned NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;


DROP TABLE IF EXISTS `ims_ewei_shop_xfqf_member`;
CREATE TABLE `ims_ewei_shop_xfqf_member` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `createtime` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;


DROP TABLE IF EXISTS `ims_ewei_shop_xfqf_recharge`;
CREATE TABLE `ims_ewei_shop_xfqf_recharge` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `mid` int(11) unsigned NOT NULL,
  `openid` varchar(255) DEFAULT NULL,
  `createdate` date NOT NULL,
  `createtime` int(11) unsigned NOT NULL,
  `money` decimal(10,2) unsigned NOT NULL,
  `merchid` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    public function uninstall() {
        $sql = "
		DROP TABLE IF EXISTS `ims_ewei_shop_xfqf_history`;
		DROP TABLE IF EXISTS `ims_ewei_shop_xfqf_log`;
		DROP TABLE IF EXISTS `ims_ewei_shop_xfqf_member`;
		";
        pdo_run($sql);
    }

    public function truncate() {
        global $_W;
        /*
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_xfqf_member'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_member'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_merch_user'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_xfqf_log'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_xfqf_history'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_order'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_order_goods'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_order_refund'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_member_log'));

        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_apply'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_bank'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_clickcount'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_level'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_log'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_rank'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_repurchase'));
        pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_commission_shop'));

        pdo_update('mc_members',array(
            'credit1' => 0.00,
            'credit2' => 0.00
        ),array(
            'uniacid'=>$_W['uniacid']
        ));

        pdo_delete('mc_credits_record',array(
            'uniacid'=>$_W['uniacid']
        ));*/
        message('数据清空完成！');
    }
}
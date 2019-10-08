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
        $plugin = pdo_fetch('SELECT id FROM ' . tablename('ewei_shop_plugin') . ' WHERE identity="jflc" ');
        if (!($plugin)) {
            pdo_insert('ewei_shop_plugin', array(
                'identity'   => 'jflc',
                'name'       => '积分理财',
                'version'    => '1.0',
                'author'     => '9CIT',
                'status'     => 1,
                'category'   => 'biz',
                'thumb'      => '../addons/ewei_shopv2/static/images/task.jpg',
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
ALTER TABLE ims_ewei_shop_merch_user ADD `commissionopenid` varchar(32) DEFAULT NULL;
ALTER TABLE ims_ewei_shop_merch_user ADD `commissionrate` decimal(10,2) DEFAULT 0.00;
            
DROP TABLE IF EXISTS `ims_ewei_shop_jflc_agent`;
CREATE TABLE `ims_ewei_shop_jflc_agent` (
  `mid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `point` decimal(10,2) unsigned NOT NULL DEFAULT '0.00',
  `createtime` int(11) unsigned NOT NULL,
  `updatetime` int(11) unsigned NOT NULL,
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `ims_ewei_shop_jflc_income`;
CREATE TABLE `ims_ewei_shop_jflc_income` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `createtime` int(11) unsigned NOT NULL,
  `proportion` decimal(10,2) unsigned NOT NULL,
  `createdate` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `createdate` (`createdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `ims_ewei_shop_jflc_merch_order_log`;
CREATE TABLE `ims_ewei_shop_jflc_merch_order_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `orderid` int(11) unsigned NOT NULL,
  `uniacid` int(11) unsigned NOT NULL,
  `merchid` int(11) unsigned NOT NULL,
  `openid` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ordersn` varchar(200) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `price` decimal(10,2) unsigned NOT NULL,
  `rate` decimal(10,2) unsigned NOT NULL,
  `commission` decimal(10,2) unsigned NOT NULL,
  `createtime` int(11) unsigned NOT NULL,
  `closetime` int(11) unsigned NOT NULL,
  `applytime` int(11) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `ims_ewei_shop_jflc_point_log`;
CREATE TABLE `ims_ewei_shop_jflc_point_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `createtime` int(11) unsigned NOT NULL,
  `point` decimal(10,2) NOT NULL,
  `mid` int(11) unsigned NOT NULL,
  `openid` varchar(200) NOT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `type` int(3) unsigned DEFAULT '0' COMMENT '1:投资 2:撤资 0:收益',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }

    public function uninstall() {
        $sql = "

		";
        pdo_run($sql);
    }

    public function truncate() {
        //pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_member'));
        //pdo_run('TRUNCATE TABLE ' . tablename('zm_shop_merch_user'));
/*
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_order'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_order_goods'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_order_refund'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_member_log'));

        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_apply'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_bank'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_clickcount'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_level'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_log'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_rank'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_repurchase'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_commission_shop'));
*/
        //pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_mt_member'));
        //pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_mt_goods'));
				/*
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_member'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_member_cart'));
				*/
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_jflc_agent'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_jflc_income'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_jflc_merch_order_log'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_jflc_point_log'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_creditmanagement_apply'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_creditmanagement_log'));

        message('数据清空完成！');
    }
}
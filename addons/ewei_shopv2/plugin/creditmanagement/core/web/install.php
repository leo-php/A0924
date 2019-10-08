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

        $config = include_once dirname(__FILE__) . '/../../config.php';

        $identity = $config['id'];
        $name = $config['name'];

        $plugin = pdo_fetch('SELECT id FROM ' . tablename('ewei_shop_plugin') . ' WHERE identity="'.$identity.'" ');
        if (!($plugin)) {
            pdo_insert('ewei_shop_plugin', array(
                'identity'   => $identity,
                'name'       => $name,
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
        CREATE TABLE `ims_ewei_shop_creditmanagement_apply` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `applyno` varchar(255) DEFAULT '',
  `mid` int(11) DEFAULT '0',
  `type` tinyint(3) DEFAULT '0',
  `orderids` longtext,
  `commission` decimal(10,2) DEFAULT '0.00',
  `commission_pay` decimal(10,2) DEFAULT '0.00',
  `content` text,
  `status` tinyint(3) DEFAULT '0',
  `applytime` int(11) DEFAULT '0',
  `checktime` int(11) DEFAULT '0',
  `paytime` int(11) DEFAULT '0',
  `invalidtime` int(11) DEFAULT '0',
  `refusetime` int(11) DEFAULT '0',
  `realmoney` decimal(10,2) DEFAULT '0.00',
  `charge` decimal(10,2) DEFAULT '0.00',
  `deductionmoney` decimal(10,2) DEFAULT '0.00',
  `beginmoney` decimal(10,2) DEFAULT '0.00',
  `endmoney` decimal(10,2) DEFAULT '0.00',
  `alipay` varchar(50) NOT NULL DEFAULT '',
  `bankname` varchar(50) NOT NULL DEFAULT '',
  `bankcard` varchar(50) NOT NULL DEFAULT '',
  `realname` varchar(50) NOT NULL DEFAULT '',
  `alipay1` varchar(50) NOT NULL DEFAULT '',
  `bankname1` varchar(50) NOT NULL DEFAULT '',
  `bankcard1` varchar(50) NOT NULL DEFAULT '',
  `repurchase` decimal(10,2) DEFAULT '0.00',
  `sendmoney` decimal(10,2) DEFAULT '0.00',
  `senddata` text,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_mid` (`mid`),
  KEY `idx_checktime` (`checktime`),
  KEY `idx_paytime` (`paytime`),
  KEY `idx_applytime` (`applytime`),
  KEY `idx_status` (`status`),
  KEY `idx_invalidtime` (`invalidtime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `ims_ewei_shop_creditmanagement_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `mid` int(11) unsigned NOT NULL,
  `openid` varchar(255) NOT NULL,
  `num` decimal(10,2) unsigned NOT NULL,
  `date` date NOT NULL,
  `createtime` int(11) NOT NULL,
  `status` tinyint(3) NOT NULL DEFAULT '0',
  `applytime` int(11) unsigned NOT NULL DEFAULT '0',
  `checktime` int(11) unsigned NOT NULL DEFAULT '0',
  `paytime` int(11) unsigned NOT NULL DEFAULT '0',
  `invalidtime` int(11) unsigned NOT NULL DEFAULT '0',
  `refusetime` int(11) unsigned NOT NULL DEFAULT '0',
  `type` int(11) unsigned NOT NULL DEFAULT '0',
  `remark` text,
  `content` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

");
        echo 'INSTSLL OK';
    }

    public function uninstall() {
        $sql = "";
        pdo_run($sql);
    }

    public function truncate() {
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_creditmanagement_apply'));
        pdo_run('TRUNCATE TABLE ' . tablename('ewei_shop_creditmanagement_log'));
        message('数据清空完成！');
    }
}
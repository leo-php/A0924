<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);

class Install_EweiShopV2Page extends PluginWebPage {
    public function main() {
        global $_W, $_GPC;
        $no_left = false;
        $plugin = pdo_fetch('SELECT id FROM ' . tablename('ewei_shop_plugin') . ' WHERE identity="yet" ');
        if (!($plugin)) {
            pdo_insert('ewei_shop_plugin', array(
                'identity'   => 'yet',
                'name'       => '积分互转',
                'version'    => '1.0',
                'author'     => '9CIT',
                'status'     => 1,
                'category'   => 'other',
                'thumb'      => '../addons/ewei_shopv2/static/images/creditshop.jpg',
                'iscom'      => 0,
                'deprecated' => 0,
                'isv2'       => 1
            ));
            $this->install();
        }

        header('location: ' . webUrl('system/plugin'));
        exit;
    }

    public function install() {
        pdo_query("
        CREATE TABLE `ims_ewei_shop_jy_yet_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) unsigned NOT NULL,
  `type` int(11) unsigned DEFAULT '0',
  `level` int(11) unsigned DEFAULT '0',
  `openid` varchar(255) NOT NULL,
  `openid2` varchar(255) DEFAULT NULL,
  `credit` decimal(10,2) unsigned NOT NULL,
  `credit2` decimal(10,2) unsigned DEFAULT NULL,
  `send_sn` varchar(255) NOT NULL,
  `send_money` decimal(10,2) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }

    public function uninstall() {
    }
}

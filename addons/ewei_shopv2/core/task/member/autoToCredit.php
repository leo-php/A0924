<?php
ini_set('display_errors', 'On');
error_reporting(32767);
require '../../../../../framework/bootstrap.inc.php';
require '../../../../../addons/ewei_shopv2/defines.php';
require '../../../../../addons/ewei_shopv2/core/inc/functions.php';
global $_W;
global $_GPC;
ignore_user_abort();
set_time_limit(0);

$allUsers = pdo_fetchall("select id,exchange_speed from ".tablename("ewei_shop_member")." where credit1 > 0");
$sysset = m('common')->getSysset();
echo 'ss';die;
var_dump($sysset.'sss');die;
foreach ($allUsers as $k => $v) {

}


?>

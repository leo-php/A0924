<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/16
 * Time: 11:39
 */
define('IN_SYS',true);
$_SERVER['REMOTE_ADDR']='localhost';
require'../framework/bootstrap.inc.php';
load()->web('common');
if(empty($argv[1]))exit('PLSINPUTUID');
$uniacid=intval($argv[1]);
if(empty($uniacid)){
    die('AccessDenied.');
}
$_W['uniacid']=$uniacid;
$_W['acid']=$uniacid;

$site=WeUtility::createModuleSite('ewei_shopv2');
$method='doTask';
$site->uniacid=$uniacid;
$site->inMobile=false;
if(method_exists($site,$method)){
    $site->$method();
    exit();
}
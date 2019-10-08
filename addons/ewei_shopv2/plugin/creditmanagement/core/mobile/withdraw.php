<?php
if (!(defined('IN_IA'))) {
    exit('Access Denied');
}

class Withdraw_EweiShopV2Page extends PluginMobileLoginPage {
    public function main() {
        global $_W;
        global $_GPC;
        $openid = $_W['openid'];
        $member = m('member')->getMember($openid);
        $commission_total = pdo_fetchcolumn('SELECT SUM(num) from ' . tablename('ewei_shop_creditmanagement_log') . ' where uniacid=:uniacid AND mid=:mid LIMIT 1', array(
            ':mid'     => $member['id'],
            ':uniacid' => $_W['uniacid']
        ));

        $commission_all = pdo_fetchall('SELECT SUM(num) num,status from ' . tablename('ewei_shop_creditmanagement_log') . ' where uniacid=:uniacid AND mid=:mid GROUP BY status', array(
            ':mid'     => $member['id'],
            ':uniacid' => $_W['uniacid']
        ),'status');

        $commission_ok = empty($commission_all['0']) ? 0.00 : $commission_all['0']['num'];
        $commission_apply = empty($commission_all['1']) ? 0.00 : $commission_all['1']['num'];
        $commission_pay = empty($commission_all['3']) ? 0.00 : $commission_all['3']['num'];
        $commission_check = empty($commission_all['2']) ? 0.00 : $commission_all['2']['num'];
        $commission_fail = empty($commission_all['-1']) ? 0.00 : $commission_all['-1']['num'];

        $cansettle = (1 <= $commission_ok) && (floatval($this->set['withdraw']) <= $commission_ok);

        include $this->template();
    }
}

?>
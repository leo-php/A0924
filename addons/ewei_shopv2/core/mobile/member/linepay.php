<?php

/*
 * 人人商城
 *
 * 青岛易联互动网络科技有限公司
 * http://www.we7shop.cn
 * TEL: 4000097827/18661772381/15865546761
 */

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Linepay_EweiShopV2Page extends MobileLoginPage {

    function main() {

        global $_W, $_GPC;
      	$mobile = pdo_getcolumn("ewei_shop_member", array('openid' => $_W['openid']), 'mobile');
        include $this->template();
    }

    public function getUploadFile()
    {
        global $_GPC;
        global $_W;
        if (!is_uploaded_file($_FILES["files"]['tmp_name'])) {
            show_json(0, '没有上传图片');
        }
        $path = '../addons/ewei_shopv2/uploads/linepayPic';
        if (!file_exists($path)) {
            mkdir($path);
        }
        $pinfo = pathinfo($_FILES["files"]['name']);
        $ftype = $pinfo['extension'];
        $newFilename = $path . '/' . $_W['openid'] . time() . '.' . $ftype;
        if (!move_uploaded_file($_FILES["files"]['tmp_name'], $newFilename)) {
            show_json(0, '上传失败');
        }
        show_json(1, array('message' => '上传成功', 'path' => $newFilename));
    }

    public function addRecordApply()
    {
        global $_W;
        global $_GPC;
        if (!$_GPC['total_price']) {
            show_json(0, '请输入进货金额');
        }
        $memberId = pdo_getcolumn('ewei_shop_member', array('openid' => $_W['openid']), 'id');
        if (!$_GPC['total_price'] || !$_GPC['username'] || !$_GPC['order_sn'] || !$_GPC['mobile']) {
            show_json(0, '请输入完整的信息');
        }
        $insertData = array(
            'create_time' => time(),
            'status' => 1,
            'remark' => $_GPC['remark'],
            'total_price' => $_GPC['total_price'],
            'picture' => $_GPC['picture'],
            'order_sn' => $_GPC['order_sn'],
            'username' => $_GPC['username'],
            'mobile' => $_GPC['mobile'],
            'user_id' => $memberId
        );
        pdo_insert("ewei_shop_linepay_record", $insertData);
        $id = pdo_insertid();
        if ($id) {
            show_json(1, '成功提交申请，我们会尽快审核');
        } else {
            show_json(0, '提交申请失败');
        }
    }
}

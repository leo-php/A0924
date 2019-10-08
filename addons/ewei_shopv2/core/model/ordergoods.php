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

class Ordergoods_EweiShopV2Model
{

    /**
     * 计算订单中商品累计赠送的积分
     * @param type $order
     */
    function getGoodsCredit1($goods)
    {

        $credit1 = 0;
        //积分累计
        $gcredit = trim($goods['credit']);
        if (!empty($gcredit)) {
            if (strexists($gcredit, '%')) {
                //按比例计算
                $credit1 += intval(floatval(str_replace('%', '', $gcredit)) / 100 * $goods['realprice']);
            } else {
                //按固定值计算
                $credit1 += intval($goods['credit']) * $goods['total'];
            }
        }

        return $credit1;
    }

    /**
     * 计算订单中商品累计赠送的余额
     * @param type $order
     */
    function getGoodsCredit2($goods)
    {

        $credit2 = 0;
        $gbalance = trim($goods['money']);
        if (!empty($gbalance)) {
            if (strexists($gbalance, '%')) {
                //按比例计算
                $credit2 += round(floatval(str_replace('%', '', $gbalance)) / 100 * $goods['realprice'], 2);
            } else {
                //按固定值计算
                $credit2 += round($goods['money'], 2) * $goods['total'];
            }
        }

        return $credit2;
    }

    /**
     * //处理订单库存情况(单品退换货)
     * @param type $goods
     */
    function setStock($goods)
    {
        global $_W;

        if (!empty($goods['optionid'])) {
            //规格
            $option = m('goods')->getOption($goods['goodsid'], $goods['optionid']);

            if (!empty($option) && $option['stock'] != -1) {
                //增加库存
                $stock = $option['stock'] + $goods['total'];

                if (!empty($stock)){
                    pdo_update('ewei_shop_goods_option', array('stock' => $stock), array('uniacid' => $_W['uniacid'], 'goodsid' => $goods['goodsid'], 'id' => $goods['optionid']));
                }
            }
        }


        $totalstock = $goods['goodstotal'] + $goods['total'];
        if (!empty($totalstock)){
            pdo_update('ewei_shop_goods', array('total' => $totalstock), array('uniacid' => $_W['uniacid'], 'id' => $goods['goodsid']));
        }


    }





}
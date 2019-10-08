<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Wxcard_EweiShopV2ComModel extends ComModel {


    protected function checkurl($url) {

        if (strexists($url, 'http://')) {
            $url = str_replace("http://", '', $url);
        }
        if (strexists($url, 'https://')) {
            $url = str_replace("https://", '', $url);
        }
        return $url;
    }

    protected function getUrl($do, $query = null) {

        $url = mobileUrl($do, $query, true);
        if (strexists($url, '/addons/ewei_shopv2/')) {
            $url = str_replace("/addons/ewei_shopv2/", '/', $url);
        }
        if (strexists($url, '/core/mobile/order/')) {
            $url = str_replace("/core/mobile/order/", '/', $url);
        }
        return $url;
    }

    /**
     * 微信卡券：上传LOGO
     */
    public function wxCardUpdateImg($url) {
        $account = m('common')->getAccount();

        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }

        $data['buffer'] = '@'.$url;
        $url             = "https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=".$token;
        $jsoninfo         = $this->wxHttpsRequest($url,$data);

        return $jsoninfo;
    }

    /**
     * 微信卡券：获取颜色
     */
    public function wxCardColor(){
        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/getcolors?access_token=".$token;
        $jsoninfo         = $this->wxHttpsRequest($url);
        return $jsoninfo;
    }

    /**
     * 微信卡券：创建卡券,json拼写
     */
    public function createCard($params) {

        $card_type=$params['card_type'];                                                          //*卡券类型

        //cardtype
        //***base_info 基础信息
        $logo_url=$params['wxlogourl'];                                                            //*卡券的商户logoURL  需要先上传
        $brand_name=$params['brand_name'];                                                //*商户名字
        $code_type="CODE_TYPE_NONE";//$params['code_type'];                 //*码型：        "CODE_TYPE_TEXT"文本；
        //"CODE_TYPE_BARCODE"一维码
        // "CODE_TYPE_QRCODE"二维码
        //"CODE_TYPE_ONLY_QRCODE",二维码无code显示；
        //"CODE_TYPE_ONLY_BARCODE",一维码无code显示；
        //CODE_TYPE_NONE，不显示code和条形码类型
        $title=$params['title'];                                                                             //*卡券名
        $color=$params['color'];                                                                         //*券颜色。按色彩规范标注填写Color010-Color100。
        if(empty($color))
        {
            $color="Color010";
        }

        $notice=$params['notice'];                                                                     //*卡券使用提醒，字数上限为16个汉字。
        $service_phone=$params['service_phone'];                                          //客服电话。
        $description=$params['description'];                                                    //*卡券使用说明，字数上限为1024个汉字。

        //date_info
        $type=$params['datetype'];                                                                   //*参数分为:DATE_TYPE_FIX_TIME_RANGE(固定时间段)       DATE_TYPE_FIX_TERM(可用时长)
        if($type=="DATE_TYPE_FIX_TIME_RANGE") {
            $begin_timestamp = $params['begin_timestamp'];                         //*固定时间段开始时间
            $end_timestamp = $params['end_timestamp'];                               //*固定时间段结束时间
        }
        else if($type=="DATE_TYPE_FIX_TERM") {
            $fixed_term = empty($params['fixed_term'])?0:$params['fixed_term'];                                                //*可用时长时 表示自领取后多少天内有效，不支持填写0。
            $fixed_begin_term = empty($params['fixed_begin_term'])?0:$params['fixed_begin_term'];              //*可用时长时 表示自领取后多少天开始生效
            //$end_timestamp = $params['end_timestamp'];                               //*可用时长时 表示卡券统一过期时间
        }



        //sku
        $quantity=empty($params['quantity'])?100:$params['quantity'];     //*卡券库存的数量，上限为100000000。

        $use_limit=empty($params['use_limit'])?1:$params['use_limit'];       //*每人可核销的数量限制,不填写默认为50。
        $get_limit=empty($params['get_limit'])?1:$params['get_limit'];        //*每人可领券的数量限制,不填写默认为50。

        $use_custom_code="false";                                                                   //是否允许自定义code码  默认false 此处暂不使用
        $bind_openid="false";                                                                           //是否指定用户领取 默认false  此处暂不使用

        $can_share=empty($params['can_share'])?"false":"true";                               //卡券领取页面是否可分享。
        $can_give_friend=empty($params['can_give_friend'])?"false":"true";  //卡券是否可转赠。

        $location_id_list="";                                                                                //门店门店位置poiid 此处暂不使用

        $center_title=$params['center_title'];                                                     //立即使用按钮文字
        $center_sub_title=$params['center_sub_title'];                                     //立即使用提示文字
        $center_url= $this->checkurl($params['center_url']);                           //立即使用跳转链接

        $setcustom=$params['setcustom'];                                                        //判断是否设置自定义入口(非微信接口参数)
        if(!empty($setcustom)) {
            $custom_url_name = $params['custom_url_name'];                          //自定义入口按钮文字
            $custom_url_sub_title = $params['custom_url_sub_title'];                //自定义入口提示文字
            $custom_url =  $this->checkurl($params['custom_url']);                  //自定义入口跳转链接
        }

        $setpromotion = $params['setpromotion'];                                        //判断是否设置营销场景入口(非微信接口参数)
        if(!empty($setpromotion)) {
            $promotion_url_name = $params['promotion_url_name'];           //营销场景入口按钮文字
            $promotion_url_sub_title = $params['promotion_url_sub_title']; //营销场景入口提示文字
            $promotion_url = $this->checkurl($params['promotion_url']);    //营销场景入口跳转链接
        }

        $source="";                                                                                                //第三方来源名(暂不使用)

        //***advanced_info                                                                                    //高级模式

        $can_use_with_other_discount=empty($params['can_use_with_other_discount'])?
            "false":"true";                          //是否可与其他优惠同时使用

        //abstract                                                                                                 //封面

        $setabstract = $params['setabstract'];                                                  //判断是否(非微信接口参数)
        $abstract=$params['abstract'];                                                              //封面摘要简介
        $icon_url_list=$params['icon_url_list'];                                                  //封面图片链接
        $text_image_list=$params['text_image_list'];                                       //图文列表
        $time_limit="";                                                                                        //时段限制; 暂不使用

        $business_service="";
        $business=array();
        if(!empty($params['BIZ_SERVICE_FREE_WIFI']))
        {
            $business[]="\"BIZ_SERVICE_FREE_WIFI\"";    //免费WIFI
        }
        if(!empty($params['BIZ_SERVICE_WITH_PET']))
        {
            $business[]="\"BIZ_SERVICE_WITH_PET\"";   //可带宠物
        }
        if(!empty($params['BIZ_SERVICE_FREE_PARK']))
        {
            $business[]="\"BIZ_SERVICE_FREE_PARK\"";     //免费停车
        }
        if(!empty($params['BIZ_SERVICE_DELIVER']))
        {
            $business[]="\"BIZ_SERVICE_DELIVER\"";   //可外卖
        }

        if(!empty($business))
        {
            $business_service=implode(',',$business);
        }




        if($card_type=="CASH")
        {
            //代金券类型专用参数
            $accept_category=$params['accept_category'];                                  //指定可用的商品类目，仅用于代金券类型
            $reject_category=$params['reject_category'];                                     //指定不可用的商品类目，仅用于代金券类型
            $least_cost=empty($params['least_cost'])?0:$params['least_cost'];  //填入后将在全面拼写消费满xx元可用。
            $reduce_cost=empty($params['reduce_cost'])?0:$params['reduce_cost'];   //表示减免金额。（单位为分）
        }
        elseif($card_type=="DISCOUNT")
        {
            //折扣券类型专用参数
            $discount=empty($params['discount'])?0:$params['discount'];      //折扣券专用，表示打折额度（百分比）。填30就是七折。
        }
        elseif($card_type=="MEMBER_CARD")
        {
            //折扣券类型专用参数
            $background_pic_url=$params['background_pic_url'];
            $supply_bonus=$params['supply_bonus'];                                      //积分
            $supply_balance=$params['supply_balance'];                      //储值

            $bonus_url=$params['bonus_url'];        //积分跳转链接
            $balance_url =$params['balance_url'];     //余额跳转链接

            $prerogative=$params['prerogative'];                                    //会员卡特权说明
            $auto_activate=$params['auto_activate'];                       //是否自动激活会员卡

            $activate_url=$params['activate_url'];                       //激活会员卡的url。

            /*
             * custom_field_name_type 属性对应参数
             * FIELD_NAME_TYPE_LEVEL              等级
             * FIELD_NAME_TYPE_COUPON        优惠券
             * FIELD_NAME_TYPE_STAMP            印花
             * FIELD_NAME_TYPE_DISCOUNT      折扣
             * FIELD_NAME_TYPE_ACHIEVEMEN  成就
             * FIELD_NAME_TYPE_MILEAGE          里程
             * FIELD_NAME_TYPE_SET_POINTS     集点
             * FIELD_NAME_TYPE_TIMS                次数             *
             */

            $custom_field1=$params['custom_field1']; //自定义会员信息类目1
            if(!empty($custom_field1))
            {
                $custom_field_name_type1=$params['custom_field_name_type1'];
                $custom_field_url1=$params['custom_field_url1'];
            }

            $custom_field2 = $params['custom_field2'];  //自定义会员信息类目2
            if(!empty($custom_field2)) {
                $custom_field_name_type2 = $params['custom_field_name_type2'];
                $custom_field_url2 = $params['custom_field_url2'];
            }

            $custom_field3 = $params['custom_field3'];  //自定义会员信息类目3
            if(!empty($custom_field3)) {
                $custom_field_name_type3 = $params['custom_field_name_type3'];
                $custom_field_url3 = $params['custom_field_url3'];
            }

            $custom_cell1 = $params['custom_cell1'];
            if(!empty($custom_cell1)) {
                $custom_cell1_name = $params['custom_cell1_name'];
                $custom_cell1_tips = $params['custom_cell1_tips'];
                $custom_cell1_url = $params['custom_cell1_url'];
            }

        }
        else
        {
            return false;
        }


        $jsonData="{";
        $jsonData.="\"card\":{";

        //卡券类型
        $jsonData.="\"card_type\":\"{$card_type}\"";
        //卡券类型信息
        if($card_type=="CASH") {
            $jsonData .= ",\"cash\":{";
        }elseif($card_type=="DISCOUNT")
        {
            $jsonData .= ",\"discount\":{";
        }
        elseif($card_type=="MEMBER_CARD")
        {
            $jsonData .= ",\"member_card\":{";
            if(!empty($background_pic_url))
            {
                $jsonData .= "\"background_pic_url\":\"{$background_pic_url}\",";
            }

        }

        //基础信息
        $jsonData.="\"base_info\":{";
        $jsonData.="\"logo_url\":\"{$logo_url}\"";
        $jsonData.=",\"brand_name\":\"{$brand_name}\"";
        $jsonData.=",\"code_type\":\"{$code_type}\"";
        $jsonData.=",\"title\":\"{$title}\"";
        $jsonData.=",\"color\":\"{$color}\"";
        $jsonData.=",\"notice\":\"{$notice}\"";

        //客服电话
        if(!empty($service_phone))
        {
            $jsonData.=",\"service_phone\":\"{$service_phone}\"";
        }

        $jsonData.=",\"description\":\"{$description}\"";


        //有效期信息
        $jsonData.=",\"date_info\":{";

        if($type=="DATE_TYPE_FIX_TIME_RANGE")
        {
            $jsonData.="\"type\":\"DATE_TYPE_FIX_TIME_RANGE\"";
            $jsonData.=",\"begin_timestamp\":{$begin_timestamp}";             //时间戳 //代金券,折扣券专用参数检查
            $jsonData.=",\"end_timestamp\":{$end_timestamp}";                   //时间戳
        }
        else if($type=="DATE_TYPE_FIX_TERM")
        {
            $jsonData.="\"type\":\"DATE_TYPE_FIX_TERM\"";
            $jsonData.=",\"fixed_term\":{$fixed_term}";                                    //数字
            $jsonData.=",\"fixed_begin_term\":{$fixed_begin_term}";             //数字
        }else if($type=="DATE_TYPE_PERMANENT")
        {
            $jsonData.="\"type\":\"DATE_TYPE_PERMANENT\"";
        }

        $jsonData.="}";//有效期信息结尾

        //存量
        $jsonData.=",\"sku\":{\"quantity\":{$quantity}}";
        $jsonData.=",\"use_limit\":{$use_limit}";
        $jsonData.=",\"get_limit\":{$get_limit}";
        //$jsonData.=",\"use_custom_code\":false";
        //$jsonData.=",\"bind_openid\":false";
        $jsonData.=",\"can_share\":{$can_share}";
        $jsonData.=",\"can_give_friend\":{$can_give_friend}";

        //入口设置
        $jsonData.=",\"center_title\":\"{$center_title}\"";
        $jsonData.=",\"center_sub_title\":\"{$center_sub_title}\"";
        $jsonData.=",\"center_url\":\"{$center_url}\"";


        if(!empty($setcustom)) {
            $jsonData .= ",\"custom_url_name\":\"{$custom_url_name}\"";
            $jsonData .= ",\"custom_url\":\"{$custom_url}\"";
            $jsonData .= ",\"custom_url_sub_title\":\"{$custom_url_sub_title}\"";
        }

        if(!empty($setpromotion))
        {
            $jsonData.=",\"promotion_url_name\":\"{$promotion_url_name}\"";
            $jsonData.=",\"promotion_url_sub_title\":\"{$promotion_url_sub_title}\"";
            $jsonData.=",\"promotion_url\":\"{$promotion_url}\"";
        }
        $jsonData.="}";//基础信息结尾

        //高级设置
        $jsonData.=",\"advanced_info\":{";


        //use_condition
        //使用限制
        $jsonData.="\"use_condition\":{";

        if(!empty($accept_category))
        {
            $jsonData.="\"accept_category\":\"{$accept_category}\",";
        }
        if(!empty($reject_category)) {
            $jsonData .= "\"reject_category\":\"{$reject_category}\",";
        }
        $jsonData.="\"can_use_with_other_discount\":{$can_use_with_other_discount}";

        $jsonData.="}";//使用限制结尾

        if(!empty($setabstract))
        {

            //封面摘要结构体
            $jsonData.=",\"abstract\":{";
            $jsonData.="\"abstract\":\"{$abstract}\"";
            $jsonData.=",\"icon_url_list\":[\"{$icon_url_list}\"]";

            if(is_array($text_image_list)&&!empty($text_image_list)){
                $jsonData.=",\"text_image_list\":[";

                $listnum =0;
                foreach( $text_image_list as $text_image)
                {
                    if($listnum>0)
                    {
                        $jsonData.=",";
                    }
                    $jsonData.="{";
                    $jsonData.="\"image_url\":\"{$text_image['image_url']}\"";
                    $jsonData.=",\"text\":\"{$text_image['text']}\"";
                    $jsonData.="}";
                    $listnum++;
                }
                $jsonData.="]";
            }
            $jsonData.="}";//封面摘要结构体结尾
        }

        if(!empty($business_service))
        {
            $jsonData.=",\"business_service\":[";
            $jsonData.=$business_service;
            $jsonData.="]";
        }
        $jsonData.="}";//高级设置结尾

        //各卡券类型专有参数

        if($card_type=="CASH")
        {
            //代金券类型专用参数
            $jsonData.=",\"least_cost\":\"{$least_cost}\"";
            $jsonData.=",\"reduce_cost\":\"{$reduce_cost}\"";
        }
        elseif($card_type=="DISCOUNT")
        {
            //折扣券类型专用参数
            $jsonData.=",\"discount\":\"{$discount}\"";
        }
        elseif($card_type=="MEMBER_CARD")
        {
            //会员卡类型专用参数
            $jsonData.=",\"supply_bonus\":{$supply_bonus}";  //积分
            if(!empty($bonus_url))
            {
                $jsonData.=",\"bonus_url\":\"{$bonus_url}\"";  //积分跳转
            }
            $jsonData.=",\"supply_balance\":{$supply_balance}";  //储值

            if(!empty($balance_url))
            {
                $jsonData.=",\"balance_url\":\"{$balance_url}\"";  //余额跳转
            }

            $jsonData.=",\"prerogative\":\"{$prerogative}\"";  //会员卡特权说明
            $jsonData.=",\"auto_activate\":{$auto_activate}";  //激活会员卡的url。

            if(!empty($custom_field1))
            {
                $jsonData.=",\"custom_field1\":{";

                $jsonData.="\"name_type\":\"{$custom_field_name_type1}\"";
                if(!empty($custom_field_url1))
                {
                    $jsonData.=",\"url\":\"{$custom_field_url1}\"";
                }
                $jsonData.="}";
            }

            if(!empty($custom_field2))
            {
                $jsonData.=",\"custom_field2\":{";

                $jsonData.="\"name_type\":\"{$custom_field_name_type2}\"";
                if(!empty($custom_field_url2)) {
                    $jsonData .= ",\"url\":\"{$custom_field_url2}\"";
                }
                $jsonData.="}";
            }

            if(!empty($custom_field3))
            {
                $jsonData.=",\"custom_field3\":{";

                $jsonData.="\"name_type\":\"{$custom_field_name_type3}\"";
                if(!empty($custom_field_url3)) {
                    $jsonData .= ",\"url\":\"{$custom_field_url3}\"";
                }
                $jsonData.="}";
            }

            if(!empty($activate_url))
            {
                $jsonData.=",\"activate_url\":\"{$activate_url}\"";
            }

            if(!empty($custom_cell1))
            {
                $jsonData.=",\"custom_cell1\":{";

                $jsonData.="\"name\":\"{$custom_cell1_name}\"";
                $jsonData.=",\"tips\":\"{$custom_cell1_tips}\"";
                $jsonData.=",\"url\":\"{$custom_cell1_url}\"";

                $jsonData.="}";
            }
        }
        $jsonData.="}";//卡券类型信息结尾
        $jsonData.="}";
        $jsonData.="}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/create?access_token=" . $token;
        $result = $this->wxHttpsRequest($url,$jsonData);


        return $result;

    }


    //更新微信卡券json拼写
    public function updateCard($params) {

        $card_id=$params['card_id'];
        $card_type=$params['card_type'];                                                          //*卡券类型

        //***base_info 基础信息
        $logo_url=$params['wxlogourl'];                                                            //*卡券的商户logoURL  需要先上传

        $color=$params['color'];                                                                         //*券颜色。按色彩规范标注填写Color010-Color100。
        $notice=$params['notice'];                                                                     //*卡券使用提醒，字数上限为16个汉字。
        $service_phone=$params['service_phone'];                                          //客服电话。
        $description=$params['description'];                                                    //*卡券使用说明，字数上限为1024个汉字。

        //date_info
        $type=$params['datetype'];                                                                   //*参数分为:DATE_TYPE_FIX_TIME_RANGE(固定时间段)       DATE_TYPE_FIX_TERM(可用时长)
        if($type=="DATE_TYPE_FIX_TIME_RANGE") {
            $begin_timestamp = $params['begin_timestamp'];                         //*固定时间段开始时间
            $end_timestamp = $params['end_timestamp'];                               //*固定时间段结束时间
        }

        $use_limit=empty($params['use_limit'])?1:$params['use_limit'];       //*每人可核销的数量限制,不填写默认为50。
        $get_limit=empty($params['get_limit'])?1:$params['get_limit'];        //*每人可领券的数量限制,不填写默认为50。


        $can_share=empty($params['can_share'])?"false":"true";                               //卡券领取页面是否可分享。
        $can_give_friend=empty($params['can_give_friend'])?"false":"true";  //卡券是否可转赠。


        $center_title=$params['center_title'];                                                     //立即使用按钮文字
        $center_sub_title=$params['center_sub_title'];                                     //立即使用提示文字
        $center_url=$params['center_url'];                                                         //立即使用跳转链接

        $setcustom=$params['setcustom'];                                                        //判断是否设置自定义入口(非微信接口参数)
        if(!empty($setcustom)) {
            $custom_url_name = $params['custom_url_name'];                                //自定义入口按钮文字
            $custom_url_sub_title = $params['custom_url_sub_title'];                      //自定义入口提示文字
            $custom_url = $params['custom_url'];                                                     //自定义入口跳转链接
        }

        $setpromotion = $params['setpromotion'];                                        //判断是否设置营销场景入口(非微信接口参数)
        if(!empty($setpromotion)) {
            $promotion_url_name = $params['promotion_url_name'];           //营销场景入口按钮文字
            $promotion_url_sub_title = $params['promotion_url_sub_title']; //营销场景入口提示文字
            $promotion_url = $params['promotion_url'];                                 //营销场景入口跳转链接
        }


        if($card_type=="MEMBER_CARD")
        {
            $activate_url=$params['activate_url'];

            //会员卡类型专用参数
            $background_pic_url=$params['background_pic_url'];
            $prerogative=$params['prerogative'];                                    //会员卡特权说明

            $supply_bonus=$params['supply_bonus'];                                      //积分
            //$supply_balance=$params['supply_balance'];                      //储值

            $bonus_url=$params['bonus_url'];        //积分跳转链接
            //$balance_url =$params['balance_url'];     //余额跳转链接


            $custom_field1=$params['custom_field1']; //自定义会员信息类目1
            if(!empty($custom_field1))
            {
                $custom_field_name_type1=$params['custom_field_name_type1'];
                $custom_field_url1=$params['custom_field_url1'];
            }

            $custom_field2 = $params['custom_field2'];  //自定义会员信息类目2
            if(!empty($custom_field2)) {
                $custom_field_name_type2 = $params['custom_field_name_type2'];
                $custom_field_url2 = $params['custom_field_url2'];
            }

            $custom_field3 = $params['custom_field3'];  //自定义会员信息类目3
            if(!empty($custom_field3)) {
                $custom_field_name_type3 = $params['custom_field_name_type3'];
                $custom_field_url3 = $params['custom_field_url3'];
            }

            $custom_cell1 = $params['custom_cell1'];
            if(!empty($custom_cell1)) {
                $custom_cell1_name = $params['custom_cell1_name'];
                $custom_cell1_tips = $params['custom_cell1_tips'];
                $custom_cell1_url = $params['custom_cell1_url'];
            }

        }


        $jsonData="{";

        //卡券类型
        $jsonData.="\"card_id\":\"{$card_id}\"";
        //卡券类型信息
        if($card_type=="CASH") {
            $jsonData .= ",\"cash\":{";
        }elseif($card_type=="DISCOUNT")
        {
            $jsonData .= ",\"discount\":{";
        }elseif($card_type=="MEMBER_CARD")
        {
            $jsonData .= ",\"member_card\":{";
            $jsonData .= "\"background_pic_url\":\"{$background_pic_url}\",";
        }

        //基础信息
        $jsonData.="\"base_info\":{";
        if(!empty($logo_url))
        {
            $jsonData.="\"logo_url\":\"{$logo_url}\",";
        }
        if(!empty($color))
        {
            $jsonData.="\"color\":\"{$color}\",";
        }

        $jsonData.="\"notice\":\"{$notice}\"";

        //客服电话
        if(!empty($service_phone))
        {
            $jsonData.=",\"service_phone\":\"{$service_phone}\"";
        }

        $jsonData.=",\"description\":\"{$description}\"";


        //有效期信息修改,仅允许修改类型为DATE_TYPE_FIX_TIME_RANGE的有效期
        if($type=="DATE_TYPE_FIX_TIME_RANGE")
        {
            $jsonData.=",\"date_info\":{";
            $jsonData.="\"type\":\"DATE_TYPE_FIX_TIME_RANGE\"";
            $jsonData.=",\"begin_timestamp\":{$begin_timestamp}";             //时间戳 //代金券,折扣券专用参数检查
            $jsonData.=",\"end_timestamp\":{$end_timestamp}";                   //时间戳
            $jsonData.="}";//有效期信息结尾
        }

        $jsonData.=",\"use_limit\":{$use_limit}";
        $jsonData.=",\"get_limit\":{$get_limit}";
        //$jsonData.=",\"use_custom_code\":false";
        //$jsonData.=",\"bind_openid\":false";
        $jsonData.=",\"can_share\":{$can_share}";
        $jsonData.=",\"can_give_friend\":{$can_give_friend}";

        //入口设置
        $jsonData.=",\"center_title\":\"{$center_title}\"";
        $jsonData.=",\"center_sub_title\":\"{$center_sub_title}\"";
        $jsonData.=",\"center_url\":\"{$center_url}\"";

        if(!empty($setcustom)) {
            $jsonData .= ",\"custom_url_name\":\"{$custom_url_name}\"";
            $jsonData .= ",\"custom_url\":\"{$custom_url}\"";
            $jsonData .= ",\"custom_url_sub_title\":\"{$custom_url_sub_title}\"";
        }

        if(!empty($setpromotion))
        {
            $jsonData.=",\"promotion_url_name\":\"{$promotion_url_name}\"";
            $jsonData.=",\"promotion_url_sub_title\":\"{$promotion_url_sub_title}\"";
            $jsonData.=",\"promotion_url\":\"{$promotion_url}\"";
        }
        $jsonData.="}";//基础信息结尾

        //会员卡类型专用参数
        if($card_type=="MEMBER_CARD")
        {
            $jsonData.=",\"prerogative\":\"{$prerogative}\"";  //会员卡特权说明

            //积分开启后不能关闭
            //会员卡类型专用参数
            //$jsonData.=",\"supply_bonus\":{$supply_bonus}";  //积分

            if(!empty($bonus_url))
            {
                $jsonData.=",\"bonus_url\":\"{$bonus_url}\"";  //积分跳转
            }

            if(!empty($balance_url))
            {
                $jsonData.=",\"balance_url\":\"{$balance_url}\"";  //余额跳转
            }

            if(!empty($custom_field1))
            {
                $jsonData.=",\"custom_field1\":{";

                $jsonData.="\"name_type\":\"{$custom_field_name_type1}\"";
                if(!empty($custom_field_url1))
                {
                    $jsonData.=",\"url\":\"{$custom_field_url1}\"";
                }
                $jsonData.="}";
            }

            if(!empty($custom_field2))
            {
                $jsonData.=",\"custom_field2\":{";

                $jsonData.="\"name_type\":\"{$custom_field_name_type2}\"";
                if(!empty($custom_field_url2)) {
                    $jsonData .= ",\"url\":\"{$custom_field_url2}\"";
                }
                $jsonData.="}";
            }

            if(!empty($custom_field3))
            {
                $jsonData.=",\"custom_field3\":{";

                $jsonData.="\"name_type\":\"{$custom_field_name_type3}\"";
                if(!empty($custom_field_url3)) {
                    $jsonData .= ",\"url\":\"{$custom_field_url3}\"";
                }
                $jsonData.="}";
            }

            if(!empty($activate_url))
            {
                $jsonData.=",\"activate_url\":\"{$activate_url}\"";
            }

            if(!empty($custom_cell1))
            {
                $jsonData.=",\"custom_cell1\":{";

                $jsonData.="\"name\":\"{$custom_cell1_name}\"";
                $jsonData.=",\"tips\":\"{$custom_cell1_tips}\"";
                $jsonData.=",\"url\":\"{$custom_cell1_url}\"";

                $jsonData.="}";
            }
        }

        $jsonData.="}";//卡券类型信息结尾
        $jsonData.="}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/update?access_token=" . $token;
        $result = $this->wxHttpsRequest($url,$jsonData);

        return $result;

    }


    /**
     * 微信卡券：批量查询平台已创建的卡券ID列表
     */
    public function wxCardGetCardidList($offset=0,$count=10,$status_list=null) {

        $jsonData = "{\"offset\":\"" . $offset ."\",\"count\":\"" . $count ."\"";
        if(!empty($status_list))
        {
            $jsonData .=",\"$status_list\":[\"" . $status_list ."\"]";
        }
        $jsonData .="}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/batchget?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }

    /**
     * 微信卡券：更新卡券库存数量
     */
    public function wxCardUpdateQuantity($card_id) {

        global $_W, $_GPC;

        $id = intval($card_id);
        if(!empty($id)){
            //平台户优惠券
            $sql ="select id,uniacid, card_id from " . tablename('ewei_shop_wxcard') ;
            $sql.="  where uniacid=:uniacid and id=:id   limit 1";
            $wxcard = pdo_fetch($sql, array(":id"=>$id,":uniacid"=>$_W["uniacid"]));


            if(empty($wxcard)||empty($wxcard['card_id']))
            {
                return false;
            }
            $card_id = $wxcard['card_id'];
        }else
        {
            //平台户优惠券
            $sql ="select id,uniacid, card_id from " . tablename('ewei_shop_wxcard') ;
            $sql.="  where uniacid=:uniacid and card_id=:card_id   limit 1";
            $wxcard = pdo_fetch($sql, array(":card_id"=>$card_id,":uniacid"=>$_W["uniacid"]));


            if(empty($wxcard)||empty($wxcard['card_id']))
            {
                return false;
            }

            $card_id = $wxcard['card_id'];
            $id= $wxcard['id'];
        }



        $result =  $this->wxCardGetQuantity($card_id);

        if(empty($result))
        {
            return false;
        }

        $data = array(
            "quantity" =>intval($result['quantity']),
            "total_quantity" =>intval($result['total_quantity'])
        );

        pdo_update('ewei_shop_wxcard',$data,array('id' => $id));

        return true;
    }


    /**
     * 微信卡券：更新会员卡库存数量
     */
    public function wxmemberCardUpdateQuantity() {

        global $_W, $_GPC;

        $card = m('cache')->getArray('membercard', $_W['uniacid']);

        $result =  $this->wxCardGetQuantity($card['card_id']);

        if(empty($result))
        {
            return false;
        }

        $card['card_totalquantity'] = intval($result['total_quantity']);
        $card['card_quantity'] = intval($result['quantity']);

        m('common')->setSysset(array('membercard'=>$card));

        return true;
    }


    /**
     * 微信卡券：查看库存数量
     */
    public function wxCardGetQuantity($cardid) {

        $result =  $this->wxCardGetInfo($cardid);

        if(is_wxerror($result))
        {
            return null;
        }


        $card_type = $result['card']['card_type'];

        $quantitys =$result['card'][strtolower($card_type)]['base_info']['sku'];


        return $quantitys;
    }


    /**
     * 微信卡券：查询卡券详情
     */
    public function wxCardGetInfo($cardid) {

        $jsonData = "{\"card_id\":\"" . $cardid ."\"}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/get?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }


    /**
     * 微信卡券：修改库存
     */
    public function wxCardModifyStock($card_id,$num,$type){

        $jsonData="{";
        //卡券类型
        $jsonData.="\"card_id\":\"{$card_id}\"";

        if(empty($type))
        {
            $jsonData.=",\"increase_stock_value\":{$num}";
        }else
        {
            $jsonData.=",\"reduce_stock_value\":{$num}";
        }

        $jsonData.="}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/modifystock?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }


    /**
     * 微信卡券：消耗卡券
     */
    public function wxCardConsume($code,$card_id=""){

        $jsonData = "{\"code\":\"" . $code ."\"";
        if(!empty($card_id))
        {
            $jsonData .=",\"card_id\":\"" . $card_id ."\"";
        }
        $jsonData .="}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/code/consume?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }

    /**
     * 微信卡券：删除卡券
     */
    public function wxCardDelete($card_id){


        $jsonData = "{\"card_id\":\"" . $card_id ."\"}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/delete?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }

    /**
     * 微信卡券：获取用户已领取卡券接口
     */
    public function wxCardGetUserCardList($openid,$card_id=""){

        $jsonData = "{\"openid\":\"" . $openid ."\"";
        if(!empty($card_id))
        {
            $jsonData .=",\"card_id\":\"" . $card_id ."\"";
        }
        $jsonData .="}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/user/getcardlist?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }


    /**
     * 微信卡券：查询Code接口
     */
    public function wxCardGetCodeInfo($code,$card_id,$check_consume=true){

        $jsonData = "{\"card_id\":\"" . $card_id ."\"";
        $jsonData .=",\"code\":\"" . $code ."\"";
        if($check_consume===true)
        {
            $jsonData .=",\"check_consume\":true";
        }else{
            $jsonData .=",\"check_consume\":false";
        }
        $jsonData .="}";

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/code/get?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }


    /**
     * 微信卡券：Code解码接口
     */
    public function wxCardCodeDecrypt($encrypt_code){
        $jsonData = "{\"encrypt_code\":\"".$encrypt_code."\"}";

        $account = m('common')->getAccount();
        //$token = $account->fetch_token();


        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }

        $url = "https://api.weixin.qq.com/card/code/decrypt?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }



    //获取可用优惠券数量
    function consumeWxCardCount($openid,  $merch_array,$goods_array) {
        global $_W, $_GPC;

        $time = time();

        $param = array();
        $ref = $this->wxCardGetUserCardList($openid);
        if(is_wxerror($ref))
        {
            return 0;
        }

        $wxcard_list =$ref['card_list'];

        $card_idlist =array();
        foreach($wxcard_list as $card)
        {
            //$ref = $this->wxCardGetCodeInfo($card['code'],$card['card_id']);
            //if(!is_wxerror($ref))
            //{
            $card_idlist[]="'".$card["card_id"]."'";
            //}
        }

        $param[':uniacid'] = $_W['uniacid'];
        $card_id =  implode(",",$card_idlist);

        if(empty($card_id))
        {
            return 0;
        }

        //平台户优惠券
        $sql ="select id,uniacid, card_id,least_cost,reduce_cost,merchid,limitgoodtype,limitgoodcatetype,limitgoodcateids,limitgoodids  from " . tablename('ewei_shop_wxcard') ;
        $sql.="  where uniacid=:uniacid and merchid=0 and card_id in ({$card_id})  order by id desc";
        $cardlist = pdo_fetchall($sql, $param);

        if(empty($cardlist))
        {
            return 0;
        }

        $result=array();

        foreach($wxcard_list as $wxcard)
        {
            foreach($cardlist  as $card)
            {
                if($wxcard['card_id']==$card['card_id'])
                {
                    $card['code']=$wxcard['code'];

                    $result[]=$card;
                }
            }
        }


        $goodlist = array();
        //商品信息
        if (!empty($goods_array)) {
            foreach ($goods_array as $key => $value) {
                $goodparam[':uniacid'] = $_W['uniacid'];
                $goodparam[':id'] = $value['goodsid'];

                $sql = "select id,cates,marketprice,merchid  from " . tablename('ewei_shop_goods') ;
                $sql.=" where uniacid=:uniacid and id =:id order by id desc LIMIT 1 "; //类型+最低消费+示使用
                $good = pdo_fetch($sql, $goodparam);
                $good['saletotal']= $value['total'];
                $good['optionid']= $value['optionid'];

                if(!empty($good)){
                    $goodlist[] = $good;
                }
            }
        }

        $result = $this->checkwxcardlimit($result,$goodlist);

        return count($result);
    }


    //获取可用优惠券
    function getAvailableWxcards($type, $money = 0, $merch_array,$goods_array=array()){

        global $_W, $_GPC;

        $time = time();

        $param = array();
        $ref = $this->wxCardGetUserCardList($_W['openid']);
        if(is_wxerror($ref))
        {
            return array();
        }

        $wxcard_list =$ref['card_list'];

        $card_idlist =array();
        foreach($wxcard_list as $card)
        {
            //$ref = $this->wxCardGetCodeInfo($card['code'],$card['card_id']);
            //if(!is_wxerror($ref))
            //{
            $card_idlist[]="'".$card["card_id"]."'";
            //}
        }

        $param[':uniacid'] = $_W['uniacid'];
        $card_id =  implode(",",$card_idlist);

        if(empty($card_id))
        {
            return  array();
        }

        //平台户优惠券
        $sql ="select id,uniacid,card_type,logo_url,title, card_id,least_cost,reduce_cost,discount,merchid,limitgoodtype,limitgoodcatetype,limitgoodcateids,limitgoodids,datetype,end_timestamp,fixed_term  from " . tablename('ewei_shop_wxcard') ;
        $sql.="  where uniacid=:uniacid and merchid=0 and card_id in ({$card_id})  order by id desc";
        $cardlist = pdo_fetchall($sql, $param);


        if(empty($cardlist))
        {
            return array();
        }

        $result=array();

        foreach($wxcard_list as $wxcard)
        {
            foreach($cardlist  as $card)
            {
                if($wxcard['card_id']==$card['card_id'])
                {
                    $card['code']=$wxcard['code'];

                    $result[]=$card;
                }
            }
        }


        $goodlist = array();
        //商品信息
        if (!empty($goods_array)) {
            foreach ($goods_array as $key => $value) {
                $goodparam[':uniacid'] = $_W['uniacid'];
                $goodparam[':id'] = $value['goodsid'];

                $sql = "select id,cates,marketprice,merchid  from " . tablename('ewei_shop_goods') ;
                $sql.=" where uniacid=:uniacid and id =:id order by id desc LIMIT 1 "; //类型+最低消费+示使用
                $good = pdo_fetch($sql, $goodparam);
                $good['saletotal']= $value['total'];
                $good['optionid']= $value['optionid'];

                if(!empty($good)){
                    $goodlist[] = $good;
                }
            }
        }

        if($type==0) {
            $list = $this->checkwxcardlimit($result,$goodlist);
        }

        $list = set_medias($list, 'logo_url');

        if (!empty($list)) {
            foreach ($list as &$row) {
                $row['logo_url'] = tomedia($row['logo_url']);

                $row['timestr'] = "永久有效";
                if ($row['datetype'] == 'DATE_TYPE_FIX_TIME_RANGE')  {
                    $row['timestr'] = date('Y-m-d H:i', $row['end_timestamp']);
                }else if ($row['datetype'] == 'DATE_TYPE_FIX_TERM')
                {
                    $row['timestr'] = "自生效日后".$row['fixed_term'].'天有效';
                }
                if ($row['card_type'] == "CASH") {
                    $row['backstr'] = '立减';
                    $row['css'] = 'deduct';
                    $row['backmoney'] =  (float)$row['reduce_cost']/100;
                    $row['backpre'] = true;

                    if($row['reduce_cost']=='0')
                    {
                        $row['color']='org ';
                    }
                    else
                    {
                        $row['color']='blue';
                    }
                } else if ($row['card_type'] == "DISCOUNT") {
                    $row['backstr'] = '折';
                    $row['css'] = 'discount';


                    $discount = (float)(100 -intval($row['discount']))/10;

                    $row['backmoney'] =  $discount;
                    $row['color']='red ';
                }
            }
            unset($row);
        }


        return $list;
    }


    //根据商品列表判断优惠卷是否可用
    function checkwxcardlimit($list ,$goodlist)
    {
        global $_W;
        foreach($list as $key=> $row)
        {

            $pass = 0;
            $least_cost =0;

            if($row['limitgoodcatetype']==0&&$row['limitgoodtype']==0&&$row['least_cost']==0)
            {
                $pass = 1;
            }
            else
            {
                foreach($goodlist as $good)
                {
                    if($row['merchid']>0&&$good['merchid']>0&&$row['merchid']!=$good['merchid'])
                    {
                        continue;
                    }

                    $p=0;

                    //判断当前商品是否可以使用此优惠券;
                    $cates = explode(',',$good['cates']);
                    $limitcateids =explode(',',$row['limitgoodcateids']);
                    $limitgoodids =explode(',',$row['limitgoodids']);

                    if($row['limitgoodcatetype']==0&&$row['limitgoodtype']==0)
                    {
                        $p= 1;
                    }

                    if($row['limitgoodcatetype']==1)
                    {
                        $result = array_intersect($cates,$limitcateids);
                        if(count($result)>0)
                        {
                            $p= 1;
                        }
                    }

                    if($row['limitgoodtype']==1)
                    {
                        $isin = in_array($good['id'],$limitgoodids);
                        if($isin){
                            $p= 1;
                        }
                    }

                    //判断当前优惠券是否有可以生效的商品;
                    if($p==1)
                    {
                        $pass=1;
                    }

                    //判断优惠券是否满足最低使用消费额度
                    if($row['least_cost']>0&&$p==1)
                    {
                        if($good['optionid']>0)
                        {
                            $optionparam[':uniacid'] = $_W['uniacid'];
                            $optionparam[':id'] = $good['optionid'];
                            $sql = "select  marketprice  from " . tablename('ewei_shop_goods_option') ;
                            $sql.=" where uniacid=:uniacid and id =:id order by id desc LIMIT 1 "; //类型+最低消费+示使用
                            $option = pdo_fetch($sql, $optionparam);

                            if(!empty($option)){
                                $least_cost += ((float)$option['marketprice'])*$good['saletotal'];
                            }

                        }else
                        {
                            $least_cost+= ((float)$good['marketprice'])*$good['saletotal'];
                        }
                    }
                }


                //如果不满足最低使用额度则移除此优惠券
                if($row['least_cost']>0&& $row['least_cost']>$least_cost*100)
                {
                    $pass = 0;
                }
            }

            //如果不满足使用添加则移除此优惠券

            if($pass == 0)
            {
                unset($list[$key]);
            }
        }


        return array_values($list);

    }


    /**
     * 微信卡券：获取卡券领取二维码链接
     */
    public function wxCardGetQrcodeUrl($cardid) {

        $jsonData = "{\"action_name\":\"QR_CARD\"";

        $jsonData .=",\"action_info\":{";
        $jsonData .="\"card\":{";
        $jsonData .= "\"card_id\":\"".$cardid."\"";

        $jsonData .="}}}";


        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/qrcode/create?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);
        return $jsoninfo;
    }

    //根据Openid激活会员卡

    public function ActivateMembercardbyopenid($openid)
    {
        global $_W;

        $sql="select *  from " . tablename('ewei_shop_member') . " where  openid=:openid  and uniacid=:uniacid  limit 1";

        $member = pdo_fetch($sql, array(":openid"=>$openid,":uniacid"=>$_W['uniacid']));

        if(empty($member))
        {
            return false;
        }

        if(empty($member['membercardid'])||empty($member['membercardcode']))
        {
            //return false;
        }

        $credit1 = m('member')->getCredit($openid, 'credit1');
        $credit2 = m('member')->getCredit($openid, 'credit2');

        $params =array();

        $params['code']=$member['membercardcode'];
        $params['card_id']=$member['membercardid'];
        $params['membership_number']=$member['membershipnumber'];

        $params['init_bonus']=$credit1;
        $params['init_bonus_record']="会员卡激活积分同步";
        $params['init_balance']=$credit2*100;


        if(empty($member['level'])){
            $shop = $_W['shopset']['shop'];

            $level = empty($shop['levelname'])?'普通会员':$shop['levelname'];
        }else {
            $level = pdo_fetchcolumn('select levelname from ' . tablename('ewei_shop_member_level') . ' where id=:id limit 1', array(':id' => $member['level']));
        }

        if(mb_strlen($level,'UTF-8')>4)
        {
            $level = mb_substr($level,0,4,'UTF-8');
        }

        $params['init_custom_field_value1']=$level;

        return $this->wxMembercardActivate($params);
    }

    //激活核销会员卡

    public function ActivateVerifygoodCard($id,$card_id,$code,$openid)
    {
        global $_W;

        $sql='select vg.*,c.card_id  from ' . tablename('ewei_shop_verifygoods') . '   vg
	 inner join ' . tablename('ewei_shop_order_goods') . ' og on vg.ordergoodsid = og.id
	 inner join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id
	 inner  join ' . tablename('ewei_shop_goods_cards') . ' c on c.id = g.cardid
	 where   vg.uniacid=:uniacid and vg.openid=:openid and vg.invalid =0
	 and  ((vg.limittype=0   and vg.limitdays * 86400 + vg.starttime >=unix_timestamp() )or ( vg.limittype=1   and vg.limitdate >=unix_timestamp() ))  and  vg.used =0 and (vg.activecard=0 or vg.activecard is null) and g.cardid>0 and vg.id=:id ';

        $verifygoods = pdo_fetch($sql, array(':uniacid' =>  $_W['uniacid'],':openid' => $openid,':id'=>$id));


        $verifygoodlogs = pdo_fetchall('select *  from ' . tablename('ewei_shop_verifygoods_log') . '    where verifygoodsid =:id  ', array(':id' => $id));

        $verifynum = 0;

        foreach($verifygoodlogs as $verifygoodlog)
        {
            $verifynum +=intval($verifygoodlog['verifynum']);
        }

        if(empty($verifygoods['limitnum']))
        {
            $init_custom_field_value1 = "不限";
        }else
        {
            $num = intval($verifygoods['limitnum']) - $verifynum;
            $init_custom_field_value1 = $num."次";
        }

        $activate_begin_time = intval($verifygoods['starttime']) ;
        $activate_end_time = intval($verifygoods['starttime']) +  $verifygoods['limitdays']*86400;



        if(empty($verifygoods))
        {
            return false;
        }

        if($verifygoods['card_id']!=$card_id)
        {
            return false;
        }


        $params =array();
        $params['code']=$code;
        $params['card_id']=$card_id;
        $params['membership_number']=$code;
        $params['activate_begin_time']=$activate_begin_time;
        $params['activate_end_time']=$activate_end_time;


        if(mb_strlen($init_custom_field_value1,'UTF-8')>4)
        {
            $level = mb_substr($init_custom_field_value1,0,4,'UTF-8');
        }

        $params['init_custom_field_value1']=$init_custom_field_value1;

        $result = $this->wxMembercardActivate($params);

        if(is_wxerror($result))
        {
            return false;
        }
        else
        {
            pdo_update("ewei_shop_verifygoods",array('activecard'=>1,'cardcode'=>$code),array('id'=>$id));
            return true;
        }
    }

    /**
     * 微信会员卡：激活会员卡
     */
    public function wxMembercardActivate($params) {

        $code=$params['code'];
        $card_id=$params['card_id'];
        $membership_number=$params['membership_number'];
        $init_bonus=$params['init_bonus'];
        $init_bonus_record=$params['init_bonus_record'];
        $init_balance=$params['init_balance'];
        $background_pic_url=$params['background_pic_url'];
        $init_custom_field_value1=$params['init_custom_field_value1'];
        $init_custom_field_value2=$params['init_custom_field_value2'];
        $init_custom_field_value3=$params['init_custom_field_value3'];
        $activate_begin_time=$params['activate_begin_time'];
        $activate_end_time=$params['activate_end_time'];


        $jsonData = "{";
        $jsonData .= "\"code\":\"" . $code ."\"";
        $jsonData .= ",\"card_id\":\"" . $card_id ."\"";
        $jsonData .= ",\"membership_number\":\"" . $membership_number ."\"";

        if(!empty($init_bonus)){
            $jsonData .= ",\"init_bonus\":" . $init_bonus;
        }

        if(!empty($init_bonus_record)){
            $jsonData .= ",\"init_bonus_record\":\"" . $init_bonus_record ."\"";
        }
        if(!empty($init_balance)) {
            $jsonData .= ",\"init_balance\":" . $init_balance ;
        }
        if(!empty($background_pic_url)) {
            $jsonData .= ",\"background_pic_url\":\"" . $background_pic_url . "\"";
        }
        if(!empty($init_custom_field_value1)) {
            $jsonData .= ",\"init_custom_field_value1\":\"" . $init_custom_field_value1 . "\"";
        }
        if(!empty($init_custom_field_value2)) {
            $jsonData .= ",\"init_custom_field_value2\":\"" . $init_custom_field_value2 . "\"";
        }
        if(!empty($init_custom_field_value3)) {
            $jsonData .= ",\"init_custom_field_value3\":\"" . $init_custom_field_value3 . "\"";
        }
        if(!empty($activate_begin_time)) {
            $jsonData .= ",\"activate_begin_time\":" . $activate_begin_time ;
        }
        if(!empty($activate_end_time)) {
            $jsonData .= ",\"activate_end_time\":" . $activate_end_time ;
        }
        $jsonData .= "}";



        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/membercard/activate?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);

        return $jsoninfo;
    }

    //根据Openid更新用户会员卡
    public function updateMemberCardByOpenid($openid)
    {
        global $_W;

        $card = m('common')->getSysset("membercard");

        $sql="select *  from " . tablename('ewei_shop_member') . " where  openid=:openid  and uniacid=:uniacid  limit 1";

        $member = pdo_fetch($sql, array(":openid"=>$openid,":uniacid"=>$_W['uniacid']));

        if(empty($member))
        {
            return false;
        }

        if(empty($member['membercardid'])||empty($member['membercardcode']))
        {
            $upres = $this->updateMemberInfoWithWxcard($openid);
            if(!$upres) return false;
        }

        $credit1 = m('member')->getCredit($openid, 'credit1');
        $credit2 = m('member')->getCredit($openid, 'credit2');

        $params =array();

        $params['code']=$member['membercardcode'];
        $params['card_id']=$member['membercardid'];

        $params['bonus']=$credit1;
        //$params['record_bonus']="会员卡积分同步";

        if( !empty($card['card_supply_balance']))
        {
            $params['balance']=$credit2*100;
        }

        //$params['record_balance']="会员卡余额同步";


        if(empty($member['level'])){
            $shop = $_W['shopset']['shop'];
            $level = empty($shop['levelname'])?'普通会员':$shop['levelname'];
        }else {
            $level = pdo_fetchcolumn('select levelname from ' . tablename('ewei_shop_member_level') . ' where id=:id limit 1', array(':id' => $member['level']));
        }

        if(mb_strlen($level,'UTF-8')>4)
        {
            $level = mb_substr($level,0,4,'UTF-8');
        }

        $params['custom_field_value1']=$level;

        return $this->wxMembercardUpdateuser($params);
    }


    /**
     * 微信会员卡：更新会员卡信息
     */
    public function wxMembercardUpdateuser($params) {

        $code=$params['code'];
        $card_id=$params['card_id'];
        $bonus=$params['bonus'];                                                 //积分全量
        $add_bonus =$params['add_bonus'];                                //本次积分变动值，传负数代表减少
        $record_bonus =$params['record_bonus'];                       //商家自定义积分消耗记录，不超过14个汉字
        $balance=$params['balance'];                                            //需要设置的余额全量值
        $add_balance=$params['add_balance'];                            //本次余额变动值，传负数代表减少
        $record_balance =$params['record_balance'];                       //商家自定义金额消耗记录，不超过14个汉字。
        $background_pic_url=$params['background_pic_url'];
        $custom_field_value1=$params['custom_field_value1'];
        $custom_field_value2=$params['custom_field_value2'];
        $custom_field_value3=$params['custom_field_value3'];

        $jsonData = "{";
        $jsonData .= "\"code\":\"" . $code ."\"";
        $jsonData .= ",\"card_id\":\"" . $card_id ."\"";

        if(!empty($bonus)){
            $jsonData .= ",\"bonus\":" . $bonus;
        }
        if(!empty($add_bonus)){
            $jsonData .= ",\"add_bonus\":" . $add_bonus;
        }

        if(!empty($record_bonus)) {
            $jsonData .= ",\"record_bonus\":\"" . $record_bonus . "\"";
        }

        if(!empty($balance)){
            $jsonData .= ",\"balance\":" . $balance;
        }
        if(!empty($add_balance)){
            $jsonData .= ",\"add_balance\":" . $add_balance;
        }

        if(!empty($record_balance)) {
            $jsonData .= ",\"record_balance\":\"" . $record_balance . "\"";
        }

        if(!empty($background_pic_url)) {
            $jsonData .= ",\"background_pic_url\":\"" . $background_pic_url . "\"";
        }
        if(!empty($custom_field_value1)) {
            $jsonData .= ",\"custom_field_value1\":\"" . $custom_field_value1 . "\"";
        }
        if(!empty($custom_field_value2)) {
            $jsonData .= ",\"custom_field_value2\":\"" . $custom_field_value2 . "\"";
        }
        if(!empty($custom_field_value3)) {
            $jsonData .= ",\"custom_field_value3\":\"" . $custom_field_value3 . "\"";
        }
        $jsonData .= "}";
        $account = m('common')->getAccount();

        if(is_error($account)){
            return false;
        }
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url = "https://api.weixin.qq.com/card/membercard/updateuser?access_token=" . $token;
        $jsoninfo = $this->wxHttpsRequest($url,$jsonData);

        return $jsoninfo;
    }


    //更新核销卡信息
    public function updateusercardbyvarifygoodid($id)
    {
        global $_W;

        $sql='select vg.*,c.card_id,vg.cardcode  from ' . tablename('ewei_shop_verifygoods') . '   vg
	 inner join ' . tablename('ewei_shop_order_goods') . ' og on vg.ordergoodsid = og.id
	 inner join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id
	 inner  join ' . tablename('ewei_shop_goods_cards') . ' c on c.id = g.cardid
	 where   vg.uniacid=:uniacid  and g.cardid>0  and vg.id=:id ';

        $item = pdo_fetch($sql, array(':uniacid' =>  $_W['uniacid'],':id'=>$id));

        if(empty($item))
        {
            return false;
        }


        if(empty($item['card_id'])||empty($item['cardcode']))
        {
            return false;
        }

        if(!empty($item['invalid']))
        {
            $custom_field_value1 = "已失效";
        }else if(empty($item['limitnum']))
        {
            $custom_field_value1 = "不限";

        }else
        {
            $verifygoodlogs = pdo_fetchall('select *  from ' . tablename('ewei_shop_verifygoods_log') . '    where verifygoodsid =:id  ', array(':id' => $id));

            $verifynum = 0;

            foreach($verifygoodlogs as $verifygoodlog)
            {
                $verifynum +=intval($verifygoodlog['verifynum']);
            }


            $num = intval($item['limitnum']) - $verifynum;
            $custom_field_value1 = $num."次";
        }




        $params =array();
        $params['code']=$item['cardcode'];
        $params['card_id']=$item['card_id'];


        if(mb_strlen($custom_field_value1,'UTF-8')>4)
        {
            $level = mb_substr($custom_field_value1,0,4,'UTF-8');
        }

        $params['custom_field_value1']=$custom_field_value1;

        $result = $this->wxMembercardUpdateuser($params);


        if(is_wxerror($result))
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * 微信会员卡：记次时商品会员卡创建/编辑
     */
    public function verifygoodcard($carddata,$card_id="") {

        $params = array(
            "card_type"=>"MEMBER_CARD",
            "title"=>$carddata['card_title'],
            "background_pic_url"=>$carddata['card_backgroundwxurl'],
            "brand_name"=>$carddata['card_brand_name'],
            "quantity"=>$carddata['card_quantity'],
            "wxlogourl"=>$carddata['card_logowxurl'],
            "prerogative"=>$carddata['prerogative'],
            "color"=>$carddata['color'],
            "notice"=>"点击使用按钮并向服务员出示二维码",
            "can_give_friend"=>0,
            "description"=>$carddata['card_description'],
            "center_title"=>"立即使用",
            "center_url"=>mobileUrl('verifygoods/detail','',true),
            "center_sub_title"=>"点击使用",

            "BIZ_SERVICE_FREE_WIFI"=>intval($carddata['freewifi']),
            "BIZ_SERVICE_WITH_PET"=>intval($carddata['withpet']),
            "BIZ_SERVICE_FREE_PARK"=>intval($carddata['freepark']),
            "BIZ_SERVICE_DELIVER"=>intval($carddata['deliver']),
            "datetype"=>"DATE_TYPE_PERMANENT",
            "custom_cell1"=>$carddata['custom_cell1'],
            "custom_cell1_name"=>$carddata['custom_cell1_name'],
            "custom_cell1_tips"=>$carddata['custom_cell1_tips'],
            "custom_cell1_url"=>$carddata['custom_cell1_url'],
            "custom_field1"=>1,
            "custom_field_name_type1"=>"FIELD_NAME_TYPE_TIMS",
            "supply_bonus"=>"false",                    //显示积分
            "supply_balance"=>"false",                  //是否支持储值

            "auto_activate"=>"false",                   //自动激活
            "wx_activate"=>"false",                     //一键激活
            "activate_url"=>mobileUrl('verifygoods/activecard','',true)//激活地址
        ,"use_limit"=>1000
        ,"get_limit"=>1000
        );


        if(empty($card_id))
        {
            return $this->createCard($params);
        }else
        {
            $params['card_id'] = $card_id;
            return $this->updateCard($params);
        }

    }


    /**
     * 微信会员卡：会员信息卡创建/编辑
     */
    public function membercardmanager($carddata,$card_id="") {

        $params = array(
            "card_type"=>"MEMBER_CARD",
            "title"=>$carddata['card_title'],
            "brand_name"=>$carddata['card_brand_name'],
            "quantity"=>$carddata['card_quantity'],
            "wxlogourl"=>$carddata['card_logowxurl'],
            "prerogative"=>$carddata['prerogative'],
            "notice"=>"点击使用按钮并向服务员出示二维码",
            "can_give_friend"=>0,
            "description"=>$carddata['card_description'],

            "center_title"=>"商城首页",
            "center_url"=>mobileUrl('',null,true),
            "center_sub_title"=>"",

            "BIZ_SERVICE_FREE_WIFI"=>intval($carddata['freewifi']),
            "BIZ_SERVICE_WITH_PET"=>intval($carddata['withpet']),
            "BIZ_SERVICE_FREE_PARK"=>intval($carddata['freepark']),
            "BIZ_SERVICE_DELIVER"=>intval($carddata['deliver']),
            "datetype"=>"DATE_TYPE_PERMANENT",
            "custom_cell1"=>$carddata['custom_cell1'],
            "custom_cell1_name"=>$carddata['custom_cell1_name'],
            "custom_cell1_tips"=>$carddata['custom_cell1_tips'],
            "custom_cell1_url"=>$carddata['custom_cell1_url'],
            "custom_field1"=>1,

            "custom_field_name_type1"=>"FIELD_NAME_TYPE_LEVEL",
            "supply_bonus"=>"true",                    //显示积分
            "bonus_url"=>mobileUrl('member',null,true), //积分链接
            "supply_balance"=>empty($carddata['card_supply_balance'])?"false":"true",                  //是否支持储值
            "balance_url"=>mobileUrl('member',null,true), //余额链接

            "auto_activate"=>"false",                   //自动激活
            "wx_activate"=>"false",                     //一键激活
            "activate_url"=>mobileUrl('member/activation',null,true)//激活地址
        );

        if(empty($carddata['card_backgroundtype']))
        {
            $params["color"] = $carddata['color'];
        }else
        {
            $params["background_pic_url"] = $carddata['card_backgroundwxurl'];
        }

        if(empty($card_id))
        {
            return $this->createCard($params);
        }else
        {
            $params['card_id'] = $card_id;
            return $this->updateCard($params);
        }

    }


    /**
     * 微信会员卡：记次时商品会员卡判断是否需要修改
     */
    public function checkchange($carddata,$card) {

        if($carddata["card_brand_name"]==$card["card_brand_name"])
        {
            return true;
        }

        if($carddata["card_logoimg"]==$card["card_logoimg"])
        {
            return true;
        }

        if($carddata["card_backgroundimg"]==$card["card_backgroundimg"])
        {
            return true;
        }

        if($carddata["prerogative"]==$card["prerogative"])
        {
            return true;
        }

        if($carddata["description"]==$card["description"])
        {
            return true;
        }


        if($carddata["custom_cell1"]==$card["custom_cell1"])
        {
            return true;
        }

        if($carddata["custom_cell1_name"]==$card["custom_cell1_name"])
        {
            return true;
        }

        if($carddata["custom_cell1_tips"]==$card["custom_cell1_tips"])
        {
            return true;
        }

        if($carddata["custom_cell1_url"]==$card["custom_cell1_url"])
        {
            return true;
        }

        return false;
    }



    /**
     *    微信获取AccessToken 返回指定微信公众号的at信息
     */

    public function wxJsApiTicket(){
        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }
        $url          = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$token;
        $result         = $this->wxHttpsRequest($url);
        $jsoninfo         = @json_decode($result, true);
        $ticket = $jsoninfo['ticket'];
        return $ticket;
    }

    /**
     * 微信卡券：JSAPI 卡券Package
     */
    public function wxCardPackage($cardId , $openid = ''){
        $timestamp = time();
        $api_ticket = $this->wxJsApiTicket();
        $cardId = $cardId;
        $arrays = array($api_ticket,$timestamp,$cardId);
        sort($arrays);
        $string = sha1(implode("",$arrays));

        $resultArray['card_id'] = $cardId;
        $resultArray['card_ext'] = array();
        $resultArray['card_ext']['openid'] = $openid;
        $resultArray['card_ext']['timestamp'] = $timestamp;
        $resultArray['card_ext']['signature'] = $string;

        return $resultArray;
    }


    /**
     * 微信卡券：JSAPI 卡券全部卡券 Package
     */
    public function wxCardAllPackage($cardIdArray = array(),$openid = ''){
        $reArrays = array();
        if(!empty($cardIdArray) && (is_array($cardIdArray) || is_object($cardIdArray))){
            //print_r($cardIdArray);
            foreach($cardIdArray as $value){
                //print_r($this->wxCardPackage($value,$openid));
                $reArrays[] = $this->wxCardPackage($value,$openid);
            }
            //print_r($reArrays);
        }
        else{
            $reArrays[] = $this->wxCardPackage($cardIdArray,$openid);
        }
        return json_encode($reArrays);
    }


    /**
     *    微信提交API方法，返回微信指定JSON
     */

    public function wxHttpsRequest($url,$data = null){

        /*        $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
                if (!empty($data)){
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $output = curl_exec($curl);
                curl_close($curl);
                return $output;*/

        $result = ihttp_request($url,$data);

        return @json_decode($result['content'], true);

    }


    /**
     * 微信格式化数组变成参数格式 - 支持url加密
     */

    public function wxSetParam($parameters){
        if(is_array($parameters) && !empty($parameters)){
            $this->parameters = $parameters;
            return $this->parameters;
        }
        else{
            return array();
        }
    }

    /**
     * 微信格式化数组变成参数格式 - 支持url加密
     */

    public function wxFormatArray($parameters = NULL, $urlencode = FALSE){
        if(is_null($parameters)){
            $parameters = $this->parameters;
        }
        $restr = "";//初始化空
        ksort($parameters);//排序参数
        foreach ($parameters as $k => $v){//循环定制参数
            if (null != $v && "null" != $v && "sign" != $k) {
                if($urlencode){//如果参数需要增加URL加密就增加，不需要则不需要
                    $v = urlencode($v);
                }
                $restr .= $k . "=" . $v . "&";//返回完整字符串
            }
        }
        if (strlen($restr) > 0) {//如果存在数据则将最后“&”删除
            $restr = substr($restr, 0, strlen($restr)-1);
        }
        return $restr;//返回字符串
    }

    /**
     * 微信Sha1签名生成器 - 需要将参数数组转化成为字符串[wxFormatArray方法]
     */
    public function wxSha1Sign($content){
        try {
            if (is_null($content)) {
                throw new Exception("签名内容不能为空");
            }
            //$signStr = $content;
            return sha1($content);
        }
        catch (Exception $e)
        {
            die($e->getMessage());
        }
    }

    public function getWxTicket(){
        $cardTicket = m('cache')->getArray("wx_card_ticket");

        if(!empty($cardTicket) && !empty($cardTicket['ticket']) && $cardTicket['expire'] > TIMESTAMP) {
            return $cardTicket['ticket'];
        }

        $account = m('common')->getAccount();
        if(method_exists($account,'fetch_token')){
            $token = $account->fetch_token();
        }else{
            $token = $account->getAccessToken();
        }

        $cardTicket=array();
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=wx_card";
        $content = ihttp_get($url);
        if(is_error($content)) {
            return error(-1, '调用接口获取微信公众号 jsapi_ticket 失败, 错误信息: ' . $content['message']);
        }
        $result = @json_decode($content['content'], true);
        if(empty($result) || intval(($result['errcode'])) != 0 || $result['errmsg'] != 'ok') {
            return error(-1, '获取微信公众号 jsapi_ticket 结果错误, 错误信息: ' . $result['errmsg']);
        }
        $cardTicket['ticket'] = $result['ticket'];
        $cardTicket['expire'] = TIMESTAMP + $result['expires_in'] - 200;

        m('cache')->set("wx_card_ticket",$cardTicket);

        return $cardTicket['ticket'];
    }



    public function getsignature($card_id,$timestamp,$nonce_str,$openid,$code){
        global $_W;

        $jsapiTicket = $this->getWxTicket();
        $code       = $code;
        $arr        = array($jsapiTicket, $code, $timestamp, $nonce_str, $card_id, $openid);
        sort($arr, SORT_STRING);

        $signature  = sha1(implode($arr));

        return $signature;
    }

    //根据Card_ID 获取商品ID
    public function getgoodidbycardid($card_id)
    {
        global $_W;
        $sql='select g.id  from ' . tablename('ewei_shop_goods') . ' g
         inner join  ' . tablename('ewei_shop_goods_cards') . '  c on c.id = g.cardid  where g.uniacid=:uniacid and c.card_id=:card_id';


        return pdo_fetch($sql, array(':uniacid' =>  $_W['uniacid'],':card_id'=>$card_id));
    }

    //根据Openid更新用户会员数据,将会员卡信息同步至会员表
    public function updateMemberInfoWithWxcard($openid){
        global $_W,$_GPC;
        $card = m('common')->getSysset("membercard");
        if(empty($card) || empty($card['card_id'])) return false;
        if(empty($openid)) return false;

        $sql="select *  from " . tablename('ewei_shop_member') . " where  openid=:openid  and uniacid=:uniacid  limit 1";
        $member = pdo_fetch($sql, array(":openid"=>$openid,":uniacid"=>$_W['uniacid']));
        if(empty($member)) return false;

        $membercard = $this->wxCardGetUserCardList($openid,$card['card_id']);
        if($membercard['errcode']) return false;
        if(empty($membercard['card_list']) || empty($membercard['card_list'][0])) return false;
        if(!is_array($membercard['card_list'][0])) return false;

        $card_id = $membercard['card_list'][0]['card_id'];
        $code = $membercard['card_list'][0]['code'];
        if($card_id != $card['card_id']) return false;
        if(empty($code)) return false;
        $arr = array(
            'membercardid'=>$card_id,
            'membercardcode'=>$code,
            'membershipnumber'=>$code,
            'wxcardupdatetime'=>time(),
            'membercardactive'=>1
        );

        $result = pdo_update('ewei_shop_member', $arr, array('openid' => $openid, 'uniacid' => $_W['uniacid']));
        return $result;
    }


}

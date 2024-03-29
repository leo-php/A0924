<?php
set_time_limit(0);

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class TaobaoModel extends PluginModel
{

    private $num = 0;


    //获取一个淘宝宝贝 $itemid
    function get_item_taobao($itemid = '', $taobaourl = '', $cates = "", $merchid = 0)
    {
        global $_W;
        error_reporting(0);
        $g = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where uniacid=:uniacid and merchid=:merchid and catch_id=:catch_id and catch_source='taobao' limit 1", array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid, ":catch_id" => $itemid));
        $item = array();
        $item['id'] = $g['id'];
        $item['merchid'] = $merchid;
        if (!empty($merchid)) {
            if (empty($_W['merch_user']['goodschecked'])) {
                $item['checked'] = 1;
            } else {
                $item['checked'] = 0;
            }
        }
        $url = $this->get_tmall_page_url($itemid);
        load()->func('communication');
        $response = ihttp_get($url);

        $length = strval($response['headers']['Content-Length']);
        if ($length != null) {
            return array("result" => '0', "error" => '未从淘宝获取到商品信息!');
        }

        $content = $response['content'];

        if (function_exists('mb_convert_encoding')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
        }

        if (strexists($response['content'], "ERRCODE_QUERY_DETAIL_FAIL")) {
            return array("result" => '0', "error" => '宝贝不存在!');
        }

        $dom = new DOMDocument;
        $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . $content);
        $xml = simplexml_import_dom($dom);
        //获取详情url
        preg_match("/var g_config\s*=(.*);/isU", $content, $match);

        $matchOne = str_replace(array(" ", "\r", "\n", "\t"), array(''), $match[1]);
        $erdr = substr($matchOne, stripos($matchOne, 'sibUrl'));

        $erdr2 = substr($erdr, 0, stripos($erdr, 'descUrl'));
        $asd = explode(':', $erdr2);
        $two = substr($asd[1], 1);

        $threeUrl = substr($two, 0, -2);

        $detailskip = ihttp_request('https:' . $threeUrl, '', array(
                "referer" => "https://item.taobao.com?id=" . $itemid,
                "accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
                "accept-encoding" => '',
                "accept-language" => "zh-CN,zh;q=0.9,en;q=0.8",
                "user-agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36",
                "CURLOPT_USERAGENT" => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36'
            )
        );
        $detailskip = json_decode($detailskip['content'], true);

        $stockArray = array();
        if ($detailskip['code']['code'] == 0 && $detailskip['code']['message'] == 'SUCCESS') {
            $stockArray = $detailskip['data']['dynStock']['sku'];
        }

        //获取规格
        $specifications = $xml->xpath('//*[@id="J_isku"]/div/dl/dd/ul');

        $specificationsArray = array();
        $guigeArr = array();
        foreach ($specifications as $key => $specificationsInfo) {
            $sizeArray = (array)$specificationsInfo;
            $sizeAttributesArray = explode(':', $sizeArray["@attributes"]["data-property"]);
            $specificationsArray[$key]['title'] = $sizeAttributesArray[0];
            $sizeLiArray = $sizeArray['li'];
            if (!is_object($sizeLiArray)) {
                $specificationsArray[$key]['itemsCount'] = count($sizeLiArray);
                foreach ($sizeLiArray as $j => $sizeLiInfo) {
                    $sizeLiInfoArray = (array)$sizeLiInfo;
                    $guigeArr[$key][$j][] = ";" . $sizeLiInfoArray['@attributes']['data-value'];
                    $sizeLiInfoAttributesArray=explode(':', $sizeLiInfoArray['@attributes']['data-value']);
                    $specificationsArray[$key]['propId'] = $sizeLiInfoAttributesArray[0];

                    $specificationsArray[$key]['items'][$j]['valueId'] = $sizeLiInfoAttributesArray[1];
                    $sizeLiInfoA = (array)$sizeLiInfoArray['a'];
                    $specificationsTitle = (array)$sizeLiInfoA['span'];
                    $specificationsArray[$key]['items'][$j]['title'] = $specificationsTitle[0];
                    $guigeArr[$key][$j][] = $specificationsTitle[0];
                    $sizeLiInfoAttr = $sizeLiInfoA['@attributes'];
                    if (!empty($sizeLiInfoAttr['style'])) {
                        $sizeLiInfoAttrStyle = substr($sizeLiInfoAttr['style'], stripos($sizeLiInfoAttr['style'], '//'));
                        $sizeLiInfoAttrStyleUrl = substr($sizeLiInfoAttrStyle, 0, stripos($sizeLiInfoAttrStyle, ')'));
                        $thumb = mb_substr($sizeLiInfoAttrStyleUrl, 0, strpos($sizeLiInfoAttrStyleUrl, '_30x30.jpg'));
                        $specificationsArray[$key]['items'][$j]['thumb'] = 'http:'.$thumb;
                    } else {
                        $specificationsArray[$key]['items'][$j]['thumb'] = '';
                    }
                }
            } else {
                $objsctArr = (array)$sizeLiArray;
                $specificationsArray[$key]['itemsCount'] = 1;
                $objsctArrAttributes = explode(':', $objsctArr["@attributes"]["data-value"]);
                $specificationsArray[$key]['propId'] = $objsctArrAttributes[0];
                $specificationsArray[$key]['items'][0]['valueId'] = $objsctArrAttributes[1];
                $sizeLiInfoA = (array)$objsctArr['a'];
                $specificationsTitle = (array)$sizeLiInfoA['span'];
                $specificationsArray[$key]['items'][0]['title'] = $specificationsTitle[0];
                $guigeArr[$key][0][] = ";" . $objsctArr["@attributes"]["data-value"];
                $guigeArr[$key][0][] = $specificationsTitle[0];
                $sizeLiInfoAttr = $sizeLiInfoA['@attributes'];
                if (!empty($sizeLiInfoAttr['style'])) {
                    $sizeLiInfoAttrStyle = substr($sizeLiInfoAttr['style'], stripos($sizeLiInfoAttr['style'], '//'));
                    $sizeLiInfoAttrStyleUrl = substr($sizeLiInfoAttrStyle, 0, stripos($sizeLiInfoAttrStyle, ')'));
                    $thumb = mb_substr($sizeLiInfoAttrStyleUrl, 0, strpos($sizeLiInfoAttrStyleUrl, '_30x30.jpg'));
                    $specificationsArray[$key]['items'][0]['thumb'] = 'http:'.$thumb;
                } else {
                    $specificationsArray[$key]['items'][0]['thumb'] = '';
                }
            }
        }
        $item['specs'] = $this->my_sort($specificationsArray,'itemsCount',SORT_ASC,SORT_STRING);
//        $item['specs'] = $specificationsArray;
        $count = count($guigeArr);
        if ($count == 1) {
            for ($i = 0; $i < count($guigeArr[0]); $i++) {
                $value = $guigeArr[0][$i][0];
                $title = $guigeArr[0][$i][1];
                $arr[] = $value . ';|' . $title;
            }

        } elseif ($count == 2) {
            for ($i = 0; $i < count($guigeArr[0]); $i++) {
                $value = $guigeArr[0][$i][0];
                $title = $guigeArr[0][$i][1];
                for ($j = 0; $j < count($guigeArr[1]); $j++) {
                    $valueTwo = $value . $guigeArr[1][$j][0];
                    $titleTwo = $title . '+' . $guigeArr[1][$j][1];
                    $arr[] = $valueTwo . ';|' . $titleTwo;
                }
            }
        } elseif ($count == 3) {
            for ($i = 0; $i < count($guigeArr[0]); $i++) {
                $value = $guigeArr[0][$i][0];
                $title = $guigeArr[0][$i][1];
                for ($j = 0; $j < count($guigeArr[1]); $j++) {
                    $valueTwo = $value . $guigeArr[1][$j][0];
                    $titleTwo = $title . '+' . $guigeArr[1][$j][1];
                    for ($g = 0; $g < count($guigeArr[2]); $g++) {
                        $valueThree = $valueTwo . $guigeArr[2][$g][0];
                        $titleThree = $titleTwo . '+' . $guigeArr[2][$g][1];
                        $arr[] = $valueThree . ';|' . $titleThree;
                    }
                }
            }
        } elseif ($count == 4) {
            for ($i = 0; $i < count($guigeArr[0]); $i++) {
                $value = $guigeArr[0][$i][0];
                $title = $guigeArr[0][$i][1];
                for ($j = 0; $j < count($guigeArr[1]); $j++) {
                    $valueTwo = $value . $guigeArr[1][$j][0];
                    $titleTwo = $title . '+' . $guigeArr[1][$j][1];
                    for ($g = 0; $g < count($guigeArr[2]); $g++) {
                        $valueThree = $valueTwo . $guigeArr[2][$g][0];
                        $titleThree = $titleTwo . '+' . $guigeArr[2][$g][1];
                        for ($r = 0; $r < count($guigeArr[3]); $r++) {
                            $valueFour = $valueThree . $guigeArr[3][$r][0];
                            $titleFour = $titleThree . '+' . $guigeArr[3][$r][1];
                            $arr[] = $valueFour . ';|' . $titleFour;
                        }
                    }
                }
            }
        } elseif ($count == 5) {
            for ($i = 0; $i < count($guigeArr[0]); $i++) {
                $value = $guigeArr[0][$i][0];
                $title = $guigeArr[0][$i][1];
                for ($j = 0; $j < count($guigeArr[1]); $j++) {
                    $valueTwo = $value . $guigeArr[1][$j][0];
                    $titleTwo = $title . '+' . $guigeArr[1][$j][1];
                    for ($g = 0; $g < count($guigeArr[2]); $g++) {
                        $valueThree = $valueTwo . $guigeArr[2][$g][0];
                        $titleThree = $titleTwo . '+' . $guigeArr[2][$g][1];
                        for ($r = 0; $r < count($guigeArr[3]); $r++) {
                            $valueFour = $valueThree . $guigeArr[3][$g][0];
                            $titleFour = $titleThree . '+' . $guigeArr[3][$g][1];
                            for ($t = 0; $t < count($guigeArr[4]); $t++) {
                                $valueFive = $valueFour . $guigeArr[4][$t][0];
                                $titleFive = $titleFour . '+' . $guigeArr[4][$t][1];
                                $arr[] = $valueFive . ';|' . $titleFive;
                            }
                        }
                    }
                }
            }
        } elseif ($count == 6) {
            for ($i = 0; $i < count($guigeArr[0]); $i++) {
                $value = $guigeArr[0][$i][0];
                $title = $guigeArr[0][$i][1];
                for ($j = 0; $j < count($guigeArr[1]); $j++) {
                    $valueTwo = $value . $guigeArr[1][$j][0];
                    $titleTwo = $title . '+' . $guigeArr[1][$j][1];
                    for ($g = 0; $g < count($guigeArr[2]); $g++) {
                        $valueThree = $valueTwo . $guigeArr[2][$g][0];
                        $titleThree = $titleTwo . '+' . $guigeArr[2][$g][1];
                        for ($r = 0; $r < count($guigeArr[3]); $r++) {
                            $valueFour = $valueThree . $guigeArr[3][$g][0];
                            $titleFour = $titleThree . '+' . $guigeArr[3][$g][1];
                            for ($t = 0; $t < count($guigeArr[4]); $t++) {
                                $valueFive = $valueFour . $guigeArr[4][$t][0];
                                $titleFive = $titleFour . '+' . $guigeArr[4][$t][1];
                                for ($k = 0; $k < count($guigeArr[5]); $k++) {
                                    $valueSix = $valueFive . $guigeArr[5][$k][0];
                                    $titleSix = $titleFive . '+' . $guigeArr[5][$k][1];
                                    $arr[] = $valueSix . ';|' . $titleSix;
                                }
                            }
                        }
                    }
                }
            }
        }
        $item['options'] =array();
        $item['total'] = 0;
        foreach ($arr as $key => $asdInfo) {
            $asdInfoArrAs = explode("|", $asdInfo);
            $asdInfoArr = explode(";", $asdInfoArrAs[0]);
            $asdInfoArr = array_filter($asdInfoArr);
            $j = 0;
            foreach ($asdInfoArr as $asdInfoArrInfo) {
                $asdInfoArrInfoArr = explode(":", $asdInfoArrInfo);
                $item['options'][$key]['option_specs'][$j]['propId'] = $asdInfoArrInfoArr[0];
                $item['options'][$key]['option_specs'][$j]['valueId'] = $asdInfoArrInfoArr[1];
                $j++;
            }
            if (!empty($stockArray[$asdInfoArrAs[0]])) {
                $item['options'][$key]['stock'] = $stockArray[$asdInfoArrAs[0]]['stock'];
                $item['total'] = $item['total'] + $stockArray[$asdInfoArrAs[0]]['stock'];
            } else {
                $item['options'][$key]['stock'] = 0;
            }
            $item['options'][$key]['title'] = explode("+", $asdInfoArrAs[1]);
            $item['options'][$key]['marketprice'] = $detailskip['data']['price'];
        }
        //获取标题
        $prodectNameContent = $xml->xpath('//*[@id="J_Title"]');

        $titleArr = (array)$prodectNameContent[0];
        $item['title'] = trim(strval($titleArr['h3']));
        //获取副标题
        $prodectDescContent = $xml->xpath('//div/div/div/div/div/div/div/div/div/div/div[1]');
        $item['subTitle'] = trim(strval($prodectDescContent[1]->p));
        //获取价格
        $prodectPrice = $xml->xpath('//*[@id="J_StrPrice"]');
        $prodectPriceArr = (array)$prodectPrice[0];
        $taoBaoPrice = trim(strval($prodectPriceArr['em'][1]));
        $taoBaoPriceArr = explode('-', $taoBaoPrice);
        $item['productPrice'] = $taoBaoPriceArr[0];
        //获取图片
        $imgs = array();
        for ($i = 1; $i < 6; $i++) {
            $img = $xml->xpath('//*[@id="J_UlThumb"]/li[' . $i . ']');
            if (!empty($img)) {
                $img = strval($img[0]->div->a->img['data-src']);
                $img = mb_substr($img, 0, strpos($img, '_50x50.jpg'));
                $imgArr = explode(":",$img);
                if(count($imgArr)==2){
                    $img = 'http:'.$imgArr[1];
                }else{
                    $img = 'http:'.$imgArr[0];
                }
                $imgs[] = $img;
            }
        }

        $item['pics'] = $imgs;
        //获取参数
        $paramsContent = $xml->xpath('//*[@id="attributes"]');
//        if (empty($paramsContent)) {
//            $paramsContent1 = $xml->xpath('//div');
//            var_dump(1111);
//            die;
//            var_dump($xml);
//            die;
//        } else {
        $paramsContent = $paramsContent[0]->ul->li;
        $paramsContent = (array)$paramsContent;
        if (!empty($paramsContent['@attributes'])) {
            unset($paramsContent['@attributes']);
        }
        $params = array();
        foreach ($paramsContent as $paramitem) {
            $paramitem = strval($paramitem);
            if (!empty($paramitem)) {
                $paramitem = trim(str_replace('：', ':', $paramitem));
                $p1 = mb_strpos($paramitem, ':');
                $ptitle = mb_substr($paramitem, 0, $p1);
                $pvalue = mb_substr($paramitem, $p1 + 1, mb_strlen($paramitem));
                $param = array(
                    'title' => $ptitle,
                    'value' => $pvalue
                );
                $params[] = $param;
            }
        }
//        }
        $item['params'] = $params;
        //商品分类
        $pcates = array();
        $ccates = array();
        $tcates = array();
        $pcateid = 0;
        $ccateid = 0;
        $tcateid = 0;

        if (is_array($cates)) {

            foreach ($cates as $key => $cid) {

                $c = pdo_fetch('select level from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));

                if ($c['level'] == 1) { //一级
                    $pcates[] = $cid;
                } else if ($c['level'] == 2) {  //二级
                    $ccates[] = $cid;
                } else if ($c['level'] == 3) {  //三级
                    $tcates[] = $cid;
                }

                if ($key == 0) {
                    //兼容 1.x
                    if ($c['level'] == 1) { //一级
                        $pcateid = $cid;
                    } else if ($c['level'] == 2) {
                        $crow = pdo_fetch('select parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $crow['parentid'];
                        $ccateid = $cid;
                    } else if ($c['level'] == 3) {
                        $tcateid = $cid;
                        $tcate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $ccateid = $tcate['parentid'];
                        $ccate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $ccateid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $ccate['parentid'];
                    }
                }
            }
        }

        $item['pcate'] = $pcateid;
        $item['ccate'] = $ccateid;
        $item['tcate'] = $tcateid;
        if (!empty($cates)) {
            $item['cates'] = implode(',', $cates);
        }

        $item['pcates'] = implode(',', $pcates);
        $item['ccates'] = implode(',', $ccates);
        $item['tcates'] = implode(',', $tcates);

        //获取详情
        $url = $this->get_taobao_detail_url($itemid);
        load()->func('communication');
        $response = ihttp_get($url);
        $response = $this->contentpasswh($response);
        $item['content'] = $response;
        return $this->save_taobao_goods($item, $taobaourl);
    }

    //之前淘宝助手使用的方法，已弃用
    function get_item_taobao_old($itemid = '', $taobaourl = '', $cates = "", $merchid = 0)
    {

        global $_W;
        $g = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where uniacid=:uniacid and merchid=:merchid and catch_id=:catch_id and catch_source='taobao' limit 1", array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid, ":catch_id" => $itemid));


        $url = $this->get_tmall_page_url($itemid);
        load()->func('communication');

        $response = ihttp_get($url);
        $length = strval($response['headers']['Content-Length']);
        if ($length != null) {
            return array("result" => '0', "error" => '未从淘宝获取到商品信息!');
        }


        $content = $response['content'];
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');

        $dom = new DOMDocument;
        $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . $content);

        $xml = simplexml_import_dom($dom);
        $item = array();
        $item['id'] = $g['id'];
        $item['merchid'] = $merchid;
        if (!empty($merchid)) {
            if (empty($_W['merch_user']['goodschecked'])) {
                $item['checked'] = 1;
            } else {
                $item['checked'] = 0;
            }
        }

        //商品标题
        $prodectNameContent = $xml->xpath('//*[@id="J_DetailMeta"]/div[1]');
        $prodectName = trim(strval($prodectNameContent[0]->h1));

        if (empty($prodectName)) {
            $prodectName = trim(strval($prodectNameContent[0]->h1->a));
        }

        $item['title'] = $prodectName;

        //连接
        $url = $this->get_taobao_info_url($itemid);
        load()->func('communication');
        $response = ihttp_get($url);
        if (!isset($response['content'])) {
            return array("result" => '0', "error" => '未从淘宝获取到商品信息!');
        }
        $content = $response['content'];


        if (strexists($response['content'], "ERRCODE_QUERY_DETAIL_FAIL")) {
            return array("result" => '0', "error" => '宝贝不存在!');
        }

        $arr = json_decode($content, true);
        $data = $arr['data'];

        if (empty($data['apiStack'][0]['value'])) {
            if ($this->num >= 2) {
                return array("result" => '0', "error" => '规格库存详情不存在,请重新抓取');
            }
            $this->num += 1;
            return $this->get_item_taobao($itemid, $taobaourl, $cates, $merchid);
        }
        $itemInfoModel = $data['itemInfoModel'];
        $item = array();
        $item['id'] = $g['id'];
        $item['merchid'] = $merchid;
        if (!empty($merchid)) {
            if (empty($_W['merch_user']['goodschecked'])) {
                $item['checked'] = 1;
            } else {
                $item['checked'] = 0;
            }
        }

        $pcates = array();
        $ccates = array();
        $tcates = array();
        $pcateid = 0;
        $ccateid = 0;
        $tcateid = 0;

        if (is_array($cates)) {

            foreach ($cates as $key => $cid) {

                $c = pdo_fetch('select level from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));

                if ($c['level'] == 1) { //一级
                    $pcates[] = $cid;
                } else if ($c['level'] == 2) {  //二级
                    $ccates[] = $cid;
                } else if ($c['level'] == 3) {  //三级
                    $tcates[] = $cid;
                }

                if ($key == 0) {
                    //兼容 1.x
                    if ($c['level'] == 1) { //一级
                        $pcateid = $cid;
                    } else if ($c['level'] == 2) {
                        $crow = pdo_fetch('select parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $crow['parentid'];
                        $ccateid = $cid;

                    } else if ($c['level'] == 3) {
                        $tcateid = $cid;
                        $tcate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $ccateid = $tcate['parentid'];
                        $ccate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $ccateid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $ccate['parentid'];
                    }
                }
            }
        }

        $item['pcate'] = $pcateid;
        $item['ccate'] = $ccateid;
        $item['tcate'] = $tcateid;
        if (!empty($cates)) {
            $item['cates'] = implode(',', $cates);
        }

        $item['pcates'] = implode(',', $pcates);
        $item['ccates'] = implode(',', $ccates);
        $item['tcates'] = implode(',', $tcates);


        $item['itemId'] = $itemInfoModel['itemId'];
        $item['title'] = $itemInfoModel['title'];
        $item['pics'] = $itemInfoModel['picsPath'];
        $params = array();
        if (isset($data['props'])) {
            $props = $data['props'];
            foreach ($props as $pp) {
                $params[] = array(
                    "title" => $pp['name'],
                    "value" => $pp['value']
                );
            }
        }
        $item['params'] = $params;
        $specs = array();
        $options = array();
        if (isset($data['skuModel'])) {
            $skuModel = $data['skuModel'];
            if (isset($skuModel['skuProps'])) {
                $skuProps = $skuModel['skuProps'];
                foreach ($skuProps as $prop) {

                    $spec_items = array();
                    foreach ($prop['values'] as $spec_item) {
                        $spec_items[] = array(
                            'valueId' => $spec_item['valueId'],
                            'title' => $spec_item['name'],
                            "thumb" => $spec_item['imgUrl']
                        );
                    }
                    $spec = array(
                        "propId" => $prop['propId'],
                        "title" => $prop['propName'],
                        "items" => $spec_items
                    );
                    $specs[] = $spec;
                }
            }
            if (isset($skuModel['ppathIdmap'])) {
                $ppathIdmap = $skuModel['ppathIdmap'];
                foreach ($ppathIdmap as $key => $skuId) {

                    $option_specs = array();
                    $m = explode(";", $key);
                    foreach ($m as $v) {
                        $mm = explode(":", $v);
                        $option_specs[] = array(
                            "propId" => $mm[0],
                            "valueId" => $mm[1]
                        );
                    }
                    $options[] = array(
                        "option_specs" => $option_specs,
                        "skuId" => $skuId,
                        "stock" => 0,
                        "marketprice" => 0,
                        "specs" => ""
                    );
                }
            }
        }
        $item['specs'] = $specs;
        $stack = $data['apiStack'][0]['value'];
        $value = json_decode($stack, true);
        $item1 = array();
        $data1 = $value['data'];

        $itemInfoModel1 = $data1['itemInfoModel'];

        $item['total'] = $itemInfoModel1['quantity'];
        $item['sales'] = $itemInfoModel1['totalSoldQuantity'];

        if (isset($data1['skuModel'])) {
            $skuModel1 = $data1['skuModel'];

            if (isset($skuModel1['skus'])) {
                $skus = $skuModel1['skus'];

                foreach ($skus as $key => $val) {
                    $sku_id = $key;
                    foreach ($options as &$o) {
                        if ($o['skuId'] == $sku_id) {
                            $o['stock'] = $val['quantity'];
                            foreach ($val['priceUnits'] as $p) {
                                $o['marketprice'] = $p['price'];
                            }
                            $titles = array();

                            foreach ($o['option_specs'] as $osp) {
                                foreach ($specs as $sp) {
                                    if ($sp['propId'] == $osp['propId']) {
                                        foreach ($sp['items'] as $spitem) {

                                            if ($spitem['valueId'] == $osp['valueId']) {
                                                $titles[] = $spitem['title'];
                                            }
                                        }
                                    }
                                }
                            }
                            $o['title'] = $titles;
                        }
                    }
                    unset($o);
                }
            } else {
                $mprice = 0;
                foreach ($itemInfoModel1['priceUnits'] as $p) {
                    $mprice = $p['price'];
                }
                $item['marketprice'] = $mprice;
            }
        } else {
            $mprice = 0;
            foreach ($itemInfoModel1['priceUnits'] as $p) {
                $mprice = $p['price'];
            }
            $item['marketprice'] = $mprice;
        }
        $item['options'] = $options;
        $item['content'] = array();
        //详情
        $url = $this->get_taobao_detail_url($itemid);
        load()->func('communication');
        $response = ihttp_get($url);

        $response = $this->contentpasswh($response);

        $item['content'] = $response;

        return $this->save_taobao_goods($item, $taobaourl);
    }

    //获取一个天猫宝贝 $itemid
    function get_item_tmall_bypage($itemid = '', $taobaourl = '', $cates = "", $merchid = 0)
    {
        error_reporting(0);
        global $_W;
        $g = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where uniacid=:uniacid and merchid=:merchid and catch_id=:catch_id and catch_source='taobao' limit 1", array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid, ":catch_id" => $itemid));

        //连接
        $url = $this->get_tmall_page_url($itemid);
        load()->func('communication');
        $response = ihttp_get($url);
        $length = strval($response['headers']['Content-Length']);
        if ($length != null) {
            return array("result" => '0', "error" => '未从淘宝获取到商品信息!');
        }
        $content = $response['content'];
//        如果抓取出现乱码，请检查服务器是否安装了mbstring扩展
        if (function_exists('mb_convert_encoding')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
        }
        $item = array();
        $arr = array();
        preg_match('/TShop\.Setup\(([\s\S]*)\s+\);/',$content,$arr);
        $arr = json_decode(trim($arr[1]),true);
        $item['marketprice'] = $arr['detail']['defaultItemPrice'];

        $dom = new DOMDocument;
        $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . $content);

        $xml = simplexml_import_dom($dom);
        $item['id'] = $g['id'];
        $item['merchid'] = $merchid;
        if (!empty($merchid)) {
            if (empty($_W['merch_user']['goodschecked'])) {
                $item['checked'] = 1;
            } else {
                $item['checked'] = 0;
            }
        }

        //商品标题
        $prodectNameContent = $xml->xpath('//*[@id="J_DetailMeta"]/div[1]/div[1]/div/div[1]');
        $prodectName = trim(strval($prodectNameContent[0]->h1));

        if (empty($prodectName)) {
            $prodectName = trim(strval($prodectNameContent[0]->h1->a));
        }

        $item['title'] = $prodectName;
        $item['total'] = 10;
        //商品图片
        $imgs = array();

        for ($i = 1; $i < 6; $i++) {
            $img = $xml->xpath('//*[@id="J_UlThumb"]/li[' . $i . ']/a/img');
            if (!empty($img)) {
                $img = strval($img[0]->attributes()->src);
                $img = mb_substr($img, 0, strpos($img, '_60x60q90.jpg'));
                $img = 'http:' . $img;
                $imgs[] = $img;
            }
        }
        $item['pics'] = $imgs;


        //商品参数
        $paramsContent = $xml->xpath('//*[@id="J_AttrList"]');
        $paramsContent = $paramsContent[0]->ul->li;
        $paramsContent = (array)$paramsContent;

        if (!empty($paramsContent['@attributes'])) {
            unset($paramsContent['@attributes']);
        }

        $params = array();

        foreach ($paramsContent as $paramitem) {
            $paramitem = strval($paramitem);


            if (!empty($paramitem)) {
                $paramitem = trim(str_replace('：', ':', $paramitem));

                $p1 = mb_strpos($paramitem, ':');
                $ptitle = mb_substr($paramitem, 0, $p1);
                $pvalue = mb_substr($paramitem, $p1 + 1, mb_strlen($paramitem));

                $param = array(
                    'title' => $ptitle,
                    'value' => $pvalue
                );

                $params[] = $param;
            }
        }

        $item['params'] = $params;

        //商品分类
        $pcates = array();
        $ccates = array();
        $tcates = array();
        $pcateid = 0;
        $ccateid = 0;
        $tcateid = 0;

        if (is_array($cates)) {

            foreach ($cates as $key => $cid) {

                $c = pdo_fetch('select level from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));

                if ($c['level'] == 1) { //一级
                    $pcates[] = $cid;
                } else if ($c['level'] == 2) {  //二级
                    $ccates[] = $cid;
                } else if ($c['level'] == 3) {  //三级
                    $tcates[] = $cid;
                }

                if ($key == 0) {
                    //兼容 1.x
                    if ($c['level'] == 1) { //一级
                        $pcateid = $cid;
                    } else if ($c['level'] == 2) {
                        $crow = pdo_fetch('select parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $crow['parentid'];
                        $ccateid = $cid;
                    } else if ($c['level'] == 3) {
                        $tcateid = $cid;
                        $tcate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $ccateid = $tcate['parentid'];
                        $ccate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $ccateid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $ccate['parentid'];
                    }
                }
            }
        }

        $item['pcate'] = $pcateid;
        $item['ccate'] = $ccateid;
        $item['tcate'] = $tcateid;
        if (!empty($cates)) {
            $item['cates'] = implode(',', $cates);
        }

        $item['pcates'] = implode(',', $pcates);
        $item['ccates'] = implode(',', $ccates);
        $item['tcates'] = implode(',', $tcates);

        //详情
        $url = $this->get_tmall_detail_url($itemid);
        load()->func('communication');
        $response = ihttp_get($url);

        preg_match_all('/data\-ks\-lazyload="(http.+?)"/i',$response['content'],$matches);

        $item['content'] = $matches;

        return $this->save_tmall_goods($item, $taobaourl);
    }

    //获取一个京东宝贝 $itemid
    function get_item_jingdong($itemid = '', $jingdongurl = '', $cates = "", $merchid = 0)
    {
        error_reporting(0);
        global $_W;
        $g = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where uniacid=:uniacid and merchid=:merchid and catch_id=:catch_id and catch_source='jingdong' limit 1", array(':uniacid' => $_W['uniacid'], ":catch_id" => $itemid, ':merchid' => $merchid));

        $item = array();
        $item['id'] = $g['id'];
        $item['merchid'] = $merchid;
        if (!empty($merchid)) {
            if (empty($_W['merch_user']['goodschecked'])) {
                $item['checked'] = 1;
            } else {
                $item['checked'] = 0;
            }
        }
        $pcates = array();
        $ccates = array();
        $tcates = array();
        $pcateid = 0;
        $ccateid = 0;
        $tcateid = 0;

        if (is_array($cates)) {

            foreach ($cates as $key => $cid) {

                $c = pdo_fetch('select level from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));

                if ($c['level'] == 1) { //一级
                    $pcates[] = $cid;
                } else if ($c['level'] == 2) {  //二级
                    $ccates[] = $cid;
                } else if ($c['level'] == 3) {  //三级
                    $tcates[] = $cid;
                }

                if ($key == 0) {
                    //兼容 1.x
                    if ($c['level'] == 1) { //一级
                        $pcateid = $cid;
                    } else if ($c['level'] == 2) {
                        $crow = pdo_fetch('select parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $crow['parentid'];
                        $ccateid = $cid;

                    } else if ($c['level'] == 3) {
                        $tcateid = $cid;
                        $tcate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $ccateid = $tcate['parentid'];
                        $ccate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $ccateid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $ccate['parentid'];
                    }
                }
            }
        }
        $item['pcate'] = $pcateid;
        $item['ccate'] = $ccateid;
        $item['tcate'] = $tcateid;
        if (!empty($cates)) {
            $item['cates'] = implode(',', $cates);
        }
        $item['pcates'] = implode(',', $pcates);
        $item['ccates'] = implode(',', $ccates);
        $item['tcates'] = implode(',', $tcates);
        $item['itemId'] = $itemid;
        $item['total'] = 10;
        $item['sales'] = 0;
//        获取京东价格
        $priceurl = $this->get_jingdong_price_url($itemid);
        $responsePrice = ihttp_get($priceurl);
        $contentePrice = $responsePrice['content'];
        if(empty($contentePrice)){
            return array("result" => '0', "error" => '未从京东获取到商品信息!');
        }
        $price = json_decode($contentePrice, true);
        $item['marketprice'] = $price[0]['p'];
        //商品详情与参数
        $url = $this->get_jingdong_detail_url($itemid);
        $responseDetail = ihttp_get($url);
        $contenteDetail = $responseDetail['content'];
        $details = json_decode($contenteDetail, true);

        $item['title'] =  $details['ware']['wname'];
        //图片路径第一张位缩略图共四张
        $pics = array();
        $imgurls = $details['ware']['images'];

        foreach ($imgurls as $imgurl) {
            if (count($pics) < 4) {
                if (count($pics) == 0) {
                    $iurl = $imgurl['bigpath'];

                    if (stripos($iurl, "//") == 0) {
                        $iurl .= 'http:' . $iurl;
                    }
                    $pics[] = $iurl;

                } else {
                    $iurl = $imgurl['bigpath'];
                    if (stripos($iurl, "//") == 0) {
                        $iurl .= 'http:' . $iurl;
                    }

                    $pics[] = $iurl;
                }
            }
        }

        $item['pics'] = $pics;

        $specs = array();
        //详情
        $prodectContent = $details['wdis'];

        $prodectContent = strval($prodectContent);

        $prodectContent = $this->contentpasswh($prodectContent);

        $item['content'] = $prodectContent;

        //参数
        $params = array();
        $pr = $details['ware']['wi']['code'];
        $pr = json_decode($pr, 1);

        foreach ($pr as $value) {
            foreach ($value as $key => $val) {
                if (is_array($val)) {
                    $paramsValue = "";
                    foreach ($val as $v) {
                        foreach ($v as $k1 => $v1) {
                            if (!empty($v1)) {
                                $params[] = array(
                                    "title" => $k1,
                                    "value" => $v1
                                );
                            }
                        }
                    }

                } else {
                    if (!empty($val)) {
                        $params[] = array(
                            "title" => $key,
                            "value" => $val
                        );
                    }
                }
            }
        }
        $item['params'] = $params;
        return $this->save_jingdong_goods($item, $jingdongurl);
    }

    /**
     * 京东全球购抓取
     * @params 与京东助手相同
     * @author cunxin
     */
    function get_item_jdHK($itemid = '', $jingdongurl = '', $cates = "", $merchid = 0)
    {
        error_reporting(0);
        global $_W;
        $g = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where uniacid=:uniacid and merchid=:merchid and catch_id=:catch_id and catch_source='jingdong' limit 1", array(':uniacid' => $_W['uniacid'], ":catch_id" => $itemid, ':merchid' => $merchid));
        //商品链接
        $url = "http://item.jd.hk/{$itemid}.html";
        load()->func('communication');
        $response = ihttp_get($url);
        $length = strval($response['headers']['Content-Length']);
        if (empty($length)) {
            return array("result" => '0', "error" => '未从京东获取到商品信息!');
        }
        $content = iconv('GBK', 'UTF-8', $response['content']);
        //产品名称

//        preg_match('/<span id="globalJDRecommendTag"><\/span>\n(.+)<\/div>/',$content,$prodectName);
        preg_match('/<div class="sku-name">\n{1}[\s\S\n]*<\/span>\n(.+)<\/div>\n\s*<div class="news">/', $content, $prodectName);
        $prodectName = trim($prodectName[1]);//商品标题

        if ($prodectName == null) {
            return array("result" => '0', "error" => '宝贝不存在!');
        }

        $item = array();
        $item['id'] = $g['id'];
        $item['merchid'] = $merchid;
        if (!empty($merchid)) {
            if (empty($_W['merch_user']['goodschecked'])) {
                $item['checked'] = 1;
            } else {
                $item['checked'] = 0;
            }
        }
        $pcates = array();
        $ccates = array();
        $tcates = array();
        $pcateid = 0;
        $ccateid = 0;
        $tcateid = 0;

        if (is_array($cates)) {

            foreach ($cates as $key => $cid) {

                $c = pdo_fetch('select level from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));

                if ($c['level'] == 1) { //一级
                    $pcates[] = $cid;
                } else if ($c['level'] == 2) {  //二级
                    $ccates[] = $cid;
                } else if ($c['level'] == 3) {  //三级
                    $tcates[] = $cid;
                }

                if ($key == 0) {
                    //兼容 1.x
                    if ($c['level'] == 1) { //一级
                        $pcateid = $cid;
                    } else if ($c['level'] == 2) {
                        $crow = pdo_fetch('select parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $crow['parentid'];
                        $ccateid = $cid;

                    } else if ($c['level'] == 3) {
                        $tcateid = $cid;
                        $tcate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                        $ccateid = $tcate['parentid'];
                        $ccate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $ccateid, ':uniacid' => $_W['uniacid']));
                        $pcateid = $ccate['parentid'];
                    }
                }
            }
        }

        $item['pcate'] = $pcateid;
        $item['ccate'] = $ccateid;
        $item['tcate'] = $tcateid;
        if (!empty($cates)) {
            $item['cates'] = implode(',', $cates);
        }

        $item['pcates'] = implode(',', $pcates);
        $item['ccates'] = implode(',', $ccates);
        $item['tcates'] = implode(',', $tcates);


        $item['itemId'] = $itemid;
        $item['title'] = $prodectName;

        //图片路径第一张位缩略图共四张
        $pics = array();
        preg_match_all('/<img.+src=\'(.+)\' data-url.+data-img=\'1\' width=\'75\' height=\'75\'>/', $content, $picRet);


//        $imgurls=$xml->xpath('//*[contains(@class, \'lh\')]/ul');
        if (empty($picRet[1])) {
            return array("result" => '0', "error" => '不能抓取到图片');
        }
        foreach ($picRet[1] as $pic) {
            $pics[] = 'https:' . str_replace('s75x75', 's450x450', $pic);
        }
        $item['pics'] = $pics;

        $specs = array();


        $item['total'] = 10;
        $item['sales'] = 0;
        //产品价格
        $priceContent = ihttp_get('https://p.3.cn/prices/mgets?skuIds=J_' . $itemid);
        $prodectPrices = json_decode($priceContent['content'], 1);

        $item['marketprice'] = $prodectPrices[0]['p'];


        //商品详情与参数
        $url = $this->get_jingdong_detail_url($itemid);
        $responseDetail = ihttp_get($url);
        $contenteDetail = $responseDetail['content'];
        $details = json_decode($contenteDetail, true);
        //详情
        $prodectContent = $details['wdis'];

        $prodectContent = strval($prodectContent);

        $prodectContent = $this->contentpasswh($prodectContent);

        $item['content'] = $prodectContent;

        //参数
        $params = array();
        $pr = $details['ware']['wi']['code'];

        preg_match_all("/<td class=\"tdTitle\">(.*?)<\/td>/i", $pr, $params1);
        preg_match_all("/<td>(.*?)<\/td>/i", $pr, $params2);
        $paramsTitle = $params1[1];
        $paramsValue = $params2[1];

        if (count($paramsTitle) == count($paramsValue)) {
            for ($i = 0; $i < count($paramsTitle); $i++) {

                $params[] = array(
                    "title" => $paramsTitle[$i],
                    "value" => $paramsValue[$i]
                );
            }
        }
        $item['params'] = $params;
        return $this->save_jingdong_goods($item, $jingdongurl);
    }



    //保存淘宝商品
    function save_taobao_goods($item = array(), $catch_url = '')
    {

        global $_W;
        $data = array(
            "uniacid" => $_W['uniacid'],
            "subtitle" => $item['subTitle'],
            "merchid" => $item['merchid'],
            "checked" => $item['checked'],
            "catch_source" => 'taobao',
            "catch_id" => $item['itemId'],
            "catch_url" => $catch_url,
            "title" => $item['title'],
            "total" => $item['total'],
            "marketprice" => $item['marketprice'],
            "productprice" => $item['productPrice'],
            "pcate" => $item['pcate'],
            "ccate" => $item['ccate'],
            "tcate" => $item['tcate'],
            "cates" => $item['cates'],
            "sales" => $item['sales'],
            "createtime" => time(),
            "updatetime" => time(),
            'hasoption' => count($item['options']) > 0 ? 1 : 0,
            'status' => 0,
            'deleted' => 0,
            'buylevels' => '',
            'showlevels' => '',
            'buygroups' => '',
            'showgroups' => '',
            'noticeopenid' => '',
            'storeids' => '',
            'merchsale' => $item['merchid'] == 0 ? 0 : 1,
            'newgoods' => 1
        );
        if (empty($item['merchid'])) {
            $data['discounts'] = '{"type":"0","default":"","default_pay":""}';
        }
        //图片
        $thumb_url = array();
        $pics = $item['pics'];
        $piclen = count($pics);

        if ($piclen > 0) {
            $img = $this->save_image($pics[0], false);
            if (empty($img)) {
                $img = $pics[0];
            }
            $info =  getimagesize("../attachment/".$img);
            $srcFileExtImg=$info['mime'];
            if($srcFileExtImg=='image/x-ms-bmp') {
                $mig = $this->changeBMPtoJPG("../attachment/" . $img);
            }else{
                $mig =  $img;
            }
            $data['thumb'] = $mig;
            //其他图片
            if ($piclen > 1) {
                for ($i = 1; $i < $piclen; $i++) {

                    $img = $this->save_image($pics[$i], false);
                    if (empty($img)) {
                        $img = $pics[$i];
                    }
                    $thumb_url[] = $img;
                }
            }
        }
        $mi = array();

        foreach ($thumb_url as $thumb_Info){
            $info =  getimagesize("../attachment/".$thumb_Info);
            $srcFileExt=$info['mime'];
            if($srcFileExt=='image/x-ms-bmp') {
                $mi[] = $this->changeBMPtoJPG("../attachment/" . $thumb_Info);
            }else{
                $mi[] =    $thumb_Info;
            }
        }
//        var_dump($mi[0]);
//        var_dump($thumb_url[0]);
//        var_dump(getimagesize($mi[0]));
//        var_dump(getimagesize("../attachment/" .$thumb_url[0]));
//
//        die

        $data['thumb_url'] = serialize($mi);
        $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where  catch_id=:catch_id and catch_source='taobao' and uniacid=:uniacid and merchid=:merchid", array(":catch_id" => $item['itemId'], ":uniacid" => $_W['uniacid'], ":merchid" => $item['merchid']));
        if (empty($goods)) {

            pdo_insert("ewei_shop_goods", $data);
            $goodsid = pdo_insertid();
        } else {
            $goodsid = $goods['id'];

            unset($data['createtime']);
            pdo_update("ewei_shop_goods", $data, array("id" => $goodsid));
        }


        //参数
        $goods_params = pdo_fetchall("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        $params = $item['params'];
        $paramids = array();
        $displayorder = 0;
        foreach ($params as $p) {

            $oldp = pdo_fetch("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and title=:title limit 1", array(":goodsid" => $goodsid, ":title" => $p['title']));
            $paramid = 0;
            $d = array(
                "uniacid" => $_W['uniacid'],
                "goodsid" => $goodsid,
                "title" => $p['title'],
                "value" => $p['value'],
                "displayorder" => $displayorder
            );
            if (empty($oldp)) {
                pdo_insert("ewei_shop_goods_param", $d);
                $paramid = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_param", $d, array("id" => $oldp['id']));
                $paramid = $oldp['id'];
            }
            $paramids[] = $paramid;
            $displayorder++;
        }

        if (count($paramids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and id not in (" . implode(",", $paramids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }


        //规格

        $specs = $item['specs'];
        $specids = array();
        $displayorder = 0;
        $newspecs = array();
        foreach ($specs as $spec) {

            $oldspec = pdo_fetch("select * from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid and propId=:propId limit 1", array(":goodsid" => $goodsid, ":propId" => $spec['propId']));
            $specid = 0;
            $d_spec = array(
                "uniacid" => $_W['uniacid'],
                "goodsid" => $goodsid,
                "title" => $spec['title'],
                "displayorder" => $displayorder,
                "propId" => $spec['propId']
            );

            if (empty($oldspec)) {
                pdo_insert("ewei_shop_goods_spec", $d_spec);
                $specid = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_spec", $d_spec, array("id" => $oldspec['id']));
                $specid = $oldspec['id'];
            }
            $d_spec['id'] = $specid;


            $specids[] = $specid;

            $displayorder++;

            //spec_items
            $spec_items = $spec['items'];
            $spec_itemids = array();
            $displayorder_item = 0;
            $newspecitems = array();
            foreach ($spec_items as $spec_item) {
                $d = array(
                    "uniacid" => $_W['uniacid'],
                    "specid" => $specid,
                    "title" => $spec_item['title'],
                    "thumb" => $this->save_image($spec_item['thumb'], false),
                    "valueId" => $spec_item['valueId'],
                    "show" => 1,
                    "displayorder" => $displayorder_item
                );
                $oldspecitem = pdo_fetch("select * from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid and valueId=:valueId limit 1", array(":specid" => $specid, ":valueId" => $spec_item['valueId']));
                $spec_item_id = 0;
                if (empty($oldspecitem)) {
                    pdo_insert("ewei_shop_goods_spec_item", $d);
                    $spec_item_id = pdo_insertid();
                } else {
                    pdo_update("ewei_shop_goods_spec_item", $d, array("id" => $oldspecitem['id']));
                    $spec_item_id = $oldspecitem['id'];
                }
                $displayorder_item++;
                $spec_itemids[] = $spec_item_id;
                $d['id'] = $spec_item_id;
                $newspecitems[] = $d;
            }
            $d_spec['items'] = $newspecitems;

            $newspecs[] = $d_spec;

            if (count($spec_itemids) > 0) {
                pdo_query("delete from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid and id not in (" . implode(",", $spec_itemids) . ")", array(":specid" => $specid));
            } else {
                pdo_query("delete from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid ", array(":specid" => $specid));
            }
            pdo_update("ewei_shop_goods_spec", array("content" => serialize($spec_itemids)), array("id" => $oldspec['id']));
        }

        if (count($specids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid and id not in (" . implode(",", $specids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }

        //规格
        $minprice = 0;
        $options = $item['options'];


        if (count($options) > 0) {
            $minprice = $options[0]['marketprice'];
        }
        $optionids = array();
        $displayorder = 0;
        foreach ($options as $o) {
            $option_specs = $o['option_specs'];
            $ids = array();
            $valueIds = array();
            foreach ($option_specs as $os) {
                foreach ($newspecs as $nsp) {
                    foreach ($nsp['items'] as $nspitem) {
                        if ($nspitem['valueId'] == $os['valueId']) {
                            $ids[] = $nspitem['id'];
                            $valueIds[] = $nspitem['valueId'];
                        }
                    }
                }
            }
            asort($ids);
            $ids = implode("_", $ids);
            $valueIds = implode("_", $valueIds);
            $do = array(
                'uniacid' => $_W['uniacid'],
                "displayorder" => $displayorder,
                "goodsid" => $goodsid,
                "title" => implode('+', $o['title']),
                "specs" => $ids,
                "stock" => $o['stock'],
                "marketprice" => $o['marketprice'],
                "skuId" => $o['skuId']
            );

            if ($minprice > $o['marketprice']) {
                $minprice = $o['marketprice'];
            }
            $oldoption = pdo_fetch("select * from " . tablename('ewei_shop_goods_option') . " where goodsid=:goodsid and skuId=:skuId limit 1", array(":goodsid" => $goodsid, ":skuId" => $o['skuId']));
            $option_id = 0;
            if (empty($oldoption)) {
                pdo_insert("ewei_shop_goods_option", $do);
                $option_id = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_option", $do, array("id" => $oldoption['id']));
                $option_id = $oldoption['id'];
            }
            $displayorder++;
            $optionids[] = $option_id;
        }
        if (count($optionids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_option') . " where goodsid=:goodsid and id not in (" . implode(",", $optionids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_option') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }

        //保存详情
        $response = $item['content'];

        $content = $response['content'];


        preg_match_all("/<img.*?src=[\\\'| \\\"](.*?(?:[\.gif|\.jpg]?))[\\\'|\\\"].*?[\/]?>/", $content, $imgs);

        if (isset($imgs[1])) {

            foreach ($imgs[1] as $img) {

                $catchimg = $img;
                if (substr($catchimg, 0, 2) == "//") {
                    $img = "http://" . substr($img, 2);
                }
                $im = array(
                    "catchimg" => $catchimg,
                    "system" => $this->save_image($img, true)
                );
                $images[] = $im;
            }
        }

        preg_match("/tfsContent : \'(.*)\'/", $content, $html);
        $html = iconv("GBK", "UTF-8", $html[1]);
        if (isset($images)) {
            foreach ($images as $img) {
                if (!empty($img['system'])) {
                    $html = str_replace($img['catchimg'], $img['system'], $html);
                }
            }
        }
        $html = m('common')->html_to_images($html);
        $hasoption = 0;

        if (count($options) > 0) {
            $hasoption = 1;
        }

        $d = array("content" => $html, "hasoption" => $hasoption);
        if ($minprice > 0) {
            $d["marketprice"] = $minprice;
        }

        pdo_update("ewei_shop_goods", $d, array("id" => $goodsid));


        if ($d['hasoption']) {
            //更新最高最低价
            $sql = "update " . tablename('ewei_shop_goods') . " g set
            g.minprice = (select min(marketprice) from " . tablename('ewei_shop_goods_option') . " where goodsid = $goodsid and marketprice > 0),
            g.maxprice = (select max(marketprice) from " . tablename('ewei_shop_goods_option') . " where goodsid = $goodsid)
            where g.id = $goodsid and g.hasoption=1";
        } else {
            $sql = "update " . tablename('ewei_shop_goods') . " set minprice = marketprice,maxprice = marketprice where id = $goodsid and hasoption=0;";
        }

        pdo_query($sql);
        return array("result" => '1', "goodsid" => $goodsid);
    }

    //保存天猫商品
    function save_tmall_goods($item = array(), $catch_url = '')
    {
        global $_W;
        $data = array(
            "uniacid" => $_W['uniacid'],
            "subtitle" => $item['subTitle'],
            "merchid" => $item['merchid'],
            "checked" => $item['checked'],
            "catch_source" => 'taobao',
            "catch_id" => $item['itemId'],
            "catch_url" => $catch_url,
            "title" => $item['title'],
            "total" => $item['total'],
            "marketprice" => $item['marketprice'],
            "productprice" => $item['productPrice'],
            "pcate" => $item['pcate'],
            "ccate" => $item['ccate'],
            "tcate" => $item['tcate'],
            "cates" => $item['cates'],
            "sales" => $item['sales'],
            "createtime" => time(),
            "updatetime" => time(),
            'hasoption' => count($item['options']) > 0 ? 1 : 0,
            'status' => 0,
            'deleted' => 0,
            'buylevels' => '',
            'showlevels' => '',
            'buygroups' => '',
            'showgroups' => '',
            'noticeopenid' => '',
            'storeids' => '',
            'merchsale' => $item['merchid'] == 0 ? 0 : 1,
            'newgoods' => 1
        );
        if (empty($item['merchid'])) {
            $data['discounts'] = '{"type":"0","default":"","default_pay":""}';
        }
        //图片
        $thumb_url = array();
        $pics = $item['pics'];
        $piclen = count($pics);

        if ($piclen > 0) {
            $img = $this->save_image($pics[0], false);
            if (empty($img)) {
                $img = $pics[0];
            }
            $info =  getimagesize("../attachment/".$img);
            $srcFileExtImg=$info['mime'];
            if($srcFileExtImg=='image/x-ms-bmp') {
                $mig = $this->changeBMPtoJPG("../attachment/" . $img);
            }else{
                $mig =  $img;
            }
            $data['thumb'] = $mig;
            //其他图片
            if ($piclen > 1) {
                for ($i = 1; $i < $piclen; $i++) {

                    $img = $this->save_image($pics[$i], false);
                    if (empty($img)) {
                        $img = $pics[$i];
                    }
                    $thumb_url[] = $img;
                }
            }
        }
        $mi = array();

        foreach ($thumb_url as $thumb_Info){
            $info =  getimagesize("../attachment/".$thumb_Info);
            $srcFileExt=$info['mime'];
            if($srcFileExt=='image/x-ms-bmp') {
                $mi[] = $this->changeBMPtoJPG("../attachment/" . $thumb_Info);
            }else{
                $mi[] =    $thumb_Info;
            }
        }
        $data['thumb_url'] = serialize($mi);
        $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where  catch_id=:catch_id and catch_source='taobao' and uniacid=:uniacid and merchid=:merchid", array(":catch_id" => $item['itemId'], ":uniacid" => $_W['uniacid'], ":merchid" => $item['merchid']));
        if (empty($goods)) {

            pdo_insert("ewei_shop_goods", $data);
            $goodsid = pdo_insertid();

        } else {
            $goodsid = $goods['id'];

            unset($data['createtime']);
            pdo_update("ewei_shop_goods", $data, array("id" => $goodsid));
        }

        //参数
        $goods_params = pdo_fetchall("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        $params = $item['params'];
        $paramids = array();
        $displayorder = 0;
        foreach ($params as $p) {

            $oldp = pdo_fetch("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and title=:title limit 1", array(":goodsid" => $goodsid, ":title" => $p['title']));
            $paramid = 0;
            $d = array(
                "uniacid" => $_W['uniacid'],
                "goodsid" => $goodsid,
                "title" => $p['title'],
                "value" => $p['value'],
                "displayorder" => $displayorder
            );
            if (empty($oldp)) {
                pdo_insert("ewei_shop_goods_param", $d);
                $paramid = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_param", $d, array("id" => $oldp['id']));
                $paramid = $oldp['id'];
            }
            $paramids[] = $paramid;
            $displayorder++;
        }

        if (count($paramids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and id not in (" . implode(",", $paramids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }
        //规格
        $specs = $item['specs'];
        $specids = array();
        $displayorder = 0;
        $newspecs = array();
        foreach ($specs as $spec) {

            $oldspec = pdo_fetch("select * from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid and propId=:propId limit 1", array(":goodsid" => $goodsid, ":propId" => $spec['propId']));
            $specid = 0;
            $d_spec = array(
                "uniacid" => $_W['uniacid'],
                "goodsid" => $goodsid,
                "title" => $spec['title'],
                "displayorder" => $displayorder,
                "propId" => $spec['propId']
            );

            if (empty($oldspec)) {
                pdo_insert("ewei_shop_goods_spec", $d_spec);
                $specid = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_spec", $d_spec, array("id" => $oldspec['id']));
                $specid = $oldspec['id'];
            }
            $d_spec['id'] = $specid;


            $specids[] = $specid;

            $displayorder++;

            //spec_items
            $spec_items = $spec['items'];
            $spec_itemids = array();
            $displayorder_item = 0;
            $newspecitems = array();
            foreach ($spec_items as $spec_item) {
                $d = array(
                    "uniacid" => $_W['uniacid'],
                    "specid" => $specid,
                    "title" => $spec_item['title'],
                    "thumb" => $this->save_image($spec_item['thumb'], false),
                    "valueId" => $spec_item['valueId'],
                    "show" => 1,
                    "displayorder" => $displayorder_item
                );
                $oldspecitem = pdo_fetch("select * from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid and valueId=:valueId limit 1", array(":specid" => $specid, ":valueId" => $spec_item['valueId']));
                $spec_item_id = 0;
                if (empty($oldspecitem)) {
                    pdo_insert("ewei_shop_goods_spec_item", $d);
                    $spec_item_id = pdo_insertid();
                } else {
                    pdo_update("ewei_shop_goods_spec_item", $d, array("id" => $oldspecitem['id']));
                    $spec_item_id = $oldspecitem['id'];
                }
                $displayorder_item++;
                $spec_itemids[] = $spec_item_id;
                $d['id'] = $spec_item_id;
                $newspecitems[] = $d;
            }
            $d_spec['items'] = $newspecitems;

            $newspecs[] = $d_spec;

            if (count($spec_itemids) > 0) {
                pdo_query("delete from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid and id not in (" . implode(",", $spec_itemids) . ")", array(":specid" => $specid));
            } else {
                pdo_query("delete from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid ", array(":specid" => $specid));
            }
            pdo_update("ewei_shop_goods_spec", array("content" => serialize($spec_itemids)), array("id" => $oldspec['id']));
        }

        if (count($specids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid and id not in (" . implode(",", $specids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }

        //规格
        $minprice = 0;
        $options = $item['options'];


        if (count($options) > 0) {
            $minprice = $options[0]['marketprice'];
        }
        $optionids = array();
        $displayorder = 0;
        foreach ($options as $o) {
            $option_specs = $o['option_specs'];
            $ids = array();
            $valueIds = array();
            foreach ($option_specs as $os) {
                foreach ($newspecs as $nsp) {
                    foreach ($nsp['items'] as $nspitem) {
                        if ($nspitem['valueId'] == $os['valueId']) {
                            $ids[] = $nspitem['id'];
                            $valueIds[] = $nspitem['valueId'];
                        }
                    }
                }
            }
            asort($ids);
            $ids = implode("_", $ids);
            $valueIds = implode("_", $valueIds);
            $do = array(
                'uniacid' => $_W['uniacid'],
                "displayorder" => $displayorder,
                "goodsid" => $goodsid,
                "title" => implode('+', $o['title']),
                "specs" => $ids,
                "stock" => $o['stock'],
                "marketprice" => $o['marketprice'],
                "skuId" => $o['skuId']
            );

            if ($minprice > $o['marketprice']) {
                $minprice = $o['marketprice'];
            }
            $oldoption = pdo_fetch("select * from " . tablename('ewei_shop_goods_option') . " where goodsid=:goodsid and skuId=:skuId limit 1", array(":goodsid" => $goodsid, ":skuId" => $o['skuId']));
            $option_id = 0;
            if (empty($oldoption)) {
                pdo_insert("ewei_shop_goods_option", $do);
                $option_id = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_option", $do, array("id" => $oldoption['id']));
                $option_id = $oldoption['id'];
            }
            $displayorder++;
            $optionids[] = $option_id;
        }
        if (count($optionids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_option') . " where goodsid=:goodsid and id not in (" . implode(",", $optionids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_option') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }

        //保存详情
        $content = $item['content'];

//        $content = $response['content'];

        /*        preg_match_all("/<img.*?src=[\\\'| \\\"](.*?(?:[\.gif|\.jpg]?))[\\\'|\\\"].*?[\/]?>/", $content, $imgs);*/
        $imgs = $content;

        if (isset($imgs[1])) {
            foreach ($imgs[1] as $img) {
                $catchimg = $img;
                if (substr($catchimg, 0, 2) == "//") {
                    $img = "http://" . substr($img, 2);
                }
                $im = array(
                    "catchimg" => $catchimg,
                    "system" => $this->save_image($img, true)
                );
                $images[] = $im;
            }
        }

        $str = '';
        $str .="<div style='width: 100%;text-align: center'>";
        foreach($images as $key=>$val){
            $src = $val['system'];
            $str .="<img src=\"$src\">";
        }
        $str .="</div>";
        $html =  htmlspecialchars_decode($str);

        $hasoption = 0;

        if (count($options) > 0) {
            $hasoption = 1;
        }

        $d = array("content" => $html, "hasoption" => $hasoption);
        if ($minprice > 0) {
            $d["marketprice"] = $minprice;
        }

        pdo_update("ewei_shop_goods", $d, array("id" => $goodsid));

        if ($d['hasoption']) {
            //更新最高最低价
            $sql = "update " . tablename('ewei_shop_goods') . " g set
            g.minprice = (select min(marketprice) from " . tablename('ewei_shop_goods_option') . " where goodsid = $goodsid and marketprice > 0),
            g.maxprice = (select max(marketprice) from " . tablename('ewei_shop_goods_option') . " where goodsid = $goodsid)
            where g.id = $goodsid and g.hasoption=1";
        } else {
            $sql = "update " . tablename('ewei_shop_goods') . " set minprice = marketprice,maxprice = marketprice where id = $goodsid and hasoption=0;";
        }

        pdo_query($sql);
        return array("result" => '1', "goodsid" => $goodsid);
    }

    //保存京东商品
    function save_jingdong_goods($item = array(), $catch_url = '')
    {

        global $_W;
        $data = array(
            "uniacid" => $_W['uniacid'],
            "merchid" => $item['merchid'],
            "checked" => $item['checked'],
            "catch_source" => 'jingdong',
            "catch_id" => $item['itemId'],
            "catch_url" => $catch_url,
            "title" => $item['title'],
            "total" => $item['total'],
            "marketprice" => $item['marketprice'],
            "pcate" => $item['pcate'],
            "ccate" => $item['ccate'],
            "tcate" => $item['tcate'],
            "cates" => $item['cates'],
            "sales" => $item['sales'],
            "createtime" => time(),
            "updatetime" => time(),
            'hasoption' => 0,
            'status' => 0,
            'deleted' => 0,
            'buylevels' => '',
            'showlevels' => '',
            'buygroups' => '',
            'showgroups' => '',
            'noticeopenid' => '',
            'storeids' => '',
            'minprice' => $item['marketprice'],
            'maxprice' => $item['marketprice'],
            'merchsale' => $item['merchid'] == 0 ? 0 : 1,
            'newgoods' => 1

        );
        if (empty($item['merchid'])) {
            $data['discounts'] = '{"type":"0","default":"","default_pay":""}';
        }

        //图片
        $thumb_url = array();
        $pics = $item['pics'];
        $piclen = count($pics);

        if ($piclen > 0) {

            $img = $this->save_image($pics[0], false);
            if (empty($img)) {
                $img = $pics[0];
            }
            $data['thumb'] = $img;

            //其他图片
            if ($piclen > 1) {
                for ($i = 1; $i < $piclen; $i++) {
                    $img = $this->save_image($pics[$i], false);
                    if (empty($img)) {
                        $img = $pics[$i];
                    }
                    $thumb_url[] = $img;
                }
            }
        }
        $data['thumb_url'] = serialize($thumb_url);
        $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where  catch_id=:catch_id and catch_source='jingdong' and uniacid=:uniacid and merchid=:merchid", array(":catch_id" => $item['itemId'], ":uniacid" => $_W['uniacid'], ":merchid" => $item['merchid']));
        if (empty($goods)) {

            pdo_insert("ewei_shop_goods", $data);
            $goodsid = pdo_insertid();
        } else {
            $goodsid = $goods['id'];

            unset($data['createtime']);
            pdo_update("ewei_shop_goods", $data, array("id" => $goodsid));
        }


        //参数
        $goods_params = pdo_fetchall("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        $params = $item['params'];
        $paramids = array();
        $displayorder = 0;
        foreach ($params as $p) {
            $oldp = pdo_fetch("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and title=:title limit 1", array(":goodsid" => $goodsid, ":title" => $p['title']));
            $paramid = 0;
            $d = array(
                "uniacid" => $_W['uniacid'],
                "goodsid" => $goodsid,
                "title" => $p['title'],
                "value" => $p['value'],
                "displayorder" => $displayorder
            );
            if (empty($oldp)) {
                pdo_insert("ewei_shop_goods_param", $d);
                $paramid = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_param", $d, array("id" => $oldp['id']));
                $paramid = $oldp['id'];
            }
            $paramids[] = $paramid;
            $displayorder++;
        }

        if (count($paramids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and id not in (" . implode(",", $paramids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }

        //保存详情
        $content = $item['content'];
        preg_match_all("/<img.*?src=[\\\'| \\\"](.*?(?:[\.gif|\.jpg]?))[\\\'|\\\"].*?[\/]?>/", $content, $imgs);

        if (isset($imgs[1])) {

            foreach ($imgs[1] as $img) {

                $catchimg = $img;
                if (substr($catchimg, 0, 2) == "//") {
                    $img = "http://" . substr($img, 2);
                }
                $im = array(
                    "catchimg" => $catchimg,
                    "system" => $this->save_image($img, true)
                );

                $images[] = $im;
            }


        }
        $html = $content;
        //$html = iconv("GBK", "UTF-8", $html[1]);

        if (isset($images)) {
            foreach ($images as $img) {
                if (!empty($img['system'])) {
                    $html = str_replace($img['catchimg'], $img['system'], $html);
                }
            }
        }
        $html = m('common')->html_to_images($html);
        $d = array("content" => $html);

        pdo_update("ewei_shop_goods", $d, array("id" => $goodsid));

        return array("result" => '1', "goodsid" => $goodsid);
    }

    //保存1688商品
    function save_1688_goods($item = array(), $catch_url = '')
    {

        global $_W;
        $data = array(
            "uniacid" => $_W['uniacid'],
            "merchid" => $item['merchid'],
            "checked" => $item['checked'],
            "catch_source" => '1688',
            "catch_id" => $item['itemId'],
            "catch_url" => $catch_url,
            "title" => $item['title'],
            "total" => $item['total'],
            "marketprice" => $item['marketprice'],
            "pcate" => $item['pcate'],
            "ccate" => $item['ccate'],
            "tcate" => $item['tcate'],
            "cates" => $item['cates'],
            "sales" => $item['sales'],
            "createtime" => time(),
            "updatetime" => time(),
            'hasoption' => 0,
            'status' => 0,
            'deleted' => 0,
            'buylevels' => '',
            'showlevels' => '',
            'buygroups' => '',
            'showgroups' => '',
            'noticeopenid' => '',
            'storeids' => '',
            'minprice' => $item['marketprice'],
            'maxprice' => $item['marketprice'],
            'merchsale' => $item['merchid'] == 0 ? 0 : 1,
            'newgoods' => 1

        );
        if (empty($item['merchid'])) {
            $data['discounts'] = '{"type":"0","default":"","default_pay":""}';
        }
        //图片
        $thumb_url = array();
        $pics = $item['pics'];
        $piclen = count($pics);

        if ($piclen > 0) {

            $img = $this->save_image($pics[0], false);
            if (empty($img)) {
                $img = $pics[0];
            }
            $data['thumb'] = $img;

            //其他图片
            if ($piclen > 1) {
                for ($i = 1; $i < $piclen; $i++) {
                    $img = $this->save_image($pics[$i], false);
                    if (empty($img)) {
                        $img = $pics[$i];
                    }
                    $thumb_url[] = $img;
                }
            }
        }
        $data['thumb_url'] = serialize($thumb_url);

        $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where  catch_id=:catch_id and catch_source='1688' and uniacid=:uniacid and merchid=:merchid", array(":catch_id" => $item['itemId'], ":uniacid" => $_W['uniacid'], ":merchid" => $item['merchid']));
        if (empty($goods)) {

            pdo_insert("ewei_shop_goods", $data);
            $goodsid = pdo_insertid();
        } else {
            $goodsid = $goods['id'];

            unset($data['createtime']);
            pdo_update("ewei_shop_goods", $data, array("id" => $goodsid));
        }


        //参数
        $goods_params = pdo_fetchall("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        $params = $item['params'];
        $paramids = array();
        $displayorder = 0;
        foreach ($params as $p) {

            $oldp = pdo_fetch("select * from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and title=:title limit 1", array(":goodsid" => $goodsid, ":title" => $p['title']));
            $paramid = 0;
            $d = array(
                "uniacid" => $_W['uniacid'],
                "goodsid" => $goodsid,
                "title" => $p['title'],
                "value" => $p['value'],
                "displayorder" => $displayorder
            );
            if (empty($oldp)) {
                pdo_insert("ewei_shop_goods_param", $d);
                $paramid = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_param", $d, array("id" => $oldp['id']));
                $paramid = $oldp['id'];
            }
            $paramids[] = $paramid;
            $displayorder++;
        }

        if (count($paramids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid and id not in (" . implode(",", $paramids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_param') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }

        //规格

        $specs = $item['specs'];
        $specids = array();
        $displayorder = 0;
        $newspecs = array();
        foreach ($specs as $spec) {

            $oldspec = pdo_fetch("select * from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid and propId=:propId limit 1", array(":goodsid" => $goodsid, ":propId" => $spec['propId']));
            $specid = 0;
            $d_spec = array(
                "uniacid" => $_W['uniacid'],
                "goodsid" => $goodsid,
                "title" => $spec['title'],
                "displayorder" => $displayorder,
                "propId" => $spec['propId']
            );
            if (empty($oldspec)) {
                pdo_insert("ewei_shop_goods_spec", $d_spec);
                $specid = pdo_insertid();
            } else {
                pdo_update("ewei_shop_goods_spec", $d_spec, array("id" => $oldspec['id']));
                $specid = $oldspec['id'];
            }
            $d_spec['id'] = $specid;


            $specids[] = $specid;

            $displayorder++;

            //spec_items
            $spec_items = $spec['items'];
            $spec_itemids = array();
            $displayorder_item = 0;
            $newspecitems = array();
            foreach ($spec_items as $spec_item) {
                $d = array(
                    "uniacid" => $_W['uniacid'],
                    "specid" => $specid,
                    "title" => $spec_item['title'],
                    "thumb" => $this->save_image($spec_item['thumb'], false),
                    "valueId" => $spec_item['valueId'],
                    "show" => 1,
                    "displayorder" => $displayorder_item
                );
                $oldspecitem = pdo_fetch("select * from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid and valueId=:valueId limit 1", array(":specid" => $specid, ":valueId" => $spec_item['valueId']));
                $spec_item_id = 0;
                if (empty($oldspecitem)) {
                    pdo_insert("ewei_shop_goods_spec_item", $d);
                    $spec_item_id = pdo_insertid();
                } else {
                    pdo_update("ewei_shop_goods_spec_item", $d, array("id" => $oldspecitem['id']));
                    $spec_item_id = $oldspecitem['id'];
                }
                $displayorder_item++;
                $spec_itemids[] = $spec_item_id;
                $d['id'] = $spec_item_id;
                $newspecitems[] = $d;
            }
            $d_spec['items'] = $newspecitems;

            $newspecs[] = $d_spec;

            if (count($spec_itemids) > 0) {
                pdo_query("delete from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid and id not in (" . implode(",", $spec_itemids) . ")", array(":specid" => $specid));
            } else {
                pdo_query("delete from " . tablename('ewei_shop_goods_spec_item') . " where specid=:specid ", array(":specid" => $specid));
            }
            pdo_update("ewei_shop_goods_spec", array("content" => serialize($spec_itemids)), array("id" => $oldspec['id']));
        }

        if (count($specids) > 0) {
            pdo_query("delete from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid and id not in (" . implode(",", $specids) . ")", array(":goodsid" => $goodsid));
        } else {
            pdo_query("delete from " . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid ", array(":goodsid" => $goodsid));
        }


        //保存详情
        $content = $item['content'];
        preg_match_all("/<img.*?src=[\\\'| \\\"](.*?(?:[\.gif|\.jpg]?))[\\\'|\\\"].*?[\/]?>/", $content, $imgs);

        if (isset($imgs[1])) {

            foreach ($imgs[1] as $img) {

                $catchimg = $img;
                if (substr($catchimg, 0, 2) == "//") {
                    $img = "http://" . substr($img, 2);
                }
                $im = array(
                    "catchimg" => $catchimg,
                    "system" => $this->save_image($img, true)
                );

                $images[] = $im;
            }


        }
        $html = $content;
        //$html = iconv("GBK", "UTF-8", $html[1]);

        if (isset($images)) {
            foreach ($images as $img) {
                if (!empty($img['system'])) {
                    $html = str_replace($img['catchimg'], $img['system'], $html);
                }
            }
        }
        $html = m('common')->html_to_images($html);
        $d = array("content" => $html);

        pdo_update("ewei_shop_goods", $d, array("id" => $goodsid));

        return array("result" => '1', "goodsid" => $goodsid);
    }

    //保存淘宝CSV商品
    function save_taobaocsv_goods($item = array(), $merchid = 0)
    {

        global $_W;
        $data = array(
            "uniacid" => $_W['uniacid'],
            "merchid" => $merchid,
            "catch_source" => 'taobaocsv',
            "catch_id" => '',
            "catch_url" => '',
            "title" => $item['title'],
            "total" => $item['total'],
            "marketprice" => $item['marketprice'],
            "pcate" => '',
            "ccate" => '',
            "tcate" => '',
            "goodssn" =>$item['goodssn'],
            "cates" => '',
            "sales" => 0,
            "createtime" => time(),
            "updatetime" => time(),
            'hasoption' => 0,
            'status' => 0,
            'deleted' => 0,
            'buylevels' => '',
            'showlevels' => '',
            'buygroups' => '',
            'showgroups' => '',
            'noticeopenid' => '',
            'storeids' => '',
            'minprice' => $item['marketprice'],
            'maxprice' => $item['marketprice'],
            'merchsale' => $item['merchid'] == 0 ? 0 : 1,

        );
        if (empty($item['merchid'])) {
            $data['discounts'] = '{"type":"0","default":"","default_pay":""}';
        }
        if (!empty($merchid)) {
            if (empty($_W['merch_user']['goodschecked'])) {
                $data['checked'] = 1;
            } else {
                $data['checked'] = 0;
            }
        }

        //图片
        $thumb_url = array();
        $pics = $item['pics'];
        $piclen = count($pics);

        if ($piclen > 0) {

            $data['thumb'] = $this->save_image($pics[0], false);

            //其他图片
            if ($piclen > 1) {
                for ($i = 1; $i < $piclen; $i++) {
                    $img = $this->save_image($pics[$i], false);
                    $thumb_url[] = $img;
                }
            }
        }
        $data['thumb_url'] = serialize($thumb_url);

        pdo_insert("ewei_shop_goods", $data);
        $goodsid = pdo_insertid();

        //保存详情
        $content = $item['content'];
        preg_match_all("/<img.*?src=[\\\'| \\\"](.*?(?:[\.gif|\.jpg]?))[\\\'|\\\"].*?[\/]?>/", $content, $imgs);

        if (isset($imgs[1])) {
            foreach ($imgs[1] as $img) {

                $catchimg = $img;
                if (substr($catchimg, 0, 2) == "//") {
                    $img = "http://" . substr($img, 2);
                }
                $im = array(
                    "catchimg" => $catchimg,
                    "system" => $this->save_image($img, true)
                );

                $images[] = $im;
            }
        }
        $html = $content;
        //$html = iconv("GBK", "UTF-8", $html[1]);

        if (isset($images)) {
            foreach ($images as $img) {
                if (!empty($img['system'])) {
                    $html = str_replace($img['catchimg'], $img['system'], $html);
                }
            }
        }
        $html = m('common')->html_to_images($html);

        if(isset($images[0])) {
            $d['thumb_url'] = serialize($images[0]);
            $d['thumb'] = $images[0]['catchimg'];
        }
        $d['content'] = $html;
        pdo_update("ewei_shop_goods", $d, array("id" => $goodsid));

        return array("result" => '1', "goodsid" => $goodsid);
    }

    //获取淘宝宝贝数据连接
    function get_taobao_info_url($itemid)
    {
        $url = "http://hws.m.taobao.com/cache/wdetail/5.0/?id=" . $itemid;
        $url = $this->getRealURL($url);

        return $url;
    }


    //获取页面天猫宝贝数据连接
    function get_tmall_page_url($itemid)
    {
        $url = "https://detail.tmall.com/item.htm?id=" . $itemid;
        $url = $this->getRealURL($url);

        return $url;
    }

    //获取淘宝宝贝数据连接
    function get_taobao_page_url($itemid)
    {
        $url = "https://item.taobao.com/item.htm?id=" . $itemid;
        $url = $this->getRealURL($url);

        return $url;
    }


    //获取淘宝宝贝详情页连接
    function get_taobao_detail_url($itemid)
    {
        $url = 'http://hws.m.taobao.com/cache/wdesc/5.0/?id=' . $itemid;
        $url = $this->getRealURL($url);

        return $url;
    }
    //获取天猫宝贝详情页连接
    function get_tmall_detail_url($itemid)
    {
        $url = "https://detail.m.tmall.com/item.htm?id=" . $itemid;
        $url = $this->getRealURL($url);
        return $url;
    }

    //获取京东宝贝数据连接
    function get_jingdong_info_url($itemid)
    {
        $url = "http://item.m.jd.com/ware/view.action?wareId=" . $itemid;
        $url = $this->getRealURL($url);

        return $url;
    }

    //获取京东宝贝详情页连接
    function get_jingdong_detail_url($itemid)
    {
        $url = 'http://item.m.jd.com/ware/detail.json?wareId=' . $itemid;
        $url = $this->getRealURL($url);

        return $url;
    }
    //获取京东宝贝详情页连接
    function get_jingdong_price_url($itemid)
    {
        $url = 'https://pe.3.cn/prices/mgets?skuids=' . $itemid;
        $url = $this->getRealURL($url);
        return $url;
    }

    //获取1688宝贝数据连接
    function get_1688_info_url($itemid)
    {
        $url = "https://m.1688.com/offer/" . $itemid . ".html";
        return $url;
    }

    function save_image($url, $iscontent)
    {
        global $_W;
        load()->func('communication');
        $ext = strrchr($url, ".");
        if ($ext != ".jpeg" && $ext != ".gif" && $ext != ".jpg" && $ext != ".png") {
            return $url;
        }
        if (trim($url) == '') {
            return $url;
        }

        $filename = random(32) . $ext;
        $save_dir = ATTACHMENT_ROOT . 'images/' . $_W['uniacid'] . '/' . date('Y') . '/' . date('m') . '/';

        //创建保存目录
        if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
            return $url;
        }

        $img = ihttp_get($url);
        if (is_error($img)) {
            return "";
        }

        $img = $img['content'];
        if (strlen($img) != 0) {
            file_put_contents($save_dir . $filename, $img);
            $imgdir = 'images/' . $_W['uniacid'] . '/' . date('Y') . '/' . date('m') . '/';
            $saveurl = save_media($imgdir . $filename, true);
            return $saveurl;
        } else {
            return '';
        }

    }


    //获取真实url
    function getRealURL($url)
    {

        if (function_exists("stream_context_set_default")) {
            stream_context_set_default(
                array(
                    'http' => array(
                        'method' => 'HEAD'
                    )
                )
            );
        }

        $header = get_headers($url, 1);
        if (strpos($header[0], '301') || strpos($header[0], '302')) {
            if (is_array($header['Location'])) {
                return $header['Location'][count($header['Location']) - 1];
            } else {
                return $header['Location'];
            }
        } else {
            return $url;
        }
    }

//以下方法暂未使用

    function check_remote_file_exists($url)
    {
        $curl = curl_init($url);
        //不取回数据
        curl_setopt($curl, CURLOPT_NOBODY, true);
        //发送请求
        $result = curl_exec($curl);
        $found = false;
        if ($result !== false) {
            //检查http响应码是否为200
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($statusCode == 200) {
                $found = true;
            }
        }
        curl_close($curl);
        return $found;
    }

    //二维数组排序
    function my_sort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){
        if(is_array($arrays)){
            foreach ($arrays as $array){
                if(is_array($array)){
                    $key_arrays[] = $array[$sort_key];
                }else{
                    return false;
                }
            }
        }else{
            return false;
        }
        array_multisort($key_arrays,$sort_order,$sort_type,$arrays);
        return $arrays;
    }

    function get_pageno_url($url = '', $pageNo = 1)
    {
        $url .= "/search.htm?pageNo=" . $pageNo;
        return $url;
    }

    //获取页面
    function get_total_page($url = '', $taobao = false)
    {
        if (empty($url)) {
            return array("totalpage" => 0);
        }
        $content = $this->get_page_content($url);

        $str = "";
        if ($taobao) {
            $str = "/<span class=\"page-info\">(.*)<\/span>/";
        } else {
            $str = "/<b class=\"ui-page-s-len\">(.*)<\/b>/";
        }
        preg_match($str, $content, $p);
        if (is_array($p)) {
            $pages = explode("/", $p[1]);
            return array("totalpage" => $pages[1]);
        }

        return array("totalpage" => 0);
    }

    function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    //获取页面内容
    function get_page_content($url = '', $pageNo = 1)
    {
        if (empty($url)) {
            return array("totalpage" => 0);
        }
        $url = $this->get_pageno_url($url, $pageNo);
        load()->func('communication');
        $response = ihttp_get($url);
        if (!isset($response['content'])) {
            return array("result" => 0);
        }

        return $response['content'];
    }


    //获取每页产品(天猫)
    function get_pag_items($pageContent = '')
    {
        $str = '/data-id="(.*)"/U';
        preg_match_all($str, $pageContent, $items);
        if (isset($items[1])) {
            return $items[1];
        }
        return array();
    }


    //详情图片去掉宽高
    function contentpasswh($content)
    {
        $content = preg_replace('/(?:width)=(\'|").*?\\1/', ' width="100%"', $content);
        $content = preg_replace('/(?:height)=(\'|").*?\\1/', ' ', $content);

        $content = preg_replace('/(?:max-width:\s*\d*\.?\d*(px|rem|em))/', '', $content);
        $content = preg_replace('/(?:max-height:\s*\d*\.?\d*(px|rem|em))/', '', $content);

        $content = preg_replace('/(?:min-width:\s*\d*\.?\d*(px|rem|em))/', ' ', $content);
        $content = preg_replace('/(?:min-height:\s*\d*\.?\d*(px|rem|em))/', ' ', $content);
        return $content;
    }

    function get_item_one688($itemid = '', $one688url = '', $cates, $merchid = 0)
    {
        //原1688助手失效后采用alibaba助手
        $this->alibaba($itemid, $cates, $merchid);
    }

    //alibaba助手
    function alibaba($itemid, $cates, $merchid = 0)
    {
        global $_W, $_GPC;
        error_reporting(0);
        if (TRUE) {
            $id = $itemid;
            $itemUrl = "http://m.1688.com/offer/{$id}.html";
            $html = file_get_contents($itemUrl);
            if(preg_match('/https:\/\/www\.taobao\.com\/markets\/bx\/deny_pc/',$html,$message)){
                show_json(0, '访问被拒绝,请检查代理或VPN或请求次数过多');
            }
            preg_match('/window\.wingxViewData=window\.wingxViewData\|\|{};window\.wingxViewData\[0\]=(.+)<\/script>/', $html, $res);
            $json1 = $res[1];//数据1
            $json1 = json_decode($json1, true);

            if (empty($json1['detailUrl'])) show_json(0, '商品获取失败');
            $detailUrl = $json1['detailUrl'];//商品详情数据地址
            load()->func('communication');
            $detail = ihttp_get($detailUrl);
            $detail = iconv('GBK', 'UTF-8', $detail['content']);
            //格式一
            preg_match('/var offer_details=(.+);$/', $detail, $detailStr);
            $detail_temp = json_decode($detailStr[1], true);
            if (empty($detail_temp)) {
                //格式二
                preg_match('/var desc=\'(.+)\';$/', $detail, $detailStr);
                unset($detail);
                $detail['content'] = $detailStr[1];
            } else {
                //格式一
                $detail = $detail_temp;
            }
            $thumb_url = array();
            foreach ($json1['imageList'] as $k => $v) {
                $thumb_url[] = $this->save_image($v['originalImageURI'], 1);
            }
            $thumb = $thumb_url[0];
            unset($thumb_url[0]);
            $priceRange = explode('-', $json1['priceDisplay']);
            $minprice = floatval($priceRange[0]);
            $maxprice = empty($priceRange[1]) ? floatval($priceRange[0]) : floatval($priceRange[1]);
            $hasoption = empty($json1['skuProps']) ? 0 : 1;
            $param = $json1['productFeatureList'];
            $detail['content'] = $this->contentpasswh($detail['content']);

            //存储详情图
            preg_match_all("/<img.*?src=[\\\'| \\\"](.*?(?:[\.gif|\.jpg]?))[\\\'|\\\"].*?[\/]?>/", $detail['content'], $imgs);
            if (isset($imgs[1])) {
                foreach ($imgs[1] as $img) {
                    $catchimg = $img;
                    if (substr($catchimg, 0, 2) == "//") {
                        $img = "http://" . substr($img, 2);
                    }
                    $im = array(
                        "catchimg" => $catchimg,
                        "system" => $this->save_image($img, true)
                    );
                    $images[] = $im;
                }
            }

            if (isset($images)) {
                foreach ($images as $img) {
                    if (!empty($img['system'])) {
                        if (!empty($img['system'])) {
                            $detail['content'] = str_replace($img['catchimg'], $img['system'], $detail['content']);
                        }
                    }

                }
            }
            $detail['content'] = m('common')->html_to_images($detail['content']);

            $data = array(
                'uniacid' => $_W['uniacid'],
                'thumb' => $thumb,
                'thumb_url' => serialize($thumb_url),
                'title' => $json1['subject'],
                'status' => 0,
                'marketprice' => $maxprice,
                'originalprice' => $minprice,
                'minprice' => $minprice,
                'maxprice' => $maxprice,
                'hasoption' => $hasoption,
                'createtime' => time(),
                'total' => $json1['canBookedAmount'],
                'content' => $detail['content'],
                'merchid' => $merchid,
                'cates' => $cates,
                'checked' => empty($merchid) ? 0 : 1,
                'newgoods' => 1
            );
//多商户商品是否免审核
            if (!empty($merchid)) {
                if (empty($_W['merch_user']['goodschecked'])) {
                    $data['checked'] = 1;
                } else {
                    $data['checked'] = 0;
                }
            }

            $pcates = array();
            $ccates = array();
            $tcates = array();
            $pcateid = 0;
            $ccateid = 0;
            $tcateid = 0;

            if (is_array($cates)) {
                foreach ($cates as $key => $cid) {
                    $c = pdo_fetch('select level from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                    if ($c['level'] == 1) { //一级
                        $pcates[] = $cid;
                    } else if ($c['level'] == 2) {  //二级
                        $ccates[] = $cid;
                    } else if ($c['level'] == 3) {  //三级
                        $tcates[] = $cid;
                    }
                    if ($key == 0) {
                        //兼容 1.x
                        if ($c['level'] == 1) { //一级
                            $pcateid = $cid;
                        } else if ($c['level'] == 2) {
                            $crow = pdo_fetch('select parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                            $pcateid = $crow['parentid'];
                            $ccateid = $cid;

                        } else if ($c['level'] == 3) {
                            $tcateid = $cid;
                            $tcate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $cid, ':uniacid' => $_W['uniacid']));
                            $ccateid = $tcate['parentid'];
                            $ccate = pdo_fetch('select id,parentid from ' . tablename('ewei_shop_category') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $ccateid, ':uniacid' => $_W['uniacid']));
                            $pcateid = $ccate['parentid'];
                        }
                    }
                }
            }

            $data['pcate'] = $pcateid;
            $data['ccate'] = $ccateid;
            $data['tcate'] = $tcateid;
            if (!empty($cates)) {
                $data['cates'] = implode(',', $cates);
            }

            $data['pcates'] = implode(',', $pcates);
            $data['ccates'] = implode(',', $ccates);
            $data['tcates'] = implode(',', $tcates);


            pdo_insert('ewei_shop_goods', $data);
            $goodsid = pdo_insertid();
            if (empty($goodsid)) show_json(0, '抓取失败');
            $param = $this->paramFormat($param, $goodsid);
            if ($hasoption) {//如果有规格
                //规格处理
                foreach ($json1['skuProps'] as $k => $v) {
                    $spec = $v['prop'];
                    pdo_insert('ewei_shop_goods_spec', array('uniacid' => $_W['uniacid'], 'goodsid' => $goodsid, 'title' => $spec));
                    $specId = pdo_insertid();

                    foreach ($v['value'] as $key => $val) {
                        $thumb = $val['imageUrl'];
                        $title = $val['name'];
                        pdo_insert('ewei_shop_goods_spec_item', array('specid' => $specId, 'title' => $val['name'], 'thumb' => empty($thumb) ? '' : $thumb, 'show' => 1));
                        $specs = pdo_insertid();
                        $specsid[$k][$key][$title] = $specs;
                    }
                }
                //处理选项
                $map = $json1['skuMap'];
                foreach ($map as $k => $v) {
                    $specArr = explode('&gt;', $k);
                    foreach ($specsid as $key => $item) {
                        foreach ($item as $v1) {
                            if (!empty($v1[$specArr[$key]])) {
                                $sss[] = $v1[$specArr[$key]];
                            }
                        }
                    }
                    $option['specs'] = implode('_', $sss);
                    unset($sss);

                    $option['title'] = str_replace('&gt;', '+', $k);//规格title
                    if (!empty($v['price'])) {
                        $option['marketprice'] = $v['price'];
                    } elseif (!empty($v['discountPrice'])) {
                        $option['marketprice'] = $v['discountPrice'];
                    } elseif (!empty($json1['discountPriceRanges'])) {
                        $option['marketprice'] = $json1['discountPriceRanges'][0]['price'];
                    } else {
                        $option['marketprice'] = 0;
                    }
                    $option['stock'] = $v['canBookCount'];//库存
                    $option['goodsid'] = $goodsid;
                    if (!empty($v['price'])) {
                        $option['productprice'] = $v['price'];
                    } elseif (!empty($v['discountPrice'])) {
                        $option['productprice'] = $v['discountPrice'];
                    } elseif (!empty($json1['discountPriceRanges'])) {
                        $option['productprice'] = $json1['discountPriceRanges'][0]['price'];
                    } else {
                        $option['productprice'] = 0;
                    }
                    pdo_insert('ewei_shop_goods_option', $option);
                }
            }
            show_json(1, '抓取成功');
        }
    }

    //商品参数格式化
    private function paramFormat($param, $id)
    {
        global $_W;
        $value = array();
        foreach ($param as $k => $v) {
            if (!empty($v['name']) && !empty($v['value'])) {//淘宝和1688数据
                $unit = empty($v['unit']) ? '' : $v['unit'];
                $value[$v['name']] = $v['value'] . $unit;
            }
        }
        foreach ($value as $key => $val) {
            pdo_insert('ewei_shop_goods_param', array('goodsid' => $id, 'uniacid' => $_W['uniacid'], 'title' => $key, 'value' => $val));
        }
    }


    /**
     * bmp图片转img图片
     * @param $filename
     * @return bool|resource
     */
    function changeBMPtoJPG($srcPathName){
        $srcFile=$srcPathName;
        $dstFile = str_replace('.bmp', '.jpg', $srcPathName);
        $photoSize = GetImageSize($srcFile);
        $pw = $photoSize[0];
        $ph = $photoSize[1];
        $dstImage = ImageCreateTrueColor($pw, $ph);
        $white = imagecolorallocate($dstImage, 255, 255, 255);
        //用 $white 颜色填充图像
        imagefill( $dstImage, 0, 0, $white);
        //读取图片
        $srcImage = $this->ImageCreateFromBMP_private($srcFile);
        //合拼图片

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $pw, $ph, $pw, $ph);
        $judge = imagejpeg($dstImage, $dstFile, 90);
        imagedestroy($dstImage);
        if($judge){
            return $dstFile;
        }else{
            return false;
        }
    }
    /**
     * bmp图片转img图片
     * @param $filename
     * @return bool|resource
     */
    function ImageCreateFromBMP_private($filename) {
        if (!$f1 = fopen($filename, "rb"))
            return false;
        $FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1, 14));
        if ($FILE['file_type'] != 19778)
            return false;

        $BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel' .
            '/Vcompression/Vsize_bitmap/Vhoriz_resolution' .
            '/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1, 40));
        $BMP['colors'] = pow(2, $BMP['bits_per_pixel']);
        if ($BMP['size_bitmap'] == 0)
            $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
        $BMP['bytes_per_pixel'] = $BMP['bits_per_pixel'] / 8;
        $BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
        $BMP['decal'] = ($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] -= floor($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] = 4 - (4 * $BMP['decal']);
        if ($BMP['decal'] == 4)
            $BMP['decal'] = 0;

        $PALETTE = array();
        if ($BMP['colors'] < 16777216) {
            $PALETTE = unpack('V' . $BMP['colors'], fread($f1, $BMP['colors'] * 4));
        }

        $IMG = fread($f1, $BMP['size_bitmap']);
        $VIDE = chr(0);

        $res = imagecreatetruecolor($BMP['width'], $BMP['height']);
        $P = 0;
        $Y = $BMP['height'] - 1;
        while ($Y >= 0) {
            $X = 0;
            while ($X < $BMP['width']) {
                switch ($BMP['bits_per_pixel']) {
                    case 32:
                        $COLOR = unpack("V", substr($IMG, $P, 3) . $VIDE);
                        break;
                    case 24:
                        $COLOR = unpack("V", substr($IMG, $P, 3) . $VIDE);
                        break;
                    case 16:
                        $COLOR = unpack("n", substr($IMG, $P, 2));
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    case 8:
                        $COLOR = unpack("n", $VIDE . substr($IMG, $P, 1));
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    case 4:
                        $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                        if (($P * 2) % 2 == 0)
                            $COLOR[1] = ($COLOR[1] >> 4);
                        else
                            $COLOR[1] = ($COLOR[1] & 0x0F);
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    case 1:
                        $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                        if (($P * 8) % 8 == 0)
                            $COLOR[1] = $COLOR[1] >> 7;
                        elseif (($P * 8) % 8 == 1)
                            $COLOR[1] = ($COLOR[1] & 0x40) >> 6;
                        elseif (($P * 8) % 8 == 2)
                            $COLOR[1] = ($COLOR[1] & 0x20) >> 5;
                        elseif (($P * 8) % 8 == 3)
                            $COLOR[1] = ($COLOR[1] & 0x10) >> 4;
                        elseif (($P * 8) % 8 == 4)
                            $COLOR[1] = ($COLOR[1] & 0x8) >> 3;
                        elseif (($P * 8) % 8 == 5)
                            $COLOR[1] = ($COLOR[1] & 0x4) >> 2;
                        elseif (($P * 8) % 8 == 6)
                            $COLOR[1] = ($COLOR[1] & 0x2) >> 1;
                        elseif (($P * 8) % 8 == 7)
                            $COLOR[1] = ($COLOR[1] & 0x1);
                        $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                        break;
                    default:
                        return false;
                        break;
                }

                imagesetpixel($res, $X, $Y, $COLOR[1]);
                $X++;
                $P += $BMP['bytes_per_pixel'];
            }
            $Y--;
            $P+=$BMP['decal'];
        }
        fclose($f1);
        return $res;
    }
}

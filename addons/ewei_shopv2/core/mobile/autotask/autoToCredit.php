<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class AutoToCredit_EweiShopV2Page extends Page
{

    public function main()
    {
        ignore_user_abort(TRUE);
        set_time_limit(0);
        $requestUrl = 'http://'.$_SERVER['HTTP_HOST']."/app/index.php?i=1&c=entry&m=ewei_shopv2&do=mobile&r=autotask.toCredit";
        $this->_curl($requestUrl);

        echo 'finish';
    }


    function _curl($url, $data=null, $timeout=0, $isProxy=false){

        $curl = curl_init();

        if($isProxy){   //是否设置代理

            $proxy = "127.0.0.1";   //代理IP

            $proxyport = "8001";   //代理端口

            curl_setopt($curl, CURLOPT_PROXY, $proxy.":".$proxyport);

        }

        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if(!empty($data)){

            curl_setopt($curl, CURLOPT_POST, 1);

            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            curl_setopt($curl, CURLOPT_HTTPHEADER, array(

                    "cache-control: no-cache",

                    "content-type: application/json",)

            );

        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        if ($timeout > 0) { //超时时间秒

            curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);

        }

        $output = curl_exec($curl);

        $error = curl_errno($curl);

        curl_close($curl);

        if($error){

            return false;

        }

        return $output;

    }
}


?>

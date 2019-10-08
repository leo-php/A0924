<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}


class YetModel extends PluginModel
{

    public function getSet($uniacid = 0)
    {
        $set = parent::getSet($uniacid);
        return $set;
    }

    public function status() {
        $set = $this->getSet();
        return empty($set['status']) ? false : true;
    }

}
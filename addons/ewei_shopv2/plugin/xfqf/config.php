<?php
if(!defined('IN_IA')) {
     exit('Access Denied');
}

return array(
    'version'=>'0.1',
    'id'=>'xfqf',
    'name'=>'消费全返',
    'v3'      => true,
    'menu'    => array(
        'plugincom' => 1,
        'icon'      => 'page',
        'items'     => array(
            array('title' => '会员列表', 'route' => '/'),
            array('title' => '全返日志', 'route' => 'log'),
            array(
                'title' => '设置',
                'route' => 'set',
                'items' => array(
                    array(
                        'title' => '基础设置',
                    ),
                )
            ),
        )
    )
);

<?php
return [
    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module'         => 'api',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Member',
    // 默认操作名
    'default_action'         => 'login',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller' =>   'EmptyController',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,
];

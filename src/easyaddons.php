<?php

// 插件框架独立配置，系统公共配置（如 version）仍保留在业务配置中。
return [
    // 调试模式下是否允许未知来源的插件包
    'unknownsources'      => true,
    // 启用或禁用插件时是否备份将被覆盖的全局文件
    'backup_global_files' => true,
    // 是否启用纯净模式，同步插件文件到系统目录
    'addon_pure_mode'     => true,
    // 是否启用插件授权校验
    'addon_auth_check'    => false,
    // 插件市场接口地址，留空时回退到旧的 rocket.api_url
    'api_url'             => null,
];

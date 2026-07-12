<?php
include __DIR__ . "/core/common.php";

// 检查系统是否安装
if (!file_exists(__DIR__ . "/core/install/install.lock")) {
    alert("未安装系统,正在前往安装...", "core/install/install.php");
    exit;
}

// 检查登录状态
if (!is_admin()) {
    include __DIR__ . "/template/login.html";
    exit;
}

// 已登录, 输出后台管理面板
include __DIR__ . "/template/admin.html";
<?php
include dirname(__DIR__) . "/common.php";

if (file_exists(__DIR__ . "/install.lock")) {
    die("系统已安装");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = $_REQUEST["admin"]??"";
    $password = $_REQUEST["password"]??"";
    
    $configFile = dirname(__DIR__,3) . "/config.json";
    
    // 这波神了
    if (!is_file($configFile)) {
        $json = [
            "bot" => [],
            "account" => []
        ];
    } else {
        $configContent = file_get_contents($configFile);
        $json = json_decode($configContent,true);
    }
    
    // 添加信息
    $json["account"]["admin"] = $admin;
    $json["account"]["password"] = $password;
    
    // 存回去
    $JSON = json_encode($json,320 | JSON_PRETTY_PRINT);
    file_put_contents($configFile,$JSON);
    file_put_contents(__DIR__."/install.lock","1");
    json(200,"安装成功");
    
}

// 引入页面
include __DIR__ . "/install.html";
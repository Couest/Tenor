<?php

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: admin/index.php');
    exit;
}

// 加载函数
include(__DIR__ . "/function/function.php");
include(__DIR__ . "/function/plugin.php");
include(__DIR__ . "/function/func.php");


// 元数据
$raw = file_get_contents("php://input");
if (empty($raw)) exit;
$data = json_decode($raw,true);

// 获取匹配项
$AppId = getallheaders()["X-Bot-Appid"];
$account = file_get_contents(__DIR__."/config.json");
$account = json_decode($account,true)["bot"];
foreach ($account as $value) {
    if ($value["appid"] == $AppId) {
        $Account = $value;
        break;
    }
}
if (!$Account) exit;

// 检测bot开关
if (!$Account["status"]) exit;

// 检查配置文件
check_file($Account["id"]);

// 定义常量
define("AppID",$Account["appid"]);
define("Secret",$Account["secret"]);

// 记录日志
wlog($Account["id"],$raw);

// 处理签名
if ($data["op"] == 13) qq_plat_sign($data,Secret);

// 关闭连接
close_php();


// 加载函数库
func::run($Account["id"]);
// 加载插件
plugin::run($Account["id"],$data);

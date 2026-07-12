<?php

// 统一 session 启动（仅启动一次）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * 跳转并带有提示信息
 */
function alert($msg, $url) {
    $alert = '<script>';
    $alert .= 'alert("' . $msg . '");';
    $alert .= 'window.location.href="' . $url . '";';
    $alert .= '</script>';
    die($alert);
}

/*
 * 跳转到某一链接
 */
function jump($url) {
    header('Location: ' . $url);
}

/*
 * json 输出数据
 */
function json($code, $msg, $data = []) {
    $json = [
        "code" => $code,
        "msg"  => $msg,
        "data" => $data
    ];
    die(json_encode($json, 320 | JSON_PRETTY_PRINT));
}

/*
 * 登录状态
 */
function is_admin() {
    return isset($_SESSION["status"]) && $_SESSION["status"] === "Tenor";
}

/*
 * 获取配置
 */
function get_config() {
    static $config = null;
    if ($config === null) {
        $configFile = dirname(__DIR__, 2) . "/config.json";
        if (is_file($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        } else {
            $config = ["bot" => [], "account" => []];
        }
    }
    return $config;
}

/*
 * 保存配置
 */
function save_config($config) {
    $configFile = dirname(__DIR__, 2) . "/config.json";
    $json = json_encode($config, 320 | JSON_PRETTY_PRINT);
    file_put_contents($configFile, $json);
}

/*
 * 获取 bot 根目录
 */
function get_bot_dir($botId = "") {
    if (empty($botId)) {
        $config = get_config();
        $bots = $config["bot"] ?? [];
        if (empty($bots)) return dirname(__DIR__, 2) . "/default";
        $botId = $bots[0]["id"];
    }
    return dirname(__DIR__, 2) . "/" . $botId;
}

/*
 * 获取插件目录
 */
function get_plugin_dir($botId = "") {
    return get_bot_dir($botId) . "/plugin";
}

/*
 * 获取函数库目录
 */
function get_func_dir($botId = "") {
    return get_bot_dir($botId) . "/function";
}

/*
 * 获取日志目录
 */
function get_log_dir($botId = "") {
    return get_bot_dir($botId) . "/log";
}

/*
 * 获取系统信息（仅基本标识，不访问 /proc 等受 open_basedir 限制的路径）
 */
function get_system_info() {
    return [
        "os"       => php_uname('s') . ' ' . php_uname('r'),
        "hostname" => php_uname('n'),
        "php"      => phpversion(),
    ];
}

/*
 * 格式化字节
 */
function format_bytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
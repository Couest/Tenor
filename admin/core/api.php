<?php
include __DIR__ . "/common.php";

$type = $_REQUEST["type"] ?? "";
if (empty($type)) json(-1, "缺少参数");

// 大部分接口需要 bot_id，用辅助函数获取
function get_bot_id() {
    $botId = $_REQUEST["bot_id"] ?? "";
    if (empty($botId)) {
        $config = get_config();
        $bots = $config["bot"] ?? [];
        // 找第一个启用的 bot
        foreach ($bots as $b) { if (!empty($b["status"])) { $botId = $b["id"]; break; } }
        if (empty($botId) && !empty($bots)) $botId = $bots[0]["id"];
    }
    return $botId;
}

function get_bot_by_id($botId) {
    $config = get_config();
    foreach (($config["bot"] ?? []) as $b) {
        if ($b["id"] === $botId) return $b;
    }
    return null;
}

switch ($type) {

    // ==================== 登录 ====================
    case "login":
        $admin    = $_REQUEST["admin"] ?? "";
        $password = $_REQUEST["password"] ?? "";
        if (empty($admin) || empty($password)) json(-1, "缺少参数");
        $config  = get_config();
        $account = $config["account"];
        if ($admin == $account["admin"] && $password == $account["password"]) {
            $_SESSION["status"] = "Tenor";
            json(200, "登录成功");
        }
        json(-1, "账号或密码错误");

    // ==================== 退出 ====================
    case "logout":
        if (is_admin()) { unset($_SESSION["status"]); json(200, "已退出登录"); }
        json(-1, "未登录");

    // ==================== 修改密码 ====================
    case "setAdmin":
        if (!is_admin()) json(-1, "未登录");
        $admin    = $_REQUEST["admin"] ?? "";
        $password = $_REQUEST["password"] ?? "";
        if (empty($admin) || empty($password)) json(-1, "缺少参数");
        $config = get_config();
        $config["account"]["admin"]    = $admin;
        $config["account"]["password"] = $password;
        save_config($config);
        json(200, "更改成功");

    // ==================== Bot 信息 ====================
    case "botInfo":
        if (!is_admin()) json(-1, "未登录");
        $botId = get_bot_id();
        $bot = get_bot_by_id($botId);
        if (!$bot) json(-1, "Bot 不存在");
        $token = get_bot_token($bot["appid"], $bot["secret"]);
        if (!$token) json(-1, "获取 Token 失败");
        $result = bot_api("/users/@me", "GET", $token, []);
        $res = json_decode($result, true);
        json(200, "", [
            "id"       => $res["id"] ?? "",
            "username" => $res["username"] ?? "",
            "avatar"   => $res["avatar"] ?? "",
        ]);

    // ==================== 系统信息 ====================
    case "systemInfo":
        if (!is_admin()) json(-1, "未登录");
        json(200, "", get_system_info());

    // ==================== 仪表盘 ====================
    case "dashboard":
        if (!is_admin()) json(-1, "未登录");
        $botId = get_bot_id();

        $config    = get_config();
        $botCount  = count($config["bot"] ?? []);
        $botActive = 0;
        foreach (($config["bot"] ?? []) as $b) {
            if (!empty($b["status"])) $botActive++;
        }

        $pluginDir  = get_plugin_dir($botId);
        $plugins    = is_dir($pluginDir) ? glob($pluginDir . "/*") : [];
        $pluginCnt  = 0;
        foreach ($plugins as $p) {
            if (is_file($p . "/info.json")) $pluginCnt++;
        }

        $funcDir  = get_func_dir($botId);
        $funcs    = is_dir($funcDir) ? glob($funcDir . "/*") : [];
        $funcCnt  = 0;
        foreach ($funcs as $f) {
            if (is_file($f . "/info.json")) $funcCnt++;
        }

        // 今日统计
        $logDir = get_log_dir($botId);
        $today  = date("Y-m-d") . ".log";
        $todayFile = $logDir . "/" . $today;

        $groupMsgCount   = 0;
        $c2cMsgCount     = 0;
        $groupIds        = [];
        $userIds         = [];
        $groupUsage      = [];
        $userUsage       = [];
        $userNames       = [];
        $hourlyData      = array_fill(0, 24, 0);

        $eventCount      = 0;

        if (is_file($todayFile)) {
            $lines = file($todayFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $d = json_decode($line, true);
                if (!$d) continue;
                $t    = $d["t"] ?? "";
                $data = $d["d"] ?? [];

                if ($t === "GROUP_MESSAGE_CREATE") {
                    $groupMsgCount++;
                    $gid = $data["group_id"] ?? "";
                    $uid = $data["author"]["id"] ?? "";
                    $uname = $data["author"]["username"] ?? "";
                    if ($gid) { $groupIds[$gid] = true; $groupUsage[$gid] = ($groupUsage[$gid] ?? 0) + 1; }
                    if ($uid) { $userIds[$uid] = true; $userUsage[$uid] = ($userUsage[$uid] ?? 0) + 1; if (!empty($uname)) $userNames[$uid] = $uname; }
                    $ts = $data["timestamp"] ?? "";
                    if ($ts) { $h = intval(date("G", strtotime($ts))); $hourlyData[$h]++; }
                }
                if ($t === "C2C_MESSAGE_CREATE") {
                    $c2cMsgCount++;
                    $uid = $data["author"]["id"] ?? "";
                    $uname = $data["author"]["username"] ?? "";
                    if ($uid) { $userIds[$uid] = true; $userUsage[$uid] = ($userUsage[$uid] ?? 0) + 1; if (!empty($uname)) $userNames[$uid] = $uname; }
                }
                // 事件计数
                if (in_array($t, ["GROUP_ADD_ROBOT","GROUP_DEL_ROBOT","GROUP_MEMBER_ADD","GROUP_MEMBER_REMOVE","GROUP_MESSAGE_REACTION_ADD","GROUP_MESSAGE_REACTION_REMOVE","INTERACTION_CREATE","FRIEND_ADD","FRIEND_DEL"])) {
                    $eventCount++;
                }
            }
        }

        arsort($groupUsage);
        arsort($userUsage);
        $topGroups = array_slice(array_map(function($k,$v){return ["id"=>$k,"count"=>$v];}, array_keys($groupUsage), $groupUsage), 0, 5);
        $topUsers  = array_slice(array_map(function($k,$v) use ($userNames) {return ["id"=>$k,"name"=>($userNames[$k]??""),"count"=>$v];}, array_keys($userUsage), $userUsage), 0, 5);

        json(200, "", [
            "botCount"      => $botCount,
            "botActive"     => $botActive,
            "pluginCount"   => $pluginCnt,
            "funcCount"     => $funcCnt,
            "groupMsgCount" => $groupMsgCount,
            "c2cMsgCount"   => $c2cMsgCount,
            "eventCount"    => $eventCount,
            "groupCount"    => count($groupIds),
            "userCount"     => count($userIds),
            "hourlyData"    => $hourlyData,
            "topGroups"     => $topGroups,
            "topUsers"      => $topUsers,
            "system"        => get_system_info(),
        ]);

    // ==================== Bot 列表 ====================
    case "botList":
        if (!is_admin()) json(-1, "未登录");
        $config = get_config();
        json(200, "", $config["bot"] ?? []);

    // ==================== Bot 保存 ====================
    case "botSave":
        if (!is_admin()) json(-1, "未登录");
        $id     = $_REQUEST["id"] ?? "";
        $newId  = $_REQUEST["newId"] ?? "";
        $appid  = $_REQUEST["appid"] ?? "";
        $secret = $_REQUEST["secret"] ?? "";
        $status = $_REQUEST["status"] ?? "1";
        if (empty($appid) || empty($secret)) json(-1, "缺少参数");
        $config = get_config();
        if (empty($id)) {
            // 新建，需要 newId
            if (empty($newId)) json(-1, "缺少 Bot ID");
            // 检查重复
            foreach (($config["bot"] ?? []) as $b) { if ($b["id"] === $newId) json(-1, "Bot ID 已存在"); }
            $config["bot"][] = ["id" => $newId, "status" => (bool)$status, "appid" => $appid, "secret" => $secret];
        } else {
            $found = false;
            foreach ($config["bot"] as &$b) {
                if ($b["id"] === $id) { $b["appid"] = $appid; $b["secret"] = $secret; $b["status"] = (bool)$status; $found = true; break; }
            }
            unset($b);
            if (!$found) json(-1, "Bot 不存在");
        }
        save_config($config);
        json(200, "保存成功");

    // ==================== Bot 启停 ====================
    case "botToggle":
        if (!is_admin()) json(-1, "未登录");
        $id = $_REQUEST["id"] ?? "";
        $status = $_REQUEST["status"] ?? "";
        if (empty($id)) json(-1, "缺少参数");
        $config = get_config();
        foreach ($config["bot"] as &$b) {
            if ($b["id"] === $id) { $b["status"] = (bool)$status; save_config($config); json(200, "操作成功"); }
        }
        json(-1, "Bot 不存在");

    // ==================== Bot 删除 ====================
    case "botDelete":
        if (!is_admin()) json(-1, "未登录");
        $id = $_REQUEST["id"] ?? "";
        if (empty($id)) json(-1, "缺少参数");
        $config = get_config();
        $newBots = [];
        foreach ($config["bot"] as $b) { if ($b["id"] !== $id) $newBots[] = $b; }
        $config["bot"] = $newBots;
        save_config($config);
        json(200, "删除成功");

    // ==================== 插件列表 ====================
    case "pluginList":
        if (!is_admin()) json(-1, "未登录");
        $botId = get_bot_id();
        $dir = get_plugin_dir($botId);
        $list = [];
        if (is_dir($dir)) {
            foreach (glob($dir . "/*") as $p) {
                $infoFile = $p . "/info.json";
                $info = is_file($infoFile) ? json_decode(file_get_contents($infoFile), true) : [];
                $list[] = [
                    "folder"     => basename($p),
                    "name"       => $info["name"] ?? basename($p),
                    "author"     => $info["author"] ?? "",
                    "desc"       => $info["desc"] ?? "",
                    "version"    => $info["version"] ?? "",
                    "github"     => $info["github"] ?? "",
                    "status"     => $info["status"] ?? true,
                    "hasBackend" => is_file($p . "/backend.json"),
                    "hasReadme"  => is_file($p . "/README.md"),
                ];
            }
        }
        json(200, "", $list);

    // ==================== 插件启停 ====================
    case "pluginToggle":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $folder = $_REQUEST["folder"] ?? "";
        $status = $_REQUEST["status"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $infoFile = get_plugin_dir($botId) . "/" . $folder . "/info.json";
        if (!is_file($infoFile)) json(-1, "插件不存在");
        $info = json_decode(file_get_contents($infoFile), true);
        $info["status"] = (bool)$status;
        file_put_contents($infoFile, json_encode($info, 320 | JSON_PRETTY_PRINT));
        json(200, "操作成功");

    // ==================== 插件保存（仅创建） ====================
    case "pluginSave":
        if (!is_admin()) json(-1, "未登录");
        $botId   = get_bot_id();
        $folder  = $_REQUEST["folder"] ?? "";
        $name    = $_REQUEST["name"] ?? "";
        $author  = $_REQUEST["author"] ?? "";
        $desc    = $_REQUEST["desc"] ?? "";
        $version = $_REQUEST["version"] ?? "1.0.0";
        $github  = $_REQUEST["github"] ?? "";
        if (empty($folder) || empty($name)) json(-1, "缺少参数");
        $pluginDir = get_plugin_dir($botId) . "/" . $folder;
        if (is_dir($pluginDir)) json(-1, "插件已存在");
        mkdir($pluginDir, 0777, true);
        $info = ["name" => $name, "author" => $author, "desc" => $desc, "version" => $version, "status" => true];
        if (!empty($github)) $info["github"] = $github;
        file_put_contents($pluginDir . "/info.json", json_encode($info, 320 | JSON_PRETTY_PRINT));
        if (!is_file($pluginDir . "/main.php")) {
            file_put_contents($pluginDir . "/main.php", "<?php\nnamespace {$folder};\n\nclass Main {\n    public function handle(\$data) {\n        // 插件逻辑\n    }\n}\n");
        }
        json(200, "保存成功");

    // ==================== 插件删除 ====================
    case "pluginDelete":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $folder = $_REQUEST["folder"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $pluginDir = get_plugin_dir($botId) . "/" . $folder;
        if (!is_dir($pluginDir)) json(-1, "插件不存在");
        deldir($pluginDir);
        json(200, "删除成功");

    // ==================== 插件上传 ====================
    case "pluginUpload":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $github = $_REQUEST["github"] ?? "";
        $proxy  = $_REQUEST["proxy"] ?? "";
        if (empty($proxy)) { $cfg = get_config(); $proxy = $cfg["proxy"] ?? ""; }
        if (!empty($github)) {
            $archiveUrl = rtrim($github, "/") . "/archive/refs/heads/main.zip";
            if (!empty($proxy)) $archiveUrl = rtrim($proxy, "/") . "/" . $archiveUrl;
            $tmpZip = sys_get_temp_dir() . "/tenor_plugin_" . time() . ".zip";
            $zipContent = @file_get_contents($archiveUrl);
            if ($zipContent === false) json(-1, "下载失败，请检查 GitHub 地址或代理");
            file_put_contents($tmpZip, $zipContent);
        } elseif (isset($_FILES["file"])) {
            $tmpZip = $_FILES["file"]["tmp_name"];
            if ($_FILES["file"]["error"] !== UPLOAD_ERR_OK) json(-1, "上传失败");
        } else { json(-1, "缺少文件或 GitHub 地址"); }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) json(-1, "解压失败");
        $extractDir = sys_get_temp_dir() . "/tenor_extract_" . time();
        mkdir($extractDir, 0777, true);
        $zip->extractTo($extractDir);
        $zip->close();
        $firstDirs = glob($extractDir . "/*", GLOB_ONLYDIR);
        $pluginRoot = count($firstDirs) === 1 ? $firstDirs[0] : $extractDir;
        $infoFile = $pluginRoot . "/info.json";
        if (!is_file($infoFile)) { deldir($extractDir); json(-1, "压缩包中未找到 info.json"); }
        $info = json_decode(file_get_contents($infoFile), true);
        $pluginName = $info["name"] ?? basename($pluginRoot);
        $destDir = get_plugin_dir($botId) . "/" . $pluginName;
        if (!is_dir($destDir)) mkdir($destDir, 0777, true);
        recurse_copy($pluginRoot, $destDir);
        deldir($extractDir);
        if (!empty($github)) @unlink($tmpZip);
        json(200, "安装成功");

    // ==================== 插件设置 ====================
    case "pluginSettings":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $folder = $_REQUEST["folder"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $backendFile = get_plugin_dir($botId) . "/" . $folder . "/backend.json";
        $values = $_REQUEST["values"] ?? "";
        if (!empty($values)) {
            $values = json_decode($values, true);
            if ($values === null) json(-1, "数据格式错误");
            if (!is_file($backendFile)) json(-1, "backend.json 不存在");
            $backend = json_decode(file_get_contents($backendFile), true);
            update_values($backend, $values);
            file_put_contents($backendFile, json_encode($backend, 320 | JSON_PRETTY_PRINT));
            json(200, "保存成功");
        } else {
            if (!is_file($backendFile)) json(-1, "backend.json 不存在");
            $backend = json_decode(file_get_contents($backendFile), true);
            json(200, "", $backend);
        }

    // ==================== 插件文档 ====================
    case "pluginReadme":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $folder = $_REQUEST["folder"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $readmeFile = get_plugin_dir($botId) . "/" . $folder . "/README.md";
        if (!is_file($readmeFile)) json(-1, "README.md 不存在");
        json(200, "", ["content" => file_get_contents($readmeFile)]);

    // ==================== 检查更新 ====================
    case "checkUpdate":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $utype  = $_REQUEST["updateType"] ?? "plugin";
        $folder = $_REQUEST["folder"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $baseDir = $utype === "plugin" ? get_plugin_dir($botId) : get_func_dir($botId);
        $infoFile = $baseDir . "/" . $folder . "/info.json";
        if (!is_file($infoFile)) json(-1, "不存在");
        $local = json_decode(file_get_contents($infoFile), true);
        $github = $local["github"] ?? "";
        if (empty($github)) json(-1, "未配置 GitHub 地址");
        $proxy = $_REQUEST["proxy"] ?? "";
        if (empty($proxy)) { $cfg = get_config(); $proxy = $cfg["proxy"] ?? ""; }
        $rawUrl = rtrim($github, "/") . "/raw/main/info.json";
        if (!empty($proxy)) $rawUrl = rtrim($proxy, "/") . "/" . $rawUrl;
        $ctx = stream_context_create(["http" => ["timeout" => 10]]);
        $remoteJson = @file_get_contents($rawUrl, false, $ctx);
        if ($remoteJson === false) json(-1, "获取远程信息失败，请检查网络或代理");
        $remote = json_decode($remoteJson, true);
        if (!$remote) json(-1, "远程 info.json 格式错误");
        $localVer  = $local["version"] ?? "0.0.0";
        $remoteVer = $remote["version"] ?? "0.0.0";
        $hasUpdate = version_compare($remoteVer, $localVer, ">");
        json(200, "", [
            "hasUpdate"  => $hasUpdate,
            "localVer"   => $localVer,
            "remoteVer"  => $remoteVer,
            "remoteName" => $remote["name"] ?? "",
            "remoteDesc" => $remote["desc"] ?? "",
        ]);

    // ==================== 函数库列表 ====================
    case "funcList":
        if (!is_admin()) json(-1, "未登录");
        $botId = get_bot_id();
        $dir = get_func_dir($botId);
        $list = [];
        if (is_dir($dir)) {
            foreach (glob($dir . "/*") as $f) {
                $infoFile = $f . "/info.json";
                $info = is_file($infoFile) ? json_decode(file_get_contents($infoFile), true) : [];
                $list[] = [
                    "folder"  => basename($f),
                    "name"    => $info["name"] ?? basename($f),
                    "author"  => $info["author"] ?? "",
                    "desc"    => $info["desc"] ?? "",
                    "version" => $info["version"] ?? "",
                    "github"  => $info["github"] ?? "",
                    "status"  => $info["status"] ?? true,
                    "hasFunc" => is_file($f . "/func.json"),
                ];
            }
        }
        json(200, "", $list);

    // ==================== 函数库启停 ====================
    case "funcToggle":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $folder = $_REQUEST["folder"] ?? "";
        $status = $_REQUEST["status"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $infoFile = get_func_dir($botId) . "/" . $folder . "/info.json";
        if (!is_file($infoFile)) json(-1, "函数库不存在");
        $info = json_decode(file_get_contents($infoFile), true);
        $info["status"] = (bool)$status;
        file_put_contents($infoFile, json_encode($info, 320 | JSON_PRETTY_PRINT));
        json(200, "操作成功");

    // ==================== 函数库保存（仅创建） ====================
    case "funcSave":
        if (!is_admin()) json(-1, "未登录");
        $botId   = get_bot_id();
        $folder  = $_REQUEST["folder"] ?? "";
        $name    = $_REQUEST["name"] ?? "";
        $author  = $_REQUEST["author"] ?? "";
        $desc    = $_REQUEST["desc"] ?? "";
        $version = $_REQUEST["version"] ?? "1.0.0";
        $github  = $_REQUEST["github"] ?? "";
        if (empty($folder) || empty($name)) json(-1, "缺少参数");
        $funcDir = get_func_dir($botId) . "/" . $folder;
        if (is_dir($funcDir)) json(-1, "函数库已存在");
        mkdir($funcDir, 0777, true);
        $info = ["name" => $name, "author" => $author, "desc" => $desc, "version" => $version, "status" => true];
        if (!empty($github)) $info["github"] = $github;
        file_put_contents($funcDir . "/info.json", json_encode($info, 320 | JSON_PRETTY_PRINT));
        if (!is_file($funcDir . "/main.php")) {
            file_put_contents($funcDir . "/main.php", "<?php\n// 函数库 {$name}\n");
        }
        json(200, "保存成功");

    // ==================== 函数库删除 ====================
    case "funcDelete":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $folder = $_REQUEST["folder"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $funcDir = get_func_dir($botId) . "/" . $folder;
        if (!is_dir($funcDir)) json(-1, "函数库不存在");
        deldir($funcDir);
        json(200, "删除成功");

    // ==================== 函数库上传 ====================
    case "funcUpload":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $github = $_REQUEST["github"] ?? "";
        $proxy  = $_REQUEST["proxy"] ?? "";
        if (empty($proxy)) { $cfg = get_config(); $proxy = $cfg["proxy"] ?? ""; }
        if (!empty($github)) {
            $archiveUrl = rtrim($github, "/") . "/archive/refs/heads/main.zip";
            if (!empty($proxy)) $archiveUrl = rtrim($proxy, "/") . "/" . $archiveUrl;
            $tmpZip = sys_get_temp_dir() . "/tenor_func_" . time() . ".zip";
            $zipContent = @file_get_contents($archiveUrl);
            if ($zipContent === false) json(-1, "下载失败");
            file_put_contents($tmpZip, $zipContent);
        } elseif (isset($_FILES["file"])) {
            $tmpZip = $_FILES["file"]["tmp_name"];
            if ($_FILES["file"]["error"] !== UPLOAD_ERR_OK) json(-1, "上传失败");
        } else { json(-1, "缺少文件或 GitHub 地址"); }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) json(-1, "解压失败");
        $extractDir = sys_get_temp_dir() . "/tenor_extract_" . time();
        mkdir($extractDir, 0777, true);
        $zip->extractTo($extractDir);
        $zip->close();
        $firstDirs = glob($extractDir . "/*", GLOB_ONLYDIR);
        $funcRoot = count($firstDirs) === 1 ? $firstDirs[0] : $extractDir;
        $infoFile = $funcRoot . "/info.json";
        if (!is_file($infoFile)) { deldir($extractDir); json(-1, "压缩包中未找到 info.json"); }
        $info = json_decode(file_get_contents($infoFile), true);
        $funcName = $info["name"] ?? basename($funcRoot);
        $destDir = get_func_dir($botId) . "/" . $funcName;
        if (!is_dir($destDir)) mkdir($destDir, 0777, true);
        recurse_copy($funcRoot, $destDir);
        deldir($extractDir);
        if (!empty($github)) @unlink($tmpZip);
        json(200, "安装成功");

    // ==================== 函数库文档 ====================
    case "funcDetail":
        if (!is_admin()) json(-1, "未登录");
        $botId  = get_bot_id();
        $folder = $_REQUEST["folder"] ?? "";
        if (empty($folder)) json(-1, "缺少参数");
        $funcFile = get_func_dir($botId) . "/" . $folder . "/func.json";
        if (!is_file($funcFile)) json(-1, "func.json 不存在");
        $content = json_decode(file_get_contents($funcFile), true);
        json(200, "", $content);

    // ==================== 日志群组列表 ====================
    case "logGroups":
        if (!is_admin()) json(-1, "未登录");
        $botId = get_bot_id();
        $date = $_REQUEST["date"] ?? date("Y-m-d");
        $logFile = get_log_dir($botId) . "/" . $date . ".log";
        $groups = [];
        $c2cUsers = [];
        $hasError = false;
        $c2cCount = 0;
        $events = []; // 事件统计
        $eventTypes = ["robot_add" => "机器人入群", "robot_remove" => "机器人退群", "member_add" => "成员加入", "member_remove" => "成员退出", "reaction_add" => "表情表态", "reaction_remove" => "取消表态", "interaction" => "互动事件", "friend_add" => "好友添加", "friend_del" => "好友删除"];
        foreach ($eventTypes as $ek => $ev) { $events[$ek] = ["type" => $ek, "name" => $ev, "count" => 0]; }
        if (is_file($logFile)) {
            $handle = fopen($logFile, "r");
            $lineCount = 0;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;
                $lineCount++;
                if ($lineCount > 5000) break;
                $d = json_decode($line, true);
                if (!$d) continue;
                $t    = $d["t"] ?? "";
                $data = $d["d"] ?? [];
                if (isset($data["type"]) && $data["type"] === "error") { $hasError = true; continue; }
                // 提取 group_id（群消息用 group_id，事件用 group_openid）
                $gid = $data["group_id"] ?? $data["group_openid"] ?? "";
                if (strpos($t, "GROUP_") === 0 && !empty($gid)) {
                    if (!isset($groups[$gid])) {
                        $groups[$gid] = ["group_id" => $gid, "group_name" => "群聊 " . substr($gid, 0, 12) . "...", "message_count" => 0, "last_time" => ""];
                    }
                    $groups[$gid]["message_count"]++;
                    if (!empty($data["timestamp"])) $groups[$gid]["last_time"] = $data["timestamp"];
                }
                if ($t === "C2C_MESSAGE_CREATE") {
                    $c2cCount++;
                    $uid = $data["author"]["id"] ?? "";
                    if ($uid) {
                        if (!isset($c2cUsers[$uid])) {
                            $c2cUsers[$uid] = ["user_id" => $uid, "user_name" => $data["author"]["username"] ?? $uid, "message_count" => 0, "last_time" => ""];
                        }
                        $c2cUsers[$uid]["message_count"]++;
                        if (!empty($data["timestamp"])) $c2cUsers[$uid]["last_time"] = $data["timestamp"];
                    }
                }
                // 事件统计
                if ($t === "GROUP_ADD_ROBOT") $events["robot_add"]["count"]++;
                elseif ($t === "GROUP_DEL_ROBOT") $events["robot_remove"]["count"]++;
                elseif ($t === "GROUP_MEMBER_ADD") $events["member_add"]["count"]++;
                elseif ($t === "GROUP_MEMBER_REMOVE") $events["member_remove"]["count"]++;
                elseif ($t === "GROUP_MESSAGE_REACTION_ADD") $events["reaction_add"]["count"]++;
                elseif ($t === "GROUP_MESSAGE_REACTION_REMOVE") $events["reaction_remove"]["count"]++;
                elseif ($t === "INTERACTION_CREATE") $events["interaction"]["count"]++;
                elseif ($t === "FRIEND_ADD") $events["friend_add"]["count"]++;
                elseif ($t === "FRIEND_DEL") $events["friend_del"]["count"]++;
            }
            fclose($handle);
        }
        usort($groups, function ($a, $b) { return $b["message_count"] - $a["message_count"]; });
        $c2cList = array_values($c2cUsers);
        usort($c2cList, function ($a, $b) { return $b["message_count"] - $a["message_count"]; });
        $eventsList = array_values(array_filter($events, function($e) { return $e["count"] > 0; }));
        json(200, "", ["groups" => array_values($groups), "c2cUsers" => $c2cList, "c2cCount" => $c2cCount, "events" => $eventsList, "hasError" => $hasError]);

    // ==================== 日志列表 ====================
    case "logList":
        if (!is_admin()) json(-1, "未登录");
        $botId   = get_bot_id();
        $date    = $_REQUEST["date"] ?? date("Y-m-d");
        $groupId = $_REQUEST["group_id"] ?? "";
        $offset  = intval($_REQUEST["offset"] ?? 0);
        $limit   = intval($_REQUEST["limit"] ?? 50);
        $ltype   = $_REQUEST["logType"] ?? "message";
        $c2cUser = $_REQUEST["c2c_user"] ?? "";

        $logFile = get_log_dir($botId) . "/" . $date . ".log";
        $filtered = [];

        if (is_file($logFile)) {
            $handle = fopen($logFile, "r");
            $lineCount = 0;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;
                $lineCount++;
                if ($lineCount > 5000) break;
                $d = json_decode($line, true);
                if (!$d) continue;
                $t    = $d["t"] ?? "";
                $data = $d["d"] ?? [];

                if ($ltype === "error") {
                    if (isset($data["type"]) && $data["type"] === "error") {
                        $filtered[] = ["type" => "error", "msg" => $data["msg"] ?? "", "error" => $data["error"] ?? "", "time" => $data["time"] ?? ""];
                    }
                } elseif ($ltype === "c2c") {
                    if ($t !== "C2C_MESSAGE_CREATE") continue;
                    if (!empty($c2cUser)) {
                        $uid = $data["author"]["id"] ?? "";
                        if ($uid !== $c2cUser) continue;
                    }
                    $parsed = parse_log_message($d);
                    if ($parsed) $filtered[] = $parsed;
                } elseif ($ltype === "events") {
                    // 只显示事件（非消息、非错误）
                    $parsed = parse_log_message($d);
                    if (!$parsed) continue;
                    $pt = $parsed["type"];
                    if (!in_array($pt, ["robot_add","robot_remove","member_add","member_remove","reaction_add","reaction_remove","interaction","friend_add","friend_del"])) continue;
                    $filtered[] = $parsed;
                } else {
                    // message 模式：显示群消息 + 该群的所有事件
                    if (empty($groupId)) continue;
                    $gid = $data["group_id"] ?? $data["group_openid"] ?? "";
                    if ($gid !== $groupId) continue;
                    $parsed = parse_log_message($d);
                    if ($parsed) $filtered[] = $parsed;
                }
            }
            fclose($handle);
        }

        $total = count($filtered);
        $filtered = array_slice($filtered, $offset, $limit);

        json(200, "", [
            "messages" => array_values($filtered),
            "total"    => $total,
            "offset"   => $offset,
            "limit"    => $limit,
        ]);

    // ==================== 发送消息 ====================
    case "sendMessage":
        if (!is_admin()) json(-1, "未登录");
        $groupId  = $_REQUEST["group_id"] ?? "";
        $c2cUser  = $_REQUEST["c2c_user"] ?? "";
        $content  = $_REQUEST["content"] ?? "";
        $botId    = $_REQUEST["bot_id"] ?? "";
        $msgType  = intval($_REQUEST["msg_type"] ?? 0);
        $mediaJson = $_REQUEST["media"] ?? "";
        $markdownJson = $_REQUEST["markdown"] ?? "";
        $isC2c    = !empty($c2cUser);

        if (empty($groupId) && empty($c2cUser)) json(-1, "缺少目标");
        if ($msgType !== 0 && empty($content) && empty($mediaJson) && empty($markdownJson)) json(-1, "缺少消息内容");

        $config = get_config();
        $bot = null;
        foreach ($config["bot"] as $b) {
            if (empty($botId)) { if (!empty($b["status"])) { $bot = $b; break; } }
            else { if ($b["id"] === $botId) { $bot = $b; break; } }
        }
        if (!$bot) json(-1, "没有可用的 Bot");
        $token = get_bot_token($bot["appid"], $bot["secret"]);
        if (!$token) json(-1, "获取 Bot Token 失败");

        $body = ["msg_type" => $msgType, "msg_seq" => rand(1, 99999)];
        if ($msgType === 0) {
            $body["content"] = $content;
        } elseif ($msgType === 2) {
            $body["markdown"] = !empty($markdownJson) ? json_decode($markdownJson, true) : ["content" => $content];
        } elseif ($msgType === 7) {
            if (!empty($mediaJson)) $body["media"] = json_decode($mediaJson, true);
            if (!empty($content)) $body["content"] = $content;
        }

        // 目标路径
        $path = $isC2c
            ? "/v2/users/" . $c2cUser . "/messages"
            : "/v2/groups/" . $groupId . "/messages";

        $result = bot_api($path, "POST", $token, $body);
        $res = json_decode($result, true);
        if ($res && isset($res["id"])) json(200, "发送成功", $res);
        else json(-1, "发送失败: " . ($res["message"] ?? $result));

    // ==================== 上传富媒体 ====================
    case "uploadMedia":
        if (!is_admin()) json(-1, "未登录");
        $groupId  = $_REQUEST["group_id"] ?? "";
        $c2cUser  = $_REQUEST["c2c_user"] ?? "";
        $fileType = intval($_REQUEST["file_type"] ?? 1);
        $url      = $_REQUEST["url"] ?? "";
        $botId    = $_REQUEST["bot_id"] ?? "";
        $isC2c    = !empty($c2cUser);

        if (empty($groupId) && empty($c2cUser)) json(-1, "缺少目标");
        if (empty($url) && !isset($_FILES["file"])) json(-1, "缺少文件或 URL");

        $config = get_config();
        $bot = null;
        foreach ($config["bot"] as $b) {
            if (empty($botId)) { if (!empty($b["status"])) { $bot = $b; break; } }
            else { if ($b["id"] === $botId) { $bot = $b; break; } }
        }
        if (!$bot) json(-1, "没有可用的 Bot");
        $token = get_bot_token($bot["appid"], $bot["secret"]);
        if (!$token) json(-1, "获取 Bot Token 失败");

        if (!empty($url)) {
            $postData = ["file_type" => $fileType, "url" => $url];
        } elseif (isset($_FILES["file"])) {
            $tmpPath = $_FILES["file"]["tmp_name"];
            $fileData = base64_encode(file_get_contents($tmpPath));
            $postData = ["file_type" => $fileType, "file_data" => $fileData];
        } else {
            json(-1, "缺少文件");
        }

        $path = $isC2c
            ? "/v2/users/" . $c2cUser . "/files"
            : "/v2/groups/" . $groupId . "/files";

        $result = bot_api($path, "POST", $token, $postData);
        $res = json_decode($result, true);
        if ($res && !empty($res["file_info"])) json(200, "上传成功", $res);
        else json(-1, "上传失败: " . ($res["message"] ?? $result));

    // ==================== 撤回消息 ====================
    case "recallMessage":
        if (!is_admin()) json(-1, "未登录");
        $groupId  = $_REQUEST["group_id"] ?? "";
        $msgId    = $_REQUEST["msg_id"] ?? "";
        $botId    = $_REQUEST["bot_id"] ?? "";
        if (empty($groupId) || empty($msgId)) json(-1, "缺少参数");
        $config = get_config();
        $bot = null;
        foreach ($config["bot"] as $b) {
            if (empty($botId)) { if (!empty($b["status"])) { $bot = $b; break; } }
            else { if ($b["id"] === $botId) { $bot = $b; break; } }
        }
        if (!$bot) json(-1, "没有可用的 Bot");
        $token = get_bot_token($bot["appid"], $bot["secret"]);
        if (!$token) json(-1, "获取 Bot Token 失败");
        $result = bot_api("/v2/groups/" . $groupId . "/messages/" . $msgId, "DELETE", $token, []);
        $res = json_decode($result, true);
        if ($res && empty($res["message"])) json(200, "撤回成功");
        else json(-1, "撤回失败: " . ($res["message"] ?? ""));

    // ==================== 删除日志 ====================
    case "deleteLog":
        if (!is_admin()) json(-1, "未登录");
        $botId = get_bot_id();
        $date = $_REQUEST["date"] ?? date("Y-m-d");
        $logFile = get_log_dir($botId) . "/" . $date . ".log";
        if (is_file($logFile)) {
            unlink($logFile);
            json(200, "日志已删除");
        }
        json(-1, "日志文件不存在");

    // ==================== 下载日志 ====================
    case "downloadLog":
        if (!is_admin()) json(-1, "未登录");
        $botId = get_bot_id();
        $date = $_REQUEST["date"] ?? date("Y-m-d");
        $logFile = get_log_dir($botId) . "/" . $date . ".log";
        if (!is_file($logFile)) json(-1, "日志文件不存在");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"log_{$date}.log\"");
        header("Content-Length: " . filesize($logFile));
        readfile($logFile);
        exit;

    // ==================== 代理设置 ====================
    case "proxySettings":
        if (!is_admin()) json(-1, "未登录");
        $proxy = $_REQUEST["proxy"] ?? "";
        if (!empty($proxy) || isset($_REQUEST["proxy"])) {
            $config = get_config();
            $config["proxy"] = $proxy;
            save_config($config);
            json(200, "保存成功");
        } else {
            $config = get_config();
            json(200, "", ["proxy" => $config["proxy"] ?? ""]);
        }

    default:
        json(-1, "未知操作");
}

// ==================== 辅助函数 ====================

function deldir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item == "." || $item == "..") continue;
        $path = $dir . "/" . $item;
        is_dir($path) ? deldir($path) : unlink($path);
    }
    rmdir($dir);
}

function recurse_copy($src, $dst) {
    $dir = opendir($src);
    if (!is_dir($dst)) mkdir($dst, 0777, true);
    while (($file = readdir($dir)) !== false) {
        if ($file == "." || $file == "..") continue;
        $srcPath = $src . "/" . $file;
        $dstPath = $dst . "/" . $file;
        is_dir($srcPath) ? recurse_copy($srcPath, $dstPath) : copy($srcPath, $dstPath);
    }
    closedir($dir);
}

function update_values(&$backend, $values) {
    foreach ($backend as $key => &$item) {
        if (isset($values[$key])) {
            if ($item["type"] === "list" && isset($item["items"])) {
                if (is_array($values[$key])) {
                    foreach ($values[$key] as $idx => $subValues) {
                        if (isset($item["items"]) && is_array($item["items"])) update_values($item["items"], $subValues);
                    }
                }
            } else {
                $item["value"] = $values[$key];
            }
        }
    }
    unset($item);
}

function get_bot_token($appid, $secret) {
    $ch = curl_init("https://bots.qq.com/app/getAppAccessToken");
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode(["appId" => $appid, "clientSecret" => $secret]),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data["access_token"] ?? null;
}

function bot_api($path, $method, $token, $body, $withData = false) {
    $ch = curl_init("https://api.sgroup.qq.com" . $path);
    $headers = ["Authorization: QQBot " . $token, "Content-Type: application/json"];
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === "GET") {
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
    } elseif ($method === "DELETE") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        if ($withData && !empty($body)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function parse_log_message($line) {
    $t    = $line["t"] ?? "";
    $data = $line["d"] ?? [];

    if ($t === "GROUP_MESSAGE_CREATE") {
        $author      = $data["author"] ?? [];
        $mentions    = $data["mentions"] ?? [];
        $attachments = $data["attachments"] ?? [];
        $content     = $data["content"] ?? "";
        $msgId       = $data["id"] ?? "";
        $time        = $data["timestamp"] ?? "";
        $gid         = $data["group_id"] ?? "";
        $msgType     = $data["message_type"] ?? 0;

        $content = fix_url_encoding($content);
        if (!empty($attachments)) {
            foreach ($attachments as &$att) { $att["url"] = fix_url_encoding($att["url"] ?? ""); }
            unset($att);
        }

        $type = "text";
        $isMarkdown = false;
        if (!empty($content)) {
            if (strpos($content, '|') !== false && preg_match('/\|[:\s-]+[|:]/', $content)) $isMarkdown = true;
            elseif (preg_match('/\*\*[^*]+\*\*|`[^`]+`|^>\s|^#+\s/m', $content)) $isMarkdown = true;
        }
        if ($isMarkdown) $type = "markdown";
        elseif ($msgType == 2) $type = "markdown";
        elseif ($msgType == 7) $type = "media";
        elseif (!empty($attachments)) {
            $ct = $attachments[0]["content_type"] ?? "";
            if (strpos($ct, "image") !== false) $type = empty($content) ? "image" : "image_text";
            elseif (strpos($ct, "audio") !== false || strpos($ct, "voice") !== false) $type = "voice";
            elseif (strpos($ct, "video") !== false) $type = "video";
        }

        return [
            "type"        => $type,
            "msg_id"      => $msgId,
            "group_id"    => $gid,
            "author_id"   => $author["id"] ?? "",
            "author_name" => $author["username"] ?? "",
            "author_bot"  => $author["bot"] ?? false,
            "content"     => $content,
            "time"        => $time,
            "images"      => $attachments,
            "mentions"    => $mentions,
            "raw"         => json_encode($line, 320),
        ];
    }

    if ($t === "C2C_MESSAGE_CREATE") {
        $author      = $data["author"] ?? [];
        $attachments = $data["attachments"] ?? [];
        $content     = fix_url_encoding($data["content"] ?? "");
        $msgId       = $data["id"] ?? "";
        $time        = $data["timestamp"] ?? "";

        $type = "text";
        $isMarkdown = false;
        if (!empty($content)) {
            if (strpos($content, '|') !== false && preg_match('/\|[:\s-]+[|:]/', $content)) $isMarkdown = true;
            elseif (preg_match('/\*\*[^*]+\*\*|`[^`]+`|^>\s|^#+\s/m', $content)) $isMarkdown = true;
        }
        if ($isMarkdown) $type = "markdown";
        elseif (!empty($attachments)) {
            $ct = $attachments[0]["content_type"] ?? "";
            if (strpos($ct, "image") !== false) $type = empty($content) ? "image" : "image_text";
            elseif (strpos($ct, "audio") !== false || strpos($ct, "voice") !== false) $type = "voice";
            elseif (strpos($ct, "video") !== false) $type = "video";
        }

        return [
            "type"        => $type,
            "msg_id"      => $msgId,
            "group_id"    => "",
            "c2c"         => true,
            "author_id"   => $author["id"] ?? "",
            "author_name" => $author["username"] ?? "",
            "author_bot"  => $author["bot"] ?? false,
            "content"     => $content,
            "time"        => $time,
            "images"      => $attachments,
            "mentions"    => [],
            "raw"         => json_encode($line, 320),
        ];
    }

    if ($t === "GROUP_MEMBER_ADD") {
        return ["type" => "member_add", "group_id" => $data["group_openid"] ?? "", "member_id" => $data["member_openid"] ?? "", "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320)];
    }
    if ($t === "GROUP_MEMBER_REMOVE") {
        return ["type" => "member_remove", "group_id" => $data["group_openid"] ?? "", "member_id" => $data["member_openid"] ?? "", "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320)];
    }

    // 机器人被加入群
    if ($t === "GROUP_ADD_ROBOT") {
        return [
            "type" => "robot_add", "group_id" => $data["group_openid"] ?? "",
            "op_member_id" => $data["op_member_openid"] ?? "",
            "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320),
        ];
    }
    // 机器人被移出群 / 群解散
    if ($t === "GROUP_DEL_ROBOT") {
        return [
            "type" => "robot_remove", "group_id" => $data["group_openid"] ?? "",
            "op_member_id" => $data["op_member_openid"] ?? "",
            "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320),
        ];
    }
    // 表情表态添加
    if ($t === "GROUP_MESSAGE_REACTION_ADD") {
        $emoji = $data["emoji"] ?? [];
        return [
            "type" => "reaction_add", "group_id" => $data["group_openid"] ?? "",
            "message_id" => $data["message_id"] ?? "", "emoji_id" => $emoji["id"] ?? "",
            "emoji_type" => $emoji["type"] ?? 0, "user_id" => $data["user_openid"] ?? "",
            "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320),
        ];
    }
    // 表情表态取消
    if ($t === "GROUP_MESSAGE_REACTION_REMOVE") {
        $emoji = $data["emoji"] ?? [];
        return [
            "type" => "reaction_remove", "group_id" => $data["group_openid"] ?? "",
            "message_id" => $data["message_id"] ?? "", "emoji_id" => $emoji["id"] ?? "",
            "emoji_type" => $emoji["type"] ?? 0, "user_id" => $data["user_openid"] ?? "",
            "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320),
        ];
    }
    // 互动事件（按钮点击等）
    if ($t === "INTERACTION_CREATE") {
        return [
            "type" => "interaction", "group_id" => $data["group_openid"] ?? $data["guild_id"] ?? "",
            "interaction_id" => $data["id"] ?? "", "app_id" => $data["application_id"] ?? "",
            "interaction_type" => $data["type"] ?? 0, "data" => $data["data"] ?? [],
            "time" => date("c", $data["timestamp"] ?? time()), "raw" => json_encode($line, 320),
        ];
    }
    // 好友添加
    if ($t === "FRIEND_ADD") {
        return [
            "type" => "friend_add", "user_id" => $data["openid"] ?? "",
            "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320),
        ];
    }
    // 好友删除
    if ($t === "FRIEND_DEL") {
        return [
            "type" => "friend_del", "user_id" => $data["openid"] ?? "",
            "time" => date("c", $data["timestamp"] ?? 0), "raw" => json_encode($line, 320),
        ];
    }

    return null;
}

function fix_url_encoding($str) {
    return str_replace("\\u0026", "&", $str);
}
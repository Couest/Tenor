<?php

class plugin {
    public function run($id,$data) {
        $dir = dirname(__DIR__) . "/{$id}/plugin";
        $list = glob($dir."/*");
        
        foreach ($list as $one) {
            $PluginFile = $one."/info.json";
            if (!is_file($PluginFile)) continue;
            $Plugin = file_get_contents($PluginFile);
            $PluginJson = json_decode($Plugin,true);
            
            if (!$PluginJson["status"]) continue;
            if (!is_file($one."/main.php")) continue;
            
            try {
                (function($file,$id,$data) {
                    $name = basename($file);
                    require_once($file."/main.php");
                    $pluginName = "\\{$name}\\Main";
                    $plugin = new $pluginName();
                    $plugin->handle($data);
                })($one,$id,$data);
            } catch (Throwable $e) {
                $name = basename($one);
                $error = json_encode([
                    "type" => "error",
                    "msg" => "插件({$name})加载失败",
                    "error" => $e->getMessage()." 位置:".$e->getLine(),
                    "time" => time()
                ],320);
                wlog($id,$error);
            }        
        }
    }
}
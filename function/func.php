<?php

class func {
    public function run($id) {
        $dir = dirname(__DIR__) . "/{$id}/function";
        $list = glob($dir."/*");
        
        foreach ($list as $one) {
            $InfoFile = $one."/info.json";
            if (!is_file($InfoFile)) continue;
            $Info = file_get_contents($InfoFile);
            $InfoJson = json_decode($Info,true);
            
            if (!$InfoJson["status"]) continue;
            if (!is_file($one."/main.php")) continue;
            
            try {
                require_once($one."/main.php");
            } catch (Throwable $e) {
                $name = basename($one);
                $error = json_encode([
                    "type" => "error",
                    "msg" => "函数库({$name})加载失败",
                    "error" => $e->getMessage()." 位置:".$e->getLine(),
                    "time" => time()
                ],320);
                wlog($id,$error);
            }        
        }
    }
}
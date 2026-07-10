<?php
namespace Example;

class Main {
    public function handle($data) {
        $msg = trim($data["d"]["content"],"/ ");
        $group = $data["d"]["group_id"];
        $id = $data["d"]["id"];
        
        if ($msg == "测试") {
            $q = new \qqbot(AppID,Secret);
            $q->sendGroup($id,$group,"text","你好");
        }
    }
    
}
<?php

class qqbot {
    private $appid;
    private $secret;
    
    public function __construct($appid,$secret) {
        $this->appid = $appid;
        $this->secret = $secret;
    }
    
    public function BotSign() {
            $url = "https://bots.qq.com/app/getAppAccessToken";
            $appid = $this->appid;
            $secret = $this->secret;
            $json = json_encode([
                "appId" => "{$appid}",
                "clientSecret" => $secret
            ]);
            $header = ['Content-Type: application/json'];
            $fw = curl($url, "POST", $header, $json);
            $fw = json_decode($fw, true);
            $Access = $fw["access_token"];
            return $Access;
    }
    
    public function BOTAPI($Address,$me,$json) {
        $url = "https://api.sgroup.qq.com".$Address;
        $header = [
            "Authorization: QQBot ".$this->BotSign(), 
            'Content-Type: application/json'
        ];
        $json = json_encode($json);
        $curl=curl($url,$me,$header,$json);
        return $curl;
    }
    
    public function sendGroup($id,$group,$type,...$value) {
        switch ($type) {
            case "text":
                $json = [
                    "content" => $value[0],
                    "msg_type" => 0,
                    "msg_seq" => rand(1,99999),
                ];
                if (前缀($id,"ROBOT")) {
                    $json["msg_id"] = $id;
                } else {
                    $json["event_id"] = $id;
                }
                $this->BOTAPI("/v2/groups/{$group}/messages","POST",$json);
        }
    }

}
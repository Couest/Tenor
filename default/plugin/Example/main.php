<?php
namespace Example;

class Main {
    public function handle($data) {
        $msg = trim($data["d"]["content"],"/ ");
        $group = $data["d"]["group_id"];
        $id = $data["d"]["id"];
        
        if ($msg == "测试") {
            $q = new \qqbot(AppID,Secret);
            $button = new \按钮();
            $q->sendGroup($id,$group,text,"你好");
            $q->sendGroup($id,$group,md,"你好",$button->构(
                $button->加(
                    $button->开("回调")
                        ->类型("回调")
                        ->样式(1)
                        ->返("按钮回调测试")
                ),
                $button->加(
                    $button->开("弹窗")
                        ->类型("弹窗")
                        ->样式(1)
                        ->返("哦")
                        ->弹窗("您被QQ诚邀为企鹅追光者","不感兴趣","了解详情")
                )
            ));
            $q->sendGroup($id,$group,md,"小字体",$button->小字体(
                $button->加(
                    $button->开("回调")
                        ->类型("回调")
                        ->样式(1)
                        ->返("按钮回调测试")
                )
            ));
            $q->sendGroup($id,$group,text,$q->BOTAPI("/users/@me","GET",0));
        }
    }
    
}
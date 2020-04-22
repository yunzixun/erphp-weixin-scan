<?php
require( dirname(__FILE__).'/../../../wp-load.php' );
header("Content-type:text/html;charset=utf-8");
$ews = new erphpWeixinScan();
$ews->valid();

class erphpWeixinScan {

    public function valid() {
        $echoStr = $_GET["echostr"];
        if ($echoStr) {
            if ($this->checkSignature()) {
                echo $echoStr;
            }
        } else {
            $this->responseMsg();
            exit;
        }
    }

    public function responseMsg() {
        date_default_timezone_set('Asia/Shanghai');
        global $wpdb, $ews_table;
        //$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $postStr = file_get_contents("php://input");

        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        //$scene_id = str_replace("qrscene_", "", $postObj->EventKey);

        $openid = $postObj->FromUserName;
        $ToUserName = $postObj->ToUserName;
        
        $msgType = $postObj->MsgType;
        $Event = strtolower($postObj->Event);

        if($msgType == 'text'){
            if($postObj->Content == '登录'){

                $exist = $wpdb->get_var("select id from $ews_table where openid='".$openid."'");
                if($exist){
                    $code = rand(10000000,99999999);
                    $result = $wpdb->query("update $ews_table set scene_id = '".$code."', update_time = '".date("Y-m-d H:i:s")."' where id='".$exist."'");
                }else{
                    $access_token = $this->getAccessToken();
                    $userinfo = $this->getUserinfo($openid, $access_token);
                    $code = rand(10000000,99999999);
                    $result = $wpdb->query("insert into $ews_table (scene_id,openid,unionid,access_token,nickname,avatar,create_time,update_time) values('".$code."','".$openid."','".$userinfo['unionid']."','".$access_token."','".$userinfo['nickname']."','".$userinfo['headimgurl']."','".date("Y-m-d H:i:s")."','".date("Y-m-d H:i:s")."')");
                }

                if($result){
                    $content = "验证码：".$code."，5分钟有效～";
                }else{
                    $content = "公众号开了小差，请稍后重试～";
                }

            }else{
                $content = "我太笨，有点不明白您的意思～";  
            }

            $str = $this->sendtext($openid, $ToUserName, $content);
            echo $str;
        }else{
            if($Event == 'subscribe' || $Event == 'scan'){

                $exist = $wpdb->get_var("select id from $ews_table where openid='".$openid."'");
                if($exist){
                    $code = rand(10000000,99999999);
                    $result = $wpdb->query("update $ews_table set scene_id = '".$code."', update_time = '".date("Y-m-d H:i:s")."' where id='".$exist."'");
                }else{
                    $access_token = $this->getAccessToken();
                    $userinfo = $this->getUserinfo($openid, $access_token);
                    $code = rand(10000000,99999999);
                    $result = $wpdb->query("insert into $ews_table (scene_id,openid,unionid,access_token,nickname,avatar,create_time,update_time) values('".$code."','".$openid."','".$userinfo['unionid']."','".$access_token."','".$userinfo['nickname']."','".$userinfo['headimgurl']."','".date("Y-m-d H:i:s")."','".date("Y-m-d H:i:s")."')");
                }

                if($result){
                    $content = "验证码：".$code."，5分钟有效～";
                }else{
                    $content = "公众号开了小差，请稍后重试～";
                }

                $str = $this->sendtext($openid, $ToUserName, $content);
                echo $str; 
                
            }
        }        
        
    }

    public function getUserinfo($openid, $access_token) {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
        $res = $this->get_curl($url);
        return json_decode($res,true);
    }


    public function getAccessToken() {
        global $ews_weixin_appid, $ews_weixin_appsecret;
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $ews_weixin_appid . "&secret=" . $ews_weixin_appsecret . "";
        $ch = curl_init();
        $headers[] = 'Accept-Charset:utf-8';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        $access_tokens = json_decode($result, true);
        $access_token = $access_tokens['access_token'];
        return $access_token;
    }

    public function get_curl($url) {
        $ch = curl_init();
        $headers[] = 'Accept-Charset:utf-8';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function checkSignature() {
        global $ews_token;
        $echoStr = $_GET["echostr"];
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = $ews_token;

        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    private function sendtext($touser, $fromuser, $content) {
        $str = "<xml>
              <ToUserName><![CDATA[" . $touser . "]]></ToUserName>
              <FromUserName><![CDATA[" . $fromuser . "]]></FromUserName>
              <CreateTime>" . time() . "</CreateTime>
              <MsgType><![CDATA[text]]></MsgType>
              <Content><![CDATA[" . $content . "]]></Content>
              </xml>";
        return $str;
    }

}
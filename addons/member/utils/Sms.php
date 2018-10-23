<?php

namespace addons\member\utils;

/**
 * 短信api
 */
class Sms{
//    短信接口：
    private static $data = [];  //返回消息
    private static $api_id = ''; //用户编码
    private static $api_key = ''; //用户秘钥
    private static $target_url = '';
    
    private static $API_SEND_URL='https://smssh1.253.com/msg/send/json'; //创蓝发送短信接口URL
    private static $API_VARIABLE_URL ='https://smssh1.253.com/msg/variable/json';//创蓝变量短信接口URL
    private static $API_BALANCE_QUERY_URL='https://smssh1.253.com/msg/balance/json';//创蓝短信余额查询接口URL
    private static $API_ACCOUNT= 'N2372657'; // 创蓝API账号
    private static $API_PASSWORD= 'WbzH8vuDQg2066';// 创蓝API密码

    // private static function _init() {
    //     $m = new \addons\config\model\Sms();
    //     $data = $m->getAllowConfig();
    //     if(empty($data))
    //         return false;
    //     self::$api_id = $data['api_id'];
    //     self::$api_key = $data['api_key'];
    //     self::$target_url = $data['api_url'];
    // }
    

    /**
     * 发送验证码
     * @param type $phone
     */
    public static function send($phone){
        $code = self::random(6,1);//验证码
        $msg = '【CBC】尊敬的用户，您本次操作的验证码为'.$code.'，请妥善保存，切勿泄露。';
        //创蓝接口参数
        $postArr = array (
            'account'  =>  self::$API_ACCOUNT,
            'password' => self::$API_PASSWORD,
            'msg' => urlencode($msg),
            'phone' => $phone,
            'report' => 'true'
        );
        $res = json_decode(self::curlPost(self::$API_SEND_URL, $postArr),1);
        if($res['code']==0){
            self::$data['code'] = $code;
            self::$data['message'] = "验证码发送成功，请注意查收";
            self::$data['success'] = true;   
        }else{
            self::$data['message'] = 'errormsg:'.$res['errorMsg'];
            self::$data['success'] = false;
        }
        return self::$data;
    }
    

    /**
     * 通过CURL发送HTTP请求
     * @param string $url  //请求URL
     * @param array $postFields //请求参数 
     * @return mixed
     *  
     */
    private static function curlPost($url,$postFields){
        $postFields = json_encode($postFields);
        
        $ch = curl_init ();
        curl_setopt( $ch, CURLOPT_URL, $url ); 
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8'   //json版本需要填写  Content-Type: application/json;
            )
        );
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); 
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt( $ch, CURLOPT_TIMEOUT,60); 
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
        $ret = curl_exec ( $ch );
        if (false == $ret) {
            $result = curl_error(  $ch);
        } else {
            $rsp = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close ( $ch );
        return $result;
    }


    /**
     * 组成url 参数
     * @param type $post_data
     */
    private static function makeData($post_data){
        $str = '';
        foreach($post_data as $k => $v){
            if(!empty($str))
                $str .= '&';
            $str .= $k .'='.$v;
        }
        return $str;
    }


    /**
     * 转换errorcode
     * @param type $code
     * 0：提交成功
     * 8301：userID为空
     * 8302：uPhone手机号码为空
     * 8303：content发送内容为空
     * 8304：提交IP限制
     * 8201：通道无法匹配
     * 8202：通道异常或暂停
     * 8203：发送内容(content)字数超出限制
     * 8204：用户ID(userID)不存在
     * 8205：发送内容含有非法字符
     * 8206：账号已被锁定
     * 8207：cpKey验证失败
     * 8208：短信条数不足
     * 8209：提交号码个数超出限制
     * 8210：通道异常：网关无法连接
     * 8211：通道异常：欠费、未免白等原因
     * 8212：发送时间段限制
     * 9999：其它原因
     */
    private static function getError($code){
        $message = '';
        switch ($code){
            case '8301':
                $message = 'userID为空';
                break;
            case '8302':
                $message = 'uPhone手机号码为空';
                break;
            default :
                $message = '发送验证码失败';
        }
        return $message;
    }
    
    //随机数
    private static function random($length = 6, $numeric = 0) {
        PHP_VERSION < '4.2.0' && mt_srand((double) microtime() * 1000000);
        if ($numeric) {
            $hash = sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
        } else {
            $hash = '';
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';
            $max = strlen($chars) - 1;
            for ($i = 0; $i < $length; $i++) {
                $hash .= $chars[mt_rand(0, $max)];
            }
        }
        return $hash;
    }

    /**
     * xml to array
     */
    private static function xml_to_array($xml) {
        $arr=array();
        $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
        if (preg_match_all($reg, $xml, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $subxml = $matches[2][$i];
                $key = $matches[1][$i];
                if (preg_match($reg, $subxml)) {
                    $arr[$key] = self::xml_to_array($subxml);
                } else {
                    $arr[$key] = $subxml;
                }
            }
        }
        return $arr;
    }

    //curl POST表单
    private static function Post($curlPost, $url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return $return_str;
    }
    
    
}
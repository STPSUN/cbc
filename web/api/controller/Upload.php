<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/22
 * Time: 15:19
 */

namespace web\api\controller;

use addons\member\model\MemberAccountModel;

class Upload extends ApiBase
{
    public function uploadHead()
    {
        $base64 = $this->_post('image');
        if($base64){
            $savePath = 'user/';
            $ret = $this->base_img_upload($base64, $this->user_id, $savePath);
            if(!$ret['success']){
                return $this->failJSON($ret['message']);
            }

            $userM = new MemberAccountModel();
            $userM->save([
                'head_img'  => $ret['path'],
            ],[
                'id'    => $this->user_id,
            ]);
            return $this->successJSON();
        }
    }

    public function uploadImg()
    {
        $base64 = $this->_post('image');
        if($base64){
            $savePath = 'user/';
            $ret = $this->base_img_upload($base64, $this->user_id, $savePath);
            if(!$ret['success']){
                return $this->failJSON($ret['message']);
            }
            return $this->successJSON($ret['path']);
        }
    }

    /**
     * 上传身份证正面
     */
    public function id_card(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $base64 = $this->_post('image');
        $type = $this->_post('type');
        if($base64){
            $m = new \addons\member\model\MemberAccountModel();
            if($type=='id_face'){
                $savePath = 'user/id_face/';
                $str = lang('UPLOAD_FACE_SUCCESS');
            }else{
                $type = 'id_back';
                $savePath = 'user/id_back/';
                $str = lang('UPLOAD_BACK_SUCCESS');
            }
            $ret = $this->base_img_upload($base64, $this->user_id, $savePath);
            if(!$ret['success']){
                return $this->failJSON($ret['message']);
            }

            $res = $m->where(['id'=>$user_id])->update([$type=>$ret['path'],'is_auth'=>2]);
            if($res){
                return $this->successJSON($str);
            }else{
                return $this->failJSON($ret['message']);
            }
        }
    }
}
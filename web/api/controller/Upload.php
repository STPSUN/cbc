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
    public function id_face(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $base64 = $this->_post('image');
        if($base64){
            $savePath = 'user/id_face/';
            $ret = $this->base_img_upload($base64, $this->user_id, $savePath);
            if(!$ret['success']){
                return $this->failJSON($ret['message']);
            }

            $m = new \addons\member\model\MemberAccountModel();
            $res = $m->where(['id'=>$user_id])->update(['id_face'=>$ret['path']]);
            if($res){
                return $this->successJSON(lang('UPLOAD_FACE_SUCCESS'));
            }else{
                return $this->failJSON($ret['message']);
            }
        }
    }

    /**
     * 上传身份证反面
     */
    public function id_back(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $base64 = $this->_post('image');
        if($base64){
            $savePath = 'user/id_back/';
            $ret = $this->base_img_upload($base64, $this->user_id, $savePath);
            if(!$ret['success']){
                return $this->failJSON($ret['message']);
            }

            $m = new \addons\member\model\MemberAccountModel();
            $res = $m->where(['id'=>$user_id])->update(['id_back'=>$ret['path']]);
            if($res){
                return $this->successJSON(lang('UPLOAD_BACK_SUCCESS'));
            }else{
                return $this->failJSON($ret['message']);
            }
        }
    }
}
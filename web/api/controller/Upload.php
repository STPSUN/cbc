<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/22
 * Time: 15:19
 */

namespace web\api\controller;

class Upload extends ApiBase
{
    public function uploadImg()
    {
        $file = request()->file('image');

        if($file)
        {
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/user');
            if($info)
            {
                $savename = $info->getSaveName();

                return $this->successJSON($savename);
            }else
            {
                return $this->failJSON($file->getError());
            }
        }
    }
}
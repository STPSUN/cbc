<?php

namespace addons\shop\user\controller;

/**
 * Description of Goods
 * 商品
 * @author shilinqing
 */
class Goods extends \web\user\controller\AddonUserBase {

    public function index() {
        $m = new \addons\shop\model\GoodsClass();
        $class_list = $m->getClassGroup(3);
        $this->assign('class_list', json_encode($class_list,512));
        return $this->fetch();
    }

    public function loadList() {
        $keyword = $this->_get('keyword');
        $class_id = $this->_get('class_id');
        $status = $this->_get('status');
        $filter = '1=1';
        if (!empty($class_id))
            $filter .= ' and class_id=' . $class_id;
        if ($status != '')
            $filter .= ' and status=' . $status;
        if ($keyword != null) {
            $filter .= ' and (goods_name like \'%' . $keyword . '%\' or goods_code=\'' . $keyword . '\')';
        }
        $m = new \addons\shop\model\Goods();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }
    
    public function loadClass(){
        $pid = $this->_get('id');
        $m = new \addons\shop\model\GoodsClass();
        $data = $m->where("pid=".$pid)->field('id,class_name')->select();
        return $data;
    }

    public function edit() {
        $m = new \addons\shop\model\Goods();
        if(IS_POST){
            try{
                //多规格
                $is_guige = $this->_post('is_guige');
                $is_bind_guige = $this->_post('is_bind_guige');
                $data = $_POST;
                $goods_id = $data['id'];
                $data['update_time'] = NOW_DATETIME;
                $m->startTrans();
                if(empty($goods_id)){
                    $goods_id = $m->add($data);
                }else{
                    $m->save($data);
                }
                if ($is_guige == 1 && $is_bind_guige == 1) {
                    $guige_json = $_POST['guige_json'];
                    $guige_list = json_decode($guige_json, true);
                    $this->saveGoodsGuige($goods_id,$guige_list);
                } 
                $m->commit();
                return $this->successData();
            } catch (\Exception $ex) {
                $m->rollback();
                return $this->failData($ex->getMessage());
            }
        }else{
            $this->assign('order_index', $m->getNewOrderIndex());
            $classM = new \addons\shop\model\GoodsClass();
            $class_list = $classM->getClassGroup(1);
            $this->assign('class_list',$class_list);
            $this->setLoadDataAction('loadData');
            $this->assign('id',$this->_get('id'));
            return $this->fetch();
        }
    }
    
    /**
     * 保存菜品多规格
     * @param type $goods_id     
     * @param type $goods_id
     * @return type
     * TODO
     */
    private function saveGoodsGuige($goods_id,$guige_list) {
        $guigeM = new \app\user\model\GuigeModel();
        $ids = array();
        foreach ($guige_list as $data) {
            $data['update_time'] = NOW_DATETIME;
            $data['goods_id'] = $goods_id;
            if(!empty($data['id'])){
                $guigeM->save($data);
                array_push($ids, $data['id']);
            }else{
                $id = $guigeM->add($data);
                array_push($ids,$id);
            }
        }
        $ids = implode(',', $ids);
        $guigeM->deleteNotINids($goods_id, $ids);
        
    }

    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\shop\model\Goods();
        $data =$m->getDetail($id);
        $picModel = new \addons\resources\model\Resources();
        $pic_list = $picModel->getPicListByIds($data['img_ids']);
        $data['picList'] = $pic_list;
        return $data;
    }

    /*
     * 获取分类列表
     */
    public function getClassList() {
        $pid = $this->_get('id');
        //获取一级分类
        $classM = new \addons\shop\model\GoodsClass();
        $list = $classM->getDataList(-1,-1,'pid='.$pid);
        return $this->successData($list);
    }

    //获取地址联动
    public function getAreaList() {
        $pid = $this->_get('id');
        //获取一级分类
        $m = new \addons\shop\model\Area();
        $areaList = $m->getDataList(-1,-1,'pid='.$pid,'','id asc');
        return $this->successData($areaList);
    }



    public function loadGuigeList() {
        $id = $this->_get('goods_id');
        $guigeM = new \app\user\model\GuigeModel();
        $guige_list = $guigeM->getDataList(-1, -1, 'goods_id=' . $id);
        return $guige_list;
    }

    public function del() {
        $id = intval($this->_post('id'));
        if (!empty($id)) {
            $m = new \addons\shop\model\Goods();
            $m->startTrans();
            try {
                $res = $m->deleteLogic($id);
                if ($res > 0) {
                    $m->commit();
                    return $this->successData();
                }
            } catch (\Exception $e) {
                $m->rollback();
                return $this->failData($e->getMessage());
            }
        } else {
            return $this->failData('删除失败，参数有误');
        }
    }

}

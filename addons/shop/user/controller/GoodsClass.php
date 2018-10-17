<?php

namespace addons\shop\user\controller;

/**
 * Description of GoodsClass
 * 商品分类
 * @author shilinqing
 */
class GoodsClass extends \web\user\controller\AddonUserBase {

    /**
     * 大类
     * @return type
     */
    public function bigClassList() {
        $this->assign('level', 1);
        return $this->fetch('big_class_list');
    }

    /**
     * 二类
     * @return type
     */
    public function midClassList() {
        $this->assign('level', 2);
        return $this->fetch('mid_class_list');
    }

    /**
     * 三类
     * @return type
     */
    public function index() {
        $this->assign('level', 3);
        return $this->fetch();
    }

    /**
     * 加载列表
     * @return type
     */
    public function loadList() {
        $pid = $this->_get('pid');
        $keyword = $this->_get('keyword');
        $level = $this->_get('level');
        $filter = 'level=' . $level;
        if ($level != 1) {
            $filter .= ' and pid!=0 ';
        }
        if ($pid != '')
            $filter .= ' and pid=' . $pid;
        if ($keyword != null) {
            $filter .= ' and (class_name like \'%' . $keyword . '%\'';
        }
        $m = new \addons\shop\model\GoodsClass();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter, '', $this->getOrderBy('level asc, id asc'));
        return $this->toDataGrid($total, $rows);
    }

    public function getLevelClassData() {
        $m = new \addons\shop\model\GoodsClass();
        $level = $this->_get('level');
        $data = $m->getList($level);
        return $this->successData($data);
    }

    public function edit() {
        if (IS_POST) {
            $data = $_POST;
            $data['update_time'] = NOW_DATETIME;
            $id = $data['id'];
            $m = new \addons\shop\model\GoodsClass();
            if ($data['pid'] == 0) {
                $data['level'] = 1;
            } else {
                $pLevel = $m->where('id='.$data['pid'])->value('level');
                $data['level'] = $pLevel + 1;
            }
            try {
                if (empty($id)) {
                    $m->add($data);
                } else {
                    $m->save($data);
                }
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            $this->assign('id', $this->_get('id'));
            $this->setLoadDataAction('loadData');
            $m = new \addons\shop\model\GoodsClass();
            $data = $m->where('pid = 0')->field('id,class_name,level')->select();
            foreach ($data as &$value) {
                $value['secondList'] = $m->where('pid = ' . $value['id'])->field('id,class_name,level')->select();
            }
            $this->assign('list', $data);
            return $this->fetch();
        }
    }

    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\shop\model\GoodsClass();
        $data = $m->getDetail($id);
        return $data;
    }

}

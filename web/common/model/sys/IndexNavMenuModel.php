<?php

namespace web\common\model\sys;

/**
 * index模块前端导航菜单
 */
class IndexNavMenuModel extends \web\common\model\Model {
    
    protected function _initialize() {
        $this->tableName = 'index_nav_menu';
    }
      /**
     * 获取类目列表。
     * @return type
     */
    public function getList() {
        return $this->order('order_index asc,id asc')->select();
    }
    
    
    public function getMenuByIDS($ids){
        $where['id'] = array('in',$ids);
        return $this->field('id,title,ontroller,action')->where($where)->select();
    }

    /**
     * 获取类目 id pid title 列表
     * @return type
     */
    public function getAddonList() {
        $table = $this->getTableName();
        $sql = 'select id,pid,title name from ' . $table . ' where allow=1 ';
        $data = $this->query($sql);
        return $data;
    }

    /**
     * 获取类目 id pid title 列表
     * @return type
     */
    public function getAddonCount() {
        return $this->where('allow=1')->count('id');
    }

    /**
     * 
     * @param type $addon
     * @return int
     */
    public function getCategoryPID($controller) {
        $table = $this->getTableName();
        //$sql = 'select (select pid from ' . $table . ' b where b.id=a.pid) pid from ' . $table . ' a where a.addon=\'' . $addon . '\'and a.controller=\'' . $controller . '\' limit 1';
        $sql = 'select pid from ' . $table . ' a where a.controller=\'' . $controller . '\' limit 1';
        $reult = $this->query($sql);
        if (!empty($reult) && count($reult) > 0)
            return intval($reult[0]['pid']);
        else
            return 0;
    }

    /**
     * 
     * @param type $addon
     * @return int
     */
    public function getCategoryID($controller) {
        $table = $this->getTableName();
        //$sql = 'select (select pid from ' . $table . ' b where b.id=a.pid) pid from ' . $table . ' a where a.addon=\'' . $addon . '\'and a.controller=\'' . $controller . '\' limit 1';
        $sql = 'select id from ' . $table . ' a where a.controller=\'' . $controller . '\' limit 1';
        $reult = $this->query($sql);
        if (!empty($reult) && count($reult) > 0)
            return intval($reult[0]['id']);
        else
            return 0;
    }


    /**
     * 获取插件菜单信息
     * @param type $addon
     * @param type $controller     
     */
    public function getCategory($controller) {
        $controller = explode('.', $controller)[0];
        $sql = 'select b.title category,a.title,a.controller,a.action from ' . $this->getTableName() . ' a,' . $this->getTableName() . ' b
where a.controller=\'' . $controller . '\' and a.pid=b.id';
        $result = $this->query($sql);
        if (!empty($result))
            return $result[0];
        else
            return array();
    }

    /**
     * 获取菜单
     * @param type $pid
     * @return type
     */
    public function getCategoryParentMenu($pid) {
        $f = 'pid=' . $pid;
        $sql = 'select id,title,icon,controller,action,target from ' . $this->getTableName() . ' where allow=1 and ' . $f . ' order by order_index asc,id asc';
        return $this->query($sql);
    }

    /**
     * 获取分类菜单。
     * @param type $company_id
     * @param type $brand_id
     * @param type $pid
     * @param type $popedom_ids
     * @return type
     */
    public function getCategoryMenu($pid) {
        $f = ' pid=' . $pid;
        $sql = 'select a.id,a.title,a.addon,a.controller,a.action from ' . $this->getTableName() . ' a where ' . $f . ' and a.allow=1 order by a.order_index asc,a.id asc';
        return $this->query($sql);
    }

    /**
     * 获取菜单     
     * @param type $pid
     * @param type $popedom_ids
     * @return type
     */
    public function getMenu($pid) {
        $sql = 'select id,title from ' . $this->getTableName() . ' where pid=' . $pid . ' and allow=1 and ' . $filter . ' order by order_index asc,id asc';
        return $this->query($sql);
    }


    /**
     * 获取插件菜单信息
     * @param type $addon
     * @param type $controller     
     */
    public function getMenuData($controller) {
        $where = array('controller' => $controller);
        return $this->field('id,title')->where($where)->find();
    }

    /**
     * 获取ID 
     * @param type $addon
     * @param type $controller     
     * @return int
     */
    public function getNavID($controller) {
        $controller = \think\Loader::parseName($controller);
        $where = array('controller' => $controller, 'is_permissions' => 1);
        $result = $this->field('id')->where($where)->find();
        if (!empty($result))
            return $result['id'];
        else
            return 0;
    }
    
    
    
}
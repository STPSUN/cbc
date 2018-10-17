<?php

namespace addons\member\model;

/**
 * Description of BonusRecord
 * 奖金明细记录
 * @author shilinqing
 */
class BonusRecord extends \web\common\model\BaseModel {
    
    protected function _initialize() {
        $this->tableName = 'member_bonus_record';
    }
    
    /**
     * 添加奖金记录 默认对碰奖金
     *  `user_id` int(11) NOT NULL,
        `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '奖金类型： 10=推荐奖金，11=对碰奖金，12=管理奖金，13=领导奖金，14=报单中心奖金，15=复投奖金',
        `amount` decimal(18,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '金额',
        `update_time` datetime NOT NULL,
     */
    public function addRecord($user_id, $amount, $type = 11){
        $data['user_id'] = $user_id;
        $data['amount'] = $amount;
        $data['type'] = $type;
        $data['update_time'] = NOW_DATETIME;
        return $this->add($data);
    }
    
        public function getTotal($filter = '') {
        $m = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,c.username from ' . $this->getTableName() . ' a,'.$m->getTableName().' c where a.user_id=c.id';
        $sql = 'select count(*) as c from ('.$sql.') as tab';
        if($filter!=''){
            $sql .= ' where '.$filter;
        }
        $count = $this->query($sql);
        return $count[0]['c'];
    }
    
    public function getDataList($pageIndex = -1, $pageSize = -1, $filter = '', $fileds = '', $order = 'id asc') {
        $userM = new \addons\member\model\MemberAccountModel();
        if($fileds == ''){
            $sql = 'select * from ' . $this->getTableName();
        }else{
            $sql = 'select ' . $fileds . ' from ' . $this->getTableName();
        }
        $sql = 'select s.*,u.username from (' . $sql . ') as s left join ' . $userM->getTableName() . ' u on s.user_id=u.id ';
        if ($filter != '') {
            $sql = 'select * from (' . $sql . ') as y where ' . $filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    
    public function getCountTotal($filter = '') {
        $m = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,c.username  from ' . $this->getTableName() . ' a,'.$m->getTableName().' c where a.user_id=c.id';
        $sql = 'select sum(amount) as count_total from ('.$sql.') as tab';
        if($filter!=''){
            $sql .= ' where '.$filter;
        }
        $count = $this->query($sql);
        return $count[0]['count_total'];
    }
    
    /**
     * 根据类型获取当日奖金总额 默认对碰奖金
     * @param type $user_id
     * @param type $type
     */
    public function getTodayTotal($user_id,$type = 11){
        $start_time = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $end_time = mktime(23, 59, 59, date('m'), date('d'), date('Y'));
        $where['update_time'] = array(">=",$start_time);
        $where1['update_time'] = array("<=",$end_time);
        $where['type'] = $type;
        $where['user_id'] = $user_id;
        return $this->where($where1)->where($where)->sum('amount');
    }
    
}

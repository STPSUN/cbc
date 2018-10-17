<?php

namespace addons\baodan\model;

/**
 * Description of LeaderConfig
 *
 * @author shilinqing
 */
class LeaderConfig extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'baodan_leader_config';
    }
    
    // where need_child <=2 and sum_total <=20000000 and small_child_total <= 40000000;
    public function getRateByWhere($total_amount, $child_count ,$childs){
        $where['need_child'] = array('<=',$child_count);
        $where['sum_total'] = array("<=",$total_amount);
        $data = $this->where($where)->order('id desc')->select();
        if(!empty($data)){
            $child_arr = explode(',', $childs);
            $child_arr = sort($child_arr);
            foreach($data as $k => $config){
                echo $config['small_child_total'];
                echo $child_arr[$config['need_child']-1];
                if($config['small_child_total'] >= $child_arr[$config['need_child']-1]){
                    return $config['bonus_rate'];
                }
            }
        }
        return 0;
    }
    
}

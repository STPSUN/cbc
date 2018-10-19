<?php

namespace addons\member\model;

/**
 * Description of TradingRecord
 *
 * @author shilinqing
 */
class TradingRecord extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'trading_record';
    }
    
    
    /**
     * 添加记录
     * @param type $user_id 用户id
     * @param type $amount 数量
     * @param type $before_amount  更新前数量
     * @param type $after_amount   更新后数量
     * @param type $asset_type 资产类型：1=订阅费，2=参股基数，3=奖金积分，4=特权分，5=增值资产
     * @param type $type        记录类型：0=转账，1=OTC交易，2=提现转出，3=后台拨币,10=推荐奖金，11=对碰奖金，12=管理奖金，13=领导奖金，14=报单中心奖金
     * @param type $change_type 0 = 减少 ；1 = 增加
     * @param type $to_user_id      目标用户
     * @param type $remark      备注
     * @return type
     */
    public function addRecord($user_id,$amount, $before_amount, $after_amount,$asset_type, $type, $change_type=0, $to_user_id = 0, $remark=''){
        $data['user_id'] = $user_id;
        $data['to_user_id'] = $to_user_id;
        $data['asset_type'] = $asset_type;
        $data['type'] = $type; 
        $data['change_type'] = $change_type;
        $data['before_amount'] = $before_amount;
        $data['after_amount'] = $after_amount;
        $data['amount'] = $amount;
        $data['remark'] = $remark;
        $data['update_time'] = NOW_DATETIME;
        return $this->add($data);
    }

    public function getList($pageIndex = -1, $pageSize = -1, $filter = '',$fileds='*', $order = 'id desc') {
        $coinM = new \addons\config\model\Coins();
        $userM = new \addons\member\model\MemberAccountModel();
        $recordConfM  = new \addons\member\model\RecordConf();
        $sql = 'select a.*,c.coin_name,d.trade_type from '.$this->getTableName() . ' a,'.$coinM->getTableName().' c,'.$recordConfM->getTableName().' d where a.coin_id=c.id and a.type = d.id';
        $sql = 'select s.*,u.username from ('.$sql.') as s left join '.$userM->getTableName().' u on s.user_id=u.id';
        if($filter != ''){
            $sql = 'select '.$fileds.' from ('.$sql.') as tab where '.$filter;
        }
        $sql = 'select t.*,p.username to_username from ('.$sql.') as t left join '.$userM->getTableName().' p on t.to_user_id=p.id';
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    
    public function getDataList($pageIndex = -1, $pageSize = -1, $filter = '',$fileds='*', $order = 'id asc') {
        $c = new \addons\config\model\BalanceConf();
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,c.name from '.$this->getTableName() . ' a,'.$c->getTableName().' c where a.asset_type=c.id';
        $sql = 'select '.$fileds.' from ('.$sql.') as tab';
        $sql = 'select s.*,u.username,t.username as to_username from ('.$sql.') as s left join '.$userM->getTableName().' u on s.user_id=u.id left join '.$userM->getTableName().' t on s.to_user_id=t.id';
        if($filter != ''){
            $sql = 'select * from ('.$sql.') as y where '.$filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    /**
     * 获取列表数据
     * @param type $pageIndex 当前页
     * @param type $pageSize 每页数量
     * @param type $filter 过滤条件
     * @param type $fields 字段信息
     * @param type $order 排序
     * @return type
     */
    public function getDataList2($pageIndex = -1, $pageSize = -1, $filter = '', $fields = '', $order = 'id desc') {
        $sql = 'select ';
        if (!empty($fields))
            $sql .= $fields;
        else
            $sql .= '*';
        $sql .= ' from ' . $this->getTableName();
        if (!empty($filter))
            $sql .= ' where ' . $filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    
    /**
     * 获取记录总数
     * @param type $filter
     * @return int
     */
    public function getTotal($filter = '') {
        $c = new \addons\config\model\BalanceConf();
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,c.name from '.$this->getTableName() . ' a,'.$c->getTableName().' c where a.asset_type=c.id';
        $sql = 'select s.*,u.username from ('.$sql.') as s left join '.$userM->getTableName().' u on s.user_id=u.id';
        if($filter != ''){
            $sql = 'select count(*) c from ('.$sql.') as y where '.$filter;
        }else{
            $sql = 'select count(*) c from ('.$sql.')';
        }
        $result = $this->query($sql);
        if (count($result) > 0)
            return intval($result[0]['c']);
        else
            return 0;
    }
    
    public function getCountTotal($filter = '') {
        $c = new \addons\config\model\BalanceConf();
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,c.name from '.$this->getTableName() . ' a,'.$c->getTableName().' c where a.asset_type=c.id';
        $sql = 'select s.*,u.username from ('.$sql.') as s left join '.$userM->getTableName().' u on s.user_id=u.id';
        if($filter != ''){
            $sql = 'select sum(amount) as count_total from ('.$sql.') as y where '.$filter;
        }else{
            $sql = 'select sum(amount) as count_total from ('.$sql.')';
        }
        $count = $this->query($sql);
        return $count[0]['count_total'];
    }
    
}

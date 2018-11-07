<?php

namespace addons\member\model;

/**
 * 用户资产
 *
 * @author shilinqing
 */
class Balance extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'member_balance';
    }

    /**
     * get user balance by type
     * @param type $user_id
     * @param type $type
     * @return int
     */
    public function getBalanceByType($user_id,$type) {
        $where['user_id'] = $user_id;
        $where['type'] = $type;
        $data = $this->where($where)->find();
        return $data;
    }
    
    public function getBalanceAmountByType($user_id, $type){
        $where['user_id'] = $user_id;
        $where['type'] = $type;
        $data = $this->where($where)->value('amount');
        return $data;
    }
    
    public function getUserBalanceList($user_id){
        $c = new \addons\config\model\BalanceConf();
        $sql = 'select id,amount,type from '.$this->getTableName().' where user_id='.$user_id;
        $sql = 'select a.id,a.amount,b.name from('.$sql.') a left join '.$c->getTableName().' b on a.type=b.id';
        return $this->query($sql);
    }
    
    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $fields = '', $order = 'id desc') {
        $c = new \addons\config\model\BalanceConf();
        $m = new \addons\member\model\MemberAccountModel();

        $sql = 'select tab.*,c.name,m.phone from '.$this->getTableName().' as tab left join '.$c->getTableName().' c on tab.type=c.id left join '.$m->getTableName().' m on tab.user_id=m.id';
        if (!empty($filter))
            $sql =  'select * from ('.$sql.') t where '.$filter;
        // echo $sql;exit();
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getCountTotal($filter = '') {
        $m = new \addons\member\model\MemberAccountModel();
        $sql = 'select sum(amount) as count_total from '.$this->getTableName().' b left join '.$m->getTableName().' m on m.id=b.user_id where '.$filter;
        $count = $this->query($sql);
        return $count[0]['count_total'];
    }

    public function getBalanceTotal($filter){
        $m = new \addons\member\model\MemberAccountModel();
        $sql = 'select count(*) c from ' . $this->getTableName() .' b left join '.$m->getTableName().' m on m.id=b.user_id';
        if (!empty($filter))
            $sql .= ' where ' . $filter;
        $result = $this->query($sql);
        if (count($result) > 0)
            return intval($result[0]['c']);
        else
            return 0;
    }


    public function getBalanceList($pageIndex, $pageSize, $filter='*',$orderby='b.id desc'){
        $m = new \addons\member\model\MemberAccountModel();
        $sql = 'select *  from ' . $this->getTableName() .' b left join '.$m->getTableName().' m on m.id=b.user_id';
        if (!empty($filter))
            $sql .=  ' and '.$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    /**
     * 更新用户资产
     * @param type $user_id
     * @param type $type
     * @param type $amount 变动金额
     * @param type $change 变动类型，false 减值，true增值
     * @return type
     */
    public function updateBalance($user_id,$type,$amount, $change = false) {
        if(!$user_id) return false;
        $map = array();
        $map['user_id'] = $user_id;
        $map['type'] = $type;
        $data = $this->where($map)->find();
        if (!$data) {
            $data['user_id'] = $user_id;
            $data['before_amount'] = 0;
            $data['amount'] = 0;
            $data['type'] = $type;
        }
        $data['update_time'] = NOW_DATETIME;
        if ($change) {
            $data['before_amount'] = $data['amount'];
            $data['amount'] = $data['amount'] + $amount;
        } else {
            $data['before_amount'] = $data['amount'];
            $data['amount'] = $data['amount'] - $amount;
        }
        $res = $this->save($data);
        if (!$res) {
            return false;
        }
        return $data;
    }

    /**
     * 验证手续费所需可用余额是否足够
     */
    public function verifyStock($user_id, $amount,$type=1) {
        $where['user_id'] = $user_id;
        $where['type'] = $type;
        $where['amount'] = array('>=', $amount);
        return $this->where($where)->find();
    }

    /**
     * otc确认交易余额操作
     * @param type $order_id
     * @return boolean
     */
    public function otcTradingConfirm($order_id) {
        $m = new \addons\otc\model\OtcOrder();
        $recordM = new \addons\member\model\TradingRecord();
        $m->startTrans();
        $order = $m->getDetail($order_id);
        $order['status'] = 4; //完成
        unset($order['pay_detail_json']);
        $update_status = $m->save($order);
        if (empty($update_status)) {
            $m->rollback();
            return false;
        }
        $buy_user_id = $order['buy_user_id'];
        $user_id = $order['user_id'];
        $coin_id = $order['coin_id'];
        $type = $order['type'];
        $tax_amount = $order['tax_amount'];
        $amount = $order['amount'];
        $total_amount = $order['total_amount'];
        if ($type == 1) {
            //买单 buy_user_id 扣除余额 , user_id 添加余额 ,手续费从total_amount 扣除
            $out_asset = $this->updateOtcAsset($buy_user_id, $amount, $coin_id);
            if (empty($out_asset)) {
                $m->rollback();
                return false;
            }
            $out_before_amount = $out_asset['amount'] + $amount;
            $out_record_id = $recordM->addRecord($buy_user_id, $coin_id, $amount, $out_before_amount, $out_asset['amount'], 1, 0, $user_id, '', '', '用户交易扣除');
            if (empty($out_record_id)) {
                $m->rollback();
                return false;
            }
            $_amount = $amount - $tax_amount;
            $in_asset = $this->updateBalance($user_id, $_amount, $coin_id, 1);
            if (empty($in_asset)) {
                $m->rollback();
                return false;
            }
            $in_record_id = $recordM->addRecord($user_id, $coin_id, $_amount, $in_asset['before_amount'], $in_asset['amount'], 1, 1, $buy_user_id, '', '', '用户交易增加');
            if (empty($in_record_id)) {
                $m->rollback();
                return false;
            }
            $m->commit();
            return true;
        } else {
            //卖单 user_id 扣除total_amount余额, buy_user_id 添加余额 amount
            $out_asset = $this->updateOtcAsset($user_id, $total_amount, $coin_id);
            if (empty($out_asset)) {
                $m->rollback();
                return false;
            }
            $out_before_amount = $out_asset['amount'] + $total_amount;
            $out_record_id = $recordM->addRecord($user_id, $coin_id, $total_amount, $out_before_amount, $out_asset['amount'], 1, 0, $buy_user_id, '', '', '用户交易扣除');
            if (empty($out_record_id)) {
                $m->rollback();
                return false;
            }
            $in_asset = $this->updateBalance($buy_user_id, $amount, $coin_id, 1);
            if (empty($in_asset)) {
                $m->rollback();
                return false;
            }
            $in_record_id = $recordM->addRecord($buy_user_id, $coin_id, $amount, $in_asset['before_amount'], $in_asset['amount'], 1, 1, $user_id, '', '', '用户交易增加');
            if (empty($in_record_id)) {
                $m->rollback();
                return false;
            }
            $m->commit();
            return true;
        }
    }

    /**
     * 更新otc余额
     * @param type $user_id     操作用户
     * @param type $amount     数量
     * @param type $type    0=减少 1=增加
     */
    public function updateOtcBalance($user_id, $amount, $type = 0) {
        $data = $this->getBalanceByUserID($user_id);
        if ($type == 0) {
            $data['otc_frozen_amount'] = $data['otc_frozen_amount'] - $amount;
            $data['update_time'] = NOW_DATETIME;
            $ret = $this->save($data);
        } else {
            $before_amount = $data['amount'];
            $data['before_amount'] = $before_amount;
            $data['amount'] = $before_amount + $amount;
            $data['otc_frozen_amount'] = $data['otc_frozen_amount'] - $amount;
            $data['update_time'] = NOW_DATETIME;
            $ret = $this->save($data);
        }
        if ($ret > 0)
            return $data;
        else
            return '';
    }
}

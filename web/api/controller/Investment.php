<?php
/**
 * Created by PhpStorm.
 * User: zhuangminghan 
 * Date: 2018/10/16
 * Time: 16:48
 * 投资
 */

namespace web\api\controller;

class Investment extends ApiBase
{

    /**
     *获取理财信息 
     */
    public function getInvestmentList(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $financialM = new \web\common\model\sys\FinancialModel();
        $list = $financialM->getDataList(-1, -1, $filter = 'status=0', $fileds = '', $order = 'id asc');
        $finaM = new \addons\member\model\Financial();
        $data['sum'] = $finaM->where(['user_id'=>$user_id])->sum('amount')+0;
        $data['interset'] = $finaM->where(['user_id'=>$user_id,'status'=>1])->sum('interset');
        $act = $finaM->where(['user_id'=>$user_id,'status'=>0])->select();
        $start = 0;
        $int = 0;
        foreach ($act as $key => $value) {
            $day = ceil((time()-strtotime($value['start_at']))/86400);
            $int += bcmul($value['interset']/$value['financing_time']*$day, 1,4);
            $start += bcmul($value['interset']/$value['financing_time'], 1,4);
        }
        $data['interset'] = $data['interset'] + $int;
        $data['today'] = $start;
        $data['list'] = $list;
        $this->successJSON($data);
    }


    /**
     * 理财  
     * @param financial_id int
     * @param amount float 资产
     */
    public function Investment(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $financialM = new \web\common\model\sys\FinancialModel();
        $fina = new \addons\member\model\Financial();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $financial_id = $this->_post('financial_id');
        $style = $this->_post('type')?$this->_post('type'):0;
        $info = $financialM->getFinancial($financial_id);
        if(!$info) return $this->failJSON(lang('INVESTMENT_FIND'));
        $amount = $this->_post('amount');
        if($amount<$info['amount_limit']) return $this->failJSON(lang('INVESTMENT_LESS').$info['amount_limit']);
        $balanceM->startTrans();
        if($style==0){
            $type = 2;
            $userAsset = $balanceM->getBalanceByType($user_id,$type);
            if($amount>$userAsset['amount'])  return $this->failJSON(lang('INVESTMENT_LESS_AMOUNT').$userAsset['amount']);
            if($amount%100!=0) return $this->failJSON(lang('INVESTMENT_INT'));
            $userAsset = $balanceM->updateBalance($user_id,$type,$amount);
            if(!$userAsset){
                $balanceM->rollback();
                return $this->failJSON(lang('INVESTMENT_REDUCE_WRONG'));
            }
            $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $type, 4,0, $user_id,'用户理财');
            if(empty($in_record_id)){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }

            $type = 1;
            $userAsset = $balanceM->getBalanceByType($user_id,$type);
            $total = $amount/0.7;
            if($total>$userAsset['amount'])  return $this->failJSON(lang('INVESTMENT_LESS_AMOUNT').$userAsset['amount']);
            $userAsset = $balanceM->updateBalance($user_id,$type,$total);
            if(!$userAsset){
                $balanceM->rollback();
                return $this->failJSON(lang('INVESTMENT_REDUCE_WRONG'));
            }
            $in_record_id = $recordM->addRecord($user_id, $total, $userAsset['before_amount'], $userAsset['amount'], $type, 4,0, $user_id,'用户理财');
            if(!$in_record_id){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }
        }else{
            $type = 4;
            $userAsset = $balanceM->getBalanceByType($user_id,$type);
            if($amount>$userAsset['amount'])  return $this->failJSON(lang('INVESTMENT_LESS_AMOUNT').$userAsset['amount']);
            if($amount%100!=0) return $this->failJSON(lang('INVESTMENT_INT'));
            $userAsset = $balanceM->updateBalance($user_id,$type,$amount);
            if(!$userAsset){
                $balanceM->rollback();
                return $this->failJSON(lang('INVESTMENT_REDUCE_WRONG'));
            }
            $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $type, 4,0, $user_id,'用户理财');
            if(empty($in_record_id)){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }
        }
        $data = [
                'user_id'       =>$user_id,
                'amount'        =>$amount,
                'month_fee'     =>$info['amount_interest'],
                'financing_time'=>$info['time_length'],
                'interset'      =>bcmul(($amount*$info['amount_interest']/100*$info['time_length']/30), 1,4),
                'start_at'      =>date('Y-m-d H:i:s'),
                'end_at'        =>date('Y-m-d H:i:s',strtotime('+'.$info['time_length'].' days')),
                'update_at'     =>date('Y-m-d H:i:s'),
                'create_at'     =>date('Y-m-d H:i:s'),
        ];
        $res = $fina->add($data);
        if(!$res){
            $balanceM->rollback();
            return $this->failJSON(lang('INVESTMENT_ADD_WRONG'));
        }
        $balanceM->commit();
        return $this->successJSON();
    }

    /**
     * 获取理财记录
     */
    public function getFinancialList(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $finaM = new \addons\member\model\Financial();
        $order = 'id desc';
        $filter = 'user_id = '.$user_id;
        $list = $finaM->getDataList($this->getPageIndex(), $this->getPageSize(),$filter,'',$order);
        return $this->successJSON($list);
    }

    /**
     * 领取理财金额
     */
    public function receiveFinancial(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $finaM = new \addons\member\model\Financial();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $financial_id = $this->_post('financial_id');
        $info = $finaM->getDetail($financial_id);
        if(!$info) return $this->failJSON(lang('INVESTMENT_CANT_FIND'));
        if($info['user_id']!=$user_id) return $this->failJSON(lang('INVESTMENT_NOT_YOUR'));
        // if(time()<strtotime($info['end_at'])) return $this->failJSON(lang('INVESTMENT_TIME_END'));
        $balanceM->startTrans();
        // $coin_id = 2;
        // $type = 12;
        // $amount = $info['amount'];
        // $userAsset = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
        // if(!$userAsset){
        //     $balanceM->rollback();
        //     return $this->failJSON(lang('COMMON_ADD_AMOUNT_WRONG'));
        // }
        // $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $coin_id, $type,1, $user_id,'用户理财');
        // if(empty($in_record_id)){
        //     $balanceM->rollback();
        //     return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
        // }

        $coin_id = 4;
        $amount = $info['interset']+$info['amount'];
        $userAsset = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
        if(!$userAsset){
            $balanceM->rollback();
            return $this->failJSON(lang('COMMON_ADD_AMOUNT_WRONG'));
        }
        $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $coin_id, $type,1, $user_id,'用户理财');
        if(empty($in_record_id)){
            $balanceM->rollback();
            return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
        }
        $data = [
                'status'        =>1,
                'update_at'     =>date('Y-m-d H:i:s'),
        ];
        $res = $finaM->where(['id'=>$financial_id])->update($data);
        if(!$res){
            $balanceM->rollback();
            return $this->failJSON(lang('INVESTMENT_ADD_FAIL'));
        }
        $balanceM->commit();
        return $this->successJSON();
    }


    
}
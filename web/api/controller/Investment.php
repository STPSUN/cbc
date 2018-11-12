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
            $number = bcmul($value['interset']/$value['financing_time'], 1,4);
            $int    += bcmul($number,$day,4);
            $start  += $number;
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
        set_time_limit(0);
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $financialM = new \web\common\model\sys\FinancialModel();
        $fina = new \addons\member\model\Financial();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $financial_id = $this->_post('financial_id');
        $style = $this->_post('type')?1:0;
        $info = $financialM->getFinancial($financial_id);
        if(!$info) return $this->failJSON(lang('INVESTMENT_FIND'));
        $amount = $this->_post('amount');
        if($amount<$info['amount_limit']) return $this->failJSON(lang('INVESTMENT_LESS').$info['amount_limit']);
        $balanceM->startTrans();
        $data = [
                'user_id'       =>$user_id,
                'amount'        =>$amount,
                'month_fee'     =>$info['amount_interest'],
                'belong'        =>$style,
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
        if($style==0){
            $type = 2;
            $userAsset = $balanceM->getBalanceByType($user_id,$type);
            if($userAsset['amount']>=$amount){
            }else{
                return $this->failJSON(lang('INVESTMENT_LESS_AMOUNT').$userAsset['amount']);
            }
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
            $total = bcmul($amount/0.7, 1,2);
            if($userAsset['amount']>=$total){
            }else{
                return $this->failJSON(lang('INVESTMENT_LESS_AMOUNT').$userAsset['amount']);
            }
            $userAsset = $balanceM->updateBalance($user_id,$type,$total);
            if(!$userAsset){
                $balanceM->rollback();
                return $this->failJSON(lang('INVESTMENT_REDUCE_WRONG'));
            }
            $AwardService = new \web\api\service\AwardService();
            $fee_num = $amount/7*3;
            $res = $AwardService->tradingReward($fee_num,$user_id);
            //计算奖金
            if(!$res){
                $balanceM->rollback();
                return $this->failJSON(lang('TRANSFER_REWARD_FAIL'));
            }
            
            $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $type, 4,0, $user_id,'用户理财');
            if(!$in_record_id){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }
        }elseif($style==1){
            $type = 4;
            $userAsset = $balanceM->getBalanceByType($user_id,$type);
            if($userAsset['amount']>=$amount){
            }else{
                return $this->failJSON(lang('INVESTMENT_LESS_AMOUNT').$userAsset['amount']);
            }
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
        }else{
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


    /**
     * 获取闪兑信息
     */
    public function getExchangeInfo(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $Quotation = new \addons\config\model\Quotation();
        $info = $Quotation->order('id desc')->find();
        $sysM = new \web\common\model\sys\SysParameterModel();
        $rate = $sysM->getValByName('xcbc_intergal');
        $balanceM = new \addons\member\model\Balance();
        $data = $balanceM->where(['user_id'=>$user_id])->select();
        $amount = [];
        foreach ($data as $k => $v) {
            if($v['type']==1){
                $amount['cbc_total'] = $v['amount'];
            }elseif($v['type']==2){
                $amount['cbc_use'] = $v['amount'];
            }elseif($v['type']==4){
                $amount['cbc_code'] = $v['amount'];
            }elseif($v['type']==6){
                $amount['shop_int'] = $v['amount'];
            }elseif($v['type']==8){
                $amount['xcbc'] = $v['amount'];
            }
        }

        if(!isset($amount['cbc_total'])) $amount['cbc_total'] = 0;
        if(!isset($amount['cbc_use'])) $amount['cbc_use'] = 0;
        if(!isset($amount['cbc_code'])) $amount['cbc_code'] = 0;
        if(!isset($amount['shop_int'])) $amount['shop_int'] = 0;
        if(!isset($amount['xcbc'])) $amount['xcbc'] = 0;

        $lilv['cbc']    = $info['price_now'];
        $lilv['xcbc']   = $rate;

        $price['cbc']       = $info['price_now'];
        $price['code']      = $info['price_now'];
        $price['shop_int']  = bcmul($info['price_now'], $info['price_now'],4);
        $price['xcbc']      =  bcmul($price['shop_int'], $rate,4);
        $this->successJSON([$amount,$lilv,$price]);
    }

    /**
     * 判断支付密码是否正确
     */
    public function checkPwd($user_id,$pay_password){
        $pay_password = md5($pay_password);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON(lang('TRANSFER_PAYPWD_WRONG'));
        }
        return $user;
    }

    /**
     * 闪兑
     * type 1-CBC转商城积分  2-激活码转商城积分 3-商城积分转XCBC-代币
     * number 价格
     */
    public function exchange(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $type = $this->_post('type');
        $number = $this->_post('number');
        $pay_password = $this->_post('pay_password');
        $user = $this->checkPwd($user_id,$pay_password);
        $balanceM = new \addons\member\model\Balance();
        if($type==1||$type==2){
            $Quotation = new \addons\config\model\Quotation();
            $info = $Quotation->order('id desc')->find();
            $amount = bcmul($number, 1/$info['price_now'],2);
            $balanceM->startTrans();
            if($type==1){
                $coin_id = 2;
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$number);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC2_LESS'));
                }

                $type = 18;
                $change_type = 0; //减少
                $remark = '用户闪兑，减少可用';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }

                $total = $number/0.7;
                $coin_id = 1;//CBC总额
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC1_LESS'));
                }
                $change_type = 0; //减少
                $remark = '用户闪兑，减少总额';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }

                $AwardService = new \web\api\service\AwardService();
                $fee = bcmul($total, 0.3,2);
                $res = $AwardService->tradingReward($fee,$user_id);
                //计算奖金
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_REWARD_FAIL'));
                }
            }else{
                $coin_id = 4;
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$number);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC2_LESS'));
                }

                $type = 18;
                $change_type = 0; //减少
                $remark = '用户闪兑，减少激活码';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }
            }
            $coin_id = 6;
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON(lang('TRANSFER_CBC6_ADD'));
            }
            $change_type = 1; //增加
            $remark = '用户闪兑，增加商城积分';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }

            $balanceM->commit();
            return $this->successJSON();
        }elseif($type==3){
            $balanceM->startTrans();
            $sysM = new \web\common\model\sys\SysParameterModel();
            $rate = $sysM->getValByName('xcbc_intergal');
            $amount = bcmul($number, 1/$rate,2);
            $coin_id = 6;
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$number);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON(lang('TRANSFER_CBC6_LESS'));
            }

            $type = 18;
            $change_type = 0; //减少
            $remark = '用户闪兑，减少商城积分';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }
            $coin_id = 8;
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON(lang('TRANSFER_CBC8_ADD'));
            }
            $change_type = 1; //增加
            $remark = '用户闪兑，增加XCBC';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }
            $balanceM->commit();
            return $this->successJSON();
        }
    }

    /**
     * 提币
     */
    public function extract(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $number = $this->_post('number');
        if(!$number) return $this->failJSON(lang('EXTRACT_NUMBER'));
        $address = $this->_post('address');
        if(!$address) return $this->failJSON(lang('EXTRACT_ADDRESS'));
        $password = $this->_post('pay_password');
        if(!$password) return $this->failJSON(lang('EXTRACT_PASSWORD'));
        $user = $this->checkPwd($user_id,$password);
        $code = $this->_post('code');
        if(!$code) return $this->failJSON(lang('EXTRACT_CODE'));
        $verifyM = new \addons\member\model\VericodeModel();
        $_verify = $verifyM->VerifyCode($code, $user['region_code'].$user['phone'],8);
        if(empty($_verify)) return $this->failJSON(lang('TRANSFER_VERIGYCODE_WRONG'));
        $balanceM = new \addons\member\model\Balance();
        $coin_id = 8;
        $userAmount = $balanceM->getBalanceByType($user_id,$coin_id);
        if($number>$userAmount['amount']) return $this->failJSON(lang('EXTRACT_XCBC_LESS'));
        $balanceM->startTrans();
        $userAmount = $balanceM->updateBalance($user_id,$coin_id,$number);
        if(!$userAmount){
            $balanceM->rollback();
            return $this->failJSON(lang('TRANSFER_CBC8_LESS'));
        }

        $type = 15;
        $change_type = 0; //减少
        $remark = '用户提币，减少XCBC';
        $recordM = new \addons\member\model\TradingRecord();
        $r_id = $recordM->addRecord($user_id, $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
        if(!$r_id){
            $balanceM->rollback();
            return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
        }
        $data = [
            'amount'=>$number,
            'address'=>$address,
            'user_id'=>$user_id,
            'status'=>0,
            'update_at'=>NOW_DATETIME,
            'create_at'=>NOW_DATETIME,
        ];
        $MemberExtract = new \addons\member\model\MemberExtract();
        $res = $MemberExtract->add($data);
        if($res){
            $balanceM->commit();
            $this->successJSON(lang('EXTRACT_SUC'));
        }else{
            $balanceM->rollback();
            $this->failJSON(lang('EXTRACT_FAIL'));
        }
    }
}
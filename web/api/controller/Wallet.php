<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/18
 * Time: 16:19
 */

namespace web\api\controller;


use addons\member\model\MemberAccountModel;
use addons\member\model\TradingRecord;
use function PHPSTORM_META\type;
use think\Request;
use think\Validate;
use web\api\service\AwardService;

class Wallet extends ApiBase
{
    /**
     * 发送CBC
     */
    public function sendCBC()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'address'   => 'require',
            'amount'    => 'require|>:0',
            'pay_password'  => 'require',
            'auth_code' => 'require',
        ]);

        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $address = $param['address'];
        $amount = $param['amount'];
        $pay_password   = $param['pay_password'];
        $auth_code  = $param['auth_code'];
        $sub_type = 1;
        $key_type = 4;
        $use_type = 2;

        $memberM = new MemberAccountModel();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();

        $member = $memberM->getDetail($this->user_id);
        if(empty($member))
            return $this->failJSON(lang('WALLET_LOGIN_WRONG'));

        $to_user_id = $memberM->getUserByAddress($address);
        if(empty($to_user_id))
            return $this->failJSON(lang('WALLET_TRUNOUT'));

//        if($member['pay_password'] != md5($pay_password))
//            return $this->failJSON(lang('WALLET_PAYPASS'));

        $verifyM = new \addons\member\model\VericodeModel();
        $_verify = $verifyM->VerifyCode($auth_code, $member['phone'],3);
        if(empty($_verify))
        {
            return $this->failJSON(lang('USER_VERI_WRONG'));
        }

        $paramM = new \web\common\model\sys\SysParameterModel();
        // $tax_rate = $paramM->getValByName('deal_tax'); //手续费比率
        $tax_amount = $amount/7*3;
        $total_amount = $amount + $tax_amount;
        $balance = $balanceM->verifyStock($this->user_id,$total_amount,$sub_type);
        if(empty($balance)){
            return $this->failJSON(lang('NODE_LESS_AMOUNT'));
        }
        if($total_amount > $balance['amount']){
            return $this->failJSON(lang('NODE_LESS_AMOUNT'));
        }

        try{
            $balanceM->startTrans();
            //扣除当前用户余额, 添加转出用户余额
            //转出方
            $balance = $balanceM->updateBalance($this->user_id, $sub_type, $total_amount);
            $use_balance = $balanceM->updateBalance($this->user_id,$use_type,$amount);

            //收入方
//            $to_type = 4;
//            $to_balance = $balanceM->updateBalance($to_user_id,$to_type,$total_amount,true);
//            $to_use_balance = $balanceM->updateBalance($to_user_id,$use_type,$amount,true);
            if($balance != false){
                $type = 1; //转账

                //转出方
                $change_type = 0; //减少
                $remark = '用户CBC转出,手续费金额:'.$tax_amount;
                $remark2 = '用户CBC转出';
                $recordM->addRecord($this->user_id,$amount,$use_balance['before_amount'],$use_balance['amount'],$use_type,$type,$change_type,$to_user_id,$remark2);
                $record_id = $recordM->addRecord($this->user_id, $total_amount, $balance['before_amount'], $balance['amount'], $sub_type, $type, $change_type, $to_user_id, $remark);

                //收入方
//                $to_change_type = 1; //增加
//                $to_remark = '用户CBC转入';
//                $recordM->addRecord($to_user_id,$amount,$to_balance['before_amount'],$to_balance['before_amount'],$to_balance['amount'],$to_type,$type,$to_change_type,$this->user_id,$to_remark);
//
//                $balanceM->commit();
//                $awardS = new AwardService();
//                $awardS->tradingReward($tax_amount,$this->user_id);
//                return $this->successJSON();

                if($record_id > 0){
                    $to_balance = $balanceM->updateBalance($to_user_id, $key_type, $amount, true);
                    if($to_balance != false){
                        $change_type = 1;
                        $remark = '用户CBC转入';
                        $record_id = $recordM->addRecord($to_user_id, $amount, $to_balance['before_amount'], $to_balance['amount'], $key_type, $type, $change_type, $this->user_id, $remark);
                        if($record_id > 0){
                            $balanceM->commit();
                            $awardS = new AwardService();
                            $awardS->tradingReward($tax_amount,$this->user_id);
                            return $this->successJSON();
                        }
                    }
                }
            }

        } catch (\Exception $ex) {
            $balanceM->rollback();
            return $this->failJSON($ex->getMessage());
        }

    }

    /**
     * 发送激活码
     */
    public function sendKey()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'address'   => 'require',
            'amount'    => 'require|>:0',
            'pay_password'  => 'require',
            'auth_code' => 'require',
        ]);

        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $address = $param['address'];
        $amount = $param['amount'];
        $pay_password   = $param['pay_password'];
        $auth_code  = $param['auth_code'];
        $sub_type = 4;
        $key_type = 4;

        $memberM = new MemberAccountModel();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();

        $member = $memberM->getDetail($this->user_id);
        if(empty($member))
            return $this->failJSON(lang('WALLET_LOGIN_WRONG'));

        $to_user_id = $memberM->getUserByAddress($address);
        if(empty($to_user_id))
            return $this->failJSON(lang('WALLET_TRUNOUT'));

        if($member['pay_password'] != md5($pay_password))
            return $this->failJSON(lang('WALLET_PAYPASS'));

        $verifyM = new \addons\member\model\VericodeModel();
        $_verify = $verifyM->VerifyCode($auth_code, $member['phone'],3);
        if(empty($_verify))
        {
            return $this->failJSON(lang('USER_VERI_WRONG'));
        }

//        $paramM = new \web\common\model\sys\SysParameterModel();
//        $tax_rate = $paramM->getValByName('deal_tax'); //手续费比率
//        $tax_amount = $tax_rate * $amount / 100;
//        $total_amount = $amount + $tax_amount;
        $balance = $balanceM->verifyStock($this->user_id,$amount,$sub_type);
        if(empty($balance)){
            return $this->failJSON(lang('NODE_LESS_AMOUNT'));
        }
        if($amount > $balance['amount']){
            return $this->failJSON(lang('NODE_LESS_AMOUNT'));
        }

        try{
            $balanceM->startTrans();
            //扣除当前用户余额, 添加转出用户余额
            $balance = $balanceM->updateBalance($this->user_id, $sub_type, $amount);
            if($balance != false){
                $type = 2; //激活码转账
                $change_type = 0; //减少
                $remark = '用户激活码转出';
                $record_id = $recordM->addRecord($this->user_id, $amount, $balance['before_amount'], $balance['amount'], $sub_type, $type, $change_type, $to_user_id, $remark);
                if($record_id > 0){
                    $to_balance = $balanceM->updateBalance($to_user_id, $key_type, $amount, true);
                    if($to_balance != false){
                        $change_type = 1;
                        $remark = '用户激活码转入';
                        $record_id = $recordM->addRecord($to_user_id, $amount, $to_balance['before_amount'], $to_balance['amount'], $sub_type, $type, $change_type, $this->user_id, $remark);
                        if($record_id > 0){
                            $balanceM->commit();
                            return $this->successJSON();
                        }
                    }
                }
            }

        } catch (\Exception $ex) {
            $balanceM->rollback();
            return $this->failJSON($ex->getMessage());
        }

    }

    /**
     * 交易明细
     */
    public function tradingRecord()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'type'    => 'require|integer'
        ]);

        $conf = array(
            'page'  => empty($param['page']) ? 1 : $param['page'],
            'list_rows' => empty($param['list_rows']) ? 5 : $param['list_rows']
        );

        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $recordM = new TradingRecord();

//        $filter = 'asset_type in (4) ';
//        if($param['type'] == 1)
//            $filter = 'asset_type in (1) ';

//        $fields = 'amount,type,to_user_id,update_time';
//        $data = $recordM->getDataList2($conf['page'],$conf['list_rows'],$filter,$fields,'update_time desc');
        $data = $recordM->getRecordList($param['type'],$conf['page'],$conf['list_rows'],$this->user_id,'update_time desc');
        foreach ($data as &$v)
        {
            $v['color_type'] = 1;
            switch ($v['type'])
            {
                case 9:
                case 10:
                case 11:
                    $v['color_type'] = 2;     break;
            }
        }

        return $this->successJSON($data);
    }

    /**
     * 我的收益
     */
    public function income()
    {
        $recordM = new TradingRecord();
        $trading_amount = $recordM->where(['user_id' => $this->user_id, 'type' => 9])->sum('amount');
        $share_amount = $recordM->where(['user_id' => $this->user_id, 'type' => 10])->sum('amount');
        $peer_amount = $recordM->where(['user_id' => $this->user_id, 'type' => 11])->sum('amount');
        $node_amount = $recordM->where(['user_id' => $this->user_id, 'type' => 14])->sum('amount');

        $data = array(
            // 'team_amount'       => 0,
            'team_amount'       => $trading_amount+$share_amount+$peer_amount,
            'node_amount'       => $node_amount,
            'trading_amount'    => $trading_amount,
            'share_amount'      => $share_amount,
            'peer_amount'       => $peer_amount,
        );
        return $this->successJSON($data);
    }
}


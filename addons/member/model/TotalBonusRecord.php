<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\member\model;

/**
 * Description of TotalBonusRecord
 * 奖金汇总表
 * @author shilinqing
 */
class TotalBonusRecord extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'member_total_bonus_record';
    }

    public function getDataList($pageIndex = -1, $pageSize = -1, $filter = '', $fileds = '*', $order = 'id asc') {
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = 'select ' . $fileds . ' from ' . $this->getTableName();
        $sql = 'select s.*,u.username from (' . $sql . ') as s left join ' . $userM->getTableName() . ' u on s.user_id=u.id ';
        if ($filter != '') {
            $sql = 'select * from (' . $sql . ') as y where ' . $filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getCrontabList($limit = '') {
        $where['total_bonus'] = array(">", 0);
        $fields = 'id,user_id,total_bonus,invite_bonus,duipen_bonus,manage_bonus,leader_bonus,center_bonus,recast_bonus';
        if ($limit != '') {
            $this->limit($limit);
        }
        return $this->where($where)->field($fields)->select();
    }

    /**
     * 推荐奖励
     * @param type $invite_send
     * @param typee $price 套餐金额
     * @param type $user_id 注册操作的用户,就是当前注册用户的上级
     * @param type $pv_value
     */
    public function inviteSend($invite_send, $price, $user_id, $pv_value) {
        $type = 10; //推荐奖励
        $invite_arr = explode(',', $invite_send);
        $u = new \addons\member\model\MemberAccountModel();
        $b = new \addons\member\model\BonusRecord();
        foreach ($invite_arr as $k => $rate) {
            if ($k != 0) {
                //查询上级
                $user_id = $u->getPID($user_id);
                if (empty($user_id)) {
                    break;
                }
            }
            $amount = $this->countAmount($price, $pv_value, $rate);
            $bonus_record = $this->updateBonus($user_id, $amount, $type);
            //添加明细记录
            $record_id = $b->addRecord($user_id, $amount, $type);
            
        }
        return true;
    }

    /**
     * 报单中心奖励
     * @param type $price
     * @param type $user_id 报单中心用户id
     * @param type $pv_value
     */
    public function centerSend($rate, $price, $user_id, $pv_value) {
        $b = new \addons\member\model\BonusRecord();
        $type = 14;
        $amount = $this->countAmount($price, $pv_value, $rate);
        $bonus_record = $this->updateBonus($user_id, $amount, $type);
        //添加明细记录
        $record_id = $b->addRecord($user_id, $amount, $type);
        
        return true;
    }

    /**
     * 对碰奖励
     * @param type $price 所购买套餐金额
     * @param type $aid 节点id
     * @param type $pv_value pv值
     */
    public function duipenSend($price, $aid, $user_id, $position, $pv_value) {
        $u = new \addons\member\model\MemberAccountModel();
        $m = new \addons\baodan\model\MealConfig();
        $b = new \addons\member\model\BonusRecord();
        $duipen_type = 11;
        //循环
        do {
            $user_total = $u->getUserTotalTrack($aid);
            $self_total = $user_total['self_total']; //自身
            $left_total = $user_total['left_total']; //左区
            $right_total = $user_total['right_total']; //右区
            if ($position == 0) {
                //新增的是左区业绩
                if ($left_total >= $right_total) {
                    //不对碰 ,直接更新业绩
                    $user_total['left_total'] = $left_total + $price;
                    $res = $u->save($user_total);
                } else {
                    $meal_id = $user_total['meal_id'];
                    
                    $devlop_bonus_rate = $m->getFieldByID($meal_id, 'devlop_bonus_rate');
                    $_left_total = $left_total + $price;
                    //判断是否已经封顶 , 如果已经达到当日封顶就更新业绩 ,continue
                    $limit = $m->getFieldByID($meal_id, 'bonus_limit'); //封顶金额
                    $today_total = $b->getTodayTotal($aid);
                    if($today_total >= $limit){
                        //更新业绩
                        $user_total['left_total'] = $_left_total;
                        $u->save($user_total);
                    }else{
                        
                        if (($right_total >= $_left_total)) {
                            //加上业绩还比右区小的话, 以新增业绩对碰
                            $amount = $this->countAmount($price, $pv_value, $devlop_bonus_rate);
                        } else {
                            //否则以差额计算: 右区旧业绩 - 左区旧业绩
                            $amount = $this->countAmount(($right_total - $left_total), $pv_value, $devlop_bonus_rate);
                        }
                        //判断还差多少到达封顶金额 $limit - $today_total = 封顶金额
                        $less_amount = $limit - $today_total;
                        if($amount > $less_amount){
                            $amount = $less_amount;
                        }
                        $bonus_record = $this->updateBonus($aid, $amount, $duipen_type);
                        //添加明细记录
                        $record_id = $b->addRecord($aid, $amount, $duipen_type);
                        //封顶判断--添加对碰奖金明细记录
                        if($bonus_record != false){
                            //发放管理奖励
                            $this->manageSend($aid, $amount, $pv_value);
                            //更新业绩
                            $user_total['left_total'] = $_left_total;
                            $u->save($user_total);
                        }
                    }
                    
                    
                }
            } else {
                //新增的是右区业绩
                if ($right_total >= $left_total) {
                    //右区旧业绩本来就比左区的大,不对碰 ,直接更新业绩
                    $user_total['right_total'] = $right_total + $price;
                    $u->save($user_total);
                } else {
                    $meal_id = $user_total['meal_id'];
                    $devlop_bonus_rate = $m->getFieldByID($meal_id, 'devlop_bonus_rate');
                    $_right_total = $right_total + $price; //新业绩
                    //判断是否已经封顶 , 如果已经达到当日封顶就更新业绩 ,continue
                    $limit = $m->getFieldByID($meal_id, 'bonus_limit'); //封顶金额
                    $today_total = $b->getTodayTotal($aid);
                    if($today_total >= $limit){
                        //更新业绩
                        $user_total['right_total'] = $_right_total;
                        $u->save($user_total);
                    }else{
                        if (($left_total >= $_right_total)) { // 14000 >= 140000
                            //加上右区新业绩还比左区小的话, 以新增业绩对碰
                            $amount = $this->countAmount($price, $pv_value, $devlop_bonus_rate);
                        } else {
                            //否则以差额计算: 左区旧业绩 - 右区旧业绩
                            $amount = $this->countAmount(($left_total - $right_total), $pv_value, $devlop_bonus_rate);
                        }
                        //判断还差多少到达封顶金额 $limit - $today_total = 封顶金额
                        $less_amount = $limit - $today_total;
                        if($amount > $less_amount){
                            $amount = $less_amount;
                        }
                        $bonus_record = $this->updateBonus($aid, $amount, $duipen_type);
                        //添加明细记录
                        $record_id = $b->addRecord($aid, $amount, $duipen_type);

                        if ($bonus_record != false) {
                            //更新业绩
                            $user_total['right_total'] = $_right_total;
                            $u->save($user_total);
                            //发放管理奖励
                            $this->manageSend($aid, $amount, $pv_value);
                        }
                    }
                    
                }
            }
            //获取上级节点id
            $aid_user = $u->getJdID($aid);
            if (!empty($aid_user)) {
                $aid = $aid_user['aid'];
                $position = $aid_user['position'];
            }else{
                $aid = 0;
            }
        } while ($aid > 0);

        return true;
    }

    /**
     * 发放管理奖励
     * @param type $user_id
     * @param type $amount
     */
    public function manageSend($user_id, $amount, $pv_value) {
        $type = 12;
        $u = new \addons\member\model\MemberAccountModel();
        $b = new \addons\member\model\BonusRecord();
        $over = 9;
        $pids = $u->getPids($user_id);
        $pid_arr = explode(',', $pids);
        foreach ($pid_arr as $k => $pid) {
            if($pid <= 0){
                break;
            }
            if ($k + 1 > $over) {
                break;
            }

            $invite_count = $u->getInviteCount($pid);
            if (($invite_count >= 1 && $k <= 3) || ($invite_count >= 2 && $k <= 5) || ($invite_count >= 3 && $k <= 7) || ($invite_count >= 5 && $k <= 9)) {
                $rate = 3;
//                $_amount = $this->countAmount($amount, $pv_value, $rate);
                $_amount = $amount * $rate / 100;
                $bonus_record = $this->updateBonus($pid, $_amount, $type);
                //添加明细记录
                $record_id = $b->addRecord($pid, $_amount, $type);
            }
        }
        return true;
    }

//    直推市场业绩累积达到1500万，第二个市场不低于350万即升级为 总监
//    比如A用户达到总监要求，升级为总监，那伞下会员新报单就能拿到总监分红:报单金额的0.5%。
//    (10000 * 0.5% *0.85 pv值 = 50),然后在A用户上级的三代内，如果存在相同级别（总监）的用户，
//    则该用户获得平级奖励:0.25% (10000 * 0.25% * 0.85 = 25)，三代后继续查询更高级别的领导，
//    如果再上级存在高级总监：则发放级差奖励：（原值为1%， 1%- （已发放的0.5%） = 0.5%），以此类推。
    public function leaderSend($user_id, $price, $pv_value) {
        $u = new \addons\member\model\MemberAccountModel();
        $leaderM = new \addons\baodan\model\LeaderConfig();
        $b = new \addons\member\model\BonusRecord();
        $type = 13;
        $pids = $u->getPids($user_id);
        $pid_arr = explode(',', $pids);
        $has_send_rate = 0; //已赠送rate 0 = 未赠送
        $conM = new \web\common\model\sys\SysParameterModel();
        $leader_equal_rate = $conM->getValByName('leader_equal_rate'); //平头奖比率
        $leader_bonus_num = $conM->getValByName('leader_bonus_num'); //平头奖代数
        $less_rate = 2.5; //总数2.5

        foreach ($pid_arr as $k => $pid) {
            $where['id'] = $pid;
            $total = $u->where($where)->field('self_total,team_direct_total')->find();
            $total_amount = $total['self_total'] + $total['team_direct_total']; //累计总金额
            $child_count = $u->getInviteCount($pid); //总直推人数
            if ($child_count != 0) {
                $child_total_amount = $u->getChildTeamTotal($pid); //小市场 : 1000,1000,..
                $rate = $leaderM->getRateByWhere($total_amount, $child_count, $child_total_amount);
                if ($rate > 0) {
                    if ($has_send_rate >= $rate) {
                        //平头奖
                        $need_send_rate = $leader_equal_rate;
                        if ($leader_bonus_num == 0) {
                            continue;
                        } else {
                            $leader_bonus_num = $leader_bonus_num - 1;
                        }
                    } else if ($has_send_rate < $rate) {
                        //更高级
                        $need_send_rate = $rate - $has_send_rate;
                        $less_rate = $less_rate - $rate; // 总监: 2.5 = 2.5 - 0.5 
                        $has_send_rate = $rate; //替换已发放的比率 0.5 = 1 
                    }
                    $amount = $this->countAmount($price, $pv_value, $need_send_rate);
                    $bonus_record = $this->updateBonus($pid, $amount, $type);
                    //添加明细记录
                    $record_id = $b->addRecord($pid, $_amount, $type);
                }
            }
        }
        return true;
    }

    /**
     * 发放复投奖
     * 50% 见点奖励:按节点释放
     * recast_bonus_num  代数
     * recast_bonus_rate 百分比
     */
    public function sendRecastBonus($user_id, $price) {
        $type = 15; //复投奖金
        $u = new \addons\member\model\MemberAccountModel();
        $conM = new \web\common\model\sys\SysParameterModel();
        $b = new \addons\member\model\BonusRecord();
        $recast_bonus_num = $conM->getValByName('recast_bonus_num');
        $recast_bonus_rate = $conM->getValByName('recast_bonus_rate');
        $pv_value = $conM->getValByName('pv_value');
        for ($i = 0; $i < $recast_bonus_num; $i++) {
            $aid = $u->where('id=' . $user_id)->value('aid');
            if ($aid > 0) {
                $amount = $this->countAmount($price, $pv_value, $recast_bonus_rate);
                $bonus_record = $this->updateBonus($aid, $amount, $type);
                //添加明细记录
                $record_id = $b->addRecord($aid, $amount, $type);
            } else {
                return true;
            }
            $user_id = $aid;
        }
        return true;
    }

    public function countAmount($amount, $pv_value, $rate) {
        $amount = $amount * $pv_value * $rate / 100;
        return $amount;
    }

    /**
     * 更新用户资产
     * @param type $user_id
     * @param type $amount 变动金额
     * @param type $change 变动类型，false 减值，true增值
     * @param type $type 10=推荐奖金，11=对碰奖金，12=管理奖金，13=领导奖金，14=报单中心奖金,15=复投奖金
     * @return type
     */
    public function updateBonus($user_id, $amount, $type) {
        $where['user_id'] = $user_id;
        $data = $this->where($where)->find();
        if (empty($data)) {
            $data['user_id'] = $user_id;
            $data['total_bonus'] = 0;
            $data['invite_bonus'] = 0;
            $data['invite_total_bonus'] = 0;
            $data['duipen_bonus'] = 0;
            $data['duipen_total_bonus'] = 0;
            $data['manage_bonus'] = 0;
            $data['manage_total_bonus'] = 0;
            $data['leader_bonus'] = 0;
            $data['leader_total_bonus'] = 0;
            $data['center_bonus'] = 0;
            $data['center_total_bonus'] = 0;
            $data['recast_bonus'] = 0;
            $data['recast_total_bonus'] = 0;
        }
        $data['update_time'] = NOW_DATETIME;
        $data['total_bonus'] = $data['total_bonus'] + $amount;
        if ($type == 10) {
            $data['invite_bonus'] = $data['invite_bonus'] + $amount;
            $data['invite_total_bonus'] = $data['invite_total_bonus'] + $amount;
        } else if ($type == 11) {
            $data['duipen_bonus'] = $data['duipen_bonus'] + $amount;
            $data['duipen_total_bonus'] = $data['duipen_total_bonus'] + $amount;
        } else if ($type == 12) {
            $data['manage_bonus'] = $data['manage_bonus'] + $amount;
            $data['manage_total_bonus'] = $data['manage_total_bonus'] + $amount;
        } else if ($type == 13) {
            $data['leader_bonus'] = $data['leader_bonus'] + $amount;
            $data['leader_total_bonus'] = $data['leader_total_bonus'] + $amount;
        } else if ($type == 14) {
            $data['center_bonus'] = $data['center_bonus'] + $amount;
            $data['center_total_bonus'] = $data['center_total_bonus'] + $amount;
        } else if ($type == 15) {
            $data['recast_bonus'] = $data['recast_bonus'] + $amount;
            $data['recast_total_bonus'] = $data['recast_total_bonus'] + $amount;
        }
        $res = $this->save($data);
        if (!$res) {
            return false;
        }
        return $data;
    }

}

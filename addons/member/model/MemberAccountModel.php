<?php

namespace addons\member\model;

/**
 * 会员账户信息
 *
 * @author shilinqing
 */
class MemberAccountModel extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'member_account';
    }
    
    /**
     * 更新团队直推业绩
     * @param type $user_id
     * @param type $price
     * @return boolean
     */
    public function updateTeamDirectTotal($user_id ,$price){
        $pids = $this->getPids($user_id);
        $pid_arr = explode(',', $pids);
        foreach($pid_arr as $k => $pid){
            $sql = 'update '.$this->getTableName().' set team_direct_total=team_direct_total+'.$price.' where id='.$pid;
            $this->query($sql);
        }
        return true;
    }
    
    /**
     * 减少用户团队直推业绩
     * @param type $user_id
     * @param type $price
     * @return boolean
     */
    public function minusTeamDirectTotal($user_id,$price){
        $pids = $this->getPids($user_id);
        $pid_arr = explode(',', $pids);
        foreach($pid_arr as $k => $pid){
            $sql = 'update '.$this->getTableName().' set team_direct_total=team_direct_total-'.$price.' where id='.$pid;
            $this->query($sql);
        }
        return true;
    }
    
//    select group_concat(self_total+team_direct_total) total from `tp_member_account` where pid=1;
    public function getChildTeamTotal($pid){
        $where['pid'] = $pid;
        $data = $this->where($where)->field('group_concat(self_total+team_direct_total) total')->find();
        if(!empty($data)){
            return $data['total'];
        }
        return '';
    }
    
    /**
     * 获取最后节点id
     * @param type $id
     * @param int $position
     * @return type
     */
    public function getChildIdByPosition($id,$position){
        $jdid = $id;
        $where['aid'] = $id;
        $where['position'] = $position;
        $_id = $this->where($where)->value('id');
        if(!empty($_id)){
            $jdid = $_id;
            // if($position == 1){
            //     //如果选择的是右区,且右区不为空,则查询右区的左区
            //     $position = 0;
            // }
            return $this->getChildIdByPosition($jdid, $position);
        }else{
            $data['position'] = $position;
            $data['aid'] = $jdid;
            return $data;
        }
    }
    
    /**
     * 获取直推总人数
     * @param type $id
     * @return type
     */
    public function getInviteCount($id){
        $where['pid'] = $id;
        return $this->where($where)->count();
    }
    
    public function getJdID($aid){
        $where['id'] = $aid;
        return $this->where($where)->field('aid,position')->find();
    }
    
    /**
     * 获取用户自身+ 左右区 业绩
     * @param type $id
     */
    public function getUserTotalTrack($id){
        $where['id'] = $id;
        return $this->where($where)->field('id,position,meal_id,self_total,left_total,right_total')->find();
    }


    /**
     * 根据用户名获取用户id
     * @param type $username
     * @return type
     */
    public function getUserIDByUsername($username, $is_center=0){
        $where['username'] = $username;
        if($is_center == 1){
            $where['is_center'] = $is_center;
        }
        return $this->where($where)->value('id');
    }
    
    public function getLeftChild($aid){
        $where['aid'] = $aid;
        $where['position'] = 0;
        return $this->where($where)->value('id');
    }
    
    /**
     * 获取节点id 
     * @param type $id
     * @return type
     */
    public function getChildsByAID($aid){
        $where['aid'] = $aid;
        return $this->where($where)->field('id,aid,username,left_total,right_total')->select();
    }
    
    public function userIsCenter($id){
        $where['id'] = $id;
        return $this->where($where)->value('is_center');
    }

    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
        $TransferM = new \addons\member\model\Transfer();
        $sql = 'select tab.*,c.phone as invite_user_phone,p.quota,p.today_quota,p.power from ' . $this->getTableName() . ' as tab left join ' . $this->getTableName() . ' c on tab.pid=c.id  left join ' . $TransferM->getTableName() . ' p on tab.id=p.user_id';
        if (!empty($filter))
            $sql = 'select * from (' . $sql . ') t where ' . $filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getList2($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
//        $TransferM = new \addons\member\model\Transfer();
        $sql = 'select tab.*,c.phone as invite_user_phone from ' . $this->getTableName() . ' as tab left join ' . $this->getTableName() . ' c on tab.pid=c.id';
        if (!empty($filter))
            $sql = 'select * from (' . $sql . ') t where ' . $filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getPids($id, $pids = '') {
        $pid = $this->getPID($id);
        if (!empty($pid)) {
            if ($pids != '')
                $pids .= ',';
            $pids .= $pid;
            return $this->getPids($pid, $pids);
        }else {
            return $pids;
        }
    }

    public function getUserBankAndAddress($id) {
        $where['id'] = $id;
        $fileds = 'real_name,bank_name,bank_other,bank_code,address';
        return $this->where($where)->field($fileds)->find();
    }

    /**
     * get user login info . (filed phone and wallet_name) one of them has to be filled out
     * @param type $password
     * @param type $phone
     * @param type $wallet_name
     * @param type $fields
     * @return boolean
     */
    public function getLoginData($password, $phone = '', $fields = 'id,username,address,is_auth', $both = false) {
        $sql = 'select ' . $fields . ' from ' . $this->getTableName() . ' where logic_delete=0';
        if (!empty($phone)) {
            if ($both) {
                $sql .= ' and (phone=\'' . $phone . '\' or username=\'' . $phone . '\')';
            } else {
                $sql .= ' and phone=\'' . $phone . '\'';
            }
        }
        $sql .= ' and password=\'' . md5($password) . '\'';
        $result = $this->query($sql);
        if (!empty($result) && count($result) > 0)
            return $result[0];
        else
            return null;
    }

    public function getLoginDataById($password, $phone = '', $fields = 'id,username,address,is_auth', $both = false) {
        $sql = 'select ' . $fields . ' from ' . $this->getTableName() . ' where logic_delete=0';
        $sql .= '  and id=\'' . $phone . '\' and password=\'' . md5($password) . '\'';
        $result = $this->query($sql);
        if (!empty($result) && count($result) > 0)
            return $result[0];
        else
            return null;
    }

    /**
     * 短信验证登录时 只有电话号码用来查询用户信息
     * get user login info . filed phone  has to be filled out
     * @param type $phone
     * @param type $fields
     * @return boolean
     */
    public function getLoginDataBySms($phone = '', $fields = 'id,username,address,is_auth') {
        $sql = 'select ' . $fields . ' from ' . $this->getTableName() . ' where logic_delete=0';
        if (!empty($phone)) {
            $sql .= ' and phone=\'' . $phone . '\'';
        } else {
            return false;
        }
        $result = $this->query($sql);
        if (!empty($result) && count($result) > 0)
            return $result[0];
        else
            return null;
    }

    /**
     * @param string $field_name
     * @param string $field_value
     * @param $password
     * @param string $fields
     * @return bool
     */
    public function getNewLoginData($field_name = '', $field_value = '', $password, $fields = 'id,username,address,is_auth') {
        $where = [
            $field_name => $field_value,
            'logic_delete' => 0,
        ];
        $info = $this->where($where)->field($fields)->find();
        if (!$info) {
            $this->error = "账号或密码错误";
            return false;
        }
        $mdPass = md5(md5($password) . $info['salt']);
        if ($mdPass !== $info['password']) {
            $this->error = "账号或密码错误";
            return false;
        }
        return $info;
    }

    /**
     * verify the user's phone is registered or not
     * @param type $phone
     * @return type
     */
    public function hasRegsterPhone($phone) {
        $where['phone'] = $phone;
        return $this->where($where)->count();
    }

    /**
     * verify the user's username is registered or not
     * @param type $phone
     * @return type
     */
    public function hasRegsterUsername($username) {
        $where['username'] = $username;
        return $this->where($where)->count();
    }

    /**
     * verify the user's wallet name is registered or not
     * @param type $name
     * @return type
     */
    public function hasRegsterWallet($name) {
        $where['wallet_name'] = $name;
        return $this->where($where)->count();
    }

    /**
     * update the user password by phone number
     * @param type $phone
     * @param type $password
     * @param type $type 2=login password ,3 = payment password
     * @return int
     */
    public function updatePassByUserID($id, $password, $type = 2) {
        if ($type == 2) {
            $data['password'] = $password;
        } else if ($type == 4) {
            $data['pay_password'] = $password;
        } else {
            return 0;
        }
        $where['id'] = $id;
        return $this->where($where)->update($data);
    }

    /**
     * get user by invite code
     * @param type $invite_code
     * @return int
     */
    public function getUserByInviteCode($invite_code) {
        $where['invite_code'] = $invite_code;
        $res = $this->where($where)->field('id')->find();
        if (!empty($res)) {
            return $res['id'];
        } else {
            return 0;
        }
    }

    /**
     * get user parent id
     * @param type $user_id
     * @return type
     */
    public function getPID($id) {
        $where['id'] = $id;
        $ret = $this->where($where)->field('pid')->find();
        return $ret['pid'];
    }

    /**
     * get user eth address
     * @param type $user_id
     */
    public function getUserAddress($id) {
        $where['id'] = $id;
        $data = $this->where($where)->field('address')->find();
        return $data['address'];
    }

    /**
     * get user by the eth address
     * @param type $address
     * @return int
     */
    public function getUserByAddress($address) {
        $where['address'] = $address;
        $data = $this->where($where)->find();
        if (!empty($data)) {
            return $data['id'];
        } else {
            return 0;
        }
    }

    /**
     * get user by the username
     * @param type $address
     * @return int
     */
    public function getUserByUsername($username) {
        $where['username'] = $username;
        $data = $this->where($where)->find();
        if (!empty($data)) {
            return $data['id'];
        } else {
            return 0;
        }
    }

    public function getUserByPhone($username) {
        $where['phone'] = $username;
        $data = $this->where($where)->find();
        if (!empty($data)) {
            return $data['id'];
        } else {
            return 0;
        }
    }

    /**
     * get user authentication data
     * @param type $id
     * @param type $fields
     * @return type
     */
    public function getAuthData($id, $fields = 'real_name,card_no,id_face,id_back') {
        $where['id'] = $id;
        return $this->where($where)->field($fields)->find();
    }

    /**
     * return user authentication status
     * @param type $id
     */
    public function getAuthByUserID($id) {
        $where['id'] = $id;
        $auth = $this->where($where)->field('is_auth')->find();
        return $auth['is_auth'];
    }

    /**
     * change user account frozen status
     * @param type $id
     * @param type $status default 1
     * @return type
     */
    public function changeFrozenStatus($id, $status = 1) {
        $where['id'] = $id;
        $data['is_frozen'] = $status;
        return $this->where($where)->update($data);
    }

    /**
     * @param $pid
     * @param string $fields
     * @return array|mixed
     */
    public function getUserListByPid($pid, $fields = "id,username,address,register_time") {

        $sql = "select {$fields} from {$this->getTableName()} where pid in ({$pid})";
        $res = $this->query($sql);
        if (!$res) {
            return [];
        }
        $m = new \addons\fomo\model\KeyRecord();
        $tokenM = new \addons\fomo\model\TokenRecord();
        $sql = "select a.*,ifnull(sum(b.key_num),0) key_num from({$sql}) a left join {$m->getTableName()} b on a.id = b.user_id";
        $sql = "select a.*,ifnull(c.token,0) token from({$sql}) a left join {$tokenM->getTableName()} c on a.id = c.user_id";
        $result = $this->query($sql);
        return $result;
    }

    /**
     * @param $pid int 顶点用户
     * @param $tier int 层级
     * @param array $arr 子集
     * @return array $arr 子集返回
     */
    public function getTeamById($pid, $tier, $arr = []) {
        $where = ['pid' => ['in', $pid]];
        $ret = $this->where($where)->field("id,pid,username,{$tier} tier")->select();
        if (!$ret) {
            return $arr;
        }
        $arr = array_merge($arr, $ret);
        $ids = array_column($ret, 'id');
        $ids = join(',', $ids);
        return $this->getTeamById($ids, $tier += 1, $arr);
    }
    
    public function getInviteTree($id) {
//        $sql = 'select id,pid pId,group_concat(username,team_direct_total) name from ' . $this->getTableName();
//        return $this->query($sql);
//        return $this->field('id,pid pId,concat(username,\' 业绩: \',team_direct_total+self_total) name')->select();
        $ids = $id;
        while ($id != ''){
            $where['pid'] = array('in',$id);
            $pids = $this->where($where)->field('group_concat(id) pids')->find();
            if(!empty($pids)){
                $pids = $pids['pids'];
                if($ids != ''){
                    $ids .= ',';
                }
                $ids .= $pids;

            }
            $id = $pids;
            
        }
        $ids = substr($ids, 0,strlen($ids)-1);
        $fields = 'id, pid pId,concat(username,\' 业绩: \',team_direct_total+self_total) name';
        $where1['id'] = array('in',$ids);
        $data = $this->where($where1)->field($fields)->select();
        return $data;
//        dump($data);exit;
    }
    //获取用户等级显示
    public function getMemberLevelDisplay($level)
    {
        switch ($level){
            case 2:
                $levelDisplay = '业务员';
                break;
            case 3:
                $levelDisplay = '经理';
                break;
            case 4:
                $levelDisplay = '总经理';
                break;
            default:
                $levelDisplay = '普通用户';
                break;
        }
        return $levelDisplay;
    }

}

<?php

namespace addons\baodan\model;

class MealOrder extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'baodan_meal_order';
    }

    public function addOrder($user_id, $meal_id, $order_code, $sub_amount, $bonus_amount, $sp_amount, $real_name, $phone, $address) {
        $data['meal_id'] = $meal_id;
        $data['user_id'] = $user_id;
        $data['order_code'] = $order_code;
        $data['sub_amount'] = $sub_amount;
        $data['bonus_amount'] = $bonus_amount;
        $data['sp_amount']= $sp_amount;
        $data['real_name'] = $real_name;
        $data['phone'] = $phone;
        $data['address'] = $address;
        $data['status'] = 0;
        $data['update_time'] = NOW_DATETIME;
        return $this->add($data);
    }

}

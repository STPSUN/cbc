<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/31
 * Time: 10:07
 */

namespace addons\config\user\controller;


use addons\member\model\Balance;
use addons\member\model\MemberAccountModel;
use addons\member\model\Trading;
use addons\member\model\TradingRecord;
use web\api\model\MemberNode;
use web\api\model\MemberNodeIncome;

class Count extends \web\user\controller\AddonUserBase
{
    public function index()
    {
        $memberM = new MemberAccountModel();
        $member_num = $memberM->count();
        $today_member_num = $memberM->whereTime('register_time','today')->count();
        $auth_member_num = $memberM->where('is_auth',1)->count();
        $not_auth_member_num = $memberM->where('is_auth',0)->count();

        $tradingM = new Trading();
        $recordM = new TradingRecord();

        $order_num = $tradingM->count();
        $order_amount = $tradingM->sum('number');
        $deal_order_num = $tradingM->where('type',3)->count();
        $deal_order_amount = $tradingM->where('type',3)->sum('number');
        $cancel_num = $tradingM->where('status',1)->count();
        $cancel_amount = $tradingM->where('status',1)->sum('number');
        $order = array(
            'num' => $order_num,
            'amount'  => $order_amount,
            'deal_num'    => $deal_order_num,
            'deal_amount' => $deal_order_amount,
            'cancel_num'  => $cancel_num,
            'cancel_amount' => $cancel_amount,
        );

        $yes_deal= array(
            'num'   => $tradingM->where('type','<>',0)->whereTime('update_time','yesterday')->count(),
            'amount'   => $tradingM->where('type','<>',0)->whereTime('update_time','yesterday')->sum('number'),
            'success_num'   => $tradingM->where('type',3)->whereTime('update_time','yesterday')->count(),
            'success_amount'   => $tradingM->where('type',3)->whereTime('update_time','yesterday')->sum('number'),
        );

        $day_deal= array(
            'num'   => $tradingM->where('type','<>',0)->whereTime('update_time','today')->count(),
            'amount'   => $tradingM->where('type','<>',0)->whereTime('update_time','today')->sum('number'),
            'success_num'   => $tradingM->where('type',3)->whereTime('update_time','today')->count(),
            'success_amount'   => $tradingM->where('type',3)->whereTime('update_time','today')->sum('number'),
        );

        $balanceM = new Balance();
        $total_cbc = $balanceM->where('type',1)->sum('amount');
        $total_key = $balanceM->where('type',4)->sum('amount');
        $total = array(
            'cbc' => $total_cbc,
            'key' => $total_key,
        );

        $incomeM = new MemberNodeIncome();
        $today_node_key = $incomeM->where('type',8)->whereTime('create_time','today')->sum('amount');
        $today_node = array(
            'cbc'   => $incomeM->whereTime('create_time','today')->where('type','not in','8')->sum('amount'),
            'key'   => bcmul($today_node_key,0.7,2),
        );

        $memberNodeM = new MemberNode();
        $today_activity = array(
            'v' => $memberNodeM->where(['status' => 1, 'type' => 1])->whereTime('create_time','today')->count(),
            'ss' => $memberNodeM->where(['status' => 1, 'type' => 2])->whereTime('create_time','today')->count(),
            's' => $memberNodeM->where(['status' => 1, 'type' => 3])->whereTime('create_time','today')->count(),
            'ms' => $memberNodeM->where(['status' => 1, 'type' => 4])->whereTime('create_time','today')->count(),
            'mb' => $memberNodeM->where(['status' => 1, 'type' => 5])->whereTime('create_time','today')->count(),
            'b' => $memberNodeM->where(['status' => 1, 'type' => 6])->whereTime('create_time','today')->count(),
            'bs' => $memberNodeM->where(['status' => 1, 'type' => 7])->whereTime('create_time','today')->count(),
            'x' => $memberNodeM->where(['status' => 1, 'type' => 8])->whereTime('create_time','today')->count(),
        );

        $yesterday_activity = array(
            'v' => $memberNodeM->where(['status' => 1, 'type' => 1])->whereTime('create_time','yesterday')->count(),
            'ss' => $memberNodeM->where(['status' => 1, 'type' => 2])->whereTime('create_time','yesterday')->count(),
            's' => $memberNodeM->where(['status' => 1, 'type' => 3])->whereTime('create_time','yesterday')->count(),
            'ms' => $memberNodeM->where(['status' => 1, 'type' => 4])->whereTime('create_time','yesterday')->count(),
            'mb' => $memberNodeM->where(['status' => 1, 'type' => 5])->whereTime('create_time','yesterday')->count(),
            'b' => $memberNodeM->where(['status' => 1, 'type' => 6])->whereTime('create_time','yesterday')->count(),
            'bs' => $memberNodeM->where(['status' => 1, 'type' => 7])->whereTime('create_time','yesterday')->count(),
            'x' => $memberNodeM->where(['status' => 1, 'type' => 8])->whereTime('create_time','yesterday')->count(),
        );

        $node = array(
            'v' => $memberNodeM->where(['status' => 1, 'type' => 1])->count(),
            'ss' => $memberNodeM->where(['status' => 1, 'type' => 2])->count(),
            's' => $memberNodeM->where(['status' => 1, 'type' => 3])->count(),
            'ms' => $memberNodeM->where(['status' => 1, 'type' => 4])->count(),
            'mb' => $memberNodeM->where(['status' => 1, 'type' => 5])->count(),
            'b' => $memberNodeM->where(['status' => 1, 'type' => 6])->count(),
            'bs' => $memberNodeM->where(['status' => 1, 'type' => 7])->count(),
            'x' => $memberNodeM->where(['status' => 1, 'type' => 8])->count(),
        );

        $total_deal = $tradingM->where('type',3)->sum('number');

        $this->assign('member_num', $member_num);
        $this->assign('today_member_num',$today_member_num);
        $this->assign('auth_member_num',$auth_member_num);
        $this->assign('not_auth_member_num',$not_auth_member_num);

        $this->assign('order',$order);
        $this->assign('total',$total);
        $this->assign('today_node',$today_node);
        $this->assign('today_activity',$today_activity);
        $this->assign('yes_activity',$yesterday_activity);
        $this->assign('yes_deal',$yes_deal);
        $this->assign('day_deal',$day_deal);
        $this->assign('node',$node);
        $this->assign('total_deal',$total_deal);

        return $this->fetch();
    }
}













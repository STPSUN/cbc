<?php

namespace addons\member\user\controller;

/**
 * Description of Record
 * 交易记录
 * @author shilinqing
 */
class Record extends \web\user\controller\AddonUserBase{
    
    public function index(){
        $m = new \addons\config\model\BalanceConf();
        $list = $m->getDataList(-1,-1,'','id,name','id asc');
        $this->assign('confs',$list);
        return $this->fetch();
    }
    
    public function loadList(){
        $keyword = $this->_get('keyword');
        $asset_type = $this->_get('asset_type');
        $change_type = $this->_get('change_type');
        $type = $this->_get('type');
        $startDate = $this->_get('startDate');
        $endDate = $this->_get("endDate");
        $filter = '1=1';
        if($type != ''){
            $filter .= ' and type='.$type;
        }
        if($asset_type != ''){
            $filter .= ' and asset_type='.$asset_type;
        }
         if($change_type != ''){
            $filter .= ' and change_type='.$change_type;
        }
        if ($keyword != null) {
            $filter .= ' and user_id like \'%' . $keyword . '%\'';
        }
        if ($startDate != null && $endDate != null)
            $filter .= ' and (update_time >= \'' . $startDate . ' 00:00:00\' and update_time <= \'' . $endDate . ' 23:59:59\')';
        elseif ($startDate != null)
            $filter .= ' and (update_time >= \'' . $startDate . ' 00:00:00\')';
        elseif ($endDate != null)
            $filter .= ' and (update_time <= \'' . $endDate . ' 23:59:59\')';
        $m = new \addons\member\model\TradingRecord();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter,'*','id desc');
        $count_total = $m->getCountTotal($filter);
        return $this->toTotalDataGrid($total, $rows,$count_total);
    }
    

    private function exportout(){
        $all = M('user u')->where($map)->field('u.userid,username,mobile,cangku_num,fengmi_num')->join('left join ysk_store s on s.uid=u.userid')->select();
        import("Vendor.PHPExcel.PHPExcel",'', '.php');
        import("Vendor.PHPExcel.PHPExcel.Writer.Excel5", '', '.php');
        import("Vendor.PHPExcel.PHPExcel.IOFactory", '', '.php');
        $objPHPExcel = new \PHPExcel();
        // 设置文档信息，这个文档信息windows系统可以右键文件属性查看
        $objPHPExcel->getProperties()->setCreator("gca")
            ->setLastModifiedBy("gca")
            ->setTitle("gca point")
            ->setSubject("gca point")
            ->setDescription("gca Capital flow");
        //根据excel坐标，添加数据
        $objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', 'UID')
        ->setCellValue('B1', '姓名')
        ->setCellValue('C1', '手机号')
        ->setCellValue('D1', '余额')
        ->setCellValue('E1', '积分');
        foreach($all as $k=>$v){
            $num = $k+2;
            $objPHPExcel->getActiveSheet()
                ->setCellValue('A'.$num,$v['userid'])
                ->setCellValue('B'.$num,$v['username'])
                ->setCellValue('C'.$num,$v['mobile'])
                ->setCellValue('D'.$num,$v['cangku_num'])
                ->setCellValue('E'.$num,$v['fengmi_num']);
            
        }
        // 设置第一个sheet为工作的sheet
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $filename = date('Y-m-d').'.xlsx';
        // 保存Excel 2007格式文件，保存路径为当前路径，名字为export.xlsx
        ob_end_clean();     //清除缓冲区,避免乱码
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="'.$filename.'"');
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        $objWriter->save('php://output');
    }
}

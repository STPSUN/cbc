<?php


// [ 应用入口文件 ]
// 定义应用目录
define('APP_NAMESPACE', 'web');
define('APP_PATH', __DIR__ . '/../web/');
// 定义插件命名空间
define('ADDONS_NAMESPACE', 'addons');
// 定义插件目录
define('ADDONS_PATH', __DIR__ . '/../addons/');
//上传目录
define('UPLOADFOLDER', './uploads/');
if(PHP_SAPI == 'cli'){
    $type = $_SERVER['argv'][1];
    if($type==1){
		define('BIND_MODULE','api/Crontab/cancleOrder');
    }elseif($type==2){
		define('BIND_MODULE','api/Crontab/nodeRelease');
    }elseif($type==3){
		define('BIND_MODULE','api/Crontab/auto_quota');
    }elseif($type==4){
		define('BIND_MODULE','api/Crontab/auto_receive');
    }elseif($type==5){
		define('BIND_MODULE','api/Crontab/superNodeAward');
    }elseif($type==6){
        define('BIND_MODULE','api/Crontab/releaseAllNode');
    }elseif($type==7){
        define('BIND_MODULE','api/Crontab/timetest');
    }elseif($type==8){
        define('BIND_MODULE','api/test/balanceDiff2');
    }elseif($type==9){
        define('BIND_MODULE','api/test/balanceDiff');
    }
}else{
	eixt();
}
// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';

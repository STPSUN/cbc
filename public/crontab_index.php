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
    }
}else{
	eixt();
}
// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';

<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午9:26
 * gearman worker manager
 */

use inhere\gearman\LiteManager;
use inhere\gearman\tools\FileLogger;

error_reporting(E_ALL | E_STRICT);

require __DIR__ . '/simple-loader.php';

date_default_timezone_set('Asia/Shanghai');

// create job logger
// use: FileLogger::info('message', ['data'], 'test_job');
FileLogger::create(__DIR__ . '/logs/jobs', FileLogger::SPLIT_DAY);

$config = [
    'name' => 'test-lite',
    'daemon' => false,
    'pid_file' => __DIR__ . '/lite-manager.pid',

    'log_level' => LiteManager::LOG_DEBUG,
    'log_file' => __DIR__ . '/logs/lite-manager.log',
];

$mgr = new LiteManager($config);

require __DIR__ . '/job_handlers.php';

$mgr->start();

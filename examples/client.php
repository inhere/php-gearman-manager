<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午11:03
 */

require __DIR__ . '/simple-loader.php';

$client = new \inhere\gearman\JobClient();

$ret1 = $client->doNormal('reverse_string', 'hello a');
$ret2 = $client->doBackground('reverse_string', 'hello b');

var_dump($ret1, $ret2);

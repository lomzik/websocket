#!/usr/bin/env php
<?php

if (empty($argv[1]) || !in_array($argv[1], array('start', 'stop'))) {
    die("не указан параметр (start|stop|test)\r\n");
}

$config = array(
    'websocket' => 'tcp://127.0.0.1:8001',
    'workers' => 16,
    'pid' => '/tmp/websocket2.pid'
);

require_once('../../../WebsocketGeneric.php');
require_once('../../../WebsocketMaster.php');
require_once('../../../WebsocketWorker.php');
require_once('../../../WebsocketServer.php');

require_once('WebsocketWorkerHandler.php');
require_once('WebsocketMasterHandler.php');

$WebsocketServer = new WebsocketServer($config);
call_user_func(array($WebsocketServer, $argv[1]));
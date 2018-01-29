<?php
/**
 * Copyright (C) Alibaba Cloud Computing
 * All rights reserved
 */

class Aliyun_Log_Logger{

    protected $client;

    protected $project;

    protected $logstore;

    protected function __construct($client, $project, $logstore)
    {
        $this ->client = $client;
        $this->logstore=$logstore;
        $this->project=$project;
    }

    public function log(Aliyun_Log_Models_LogLevel_LogLevel $logLevel, $logMessage, $topic){
        if(!$logLevel instanceof Aliyun_Log_Models_LogLevel_LogLevel){
            throw new Exception('LogLevel value is invalid!');
        }
        if(is_array($logMessage)){
            throw new Exception('array is not supported in this function, please use logArray!');
        }
        $ip = $this->getLocalIp();
        $contents = array( // key-value pair
            'time'=>date('m/d/Y h:i:s a', time()),
            'message'=> $logMessage,
            'loglevel'=> Aliyun_Log_Models_LogLevel_LogLevel::getLevelStr($logLevel)
        );
        try {
            $logItem = new Aliyun_Log_Models_LogItem();
            $logItem->setTime(time());
            $logItem->setContents($contents);
            $logitems = array($logItem);
            $request = new Aliyun_Log_Models_PutLogsRequest($this->project, $this->logstore,
                $topic, $ip, $logitems);
            $response = $this->client->putLogs($request);
        } catch (Aliyun_Log_Exception $ex) {
            var_dump($ex);
        } catch (Exception $ex) {
            var_dump($ex);
        }
    }

    public function logArray(Aliyun_Log_Models_LogLevel_LogLevel $logLevel, $logMessage, $topic){
        if(!$logLevel instanceof Aliyun_Log_Models_LogLevel_LogLevel){
            throw new Exception('LogLevel value is invalid!');
        }
        if(!is_array($logMessage)){
            throw new Exception('input message is not array, please use log!');
        }
        $contents = array( // key-value pair
            'time'=>date('m/d/Y h:i:s a', time())
        );
        $ip = $this->getLocalIp();
        if(is_array($logMessage)){
            foreach ($logMessage as $key => $value)
            $contents[$key] = $value;
        }
        $contents['logLevel'] = Aliyun_Log_Models_LogLevel_LogLevel::getLevelStr($logLevel);
        try {
            $logItem = new Aliyun_Log_Models_LogItem();
            $logItem->setTime(time());
            $logItem->setContents($contents);
            $logitems = array($logItem);
            $request = new Aliyun_Log_Models_PutLogsRequest($this->project, $this->logstore,
                $topic, $ip, $logitems);
            $response = $this->client->putLogs($request);
        } catch (Aliyun_Log_Exception $ex) {
            var_dump($ex);
        } catch (Exception $ex) {
            var_dump($ex);
        }
    }

    public function logBatch($logItems, $topic){
        $ip = $this->getLocalIp();
        try{
            $request = new Aliyun_Log_Models_PutLogsRequest($this->project, $this->logstore,
                $topic, $ip, $logItems);
            $response = $this->client->putLogs($request);
        } catch (Aliyun_Log_Exception $ex) {
            var_dump($ex);
        } catch (Exception $ex) {
            var_dump($ex);
        }
    }

    private function getLocalIp(){
        $local_ip = getHostByName(php_uname('n'));
        if(strlen($local_ip) == 0){
            $local_ip = getHostByName(getHostName());
        }
        return $local_ip;
    }
}
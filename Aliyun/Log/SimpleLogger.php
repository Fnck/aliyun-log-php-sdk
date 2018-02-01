<?php
/**
 * Copyright (C) Alibaba Cloud Computing
 * All rights reserved
 */

/**
 * Class Aliyun_Log_SimpleLogger
 * simple logger for submit log message
 */
class Aliyun_Log_SimpleLogger{

    private $logItems = [];

    private $arraySize;

    private $topic;

    private $waitTime;

    private $previousLogTime;

    private $client;

    private $project;

    private $logstore;

    /**
     * Aliyun_Log_Models_LogBatch constructor.
     * @param $client log client
     * @param $project the corresponding project
     * @param $logstore the logstore
     * @param $topic
     * @param null $cacheLogCount max log items limitation, by default it's 100
     * @param null $cacheLogWaitTime max thread waiting time, bydefault it's 5 seconds
     */
    public function __construct($client, $project, $logstore, $topic, $cacheLogCount = null, $cacheLogWaitTime = null)
    {
        if(NULL === $cacheLogCount || !is_integer($cacheLogCount)){
            $this->arraySize = 10;
        }else{
            $this->arraySize = $cacheLogCount;
        }

        if(NULL === $cacheLogWaitTime || !is_integer($cacheLogWaitTime)){
            $this->waitTime = 5;
        }else{
            $this->waitTime = $cacheLogWaitTime;
        }
        if($client == null || $project == null || $logstore == null){
            throw new Exception('the input parameter is invalid! create SimpleLogger failed!');
        }
        $this->client = $client;
        $this->project = $project;
        $this->logstore = $logstore;
        $this->topic = $topic;
    }

    /**
     * log expected message with proper level
     * @param $logMessage
     * @param $logLevel
     * @param $topic should be null
     */
    private function log(Aliyun_Log_Models_LogLevel_LogLevel $logLevel,$logMessage){
        $previousCallTime = $this->previousLogTime;
        if(null ===  $previousCallTime){
            $previousCallTime = 0;
        }
        $this->previousLogTime = time();
        if(is_array($logMessage)){
            throw new Exception('array is not supported in this function, please use logArrayMessage!');
        }else{
            $logItems = $this->logItems;
            $contents = array( // key-value pair
                'time'=>date('m/d/Y h:i:s a', time()),
                'message'=> $logMessage,
                'loglevel'=> Aliyun_Log_Models_LogLevel_LogLevel::getLevelStr($logLevel)
            );
            $logItem = new Aliyun_Log_Models_LogItem();
            $logItem->setTime(time());
            $logItem->setContents($contents);

            array_push($logItems, $logItem);

            if((sizeof($logItems) == $this->arraySize
                    || $this->previousLogTime - $previousCallTime > 5000)
                    && $previousCallTime > 0){
                $this->logBatch($logItems, $this->topic);
                $logItems = [];
            }
            $this->logItems = $logItems;
        }
    }

    private function logItem($logItem){
        $previousCallTime = $this->previousLogTime;
        if(null ===  $previousCallTime){
            $previousCallTime = 0;
        }
        $this->previousLogTime = time();
        $logItems = $this->logItems;

        array_push($logItems, $logItem);

        if((sizeof($logItems) == $this->arraySize
                || $this->previousLogTime - $previousCallTime > 5000)
            && $previousCallTime > 0){
            $this->logBatch($logItems, $this->topic);
            $logItems = [];
        }
        $this->logItems = $logItems;
    }

    public function logSingleMessage(Aliyun_Log_Models_LogLevel_LogLevel $logLevel, $logMessage){
        if(!$logLevel instanceof Aliyun_Log_Models_LogLevel_LogLevel){
            throw new Exception('LogLevel value is invalid!');
        }
        if(is_array($logMessage)){
            throw new Exception('array is not supported in this function, please use logArrayMessage!');
        }
        $this->log($logLevel, $logMessage);
    }

    public function logArrayMessage(Aliyun_Log_Models_LogLevel_LogLevel $logLevel, $logMessage){
        if(!$logLevel instanceof Aliyun_Log_Models_LogLevel_LogLevel){
            throw new Exception('LogLevel value is invalid!');
        }
        if(!is_array($logMessage)){
            throw new Exception('input message is not array, please use logSingleMessage!');
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
        $logItem = new Aliyun_Log_Models_LogItem();
        $logItem->setTime(time());
        $logItem->setContents($contents);

        $this->logItem($logItem);
    }

    public function info( $logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelInfo();
        $this->logSingleMessage($logLevel, $logMessage);
    }
    public function debug($logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelDebug();
        $this->logSingleMessage($logLevel, $logMessage);
    }
    public function warn($logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelWarn();
        $this->logSingleMessage($logLevel, $logMessage);
    }
    public function error($logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelError();
        $this->logSingleMessage($logLevel, $logMessage);
    }

    public function infoArray($logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelInfo();
        $this->logArrayMessage($logLevel, $logMessage);
    }
    public function debugArray($logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelDebug();
        $this->logArrayMessage($logLevel, $logMessage);
    }
    public function warnArray($logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelWarn();
        $this->logArrayMessage($logLevel, $logMessage);
    }
    public function errorArray( $logMessage){
        $logLevel = Aliyun_Log_Models_LogLevel_LogLevel::getLevelError();
        $this->logArrayMessage($logLevel, $logMessage);
    }

    private function getLocalIp(){
        $local_ip = getHostByName(php_uname('n'));
        if(strlen($local_ip) == 0){
            $local_ip = getHostByName(getHostName());
        }
        return $local_ip;
    }

    private function logBatch($logItems, $topic){
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

    /**
     * manually flush all cached log to log server
     */
    public function logFlush(){
        if(sizeof($this->logItems) > 0){
            $this->logBatch($this->logItems, $this->topic);
            $this->logItems = [];
        }
    }

    function __destruct() {
        if(sizeof($this->logItems) > 0){
            $this->logBatch($this->logItems, $this->topic);
        }
    }
}
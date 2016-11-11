<?php
 /*
  * Spring Signage Ltd - http://www.springsignage.com
  * Copyright (C) 2016 Spring Signage Ltd
  * (YahooBase.php)
  */
 

 namespace Xibo\Widget;
 
 use GuzzleHttp\Client;
 use GuzzleHttp\Exception\RequestException;
 use Xibo\Exception\NotFoundException;
 use Xibo\Factory\ModuleFactory; 
 
 /**
  * Class YahooBase
  * @package Xibo\Widget
  */
 abstract class YahooBase extends ModuleWidget
 {
     /**
      * Request from Yahoo API
      * @param $yql
      * @return array|bool
      */
     protected function request($yql)
     {
         // Encode the YQL and make the request
         $url = 'https://query.yahooapis.com/v1/public/yql?q=' . urlencode($yql) . '&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys';
         //$url = 'https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quote%20where%20symbol%20in%20(%22TEC.PA%22)&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=';

         $client = new Client();

         try {
             $response = $client->get($url, $this->getConfig()->getGuzzleProxy());

             if ($response->getStatusCode() == 200) {
                 return json_decode($response->getBody(), true)['query']['results'];
             }
             else {
                 $this->getLog()->info('Invalid response from Yahoo %d. %s', $response->getStatusCode(), $response->getBody());
                 return false;
             }
         }
         catch (RequestException $e) {
             $this->getLog()->error('Unable to reach Yahoo API: %s', $e->getMessage());
             return false;
         }
     }
 
 }
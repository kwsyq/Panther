<?php 
/* inc/classesSMS_FlowRoute.class.php

EXECUTIVE SUMMARY: a class related to SMS messaging. Extends abstract class SMS.

>>>00001 JM: I've never properly studied this, nor (I believe) has anyone 
other than Martin ever even looked at it as of 2019-02-27. I made a quick-
and-dirty pass through it today, did some cleanup, added some comments.

* Public methods
** __construct($did, $otherDid, $id, $body, $direction,$mediaarray, $customer = false)
** processOutbound($returnBody) 
** + a large number of inherited public methods
*/

require_once dirname(__FILE__).'/../config.php'; // ADDED 2019-02-13 JM
// require_once dirname(__FILE__).'/../determine_environment.php'; // ADDED 2020-05-13 CP (and promptly removed by JM: already included by config.php)

class SMS_FlowRoute  extends SMS {
    
    /*
    OLD CODE removed 2019-02-04 JM
    const USERNAME = '41470238';
    const PASSWORD = '31nA0oi3xXUdaZ4GjQt6QY3xCAI7gx5M';
    */
    // BEGIN NEW CODE 2019-02-04 JM
    const USERNAME = FLOWROUTE_API_USER;
    const PASSWORD = FLOWROUTE_API_PASS;
    // END NEW CODE 2019-02-04 JM
    
    public function __construct($did, $otherDid, $id, $body, $direction,$mediaarray, $customer = false) {        
        parent::__construct();
        
        $this->setDid($did);
        $this->setOtherDid($otherDid);
        $this->setId($id);
        $this->setBody($body);
        $this->setDirection($direction);
        $this->setMediaArray($mediaarray);
        $this->customer = $customer;        
    }
    
    // Sends SMS messages, but ONLY if we are in a production enironment. Otherwise, just logs to
    // let us know what would have been sent.
    public function processOutbound($returnBody) {        
        global $logger;
        $success = false;
        
        $data = array();
        $data['to'] = $this->getOtherDid();
        $data['from'] = $this->getDid();
        $data['body'] = $returnBody;

        $data_string = json_encode($data);
        
        if (environment()==ENVIRONMENT_PRODUCTION) {            
            $ch = curl_init('https://api.flowroute.com/v2/messages');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_USERPWD, self::USERNAME . ":" . self::PASSWORD);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string))
            );
            
            $result = curl_exec($ch);            
            $info = curl_getinfo($ch);            
            curl_close($ch);
            
            $success = false;            
            if (is_array($info)) {            
                if (isset($info['http_code'])) {                        
                    if ($info['http_code'] == 200) {            
                        $success = true;            
                    }                        
                }            
            }
                
            return $success;
        } else {
            // log the JSON-encoded method
            $logger->info2('1589372087', "As system is not in production environment, SMS is logged and not sent: ". $data_string);            
            return true; // simulate success
        }

    } // END public function processOutbound    
    
}

?>
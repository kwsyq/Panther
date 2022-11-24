<?php
/* SSSMail.class.php

EXECUTIVE SUMMARY: Email. Our extension of Zend_Mail. Imposes a configuration, and 
catches some exceptions, returning false instead of throwing an exception.

>>>00001 JM: I've never properly studied this, nor (I believe) has anyone 
else other than Martin ever even looked at it as of 2019-02-28. I made a quick-
and-dirty pass through it today, did some cleanup, added some comments.

* Public methods
**  __construct()
** send($transport = null)
** & of course all the inherited methods from Zend_Mail 

*/

include('Zend/Mail/Transport/Smtp.php');
include 'Zend/Mail.php';
require_once dirname(__FILE__).'/../config.php'; // ADDED 2019-02-13 JM

/* Martin comment:
 * make sure to have the path to the zend framework classes set in the include path.
 * i.e. put something like this in config
 * 
 *    $path = BASEDIR . '/inc/classes/';
 *    set_include_path(get_include_path() . PATH_SEPARATOR . $path);
 * 
 */

class SSSMail extends Zend_Mail{    
    public function __construct() {
        
        //Martin comment:
        // at some point on a per customer basis maybe pull
        // this info from a DB.  
        // if no config it will also try to send from localhost 
    
        try {
            /*
            OLD CODE removed 2019-02-04 JM
            $config = array('ssl' => 'tls',
                'port' => 587,
                'auth' => 'login',
                'username' => 'inbox@ssseng.com',
                'password' => '$alutations4Th3m'
            );
            */
            // BEGIN NEW CODE 2019-02-04 JM
            $config = array('ssl' => 'tls',
                'port' => SSSMAIL_PORT,
                'auth' => 'login',
                'username' => SSSMAIL_USERNAME,
                'password' => SSSMAIL_PASSWORD
            );
            // END NEW CODE 2019-02-04 JM

                $transport = new Zend_Mail_Transport_Smtp('smtp.office365.com', $config);
                $this->setDefaultTransport($transport);
            } catch (Zend_Exception $e) {
        }        
    } // END public function __construct()


    /*
        2020-05-19 [CP] 
        In case of production environment, the send method will send a real email message
        In case of a different environment the message with all the details will be logged as info message
    */
    public function send($transport = null) {        
        global $logger;
        $sent = true;
        try {
            if (environment()==ENVIRONMENT_PRODUCTION) {   
                parent::send($transport);
            } else {
                // 2020-05-19 [CP] log the body message 
                $logger->info2('1589372088', "As system is not in production environment, Email message is logged and not sent. Recipients: [" .print_r($this->getRecipients(), true) ."]\n Subject: [ ". print_r($this->getSubject(), true). "]\n Body: [". print_r($this->getBodyText(), true)."]");                           
                // END - [CP] log the body message
            }
        } catch (Exception $e) {
            $sent = false;
        }
        
        if ($sent) {
            return true;
        } else {
            return false;
        }        
    }    
}

?>
<?php
/* inc/classes/SMS.class.php

EXECUTIVE SUMMARY: This is an abstract class related to SMS messaging.

>>>00001 JM: I've never properly studied this, nor (I believe) has anyone 
other than Martin ever even looked at it as of 2019-02-27. I made a quick-
and-dirty pass through it today, did some cleanup, added some comments.

* Public methods
** public static function smsPermsEmployee()
** public static function smsPerms()
** __construct()
** getDid()
** getOtherDid()
** getId()
** getBody()
** getMediaArray
** processInbound()

* This is an abstract class, so no surprise there are a lot of protected "set" methods.
  >>>00001 What is perhaps odder is that inheriting classes can also directly access the variables
  in question. Protected methods:
** setDid($did)
** setOtherDid($otherDid)
** setId($id)
** setBody($body)
** setMediaArray($mediaarray)
** setDirection($direction)
** checkBody($inboundSmsId)
*/
require_once dirname(__FILE__).'/../config.php'; // ADDED 2019-02-13 JM

abstract class  SMS {
    // Because this doesn't inherit from class SSSEng, it needs this handle to the database.
    protected $db;

    protected $direction;
    protected $did;
    protected $otherDid;
    protected $id;
    protected $body;
    protected $mediaarray;
    protected $customer;	
    
    // RETURNs an array indexed by the IDs of SMS permissions. Content seems to be names of those
    //  permissions & what commands they correspond to.
    // >>>00001: why "employee"? Needs further documentation.
    public static function smsPermsEmployee() {	
        $smsPermsEmployee = array();
        $smsPermsEmployee[SMS_PERM_EMP_PING] = array('display' => 'Ping', 'command' => 'PING');
        $smsPermsEmployee[SMS_PERM_EMP_SOU] = array('display' => 'State Of Union', 'command' => 'SOU');	
        return $smsPermsEmployee;	
    }
    
    // RETURNs an array indexed by the IDs of SMS permissions. Content seems to be names of those
    //  permissions & what commands they correspond to.
    // [Martin comment, moved]: if this array needs updating then update it in config.php too
    public static function smsPerms() {
        $smsPerms = array();
        $smsPerms[SMS_PERM_PING] = array('display' => 'Ping','command' => 'PING');
        $smsPerms[SMS_PERM_HELP] = array('display' => 'Help','command' => 'HELP');
        $smsPerms[SMS_PERM_OPEN] = array('display' => 'Open','command' => 'OPEN');
        $smsPerms[SMS_PERM_JOBS] = array('display' => 'Jobs','command' => 'JOBS');
        $smsPerms[SMS_PERM_OTHER] = array('display' => 'Other','command' => 'OTHER');

        return $smsPerms;
    }

    public function __construct() {
        $this->db = DB::getInstance();
    }

    protected function setDid($did) {
        $did = trim($did);
        $did = preg_replace("/[^0-9]/", "", $did);
        $this->did = $did;
    }

    protected function setOtherDid($otherDid) {
        $otherDid = trim($otherDid);
        $otherDid = preg_replace("/[^0-9]/", "", $otherDid);
        $this->otherDid = $otherDid;
    }

    protected function setId($id) {
        $id = trim($id);
        $id = substr($id, 0, 128); // >>>00002 truncates silently
        $this->id = $id;
    }

    protected function setBody($body) {
        $body = trim($body);
        $body = substr($body, 0, 1024); // >>>00002 truncates silently
        $this->body = $body;
    }

    protected function setMediaArray($mediaarray) {
        if (!is_array($mediaarray)) {
            $mediaarray = array();
        }

        $this->mediaarray = $mediaarray;
    }

    protected function setDirection($direction) {
        $this->direction = $direction;
    }

    public function getDid() {
        return $this->did;
    }

    public function getOtherDid() {
        return $this->otherDid;
    }

    public function getId() {
        return $this->id;
    }

    public function getBody() {
        return $this->body;
    }

    public function getMediaArray() {
        return $this->mediaarray;
    }

    protected function checkBody($inboundSmsId) {
        $persons = array();
        $db = DB::getInstance();  // >>>00017 no idea why this gets its own instance of the DB. Similarly for many 
                                  // other functions here, haven't bothered to note this at each use
                                  // In this particular case, just below, it ignores that we got this instance, and
                                  // uses $this->db anyway! 

        $num = (strlen($this->otherDid) == 11) ? substr($this->otherDid,1) : $this->otherDid;

        $query  = " select * ";
        $query .= " from " . DB__NEW_DATABASE . ".personPhone pp ";
        $query .= " join " . DB__NEW_DATABASE . ".person p on pp.personId = p.personId ";
        $query .= " where pp.phoneNumber  = '" . $db->real_escape_string($num) . "' ";

        if ($result = $this->db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $persons[] = $row;
                }
            }
        } // >>>00002 else ignores failure on DB query! Does this throughout file, 
          // haven't noted each instance.		
        
        $check = strtoupper($this->getBody());
        
        if ($this->customer) {
            if (intval($this->customer->getCustomerId())) {
                foreach (self::smsPermsEmployee() as $key => $perm) {				
                    if ($check == $perm['command']) {
                        $e = array();							
                        $employees = $this->customer->getEmployees(1);
                            
                        foreach ($employees as $employee) {						
                            $e[$employee->getUserId()] = $employee;
                        }

                        $personId = 0;
                        
                        foreach ($persons as $person) {
                            $p = new Person($person['personId']);
                                
                            if (($key & $e[$p->getPersonId()]->smsPerm) && (array_key_exists($p->getPersonId(), $e))  ) {
                                $personId = $p->getPersonId();
                                break;
                            }
                        }
                        if (intval($personId)) {						
                            $delete = false;
                            if ($key == SMS_PERM_EMP_PING) {				
                                $returnBody = "PONG\n" . time() . "\n";
                                $returnBody .= "\n\n";
                            
                                $this->processOutbound($returnBody);
                                $delete = true;							
                            }							
                            
                            if ($key == SMS_PERM_EMP_SOU) { // "state of union"
                                $titles = array();
                                $titles['tab0'] = '(wo closed, tasks open)';
                                $titles['tab1'] = '(wo open, tasks closed)';
                                $titles['tab2'] = '(wo no invoice)';
                                $titles['tab3'] = '(wo closed, open invoice)';
                                $titles['tab4'] = '(mailroom -- awaiting delivery)';
                                $titles['tab5'] = '(aging sumary - awaiting payment)';
                                $titles['tab6'] = '(cred recs)';
                                $titles['tab7'] = '(do payments)';							    
                        
                                $payload = array();							    
                                $tabs = array();
                                
                                $db = DB::getInstance();
                                
                                $query = " select * from " . DB__NEW_DATABASE . ".ajaxData where dataName in ('tab0','tab1','tab2','tab3','tab4','tab5') ";
                                
                                if ($result = $db->query($query)) {  // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $payload[$row['dataName']] = unserialize(base64_decode($row['dataArray']));
                                        }
                                    }
                                }
                                
                                $returnBody = '';
                                $returnBody .= "State of the union....\n\n";
                                
                                foreach ($payload as $pay) {
                                    $returnBody .= $pay['title'];
                                    $returnBody .= "\n";
                                    if (isset($pay['data'])) {
                                        $dat = $pay['data'];
                                        foreach ($dat as $dkey => $d) {
                                            $returnBody .= $dkey . ' : ' . $d . "\n";							                
                                        }
                                    }
                                    $returnBody .= "===============\n\n";							        
                                }
                                $returnBody .= "\n\n";
                                    
                                $this->processOutbound($returnBody);
                                $delete = true;
                            }
                        }
                        
                        if ($delete) {
                            if (intval($inboundSmsId)) {
                                $query = "delete from " . DB__NEW_DATABASE . ".inboundSms where inboundSmsId = " . intval($inboundSmsId);
                                $this->db->query($query);
                            }
                        }
                    }
                }				
            }			
        }
        
        foreach (self::smsPerms() as $key => $perm) {
            if ($check == $perm['command']) {
                $personId = 0;
                foreach ($persons as $person) {
                    $p = new Person($person['personId']);

                    if ($key & $p->getSmsPerms()) {
                        $personId = $p->getPersonId();
                        break;
                    }
                }

                if (intval($personId)) {
                    $delete = false;
                    if ($key == SMS_PERM_PING) {
                                $returnBody = "PONG\n" . time() . "\n";
                                $returnBody .= "\n\n";

                                $this->processOutbound($returnBody);
                                $delete = true;
                    }
                    if ($key == SMS_PERM_HELP) {
                            $delete = true;
                    }
                    if ($key == SMS_PERM_OPEN) {
// [Martin comment] encapsulate the generating of these urls to some other place
                        $privateTypeId = PRIVTYPE_OPEN_WO;
                        if ($privateTypeId) {
                            $db = DB::getInstance();
                            $token = 0;
                            $query  = " select max(privateId) as maxid from " . DB__NEW_DATABASE . ".private ";
                            
                            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $token = $row['maxid'];
                                    }
                                }
                            }

                            $token += rand(10,50);

                            $query = " insert into " . DB__NEW_DATABASE . ".private (privateId, privateTypeId, id) values (";
                            $query .= " " . intval($token) . " ";
                            $query .= " ," . intval($privateTypeId) . " ";
                            $query .= " ," . intval($personId) . ") ";

                            $this->db->query($query);

                                $expires = 1;

                                $params = array();
                                $params['e'] = time() + ($expires * 60);
                                $params['t'] = $token;

                                $qs = signRequest($params, PRIVATE_HASH_KEY);

                                $returnBody = "Use this link to view your current open workorders\n\n";
                                $returnBody .= REQUEST_SCHEME . '://' . HTTP_HOST . '/c/openwo.php?' . $qs;
                                $returnBody .= "\n\n";
                                $returnBody .= "Link is valid for " . $expires . " minutes";
                                $returnBody .= "\n";

                                $this->processOutbound($returnBody);
                        }

                        $delete = true;
                    }
                    if ($key == SMS_PERM_JOBS) {
                        $privateTypeId = PRIVTYPE_JOBS;

                        if ($privateTypeId) {
                            $db = DB::getInstance();
                            $token = 0;
                            $query  = " select max(privateId) as maxid from " . DB__NEW_DATABASE . ".private ";

                            if ($result = $db->query($query)) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $token = $row['maxid'];
                                    }
                                }
                            }

                            $token += rand(10,50);
                            
                            $query = " insert into " . DB__NEW_DATABASE . ".private (privateId, privateTypeId, id) values (";
                            $query .= " " . intval($token) . " ";
                            $query .= " ," . intval($privateTypeId) . " ";
                            $query .= " ," . intval($personId) . ") ";

                            $this->db->query($query);
                                $expires = 1; 

                                $params = array();
                                $params['e'] = time() + ($expires * 86400);
                                $params['t'] = $token;

                                $qs = signRequest($params, PRIVATE_HASH_KEY);

                                $returnBody = "Use this link to view your current open jobs/workorders\n\n";
                                $returnBody .= REQUEST_SCHEME . '://' . HTTP_HOST . '/c/jobs.php?' . $qs;

                                $returnBody .= "\n\n";
                                $returnBody .= "Link is valid for " . $expires . " day(s)";
                                $returnBody .= "\n";

                                $this->processOutbound($returnBody);
                        }

                        $delete = true;
                    }
                    if ($key == SMS_PERM_OTHER) {
                        $delete = true;
                    }

                    if ($delete) {
                        if (intval($inboundSmsId)) {
                            $query = "delete from " . DB__NEW_DATABASE . ".inboundSms where inboundSmsId = " . intval($inboundSmsId);
                            $this->db->query($query);
                        }
                    }
                }
            }
        }


        /* BEGIN commented out by Martin some time before 2019
        if (strtoupper($this->getBody()) == 'STAT'){

            $returnBody = "STATS\n";
            $returnBody .= "nothing here yet !!!\n";
            $returnBody .= "\n\n";

            $this->processOutbound($returnBody);

        }
        // END commented out by Martin some time before 2019
        */
    } // END protected function checkBody

    private function makeThumb($image_name,$new_width,$new_height,$thumbname) {
            $path = $image_name;
            $mime = getimagesize($path);

            if($mime['mime']=='image/png') {
                $src_img = imagecreatefrompng($path);
            }
            if($mime['mime']=='image/jpg' || $mime['mime']=='image/jpeg' || $mime['mime']=='image/pjpeg') {
                $src_img = imagecreatefromjpeg($path);
            }
            if($mime['mime']=='image/gif') {
                $src_img = imagecreatefromgif($path);
            }
            if($mime['mime']=='image/bmp') {
                $src_img = imagecreatefromwbmp($path);
            }

            $old_x          =   imageSX($src_img);
            $old_y          =   imageSY($src_img);

            if($old_x > $old_y) {
                $thumb_w    =   $new_width;
                $thumb_h    =   $old_y*($new_height/$old_x);
            }

            if($old_x < $old_y) {
                $thumb_w    =   $old_x*($new_width/$old_y);
                $thumb_h    =   $new_height;
            }

            if($old_x == $old_y) {
                $thumb_w    =   $new_width;
                $thumb_h    =   $new_height;
            }

            $dst_img        =   ImageCreateTrueColor($thumb_w,$thumb_h);

            imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y);

            // New save location
            $new_thumb_loc = $thumbname;

            if($mime['mime']=='image/png') {
                $result = imagepng($dst_img,$new_thumb_loc,8);
            }
            if($mime['mime']=='image/jpg' || $mime['mime']=='image/jpeg' || $mime['mime']=='image/pjpeg') {
                $result = imagejpeg($dst_img,$new_thumb_loc,80);
            }
            if($mime['mime']=='image/gif') {
                $result = imagegif($dst_img,$new_thumb_loc,8);
            }
            if($mime['mime']=='image/bmp') {
                $result = imagewbmp($dst_img,$new_thumb_loc,8);
            }

            imagedestroy($dst_img);
            imagedestroy($src_img);

            return $result;
    } // END private function makeThumb

    public function processInbound() {
        $query = " insert into  " . DB__NEW_DATABASE . ".inboundSms (smsProviderId,didTo,didFrom,id,body,media) values (";
        $query .= " " . intval(SMSPROVIDERID_FLOWROUTE) . " ";
        $query .= " ," . $this->db->real_escape_string($this->getDid()) . " ";
        $query .= " ," . $this->db->real_escape_string($this->getOtherDid()) . " ";
        $query .= " ,'" . $this->db->real_escape_string($this->getId()) . "' ";
        $query .= " ,'" . $this->db->real_escape_string($this->getBody()) . "' ";
        $query .= " ,'" . $this->db->real_escape_string(serialize($this->getMediaArray())) . "') ";

        $this->db->query($query);
        ///// <--- JM 2019-02: here & below, where Martin left marks like this I've left them. Please feel free to remove if you don't find them useful 
        $id = $this->db->insert_id;

        // [BEGIN MARTIN COMMENT]
        // make this more complete
        //
        //http://developer.flowroute.com/api/messages/v2.1/
        // [END MARTIN COMMENT]

        $mimes = array();
        $mimes['image/gif'] = array('mime' => 'image/gif', 'extension' => 'gif');
        $mimes['image/jpeg'] = array('mime' => 'image/jpeg', 'extension' => 'jpg');
        $mimes['image/bmp'] = array('mime' => 'image/bmp', 'extension' => 'bmp');
        $mimes['video/3gpp'] = array('mime' => 'video/3gpp', 'extension' => '3gp');
        $mimes['video/mp4'] = array('mime' => 'video/mp4', 'extension' => 'mp4');
        $mimes['video/x-msvideo'] = array('mime' => 'video/x-msvideo', 'extension' => 'avi');
        $mimes['video/avi'] = array('mime' => 'video/avi', 'extension' => 'avi');
        $mimes['audio/amr'] = array('mime' => 'audio/amr', 'extension' => 'amr');
        $mimes['audio/wav'] = array('mime' => 'audio/wav', 'extension' => 'wav');
        $mimes['audio/x-wav'] = array('mime' => 'audio/x-wav', 'extension' => 'wav');
        $mimes['audio/ac3'] = array('mime' => 'audio/ac3', 'extension' => 'ac3');
        $mimes['audio/mpeg'] = array('mime' => 'audio/mpeg', 'extension' => 'mp3');
        $mimes['audio/mpeg3'] = array('mime' => 'audio/mpeg3', 'extension' => 'mp3');
        $mimes['application/pdf'] = array('mime' => 'application/pdf', 'extension' => 'pdf');
        $mimes['application/zip'] = array('mime' => 'application/zip', 'extension' => 'zip');

        if (intval($id)) {
            /////
            /////
            $recipients = getSmsNotifyEmails();
            $body = "SMS Arrived from " . $this->getOtherDid() . "\n\n";
            $mail = new SSSMail();
            /*
            OLD CODE removed 2019-02-04 JM
            $mail->setFrom('inbox@ssseng.com', 'Sound Structural Solutions');
            */
            // BEGIN NEW CODE 2019-02-04 JM
            $mail->setFrom(CUSTOMER_INBOX, CUSTOMER_NAME);
            // END NEW CODE 2019-02-04 JM

            foreach ($recipients as $recipient) {
                $mail->addTo($recipient['address'], $recipient['name']);
            }
            $mail->setSubject('SMS Arrived (' . $this->getOtherDid() . ')');
            $mail->setBodyText($body);
            $result = $mail->send();
            if ($result) {
                //echo "ok";
            } else {
                //echo "fail";
            }
            //////
            /////

            $medias = $this->getMediaArray();
            if (is_array($medias)) {
                $mediasnew = array();
                foreach ($medias as $mkey => $media) {
                    $mimetype = strtolower($media['mimetype']);
                    if (isset($mimes[$mimetype])) {
                        $name = $id . '_' . $mkey . '.' . $mimes[$mimetype]['extension'];
                        $thumbname = $id . '_' . $mkey . '_thumb.' . $mimes[$mimetype]['extension'];

                        /*
                        OLD CODE removed 2019-02-04 JM
                        $newfname = BASEDIR . '/../ssseng_documents/sms_attachments/' . $name;
                        $thumbfname = BASEDIR . '/../ssseng_documents/sms_attachments/' . $thumbname;
                        */
                        // BEGIN NEW CODE 2019-02-04 JM
                        $newfname = LANG_FILES_DIR.'/sms_attachments/' . $name;
                        $thumbfname = LANG_FILES_DIR.'/sms_attachments/' . $thumbname;
                        // END NEW CODE 2019-02-04 JM

                        $file = fopen ($media['url'], 'rb');
                        if ($file) {
                            $newf = fopen ($newfname, 'wb');
                            if ($newf) {
                                while(!feof($file)) {
                                    fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
                                }
                            }
                        }
                        if ($file) {
                            fclose($file);
                        }
                        if ($newf) {
                            fclose($newf);
                            
                            $pos = strpos($mimetype, 'image/');
                            if ($pos !== false) {
                                $info = getimagesize($newfname);
                                $res = @$this->makeThumb($newfname,120,120,$thumbfname);

                                $thumbx = 0;
                                $thumby = 0;

                                $thumbinfo = getimagesize($thumbfname);

                                if (!is_array($thumbinfo)) {
                                    $thumbinfo = array('width' => 0, 'height' => 0);
                                }

                                $media['attachment'] = array('name' => $name,
                                        'x' => $info[0],
                                        'y' => $info[1],
                                        'thumbname' => $thumbname,
                                        'thumbx' => $thumbinfo[0],
                                        'thumby' => $thumbinfo[1]
                                );
                            }

                            $pos = strpos($mimetype, 'video/');

                            if ($pos !== false) {
                                $media['attachment'] = array(
                                        'name' => $name
                                );
                            }

                            $pos = strpos($mimetype, 'audio/');

                            if ($pos !== false) {
                                $media['attachment'] = array(
                                        'name' => $name
                                );
                            }

                            $pos = strpos($mimetype, 'application/');

                            if ($pos !== false) {
                                $media['attachment'] = array(
                                        'name' => $name
                                );
                            }
                        }
                    }
                    
                    $mediasnew[] = $media;
                }

                $this->setMediaArray($mediasnew);

                $query = " update  " . DB__NEW_DATABASE . ".inboundSms set ";
                $query .= " media = '" . $this->db->real_escape_string(serialize($this->getMediaArray())) . "' ";
                $query .= " where inboundSmsId = " . intval($id);

                $this->db->query($query);
            }
        }
        
        ////////////

        $this->checkBody($id);

    } // END public function processInbound
    
}

?>

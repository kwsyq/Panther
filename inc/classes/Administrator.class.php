<?php
/*  /inc/classes/Administrator.class.php

    >>>00004 As of 2019-06, entirely independent of the "permissions", access to the
    administrative side of the system is controlled by Apache logins, using
    /var/www/.htpasswords. This class is an effort to 
    at least somewhat rationalize access to that (previously always done from
    the *nix shell by a sysadmin). This currently relies on there being only a single
    customer on the system, since there is no way to split up admin access per customer.
    To change that, we would have to have an explicit list somewhere -- probably in a DB 
    table -- of what admins have access to what customer.
    
    Public functions:
    * public static function isAdmin($username, $customer)
    * public static function getAllAdmins($customer)
    * public static function addAdmin($username, $password, $customer)
    * public static function addAdminUsingEncryptedPassword($lineForHTPasswords, $customer)
    * public static function removeAdmin($username, $customer)
*/

require_once __DIR__.'/../config.php';

class Administrator {
    // INPUT $username
    // INPUT $customer - Customer object
    // RETURN true if $username is an administrator; false otherwise, including
    //  error cases (no real way to distinguish that on a Boolean return)
    public static function isAdmin($username, $customer) {
        $allAdmins = Administrator::getAllAdmins($customer);
        if (!$allAdmins) {
            // If there was an error, it should already have been logged
            return false;
        }
        foreach ($allAdmins as $row) {
            if ($row['username'] == $username) {
                return true;
            }
        }
        return false;
    }
    
    // INPUT $customer - Customer object
    // RETURN an array of associative arrays of the following form, one for each current admin:
    //   * 'username'
    //   * 'user': user object, or false if none.
    // Returns empty array if there are no admins, FALSE on error.
    public static function getAllAdmins($customer) {
        global $logger;
        $ret = Array();
        $htpasswords = file_get_contents('/var/www/.htpasswords');
        if ($htpasswords === false) {
            $logger->error2('1578683509', 'Cannot read file /var/www/.htpasswords');
            return false;
        }
        $htpasswordsArray = explode("\n", $htpasswords); 
        if ($htpasswordsArray === false) {
            $logger->error2('1578683518', 'Cannot explode file content of file /var/www/.htpasswords');
            return false;
        }
        
        $line_number = 1;            
        foreach($htpasswordsArray as $htpasswordsLine) {
            $htpasswordsLine = trim($htpasswordsLine);
            $parts = explode(':', $htpasswordsLine);
            if (count($parts) == 2) {
                $username = $parts[0];
                $user = User::getByUsername($username, $customer); // Can return null if no such user, that's OK
                $ret[] = Array('username' => $username, 'user' => $user); 
            } else {
                // Avoid logging the whole line, which might give info in a log that would help someone
                // give Admin-level access to an inapppropriate acount, but enough to identify the line.
                $logger->error2('1578683527', 'Bad line in file /var/www/.htpasswords line ' . $line_number . ', begins ' . 
                    substr($htpasswordsLine, 0, 12) . ' Expect exactly one semicolon in line, got ' . count($parts)-1 );
            }
            ++$line_number;
        }
        return $ret;
    } // END public static function getAllAdmins
    
    // Add administrator with known password.
    // ALSO can be used to set a new password for an existing administrator.
    // INPUT $username
    // INPUT $password - unencrypted password provided by current administrator for another administrator.
    // INPUT $customer - Customer object (>>>00004 not yet used at all here except for logging)
    // NOTE that this approach means the current administrator knows the other administrator's password
    // RETURN true on success, false otherwise
    public static function addAdmin($username, $password, $customer) {
        global $logger, $user;
        if (!$username) {
            $logger->error2('1578683540', "Missing username"); 
            return false;
        }
        if (!$password || strlen($password) < 8) {
            $logger->error2('1578683552', "Proposed new password is less than 8 characters");
            return false;
        }
        
        // "htpasswd -b -B /var/www/.htpasswords $username $password"
        $test = exec("sudo ". SUDO_SCRIPTS_FOR_ADMIN ."/add_admin.sh '$username' '$password'", $output, $return_var);
        
        // >>>00014: on success, $test appears to be coming back empty instead of 0, so we are ignoring it and just presuming success
        
        $logger->info2('1578683560', $user->getFormattedName(true) . " added or modified administrator $username [" . $customer->getCustomerName() . "]" );
        return true;
    } // END public static function addAdmin
    
    // INPUT $lineForHTPasswords - should be $data['forPasswordFile'] as returned from ajax/encryptpassword.php
    //   This includes both the username and the new encrypted password.
    // Minimally validated here, relies on caller to know what they are doing.
    // INPUT $customer - Customer object (>>>00004 not yet used at all here except for logging)
    // DOES NOT validate whether username has an associated Person/User, just whether it is present
    // DOES NOT validate encrypted password string other than its presence
    // NOTE that this removes any prior password for that person, and that this is not transactional: it could remove an old
    //  password and fail to add a new one.
    // RETURN true on success, false otherwise
    public static function addAdminUsingEncryptedPassword($lineForHTPasswords, $customer) {
        global $logger, $user;
        $parts = explode(':', $lineForHTPasswords);
        if (count($parts) != 2) {
            // Avoid logging the whole $lineForHTPasswords, which might give info in a log that would help someone
            // give Admin-level access to an inapppropriate acount, but enough to identify the line.
            $logger->error2('1578683572', 'Trying to add bad line to passwords file, begins ' . 
                substr($htpasswordsLine, 0, 12) . ' Expect exactly one semicolon in line, got ' . count($parts)-1);
            return false;
        }
        $username = $parts[0];
        if (!$username) {
            $logger->error2('1578683577', "Missing username");
            return false;
        }
        
        // If there was an entry for this username, we need to remove that before adding a new one. 
        if ( ! Administrator::removeAdmin($username, $customer) ) {
            return false; // any error should already be logged
        }
        
        $passwordFile = 
        $htpasswords = trim(file_get_contents('$passwordFile'));
        if ($htpasswords === false) {
            $logger->error2('1578683585', "Cannot read file $passwordFile to add $username *after* successfully removing any old password for $username");
            return false;
        }

        $tmpfname = tempnam("/tmp", "passwords");

        $handle = fopen($tmpfname, "w");
        if (!$handle) {
            $logger->error2('1578683590', "Cannot fopen temp file $tmpfname for write to add $username *after* successfully removing any old password for $username");
            return false;
        }
        $ret = fwrite($handle, $htpasswords . "\n". $lineForHTPasswords . "\n");
        if ($ret === false) {
            $logger->error2('1578683601', "Cannot write to temp file $tmpfname to add $username *after* successfully removing any old password for $username");
            return false;
        }
        $ret = fclose($handle);
        if ($ret === false) {
            $logger->error2('1578683612', "Cannot close to temp file $tmpfname after successfully adding password for $username");
            return false;
        }

        // COPY INTO /var/www/.htpasswords
        $test = exec("sudo ". SUDO_SCRIPTS_FOR_ADMIN ."/copy_passwords.sh $tmpfname");
        // >>>00014: on success, $test appears to be coming back empty instead of 0, so we are ignoring it and just presuming success

        unlink($tmpfname);
        
        $logger->info2('1578683517', $user->getFormattedName(true) . " added or modified administrator $username [" . $customer->getCustomerName() . "]" );
        return true;            
    } // END public static function addAdminUsingEncryptedPassword   
    
    // INPUT $username
    // INPUT $customer - Customer object (>>>00004 not yet used at all here except for logging)
    // If $username is an administrator, remove it from the list.
    // DOES NOT validate whether $username has an associated Person/User, and empty $username string is treated as success.
    // Returns true on success, in theory false on failure but so far we have no way to detect a failure, so right now it will always return true.
    public static function removeAdmin($username, $customer) {
      global $logger, $user;
      if ($username) {
            // "htpasswd -D /var/www/.htpasswords $username"
            $test = exec("sudo ". SUDO_SCRIPTS_FOR_ADMIN ."/remove_admin.sh '$username'");
            // >>>00014: on success, $test appears to be coming back empty instead of 0, so we are ignoring it and just presuming success
            
            $logger->info2('1578683533', $user->getFormattedName(true) . " deleted administrator $username [" . $customer->getCustomerName() . "]" );
            return true;
        }
        return true;
    } // END public static function removeAdmin
    
} // END class Administrator
?>
<?php
/*  inc/functions.php
    EXECUTIVE SUMMARY: a very mixed bag of functions, all of them public.
    >>>00001 A lot of these would make more sense as static methods of existing classes.

    Functions:
    * nextDate($userDay)
    * ajaxorigin()
    * getSmsNotifyEmails()
    * getWorkOrderNotifyEmails()
    * validJSON($str)
    * getAllVimeoVideos()
    * generateCodeJobAndWorkOrder($len = 0)
    * generateCode($len = 0)
    * checkPerm($userPermissions, $permissionName, $permissionLevel)
    * isPrivateSigned($params, $hashkey)
    * signRequest($params,$hashkey)
    * do404()
    * checkPassword($pwd, &$errors)
    * getFullPathnameOfTaskIcon($icon_name, $error_number = '')
    * getIconPath($customer)
    * overlay($workOrder, $obj, $formWorkOrderTasks = array())
    * putTaskArrayInCanonicalOrder($wotArray)
    * getTaskTypes()
    * getContractLanguageFiles(&$errCode=false)
    * getTerms(&$errCode=false)
    * getJobLocationTypes() // not in this file.
    * allStates()
    * getServiceLoads(&$errCode = false)
    * getServiceLoadsVarData(&$errCode = false)
    * getServiceLoadsVarIds($onlyServ = 0, &$errCode = false)
    * getTeamPositions()
    * getRoles()
    * formatGenesisAndAge($genesisDate, $getDeliveryDate, $isDone)
    * existPersonId($personId)
    * existContract($contractId)
    * existWorkOrderTask($workOrderTaskId)
    * existWorkOrder($workOrderId)
    * existTaskId($taskId)
    * existWorkOrderId($workOrderId)
    * existElementId($elementId)
    * getEmployees($customer)
    * phoneTypes()
    * selectMonths()
    * validatePermissionString($permissionString)
    * getWorkOrderDescriptionTypes($activeOnly = 0, &$errCode = false)
    * canDelete($databaseTable, $primaryKeyName, $primaryKeyValue)
    * truncate_for_db ($var, $var_name, $len, $errorId)
    * entityExists($entityTable, $entityName, $entityVal, $whereCondition = '')
    * includeOurPost()
*/

// INPUT $userDay: day of the month, 0-31
// RETURN a UNIX timestamp (integer) representing the next time this day-of-month will occur,
//  corresponding to midnight at the start of the day in question.

function base64url_decode($data) {
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

function nextDate($userDay) {
    $today = date('d'); // [Martin comment] today // JM: day of month, 00-31
    $target = date('Y-m-'.$userDay);  // [Martin comment] target day
    if($today <= $userDay) {
        // input $userDay would be later in the month; we return this as a date of this month
        // >>>00016 >>>00002: input $userDay not validated at all in this case.
        $return = strtotime($target);
    } else {
        $thisMonth = date('m') + 1; // push into the next month
        $thisYear = date('Y');
        if($thisMonth>12){
            $thisMonth=1;
            $thisYear++;
        }
        if($userDay >= 28 && $thisMonth == 2) {
            // Make sure we don't go off the end of February
            // >>>00026 but doesn't account for leap years
            $userDay = 28;
        }
        // >>>00016 >>>00002: No check for the 31st of a 30-day month or for a $userDay entirely out of range.
        while (!checkdate($thisMonth, $userDay, $thisYear)) {
            // wrap the year
            $thisMonth++;
            if($thisMonth == 13) {
                $thisMonth = 1;
                $thisYear++;
            }
        }
        $return = strtotime($thisYear.'-'.$thisMonth.'-'.$userDay);
    }
    return $return;
}

// This enforces an appropriate origin for AJAX calls. Dies if domain is inappropriate.
// Outputs some headers (and sets an IE cookie) before returning.
// if $_SERVER['REQUEST_METHOD'] == 'OPTIONS', dies after putting out headers. This is apparently
//  for 'preflight': See https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods/OPTIONS
//
// Early 2019: Joe pushed what were previously SSS-specific considerations here into inc/config.php.
// We now presume that inc/config.php will properly set $valid_ajax_origin_domains.
function ajaxorigin() {
    global $valid_ajax_origin_domains; // added 2019-02-04 JM

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $ok = $valid_ajax_origin_domains;
        $use_wildcard = false;

        if ($use_wildcard) {
            // Looks like this can never happen
            $origin = '*';
        } else {
            if (!in_array($_SERVER['HTTP_ORIGIN'], $ok)) {
                // Not an authorized origin domain. Die.
                die();
            }
            $origin = $_SERVER['HTTP_ORIGIN'];
        }

        header("Access-Control-Allow-Origin: " . $origin);

        if ($origin != '*') {
            // Looks like this can never happen
            header("Vary: origin");
        }

        header("Access-Control-Allow-Methods: POST, GET");
        header("Access-Control-Allow-Headers: Origin");
        header('P3P: CP="CAO PSA OUR"'); // [Martin comment] make ie support cookies

        // [Martin comment] preflight
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            die();
        }
    }
}

// Return recipients who should get an email when there is an inbound SMS.
// RETURN an array of associative arrays, each with elements:
//  * 'address': email address
//  * 'name': name associated with that email address
function getSmsNotifyEmails() {
    $smsNotify = array(
            array('address' => CUSTOMER_INBOX, 'name' => 'INBOX')
    );
    return $smsNotify;
}

// Return recipients who should get an email whenever a workOrder is closed.
// RETURN an array of associative arrays, each with elements:
//  * 'address': email address
//  * 'name': name associated with that email address
function getWorkOrderNotifyEmails() {
    $workOrderNotify = array(
            array('address' => CUSTOMER_INBOX, 'name' => 'INBOX')
    );
    return $workOrderNotify;
}

// INPUT $str: a string we want checked for whether it is valid JSON.
// RETURN Boolean: whether $str is valid JSON
function validJSON($str) {
    json_decode($str);
    return (json_last_error() == JSON_ERROR_NONE);
}


//  Getting some sort of date from https://api.vimeo.com about some videos,
//  based on our authorization, stored in inc/config.php as VIMEO_AUTHORIZATION.
//  Repeatedly gets up to 100 at a time until we have all of them.
//  >>>00015 JM 2019-03-11: I haven't looked closely at this, I don't think
//  it's really being used 2019-03.
function getAllVimeoVideos() {
    $return = array();
    $videos = array();

    $headers = array(VIMEO_AUTHORIZATION);

    $next = '/me/videos?per_page=100';

    do {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.vimeo.com' . $next);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        $next = '';

        if ((strlen($response) > 0) && validJSON($response)) {
            $json = json_decode($response,true);

            if (isset($json['paging'])) {
                $paging = $json['paging'];
                if (is_array($paging)){
                    if (isset($paging['next'])) {
                        $next = $paging['next'];
                    }
                }
            }

            if (isset($json['data'])) {
                $data = $json['data'];
                if (is_array($data)) {
                    foreach ($data as $video) {
                        $videos[] = $video;
                    }
                }
            }
        }
    } while (strlen($next));

    return $videos;
}

// Generate the pseudo-random code we use as an alternate key for jobs & workOrders.
// RETURN such a key; still needs to be checked for collisions with other such codes.
// NOTE that we never use '1', '0', 'I', 'O' or 'S'.
// NOTE that the pattern here is slightly stricter than in function generateCode, because
// we alternate between letter (or comma) and digit (or comma).
function generateCodeJobAndWorkOrder($len = 0) {
    if (!intval($len)) {
        $len = 8;
    }
    $str = '';

    for ($i = 0; $i < $len; ++$i) {
        if ($i % 2) {
            $chars = explode(",","A,C,D,E,F,G,H,J,K,L,M,N,P,Q,R,T,U,V,W,X,Y,Z");
            shuffle($chars);
            $str .= $chars[0];
        } else {
            $chars = explode(",","2,3,4,5,6,7,8,9");
            shuffle($chars);
            $str .= $chars[0];
        }
    }

    return $str;
}

// Generate the pseudorandom salt for a hash.
// RETURN such a salt; still needs to be checked for collisions with other such codes.
// NOTE that we never use '1', '0', 'I', 'O' or 'S'.
// NOTE exact same code in Customer::genCode (which as of 2019-03-11 is not static, but should be)
//   >>>00006 we may want to eliminate common code by getting rid of one of these.
function generateCode($len = 0) {
    if (!intval($len)) {
        $len = 5;
    }

    $str = '';

    for ($i = 0; $i < $len; ++$i) {
        $chars = explode(",","A,C,D,E,F,G,H,J,K,L,M,N,P,Q,R,T,U,V,W,X,Y,Z,2,3,4,5,6,7,8,9");
        shuffle($chars);
        $str .= $chars[0];
    }

    return $str;
}

// See whether a particular permission is present for a particular user
// INPUT $userPermissions: permissions for a particular user
// INPUT $permissionName: name of permission in question, permissionIdName from
//  DB table Permission. E.g. PERM_INVOICE.
// INPUT $permissionLevel: minimu required level of permission in question,
//  from inc/config.php. E.g. PERMLEVEL_ADMIN.
// RETURN Boolean
function checkPerm($userPermissions, $permissionName, $permissionLevel) {
    if (isset($userPermissions[$permissionName])) {
        $lev = intval($userPermissions[$permissionName]);
        if (($lev) && ($lev <= $permissionLevel)) {
            return true;
        }
    }

    return false;
}

// Take a one-off URL that we use to give someone private access to a page,
//  and make sure that its SHA1 hash is correct, given the expected hashkey.
// Dual of signRequest.
// JM: as of 2019-03-11, I don't think we really are doing this.
// INPUT $params: associative array corresponding to name-value pairs from query string of URL
// INPUT $hashkey: As of 2019-03, one of PRIVATE_HASH_KEY, SMSMEDIA_HASH_KEY; possibly others to be introduced
// RETURN true if $params appears be valid for this hash key
function isPrivateSigned($params, $hashkey) {
    $ok = false;
    if ($hashkey) {
        $expires = isset($params['e']) ? $params['e'] : 0;
        if ((time() - $expires) < 0) {
            // URL is not expired
            if (isset($params['hash'])) {
                // URL includes a hash in the query portion. Save off the hash...
                $hash = isset($params['hash']) ? $params['hash'] : '';
                // ...Remove the hash from $params, since it is not part of what gets hashed...
                unset ($params['hash']);
                // ...Put $params in canonical order...
                ksort($params);
                // ...And rebuild the query string that would get hashed.
                $str = '';
                foreach ($params as $key => $value) {
                    if (strlen($str)){
                        $str .= '&';
                    }
                    $str .= rawurlencode($key) . '=' . rawurlencode($value);
                }

                if (strlen($str)) {
                    // if SHA1 of that string with the hashkey appended produces this hash, then
                    // the initial $params had a valid has for this hashkey.
                    if (sha1($str . $hashkey) == $hash) {
                        $ok = true;
                    }
                }
            }
        }
    }

    return $ok;
} // END function isPrivateSigned

// Create a one-off URL that we use to give someone private access to a page.
// Dual of isPrivateSigned.
// JM: as of 2019-03-11, I don't think we really are doing this.
// INPUT $params: associative array corresponding to name-value pairs intended for query string of URL
// INPUT $hashkey: As of 2019-03, one of PRIVATE_HASH_KEY, SMSMEDIA_HASH_KEY; possibly others to be introduced
// RETURN signed query string for URL.
function signRequest($params, $hashkey) {
    ksort($params); // canonical sort

    // Build query string *without* hash...
    $str = '';
    foreach ($params as $key => $value) {
        if (strlen($str)) {
            $str .= '&';
        }
        $str .= rawurlencode($key) . '=' . rawurlencode($value);
    }

    // ... hash it ...
    $hash = sha1($str . $hashkey);

    // ... and append the hash.
    if (strlen($str)) {
        $str .= '&hash=' . $hash;
    } else {
        $str = 'hash=' . $hash;
    }

    return $str;
}

// Write a simple 404 page; NOTE that content is distinct from 404.php
function do404() {
    header('HTTP/1.0 404 Not Found');
    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n";
    echo "<html><head>\n";
    echo "<title>404 Not Found</title>\n";
    echo "</head><body>\n";
    echo "<h1>Not Found</h1>\n";

    $uri = $_SERVER['REQUEST_URI'];
    $parts = explode("?", $uri);
    echo "<p>The requested URL " . $parts[0] . " was not found on this server.</p>\n";
    echo "</body></html>\n";
}

// Validates password against a set of rules (length, types of characters).
// Similar to, but not identical to, Validate::password.
// INPUT: $pwd: password
// INPUT/OUTPUT: $errors. Array of strings. If we detect errors, we append appropriate
//  error strings to the array.
// RETURN: true if we haven't found any (additional) errors. JM remarks: prior to 2019-12-02, always
//  returned true, which was almost certainly an error, but fortunately no callers relied on that return.
function checkPassword($pwd, &$errors) {
    $error_count_init = count($errors); // added 2019-12-02 JM
    $errors_init = $errors;

    if (strlen($pwd) < 8) {
        $errors[] = "Password too short!  Must be at least 8 characters";
    }

    if (!preg_match("#[0-9]+#", $pwd)) {
        $errors[] = "Password must include at least one number!";
    }

    if (!preg_match("#[a-z]+#", $pwd)) {
        $errors[] = "Password must include at least one lowercase letter!";
    }

    if (!preg_match("#[A-Z]+#", $pwd)) {
        $errors[] = "Password must include at least one uppercase letter!";
    }

    if (!preg_match("/[^a-zA-Z\d]/", $pwd)) {
        $errors[] = "Password must include at least one special character!";
    }
    return (count($errors) == $error_count_init);
}

// Path to UI icons for a particular customer, under DOMAIN ROOT.
// INPUT $customer accepts a Customer object, a string, or can be false/null/empty-string to fall back to constant CUSTOMER (e.g. 'ssseng' for SSS)
// For example, if this returns $FOO, a typical icon path might be $FOO.'/icon_time_b_15.png'
function getIconPath($customer) {
    $customerName  = CUSTOMER;
    if ($customer instanceof Customer) {
        $customerName = $customer->getShortName();
    } else if (is_string($customer) && !!trim($customer)) {
        $customerName = $customer;
    }
    return '/cust/' . $customerName. '/img/icons';
}

// Returns the full pathname of a task icon for the current domain.
// This was created to address http://bt.dev2.ssseng.com/view.php?id=183 ("Cannot serve directory /var/www/html/cust/ssseng/img/icons_task/").
// We were getting an error that we presume is because we tried to access a path either with a null-string
//  icon name or possibly with one where blanks were not handled appropriately.
// This function provides a single systemwide way to form such a path, and does appropriate error reporting.
// if the taskicon name is a null string.
// BIG CHANGE 2020-10-01 for v2020-4 JM: the actual icons_task directory will now be
//  /var/www/ssseng_documents/icons_task rather than /var/www/html/cust/ssseng/img/icons_task.
//  The latter will be mapped in Apache for easy access from HTML, but it will not have an actual
//  directory underlying it. The return of this function uses the Apache/web path, cust/ssseng/img/icons_task.
// NOTE that this relates to a different directory than function getIconPath above, and it always uses
//  global constant CUSTOMER rather than looking at $customer.
// INPUT $icon_name: task icon name, such as "Lat 4 story.jpg" or "FoundationDesign1.JPG"
// INPUT $error_number: optional error number for logging, lets us see where in the code this function was called
//   rather than just the fact that it was called.
function getFullPathnameOfTaskIcon($icon_name, $error_number = '') {
    global $logger;
    $error_number = $error_number ? $error_number : '1595356470';
    if (!$icon_name) {
        $logger->error2($error_number, 'Empty icon name');
        $icon_name = 'none.jpg';
    }
    return '/cust/'.CUSTOMER.'/img/icons_task/' . rawurlencode($icon_name);
}


/*
    Martin described this in May 2018 as "A merge to look at [JM: that's a terribly
    imprecise verb] tasks in a workorder vs. a contract."

    According to Martin, this is intended for the current (not yet committed) contract;
    only inadvertent that this is was used in some contexts for committed contracts,
    and as of 2018-06 his plan was that the latter should change. Take that for what it's worth.

    (Business logic aside: a contract or invoice constitutes a "snapshot" and may not
    line up with ongoing workorders. As of 2020-08, we are rethinking the exact relationships
    among contract, invoice, workorder, etc. but one thing is clear: a committed contract should
    reference only the state of a workorder at the time the contract was committed. There should
    be no logic that "rethinks" how to display the contract. The relevant "state of the world"
    is is stored in a sort of blob, the 'data' column of the contract table.)

    NOTE that, at least in v2020-3, the return of this function is exactly what is serialized & encrypted, then
    goes in the 'data' column of the contract & invoice tables. In v2020-4, we plan to modify the return of this
    function (different handling of workOrderTasks that relate to multiple elements), and we will use a distinct
    column for that (tentatively 'data2') rather than change the meaning of an existing column. Eventually, data2
    will presumably supersede the old data column.

    INPUT $workOrder: WorkOrder object
    INPUT $obj: Contract or Invoice object
    INPUT $formWorkOrderTasks: optional array, empty by default.
      Empty for a fresh page load; has content for an update or commit.
      This allows adding more workOrderTasks to a workOrder and to the associated contract,
      or changing things like estQuantity for a task.
        * JM wonders 2020-08-05: is estQuantity for a task really a good example here? That
          is in DB table 'task', not DB table workOrderTask. But maybe that is exactly
          what Martin had in mind.
      >>>00001 JM: how would you remove a task? or does that not arise here?
      $formWorkOrderTasks is a quasi-associative array of associative arrays.
       First-level index is workOrderTaskId (with all the potential problems entailed in PHP
        by using a sparse numerically indexed array like an associative array);
      second-level is a column name such as 'billingDescription', 'estQuantity', 'estCost'.
    If $formWorkOrderTasks is empty, then we use the Contract or Invoice object's getData
      method to get its 'data' (a blob representing a hierarchy of workOrder tasks, set
      in stone at the time the Contract or Invoice is committed) and to fill
      in the associative arrays $commitIds and $formWorkOrderTasks (the latter being
      the parameter that was passed in empty).

    RETURN array, enumerating tasks on a per-element basis. Index is elementId
     (with all the potential problems entailed in PHP by using a sparse numerically indexed
     array like an associative array). Also the following special values:
        * 0 or '' meaning "general, no element".
        * (This should no longer occur in v2020-4 or later) PHP_INT_MAX meaning "multiple elements"
        * (Introduced in v2020-4) a comma-separated list of elementIds, no spaces, meaning "precisely these multiple elements"
    Each member of that array is an associative array with the following indexes:
      * 'element'
        * If the elementgroup is a single element, then this is an Element object
        * (This should no longer occur in v2020-4 or later) If the elementgroup is the "multiple elements" group (index PHP_INT_MAX)
            then this has the Boolean value false.
        * If this is a comma-separated list of elementIds, that same comma-separated list of elementIds
        * If the elementgroup is 0 ("General"), >>>00001 uncertain what happens, or whether this ever even occurs.
      * 'tasks': this is an array with small numeric indexes, but appparently (based on a close reading of the code JM 2020-08-05)
        in some edge cases (which may never arise), there could be gaps in the series. The sequence should correspond to pre-order
        traversal of the relevant subset of the (abstract) task hierarchy. Each member is an associative array with the
        following array elements (>>>00001 JM 2020-08-05: it is possible that there are cases not well covered here):
        * For all workOrderTasks:
            * 'type' : 'real' or 'fake'
            * 'level': depth in the task hierarchy
            * 'fromContract', Boolean, only present for invoices
            * 'arrowDirection'- See inc/config.php for thorough documentation.
              ARROWDIRECTION_DOWN means on the invoice/contract all subtasks of this task are bundled into this task, rather than having their own line items
              ARROWDIRECTION_RIGHT is the default case.
        * For 'fake' workOrderTasks:
            * 'task': just a string consisting of the letter 'a' followed by the taskId.
        * For 'real' tasks:
            * 'workOrderId': this and the following are IDs from the DB
            * 'workOrderTaskId'
            * 'taskStatusId'
            * 'taskId'
            * 'task', another associative array; all of this data seems to relate to Task, not WorkOrderTask
                * 'taskId': (redundantly)
                * 'icon': name of icon in cust/ssseng/img/icons_task ('ssseng' there would be different for a different customer)
                          e.g. "SoldierPile1.jpg", "Lat 1 story.jpg".
                * 'description': e.g. "RFI"
                * 'billingDescription: e.g. "Respond to request for information"
                * 'estQuantity'
                * 'estCost'
                * 'taskTypeId'
                * 'sortOrder', which is only meaningful within a particular parent task, or at level 0.

    Further notes:  Martin hoped eventually to get rid of faked-up tasks, but Joe
    believes as of 2020-08 they are still sometimes being newly generated,
    so we will be living with for a long time, especially
    if we still want to be able to look at old contracts.
    Going forward, we should make it so that if a task is in a workorder, then so is its parent.

    NOTE that this is one of the rare times we use a nested helper function.
*/
function overlay($workOrder, $obj, $formWorkOrderTasks = array()) {
    global $logger; // access to $logger ADDED 2020-07-24 JM fixing bug http://bt.dev2.ssseng.com/view.php?id=189

    /* Helper function $elementGroupsToArrayTree for function overlay.
       We deliberately limit its scope by assigining it here to a local variable.
       INPUT $elementgroups should be the return of WorkOrder::getWorkOrderTasksTree(). That is an array indexed
         by elementId. There are also some special cases:
         * (This should no longer occur in v2020-4 or later) PHP_INT_MAX meaning "multiple elements"
         * (Introduced in v2020-4) a comma-separated list of elementIds, no spaces, meaning "precisely these multiple elements"
         * (>>>00001 unconfirmed as of 2020-07-24) elementId = 0 or '' meaning "general, no element".
         Each $elementgroups[$elementId] should be an associative array with the following members:
             * $elementgroups[$elementId]['element']: an Element object
             * $elementgroups[$elementId]['elementName']: just what it says; can be a comma-separated list of element names
             * $elementgroups[$elementId]['elementId']: just what it says, redundant to the index; can be a comma-separated list of elementIds
             * $elementgroups['tasks']: (not used here)
             * $elementgroups['maxdepth']: (not used here)
             * $elementgroups['render']: (not used here)
             * $elementgroups['gold']: A flat array indicating the order to display workOrderTasks for this element and
               providing information needed to display them correctly:
               This is a flat numerically-indexed array, even though it implicitly represents a hierarchy.
               It is in pre-order traversal order, based on the task tree.
               For each reconstructed internal node that doesn't have an explicit workOrderTask, we will have:
                   * 'type' => 'fake'
                   * 'level' => $level
                   * 'data' => a key from input $array, e.g. 'a210' corresponding to taskId 210
               For each leaf node and any explicit internal node, we will have:
                   * 'type' => 'real'
                   * 'level' => $level
                   * 'data' => workOrderTask object if it is a leaf node,
                      or (I believe) a key from input $array, e.g. 'a210' corresponding to taskId 210 if it is an internal node.
               So, if in the task hierarchy we had parent-child relationships (based on taskId)
                    35 => 210 => 455, we would get:
                   $gold[0] array('type' => 'real', 'level' => 0, 'data' => 'a35')
                   $gold[1] array('type' => 'real', 'level' => 1, 'data' => $wot1), where $wot1 is a workOrderTask whose taskId == 210
                   $gold[2] array('type' => 'real', 'level' => 2, 'data' => $wot2 is a workOrderTask whose taskId == 455

               We can, of course, have multiple workOrderTasks at any level of the hierarchy; each workOrderTasks with a level $level > 0
               is to be understood in the context of the closest workOrderTasks before it with a level of $level-1.
               So, for example, we could easily have as well:
               $gold[3] = array('type' => 'real', 'level' => 2, 'data' => $wot3), where $wot3 is a DIFFERENT workOrderTask whose taskId == 455
               $gold[4] = array('type' => 'real', 'level' => 2, 'data' => $wot4), where $wot4 is a workOrderTask with a DIFFERENT taskId that is
                       also a child of taskId 210
               $gold[5] = array('type' => 'real', 'level' => 1, 'data' => $wot5), where $wot5 is a workOrderTask with a DIFFERENT taskId that is
                       also a child of taskId 35
               etc.

       INPUT $formWorkOrderTasks, if present, should represent the content of an HTML form controlling the choices related to a contract or invoice
       that are controllable from contract.php and invoice.php, respectively. As of 2020-09-03, in contract.php this is the form with id="contractform"
       (which, besides its obvious submission, is also implicitly used as part of a "commit"); in invoice.php, it is the form with id="invoiceform".
       In both cases, the data has been considerably restructured before being passed to this function, and we get data only about the values with '::'
       in their respective names. A typical example is that the form might contain something like:
         <input type="text" name="estQuantity::98765[]" value="12">
       where 98765 is a workOrderTaskId. We would get that as
         $formWorkOrderTasks['98765']['estQuantity'] => 12;

       RETURNs an array indexed by elementId (with the same special cases noted above); each array element is an associative array with
       the following content:
            * 'element' - JM 2020-09-03: this is actually rather redundant.
                  * If an element object was input for this elementGroup, then it's a small associative array containing
                    'elementName' and 'elementId', duplicating the information in the next two array elements.
                  * If no element object was input for this elementGroup, then false.
            * 'elementName' - $elementgroups[$elementId]['elementName'], exactly as input
            * 'elementId' - $elementgroups[$elementId]['elementId'], exactly as input
            * 'tasks' - array of associative arrays, with the following array elements. These
              correspond to the abstract tasks underlying the workOrderTasks for $elementId.
              They are about the Task, not the workOrderTask:
                * 'type' - 'real' or 'fake', same as input $elementgroups[$elementId]['gold']['type'].
                    * 'real' here means that there is an explicit workOrderTask associated with this task and workOrder for this element.
                    * 'fake' here means the opposite.
                    * NOTE that "leaf nodes" are necessarily 'real', but other nodes (tasks) could be 'fake':
                      a workOrderTask references a descendant of this task, so we need to bring this task into the hierarchy to make sense of it.
                * 'level' - same as input $elementgroups[$elementId]['gold']['type]['level']
                * 'data' - Task object (not WorkOrderTask object) as array. This is an associative
                  array with the obvious semantics for the following indexes:
                  * 'taskId'
                  * 'icon'
                  * 'description'
                  * 'billingDescription'
                  * 'estQuantity' - This name is appropriate in a contract, but somewhat inappropriate in an invoice. No big deal, because
                                    it is an internal name, but really: in an invoice, there is presumably nothing "estimated" about it.
                                    >>>00014 Does this relate *at all* to workOrderTask.quantity in the DB?
                  * 'estCost'     - Similarly. >>>00014 Does this relate *at all* to workOrderTask.cost in the DB?
                  * 'taskTypeId'
                  * 'sortOrder'
                NOTE that 'data' here is all about the abstract Task rather than the WorkOrderTask. For example,
                it gives us the the estimated quantity & cost from a generic task, rather than the specific
                quantity and cost for the relevant workOrderTask.
    */
    $elementGroupsToArrayTree = function ($elementgroups) {
        global $logger;
        $save = array();
        // In the following loop, $ekey is typically an elementId, see documentation above function.
        foreach ($elementgroups as $ekey => $elementgroup) {
            if (isset($elementgroup['element']) && $elementgroup['element'] !== false) {
                /* BEGIN REPLACED 2020-09-09 JM
                $save[$ekey]['element'] = $elementgroup['element']->toArray();
                // END REPLACED 2020-09-09 JM
                */
                // BEGIN REPLACEMENT 2020-09-09 JM
                if ($elementgroup['element'] && $elementgroup['element'] !== false) {
                    // new style multi-element introduced in v2020-4.
                    $save[$ekey]['element'] = Array('elementId'=>$elementgroup['elementId'], 'elementName'=>$elementgroup['elementName']);
                } else {
                    $save[$ekey]['element'] = $elementgroup['element']->toArray();
                }
                // END REPLACEMENT 2020-09-09 JM
            } else {
                $save[$ekey]['element'] = false;
            }

            $save[$ekey]['elementName'] = $elementgroup['elementName'];
            $save[$ekey]['elementId'] = $elementgroup['elementId'];

            $tasks = array();
            if (isset($elementgroup['gold'])) { // Presumably should never fail
                foreach ($elementgroup['gold'] as $element) {
                    if (isset($element['type'])) {
                        $type = $element['type'];
                        if ($type == 'real') {
                            $save[$ekey]['tasks'][] = array('type' => $element['type'],
                                            'level' => $element['level'],
                                            'data' => $element['data']->toArray()
                                    );
                        } else {
                            $t = new Task(str_replace("a","",$element['data']));
                            $save[$ekey]['tasks'][] = array('type' => $element['type'],
                                    'level' => $element['level'],
                                    'data' => $t->toArray()
                            );
                        }
                    }
                }
            }
        }
        return $save;
    }; // END function $elementGroupsToArrayTree

    // Resume main body of function overlay

    // BEGIN ADDED 2020-09-03 JM
    if ( ! ($obj instanceOf Contract || $obj instanceOf Invoice ) ) {
        $logger->error2('1599150215', '$overlay called with something other than a Contract or Invoice object in second parameter');
        return Array();
    }
    // END ADDED 2020-09-03 JM

    /* BEGIN REPLACED 2020-09-04 JM
    // [Martin comment] "live" data
    $class = get_class($obj);
    // END REPLACED 2020-09-04 JM
    */
    // BEGIN REPLACEMENT 2020-09-04 JM
    // Arriving here means it is either a contract or an invoice.
    // Prior to v2020-4, we had some rather convoluted code to deal with $class,
    //  but since there are only two possibilities, a Boolan is a lot clearer.
    //  NOTE that below I have not typically kept around a "before" version of the
    //  code I replaced in switching from $class to $isInvoice; suffice it to say it was
    //  unnecessarily complicated, with a lot of common code now eliminated, as well as
    //  impossible cases removed. JM
    $isInvoice = $obj instanceOf Invoice;
    // END REPLACEMENT 2020-09-04 JM

    /* BEGIN REPLACED 2020-07-29 JM
    $elementgroups = $workOrder->getWorkOrderTasksTree($class);
    // END REPLACED 2020-07-29 JM
    // THIS WENT THROUGH A FEW ITERATIONS AND EVENTUALLY WE GOT TO WHERE WE DIDN'T NEED AN INPUT HERE.
    // END REPLACEMENT 2020-07-29 JM
    */
    // BEGIN FURTHER SIMPLIFIED 2020-10-28 JM because above we verify $obj instanceOf Contract || $obj instanceOf Invoice
    $elementgroups = $workOrder->getWorkOrderTasksTree();
    // END FURTHER SIMPLIFIED 2020-10-28 JM

    $workingArray = $elementGroupsToArrayTree($elementgroups);

    // $workingArray at this point is an internal representation of the task structure of the relevant workOrder.
    // NOTE that it contains detailed information about the
    //  abstract Tasks associated with each elementGroup, but not the WorkOrderTasks.
    // See comment at head of function elementGroupsToArrayTree for documentation of its structure.

    // BEGIN MARTIN COMMENT
    // if the passed in array of form data ($formWorkOrderTasks) is empty then populate it from existing data
    // so theres an array to work with
    // if its empty then assume its a fresh page load
    // if that array has data then its an update or a commit
    // END MARTIN COMMENT

    $commitIds = array();

    /*
    If $formWorkOrderTasks was passed in, then it should represent the content of an
        HTML FORM (see remarks above about this as in input), and we get information about the
      user-controlled values for the contract/invoice from $formWorkOrderTasks. However, $formWorkOrderTasks
      is an optional input, and the code here deals with the case where it wasn't passed in.

    If $formWorkOrderTasks is empty, then we use the Contract or Invoice object's getData
      method to get its 'data' and to fill in the associative arrays $commitIds and $formWorkOrderTasks
      (the latter being the parameter that was passed in empty). The 'data' column in the DB is a blob that,
      once decrypted and unserialized, has exactly the same structure as what this function 'overlay' returns.
      This allows us to "set something in stone" at the time the Contract or Invoice is committed.

      $commitIds: For each "real" task (as against faked-up parent tasks not explicitly present)
      already associated with the contract/invoice, $commitIds[workOrderTaskId] is
      an associative array with the value workOrderTaskId (so always $commitIds[id] == id).
      So, basically, this is an implementation of a mathematical 'set'.

      We also fill in $formWorkOrderTasks[workOrderTaskId] with the following elements,
      so in this case $formWorkOrderTasks represents what is already in the contract/invoice:
        - 'billingDescription'
        - 'estQuantity'
        - 'estCost'
        - 'nte' (contracts only, maybe vestigial as of 2018-09 >>>00042 should talk to Ron & Damon)
        - 'arrowDirection'
        - 'fromContract' Boolean, only present for invoices
    */
    if (!count($formWorkOrderTasks)) {
        $data = $obj->getData();
        foreach ($data as $element) {
            if (isset($element['tasks'])) {
                $tasks = $element['tasks'];
                if (is_array($tasks)) {
                    foreach ($tasks as $task) {
                        if (isset($task['workOrderTaskId'])) {
                            /* OLD CODE REMOVED 2020-03-11
                                        $commitIds[$task['workOrderTaskId']] = $task['workOrderTaskId'];
                            */
                            // BEGIN NEW CODE (rewritten for clarity JM 2020-03-11)
                            $wotId = $task['workOrderTaskId'];
                            $commitIds[$wotId] = $wotId;
                            // So all that really matters in $commitIds are the associative indexes;
                                        // the value is always exactly the same as the index.

                            // END NEW CODE (rewritten for clarity JM 2020-03-11)
                        }

                        // BEGIN MARTIN COMMENT
                        // this might be where
                        // do diff shit for contract and invoice
                        // END MARTIN COMMENT

                        $taskType = isset($task['type']) ? $task['type'] : '';

                        if ($taskType == 'real') {
                            // BEGIN ADDED 2020-08-05 JM
                            if (!array_key_exists($task['workOrderTaskId'], $formWorkOrderTasks)) {
                                // initialize array before using it.
                                $formWorkOrderTasks[$task['workOrderTaskId']] = array();
                            }
                            // END ADDED 2020-08-05 JM
                            $formWorkOrderTasks[$task['workOrderTaskId']]['billingDescription'] = $task['task']['billingDescription'];
                            $formWorkOrderTasks[$task['workOrderTaskId']]['estQuantity'] = $task['task']['estQuantity'];
                            $formWorkOrderTasks[$task['workOrderTaskId']]['estCost'] = $task['task']['estCost'];
                            if ($isInvoice) {
                                if (isset($task['task']['arrowDirection'])){
                                    $formWorkOrderTasks[$task['workOrderTaskId']]['arrowDirection'] = $task['task']['arrowDirection'];
                                } else {
                                    $formWorkOrderTasks[$task['workOrderTaskId']]['arrowDirection'] = ARROWDIRECTION_RIGHT;
                                }
                            } else {
                                // Contract
                                // NOTE that the logic above for the $isInvoice case would be perfectly OK here, but I (JM) think
                                //  it's probably worth preserving the difference, since the fact that this works apparently means that
                                //  $task['task']['arrowDirection'] is alway set in this case.
                                $formWorkOrderTasks[$task['workOrderTaskId']]['arrowDirection'] = $task['task']['arrowDirection'];
                            }
                            if ($isInvoice) {
                                // NOTE that in the following we look first at $task['task']['fromContract'], then potentially
                                //  override it from $task['fromContract']; >>>00014 JM 2020-08-05 I have no idea why it is this way,
                                //  leave it alone unless definitively understood.
                                if (isset($task['task']['fromContract'])){
                                    $formWorkOrderTasks[$task['workOrderTaskId']]['fromContract'] = $task['task']['fromContract'];
                                }
                                if (isset($task['fromContract'])){
                                    $formWorkOrderTasks[$task['workOrderTaskId']]['fromContract'] = $task['fromContract'];
                                }
                            } else {
                                // Contract
                                // JM 2020-08-05; 'nte' ("not to exceed") is probably vestigial, but might make a comeback
                                $nte = isset($task['task']['nte']) ? $task['task']['nte'] : '';
                                $formWorkOrderTasks[$task['workOrderTaskId']]['nte'] = $nte;
                            }
                            // BEGIN ADDED 2020-07-24 JM fixing bug http://bt.dev2.ssseng.com/view.php?id=189
                            if ( ! is_numeric($formWorkOrderTasks[$task['workOrderTaskId']]['estQuantity']) ) {
                            $class = ($obj instanceOf Contract) ? 'contract' : 'invoice';
                                $logger->error2('1595614552', 'non-numeric estQuantity "' .
                                    $formWorkOrderTasks[$task['workOrderTaskId']]['estQuantity'] .
                                    '" extracted from ' . $class . '\'s getData method for workOrderTask '.
                                    $task['workOrderTaskId'] . '; will set to 0.');
                                $formWorkOrderTasks[$task['workOrderTaskId']]['estQuantity'] = 0;
                            }

                            if ( ! is_numeric($formWorkOrderTasks[$task['workOrderTaskId']]['estCost']) ) {
                                $class = ($obj instanceOf Contract) ? 'contract' : 'invoice';
                                $logger->error2('1595614792', 'non-numeric estCost "' .
                                    $formWorkOrderTasks[$task['workOrderTaskId']]['estCost'] .
                                    '" extracted from ' . $class . '\'s getData method for workOrderTask '.
                                    $task['workOrderTaskId'] . '; will set to 0.');
                                $formWorkOrderTasks[$task['workOrderTaskId']]['estCost'] = 0;
                            }
                            // END ADDED 2020-07-24 JM
                        } // else ("fake") do nothing
                    }
                }
            }
        }
    }

    // At this point we have $workingArray from the current workOrderTasks
    // and $formWorkOrderTasks either from a form or from what was previously saved to the DB.
    // We will take our basic structure from the former, and "overlay" certain data from the latter.
    // Then, if the contract/invoice is committed -- that is, if $obj->getCommittedNew() == 1
    //  -- we will throw away the tasks (really workOrderTasks) that are not already in the contract/invoice.
    // By "throw away" I mean that they won't make it into $processedTasks, which is what we return in $overlaid[$elementId]['tasks'].

    // Whichever way we got the content of $formWorkOrderTasks, we now go about
    //  building the array $overlaid, indexed by elementId with a few special cases,
    //  which is what we eventually return.
    $overlaid = array();

    // [Martin comment] assume this is when updating or committing
    // JM 2020-08-05: $elementId here was previously called $ekey, which was much less mnemonic.
    //  It is normally an elementId but can also be PHP_INT_MAX (sentinel meaning "multiple elements"; should no longer occur in v2020-4 or later);
    //  a comma-separated list of elementIds, no spaces, meaning "precisely these multiple elements" (introduced in v2020-4);
    //  or possibly 0 ("General", no particular element).
    foreach ($workingArray as $elementId => $element) {
        // BEGIN ADDED 2020-08-05 JM
        // initialize array before using it.
        $overlaid[$elementId] = array();
        // END ADDED 2020-08-05 JM

        /*  As noted in documentation of function $elementGroupsToArrayTree, 'element' here is not an Element object;
            it is either false ore a small associative array containing 'elementName' and 'elementId'. */
        $overlaid[$elementId]['element'] = $element['element'];

                    // -------------
        // If the element has any associated workOrderTasks, then we also fill in
        // $overlaid[elementId]['tasks'] as follows.
        // JM: the coding here is very convoluted. Apparently it works.
        //
        if (isset($element['tasks'])) {
            $tasks = $element['tasks'];
            $processedTasks = array();

            // BEGIN MARTIN COMMENT
            // now go through all the live tasks
            // and check also if theres data in the formtasks and use that to overwrite
            // put each one into the processed tasks and use that array at the end.
            // END MARTIN COMMENT

            // $tasks is a flat, numerically-indexed array of associative arrays, corresponding to
            //   a pre-order traversal of the relevant portion of the task hierarchy. Each member
            //   of this array is an associative array with the following indexes:
            //  * 'type': 'real' or 'fake' (whether there is an overt workOrderTask)
            //  * 'level': depth in the task hierarchy
            //  * 'data':
            //     For 'fake' workOrderTasks, a string like 'a210' indicating the taskId 210 (similarly for any other number).
            //     For 'real' workOrderTasks, a WorkOrderTask object.
            if (is_array($tasks)) {
                foreach ($tasks as $tkey => $tt) {
                    // if $element['tasks'][taskId]['data'] doesn't have a member $tt['arrowDirection'],
                    //  then we default to ARROWDIRECTION_RIGHT.
                    if (!isset($tt['arrowDirection'])) {
                        $tt['arrowDirection'] = ARROWDIRECTION_RIGHT;
                    }

                    // if the workOrderTask is "fake" (not explicit), then we initialize
                    //  $task['task'] from $element['tasks'][taskId]['data']
                    if ($tt['type'] == 'fake') {
                        $task= array();
                        $task['task'] = $tt['data']; // the letter 'a' followed by the taskId.
                    } else if ($tt['type'] == 'real') {
                        // NOTE the contrast here to the "fake" case: we are assigning to $task, NOT to $task['task']
                        $task = $tt['data']; // This grabs 'type', 'level' & 'data' all at once.

                        // If this task is in $formWorkOrderTasks (which can either have been passed in or
                        //  built from existing contract/invoice) then
                        // we also add the following associative array elements to $task['task']:
                        // * 'billingDescription'
                        // * 'estQuantity'
                        // * 'estCost'
                        // * 'arrowDirection'
                        // * in the invoice case, also 'fromContract' if it is set.
                        if (array_key_exists(intval($task['workOrderTaskId']), $formWorkOrderTasks)) {
                            // BEGIN MARTIN COMMENT
                            // this might be where
                            // do diff shit for contract and invoice
                            // END MARTIN COMMENT

                            $formTask = $formWorkOrderTasks[intval($task['workOrderTaskId'])];
                            // Do NOT initialize $task['task'] here, it already contains some useful values.
                            $task['task']['billingDescription'] = $formTask['billingDescription'];
                            $task['task']['estQuantity'] = $formTask['estQuantity'];
                            $task['task']['estCost'] = $formTask['estCost'];
                            $task['task']['arrowDirection'] = $formTask['arrowDirection'];
                            if ($isInvoice && isset($formTask['fromContract'])) {
                                $task['task']['fromContract'] = $formTask['fromContract'];
                            }
                        } // else >>>00015: in what circumstances would the workOrderTaskId not be in $formWorkOrderTasks?
                          // >>>00002 would that indicate a problem we should log?
                    }

                    // Then, regardless of whether the task is "real" or "fake", we set the following elements of the
                    //  $task associative array itself:
                    //  * 'type': 'real' or 'fake'
                    //  * 'level': depth in the task hierarchy
                    //  * 'arrowDirection' - >>>00014: how does this differ from $task['task']['arrowDirection']?
                    //  * 'fromContract' if present.
                    $task['type'] = $tt['type'];
                    $task['level'] = $tt['level'];
                    $task['arrowDirection'] = $tt['arrowDirection'];

                    if (isset($tt['fromContract'])){
                        $task['fromContract'] = $tt['fromContract'];
                    }

                    // Then, if it is a committed contract or invoice then I -- JM 2020-08-05 -- *hope* we are
                    //  consistently getting our info from the data blob stored with it in the first place,
                    //   or that something prevents the workOrderTask from changing after commitment,
                    //   but suspect that is not the case, >>>00015 deserves study). Anyway,
                    //   if it is a committed contract or invoice and this is a "real" task, and we got it from
                    //   the stored contract/invoice data rather than an explicitly passed-in form (good!), then
                    //   we add it to associative array $processedTasks with $tkey (which is just a small integer index)
                    //  as its index.
                    // If it's NOT a committed contract or invoice, we add $tkey to $processedTasks
                    //  regardless of any other considerations.
                    if ($obj->getCommittedNew() == 1) {
                        if (isset($task['workOrderTaskId'])) { // [BEGIN Martin comment] kind of ignoring fake tasks here
                                                               // (that shit should be eliminated anyway
                                                               // [END Martin comment]
                                                               // Regardless of Martin's intent, this exists in some recent
                                                               // workOrders as of 2020-08.
                            if (in_array($task['workOrderTaskId'], $commitIds)) {
                                $processedTasks[$tkey] = $task;
                            } else {
                                // Added 2020-08-05 JM: want to see if this happens, and if so on what contracts/invoices
                                // This code is probably temporary, just here to help us understand what's going on.
                                if ($isInvoice) {
                                    $id = $obj->getInvoiceId();
                                    $class = 'invoice';
                                } else {
                                    // Contract
                                    $id = $obj->getContractId();
                                    $class = 'contract';
                                }
                                /*$logger->info2('1596666313', 'Committed ' . $class . '", id=' . $id . ', $elementId=' . $elementId .
                                    '; workOrderTask ' . $task['workOrderTaskId'] . ' at index ' . $tkey .
                                    ' missing from $commitIds, will be a gap in the indexes of the returned array.');*/
                                unset($id, $class);
                            }
                        } else {
                            // Added 2020-08-05 JM: want to see if this happens, and if so on what contracts/invoices
                            // This code is probably temporary, just here to help us understand what's going on.
                            if ($isInvoice) {
                                $id = $obj->getInvoiceId();
                                $class = 'invoice';
                            } else {
                                // Contract
                                $id = $obj->getContractId();
                                $class = 'contract';
                            }
                            /*$logger->info2('1596666528', 'Committed ' . $class . '", id=' . $id . ', $elementId=' . $elementId .
                                '; apparently fake task at index ' . $tkey . ', will be a gap in the indexes of the returned array.'); */
                            unset($id, $class);
                        }
                    } else {
                        $processedTasks[$tkey] = $task;
                    }
                }
            } else {
                // Added 2020-08-05 JM: want to see if this happens, and if so on what contracts/invoices
                // This code is probably temporary, just here to help us understand what's going on.
                if ($isInvoice) {
                    $id = $obj->getInvoiceId();
                    $class = 'invoice';
                } else {
                    // Contract
                    $id = $obj->getContractId();
                    $class = 'contract';
                }
                $logger->info2('1596666754', $class . '", id=' . $id . ', $elementId=' . $elementId .
                    '; $element[\'tasks\'] exists but is not an array.');
                unset($id, $class);
            }

            // Finally, for each element:
            $overlaid[$elementId]['tasks'] = $processedTasks;
        }
    }

    // After looping like this over all elements, $overlaid is completely built, and that is what we return.
    return $overlaid;
} // END function overlay

function orderwotArray($wotArray){


    return $wotArray;
}


/* The following function, introduced by Joe Mabel 2020-11-11, probably duplicates some functionality we have elsewhere,
   but it does so relatively cleanly and may be useful going forward. In particular, it may be able to replace other,
   more convoluted, code.

   Typically, the input here will describe to workOrderTasks, and the variables are named accordingly, but that is not a requirement.

   INPUT $wotArray should be an array of associative arrays. Its top-level indexes are irrelevant. However, some of the
         second-level indexes are relevant. Specifically, for any valid index $i:
   * Every $wotArray[$i] MUST have an element with index 'taskId', which should be a primary key in DB table 'task'. These need not be unique
     across $wotArray.
   * If $wotArray[$i] has an element with index 'parentId', we will trust that to correspond correctly to the parent of task 'taskId'. If not, we
     will make a DB query to fill in $wotArray[$i]['parentId'], which will be used in the return
   * If $wotArray[$i] has an element with index 'workOrderTaskId', it should be a primary key in DB table 'workOrderTask'. These should be unique
     across $wotArray, but if they are not we do not consider that a bug.
   * If $wotArray[$i] has an element with index 'level', that will be ignored and overwritten in the return.
   * If $wotArray[$i] has an element with index 'type', that will be overwritten with 'real'; added elements necessary to fill in the hierarchy
     will be marked with 'fake'.
   * $wotArray need not represent sortOrder, and in fact its own values for sortOrder (if present) will be ignored.

   We also expect the input to have the discipline that no "internal" node (a task with children) will appear more than once in the input $wotArray.
   >>>00016 as of 2020-11-11, this is not yet enforced.

   RETURN: on error, false. On success, an array with the same elements as $wotArray; 'parentId' values added as appropriate; sorted according to
   the pre-order traversal of the relevant subset of the (abstract) task hierarchy. If two array elements have the same taskId, they will be sorted
   in order of their 'workOrderTaskId' (if present).

   Algorithm here is not particularly efficient, but it should be clear, and we are not dealing with anything big enough for the inefficiency to become
   an issue.

   NOTE that this uses two quasi-private functions, which follow immediately in this file
*/
function putTaskArrayInCanonicalOrder($wotArray) {
    global $logger;
    $scratchTasks = Array(); // Just a place to keep stuff so we don't have to get the same object multiple times.
    $parentIdSet = Array();  // Only the indexes here are significant: if $id is a parentId, then $parentIdSet[$id] == 1
    if (!is_array($wotArray)) {
        $logger->error2('1605117636', 'input $wotArray is not an array');
        return false;
    }
    if (count($wotArray) == 0) {
        return Array();
    }
    foreach ($wotArray as $i => $wot) {
        if($wot['type']=='fake'){
            $wotArray[$i]['taskId']=$wot['task']['taskId'];
        }

    }


    // Make sure every element of $wotArray has a valid taskId
    foreach ($wotArray as $i => $wot) {
        if (!array_key_exists('taskId', $wot)) {
            $logger->error2('1605117890', '$wotArray[\''. $i . '\'] missing element \'taskId\'');
            return false;
        }
        if (!array_key_exists($wot['taskId'], $scratchTasks)) {
            $scratchTasks[$wot['taskId']] = new Task($wot['taskId']);
            if (!$scratchTasks[$wot['taskId']]) {
                $logger->error2('1605118075', 'Failed to build Task object for taskId' . $wot['taskId']);
                return false;
            }
        }
    }
    // Validate or fill in parentId as needed
    foreach ($wotArray as $i => $wot) {
        if (array_key_exists('parentId', $wot)) {
            $parentId = $wot['parentId'];
        } else {
            $parentId = $scratchTasks[$wot['taskId']]->getParentId();
            $wotArray[$i]['parentId'] = $parentId;
        }
        if (!array_key_exists($parentId, $scratchTasks)) {
            if ($parentId != 0) {
                $scratchTasks[$parentId] = new Task($parentId);
                if (!$scratchTasks[$parentId]) {
                    $logger->error2('1605118571', 'Failed to build Task object for ' . $parentId . ', parentId of $wotArray[\'' . $i . '\'] ' .
                        '(taskId = ' . $wot['taskId'] . ')');
                    return false;
                }
            }
        }
    }
    // Basic form of $wotArray is now validated, and parentIds have been added if needed
    // Mark everything as "real"
    foreach ($wotArray as $i => $wot) {
        $wotArray[$i]['type'] = 'real';
        $parentId = $wot['parentId'];
        if ($parentId != 0) {
            $parentIdSet[$parentId] = 1;
        }
    }
    // Are any parents missing from $wotArray? $inArray test here may not be needed (PHP spec on sparse array with
    //  numeric indexes is vague) but should be harmless
    // We handle this with a nested function, because otherwise the looping would be pretty miserable to describe
    //  (consider the very normal case where we need to add a 'fake' parent that has a lower taskId than its child;
    //  recursion is simpler).
    $fakes = Array();
    foreach ($parentIdSet as $parentId => $inArray) {
        if ($inArray) {
            canonical_buildFakes($parentId, $fakes, $wotArray, $scratchTasks, $parentIdSet);
        }
    }


    $ret = Array();
    // get all zero-level tasks
    $zeroLevelTasks = Task::getZeroLevelTasks(false, false); // false, false -> use sortOrder (not alphabetical), don't limit to active
    canonical_buildReturn($zeroLevelTasks, $wotArray, $ret, $fakes, $scratchTasks);
    return $ret;
} // END function putTaskArrayInCanonicalOrder
// function canonical_buildFakes exists only to be used locally by function putTaskArrayInCanonicalOrder (where it needs to go recursive)
function canonical_buildFakes($taskId, &$fakes, &$wotArray, &$scratchTasks, &$parentIdSet, $level=0) {
    global $logger;

    if ($level > 20) {
        $logger->error2('1605196047', 'Runaway recursion in canonical_buildFakes');
        return;
    }
    $matchFound = false;
    foreach ($wotArray as $wot) {
        if ($wot['parentId'] == $taskId) {
            $matchFound = true;
            break;
        }
    }
    if (!$matchFound) {
        foreach ($fakes as $fake) {
            if ($fake['parentId'] == $taskId) {
                $matchFound = true;
                break;
            }
        }
    }
    if (!$matchFound) {
        $scratchTasks[$taskId] = new Task($taskId);
        $parentIdSet[$taskId] = 1;

        $parentId = $scratchTasks[$taskId]->getParentId();
        $wotArray[] = Array('type' => 'fake', 'taskId' => $taskId, 'parentId' => $parentId);

        if ($parentId) {
            // Not top level
            canonical_buildFakes($parentId, $fakes, $wotArray, $scratchTasks, $parentIdSet, $level+1);
        }
    }
} // END FUNCTION canonical_buildFakes
// function canonical_buildReturn exists only to be used locally by function putTaskArrayInCanonicalOrder (where it needs to go recursive)
function canonical_buildReturn($taskArray, $wotArray, &$ret, $fakes, $scratchTasks, $level=0) {
    global $logger;
    if ($level > 20) {
        $logger->error2('1605196047', 'Runaway recursion in canonical_buildReturn');
        return;
    }
    foreach ($taskArray as $task) {
        //echo $task->getTaskId()."<br>"."\n";
        $taskId = $task->getTaskId();

        // I don't think we will have "real" rows both with and without workOrderTaskId, but just in case...
        $rowsForThisTaskWithWorkOrderTaskId = Array();
        $rowsForThisTaskWithoutWorkOrderTaskId = Array();
        $fakeRowsForThisTask = Array();
        foreach ($wotArray as $wot) {
            if ($wot['taskId'] == $taskId) {
                if (array_key_exists('workOrderTaskId', $wot)) {
                    $rowsForThisTaskWithWorkOrderTaskId[] = $wot;
                } else {
                    $rowsForThisTaskWithoutWorkOrderTaskId = $wot;
                }
            }
        }
        array_multisort(array_column($rowsForThisTaskWithWorkOrderTaskId, 'workOrderTaskId'), SORT_ASC, $rowsForThisTaskWithWorkOrderTaskId);

        if (count($rowsForThisTaskWithWorkOrderTaskId) == 0 && count($rowsForThisTaskWithWorkOrderTaskId) == 0) {
            // Nothing "real" here, but could be a "fake"
            foreach ($fakes as $fake) {
                if ($fake['taskId'] == $taskId) {
                    $fakeRowsForThisTask[] = $fake;
                    break; // can't be more than one of these that matter.
                }
            }
        }
        $rowsForThisTask = array_merge($rowsForThisTaskWithWorkOrderTaskId, $rowsForThisTaskWithoutWorkOrderTaskId, $fakeRowsForThisTask);
        foreach ($rowsForThisTask as $rowForThisTask) {
            if(is_array($rowForThisTask)){
                $rowForThisTask['level'] = $level;
                $ret[] = $rowForThisTask;
                $nextLevelTasks = $scratchTasks[$rowForThisTask['taskId']]->getChilds(false, false); // false, false -> use sortOrder (not alphabetical), don't limit to active
                canonical_buildReturn($nextLevelTasks, $wotArray, $ret, $fakes, $scratchTasks, $level+1);
            }
        }
    }
} // END FUNCTION canonical_buildReturn

/**
    * @return array $ret. RETURNs content of DB table TaskType as an associative array indexed by taskTypeId,
    *  each element of which is an associative array giving the canonical representation
    *  of the appropriate row from DB table TaskType (column names as indexes).
*/
function getTaskTypes() {
    global $logger;
    $db = DB::getInstance();
    $ret = array();
    $query  = " select * from " . DB__NEW_DATABASE . ".taskType order by displayOrder,taskTypeId ";
    $result = $db->query($query);
    if (!$result) { // George 2020-09-30. Rewrite if statement.
        $logger->errorDB('637370696209480315', " getTaskTypes() => Hard DB error", $db);
    } else {
        while ($row = $result->fetch_assoc()) {
            $ret[$row['taskTypeId']] = $row;
        }
    }
    return $ret;
}
/**
    *
    * @param  bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return array $ret. RETURNs contents of DB table ContractLanguage as an array, each of whose elements
    *  is an associative array giving the canonical representation
    *  of a row from the table (column names as indexes), augmented by an index 'fileExists'
    *  whose value is a Boolean indicated whether this file exists. Ordered by contractLanguageId.
*/
function getContractLanguageFiles(&$errCode=false){
    global $db, $logger;
    $db = DB::getInstance();
    $errCode = false;
    $files = array();
    $ret = array();

    if ($handle = opendir(LANG_FILES_DIR)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                if (is_file(LANG_FILES_DIR . $file)) {
                    $files[] = $file;
                }
            }
        }
        closedir($handle);
    }

    $query  = "SELECT * FROM " . DB__NEW_DATABASE . ".contractLanguage ORDER BY inserted DESC";

    $result = $db->query($query);

    if (!$result) {
        $logger->errorDB('637317078610953548', "Hard DB error", $db);
        $errCode=true;
    } else {
        while ($row = $result->fetch_assoc()) {
            if (in_array($row['fileName'], $files)) {
                $row['fileExists'] = true;
            } else {
                $row['fileExists'] = false;
            }
            $ret[] = $row;
        }
    }

    return $ret;
}
/**
    *
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return array $ret. RETURNs contents of DB table Terms as an array, each of whose elements
    *  is an associative array giving the canonical representation
    *  of a row from the table (column names as indexes). Ordered by (sortOrder, termsId).
*/

function getTerms(&$errCode=false) {
    global $db, $logger;
    $db = DB::getInstance();
    $errCode = false;
    $ret = array();

    $query  = " SELECT * FROM " . DB__NEW_DATABASE . ".terms ORDER BY sortOrder,termsId ";

    $result = $db->query($query);
    if (!$result) {
        $logger->errorDB('637318099273942698', "Hard DB error", $db);
        $errCode = true;
    } else {
        while ($row = $result->fetch_assoc()) {
            $ret[] = $row;
        }
    }

    return $ret;
}

// Builds array of US state names & abbreviations
function allStates() {
    $states = array();

    $states[] = array('Alabama', 'AL');
    $states[] = array('Alaska', 'AK');
    $states[] = array('Arizona', 'AZ');
    $states[] = array('Arkansas', 'AR');
    $states[] = array('California', 'CA');
    $states[] = array('Colorado', 'CO');
    $states[] = array('Connecticut', 'CT');
    $states[] = array('Delaware', 'DE');
    $states[] = array('Florida', 'FL');
    $states[] = array('Georgia', 'GA');
    $states[] = array('Hawaii', 'HI');
    $states[] = array('Idaho', 'ID');
    $states[] = array('Illinois', 'IL');
    $states[] = array('Indiana', 'IN');
    $states[] = array('Iowa', 'IA');
    $states[] = array('Kansas', 'KS');
    $states[] = array('Kentucky', 'KY');
    $states[] = array('Louisiana', 'LA');
    $states[] = array('Maine', 'ME');
    $states[] = array('Maryland', 'MD');
    $states[] = array('Massachusetts', 'MA');
    $states[] = array('Michigan', 'MI');
    $states[] = array('Minnesota', 'MN');
    $states[] = array('Mississippi', 'MS');
    $states[] = array('Missouri', 'MO');
    $states[] = array('Montana', 'MT');
    $states[] = array('Nebraska', 'NE');
    $states[] = array('Nevada', 'NV');
    $states[] = array('New Hampshire', 'NH');
    $states[] = array('New Jersey', 'NJ');
    $states[] = array('New Mexico', 'NM');
    $states[] = array('New York', 'NY');
    $states[] = array('North Carolina', 'NC');
    $states[] = array('North Dakota', 'ND');
    $states[] = array('Ohio', 'OH');
    $states[] = array('Oklahoma', 'OK');
    $states[] = array('Oregon', 'OR');
    $states[] = array('Pennsylvania', 'PA');
    $states[] = array('Rhode Island', 'RI');
    $states[] = array('South Carolina', 'SC');
    $states[] = array('South Dakota', 'SD');
    $states[] = array('Tennessee', 'TN');
    $states[] = array('Texas', 'TX');
    $states[] = array('Utah', 'UT');
    $states[] = array('Vermont', 'VT');
    $states[] = array('Virginia', 'VA');
    $states[] = array('Washington', 'WA');
    $states[] = array('West Virginia', 'WV');
    $states[] = array('Wisconsin', 'WI');
    $states[] = array('Wyoming', 'WY');

    return $states;
} // END function allStates



/**
* @param bool $errCode, variable pass by reference. Default value is false.
*    $errCode is True on query failed.
* @return array $ret. RETURNs content of DB table ServiceLoad as an array of ServiceLoad objects,
*    ordered by loadName.
*/
function getServiceLoads(&$errCode = false) {
    global $logger;
    $db = DB::getInstance();
    $errCode = false;
    $ret = array();

    $query  = " select * from " . DB__NEW_DATABASE . ".serviceLoad order by loadName ";
    $result = $db->query($query);

    if (!$result) { // George 2020-09-24. Rewrite if statement.
        $logger->errorDB('637365581075371094', "Hard DB error", $db);
        $errCode = true;
    } else {
        while ($row = $result->fetch_assoc()){
            $ret[] = new ServiceLoad($row['serviceLoadId']);
        }
    }

    return $ret;
}


/**
* @param bool $errCode, variable pass by reference. Default value is false.
*    $errCode is True on query failed.
* @return array $ret. RETURNs an unique array with loadVarData, where loadVarType = 1.
*/
function getServiceLoadsVarData(&$errCode = false) {
    global $logger;
    $db = DB::getInstance();
    $errCode = false;
    $varData = $arr = $ret = array();

    $query = "SELECT loadVarData FROM " . DB__NEW_DATABASE . ".serviceLoadVar ";
    $query .= "WHERE loadVarData <> '' AND loadVarType = ". SERVLOADVARTYPE_MULTI  ;

    $result = $db->query($query);

    if (!$result) { // George 2020-09-24. Rewrite if statement.
        $logger->errorDB('637366378165805539', " getServiceLoadsVarData() => Hard DB error", $db);
        $errCode = true;
    } else {
        while ($row = $result->fetch_assoc()) {
            $varData[] = $row['loadVarData']; //only loadVarData
        }
    }

    foreach ($varData as $key=>$value) {
        $varData = (explode("|", $value)); // typically example Enclosed Building|Partially Enclosed Building|Open Building
        $arr[] = $varData;
    }

    foreach($arr as $key=>$value) {
       foreach($value as $value2) {
           $ret[] = $value2;
        }
    }
    return array_unique($ret); // unique loadVarData (used for validation in apellant code fb/ serviceloads.php)
}

/**
* @param int $onlyServ. Default value is 0. If 1, we only select serviceLoadvarId for var Type Multi.
* @param bool $errCode, variable pass by reference. Default value is false.
*    $errCode is True on query failed.
* @return array $ret. RETURNs an array with serviceLoadvarIds.
*/
function getServiceLoadsVarIds($onlyServ = 0, &$errCode = false) {
    global $logger;
    $db = DB::getInstance();
    $errCode = false;
    $ret = array();

    $query = "SELECT serviceLoadvarId FROM " . DB__NEW_DATABASE . ".serviceLoadVar ";
    if($onlyServ == 1) {
        $query .= " WHERE loadVarType = ". SERVLOADVARTYPE_MULTI ;
    }

    $result = $db->query($query);

    if (!$result) { // George 2020-09-24. Rewrite if statement.
        $logger->errorDB('637366444107112232', " getServiceLoadsVarIds() => Hard DB error", $db);
        $errCode = true;
    } else {
        while ($row = $result->fetch_assoc()) {
            $ret[] = $row['serviceLoadvarId'];
        }
    }

    return $ret;
}


/**
 *
    * @param bool $errCode, variable pass by reference. Default value is false.
    *  $errCode is True on query failed.
    * @return array $positions. RETURNs content of DB table TeamPosition as an array, each of whose elements
    *  is an associative array giving the canonical representation
    *  of a row from the table (column names as indexes). Ordered by name.
*/
function getTeamPositions(&$errCode=false) {
    global $logger;
    $positions = array();
    $errCode=false;
    $db = DB::getInstance();

    $query  = " select * from " . DB__NEW_DATABASE . ".teamPosition order by name ";

    $result = $db->query($query); // George 2020-09-30. Rewrite if statement.

    if (!$result) {
        $logger->errorDB('637370759616981825', "getTeamPositions() => Hard DB error", $db);
        $errCode=true;
    } else {
        while ($row = $result->fetch_assoc()) {
            $positions[] = $row;
        }
    }

    return $positions;
}


/**
    * @return array $roles. RETURNs content of DB table Role as an array, each of whose elements
    *  is an associative array giving the canonical representation
    *  of a row from the table (column names as indexes). Ordered by name.
*/
function getRoles() {
    global $logger;
    $roles = array();
    $db = DB::getInstance();

    $query  = " select * from " . DB__NEW_DATABASE . ".role order by name ";

    $result = $db->query($query); // George 2020-09-30. Rewrite if statement.

    if (!$result) {
        $logger->errorDB('637370759616981825', "getRoles() => Hard DB error", $db);
    } else {
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
    }

    return $roles;
}

// Makes sense of dates related to a workOrder, including coping with zeroed dates.
// INPUT $genesisDate: genesisDate of workOrder, in 'Y-m-d H:i:s' form.
// INPUT $getDeliveryDate: (>>>00012 really should just be $deliveryDate). deliveryDate of workOrder, in 'Y-m-d H:i:s' form.
// INPUT $isDone: true if workOrder in question is done/closed, because the we don't really want to return an "age".
// >>>00016, >>>00002: we should really validate inputs better. We cope with a "zero date", but not with a bad one.
//
// RETURN associative array with the following elements:
//  * 'genesisDT': Genesis date in 'M d Y' form, or an m-dash if not a valid date
//  * 'deliveryDT': Delivery date in 'M d Y' form, or an m-dash if not a valid date
//  * 'ageDT': if genesis date is valid and workOrder is not completed, signed integer
//    number of days since genesis date. Otherwise, an m-dash.
function formatGenesisAndAge($genesisDate, $getDeliveryDate, $isDone) {
    $genesisDT = '';
    $deliveryDT = '';
    $ageDT = '';

    if ($genesisDate != '0000-00-00 00:00:00'){
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $genesisDate);
        $genesisDT = $dt->format('M d Y');
    } else {
        $genesisDT = '&mdash;';
    }

    if ($getDeliveryDate != '0000-00-00 00:00:00'){
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $getDeliveryDate);
        $deliveryDT = $dt->format('M d Y');
    } else {
        $deliveryDT = '&mdash;';
    }

    if ($genesisDate != '0000-00-00 00:00:00'){
        $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $genesisDate);
        $dt2 = new DateTime;
        $interval = $dt1->diff($dt2);
        $ageDT = $interval->format('%R%a days');
    } else {
        $ageDT = '&mdash;';
    }

    if ($isDone) {
        $ageDT = '&mdash;';
    }

    return array(
            'genesisDT' => $genesisDT,
            'deliveryDT' => $deliveryDT,
            'ageDT' => $ageDT

    );
} // END function formatGenesisAndAge

// INPUT $personId
// RETURNs a Boolean to say whether input $personId exists in DB table Person.
// Radically simplified 2020-01-13 JM
function existPersonId($personId) {
    return Person::validate($personId);
}

// INPUT $contractId
// RETURNs a Boolean to say whether input $contractId exists in DB table Contract.
// Radically simplified 2020-01-13 JM
function existContract($contractId) {
    return Contract::validate($contractId);
}

// INPUT $workOrderTaskId
// RETURNs a Boolean to say whether input $workOrderTaskId exists in DB table WorkOrderTask.
// Radically simplified 2020-01-13 JM
function existWorkOrderTask($workOrderTaskId) {
    return WorkOrderTask::validate($workOrderTaskId);
}

// INPUT $workOrderId
// RETURNs a Boolean to say whether input $workOrderId exists in DB table WorkOrder.
// Radically simplified 2020-01-13 JM
function existWorkOrder($workOrderId) {
    return WorkOrder::validate($workOrderId);
}

// INPUT $taskId
// RETURNs a Boolean to say whether input $taskId exists in DB table Task.
// Radically simplified 2020-01-13 JM
function existTaskId($taskId) {
    return Task::validate($taskId);
}

// >>> Appears to be an exact duplicate of function existWorkOrder, >>>00006 we really
//  should get rid of one of them.
// INPUT $workOrderId
// RETURNs a Boolean to say whether input $workOrderId exists in DB table WorkOrder.
// Radically simplified 2020-01-13 JM
function existWorkOrderId($workOrderId) {
    return WorkOrder::validate($workOrderId);
}

// INPUT $elementId
// RETURNs a Boolean to say whether input $elementId exists in DB table Element.
// Radically simplified 2020-01-13 JM
function existElementId($elementId) {
    return Element::validate($elementId);
}

// INPUT $customer: Customer object. As of 2019-03, the only customer is SSS itself.
// RETURNs an array of associative arrays representing all employees (persons)
//  associated with this customer via customerPerson table. Each element in the
//  returned array is an associative array whose indexes correspond to the columns
//  of DB tables Person and CustomerPerson.
//  Return is sorted by (lastName, firstName)
// >>>00006 JM: As of 2020-07-06 this is used only in _admin/phone/phone.php and _admin/time/time.php. All other
//   calls have been converted to use Customer::getEmployees, which is similar but definitely not identical.
//   It would probably be worth reworking those two files to get rid of this function.
function getEmployees($customer) {
    global $logger;
    $db = DB::getInstance();
    $ret = array();

    //    >>>00022 SELECT * on a SQL JOIN is not
    //  really a good idea. Fortunately, the colliding column names between the two tables
    //  (personId, customerId) should always have the same value in both tables, but we
    //  get away with this only because MySQL is very slack in this respect. The query
    //  should be more overt about what columns are returned.
    $query  = " select * ";
    $query .= " from " . DB__NEW_DATABASE . ".customerPerson cp ";
    $query .= " join " . DB__NEW_DATABASE . ".person p on cp.personId = p.personId ";
    $query .= " where cp.customerId = " . intval($customer->getCustomerId()) . " ";
    $query .= " order by p.LastName, p.firstName ";

    $result = $db->query($query); // George 2020-09-30. Rewrite if statement.

    if (!$result) {
        $logger->errorDB('637370743013114420', "getEmployees() => Hard DB error", $db);
    } else {
        while ($row = $result->fetch_assoc()) {
            $ret[] = $row;
        }
    }

    return $ret;
}

/**
    * @return array $ret. RETURNs contents of DB table phonetype as an array,
    * each element of which is an associative array giving the canonical representation
    * of the appropriate row from DB table phonetype (column names as indexes).
    * Order by typeName.
*/
function phoneTypes() { // George. I don't think this function is used.
    global $logger;
    $db = DB::getInstance();
    $ret = array();

    $query  = " select * ";
    $query .= " from " . DB__NEW_DATABASE . ".phoneType ";
    $query .= " order by typeName ";

    $result = $db->query($query); // George 2020-09-30. Rewrite if statement.
    if (!$result) {
        $logger->errorDB('637370712503192607', "phoneTypes() => Hard DB error", $db);
    } else {
        while ($row = $result->fetch_assoc()) {
            $ret[] = $row;
        }
    }

    return $ret;
}

/* BEGIN REMOVED 2020-11-18 JM - no longer needed
//    * @param bool $errCode, variable pass by reference. Default value is false.
//    * $errCode is True on query failed.
//    * @return array $ret. RETURNs contents of DB table JobStatus as an array,
//    * each element of which is an associative array giving the canonical representation
//    * of the appropriate row from DB table JobStatus (column names as indexes).
//    * Order by displayOrder.
function jobStatuses(&$errCode = false) {
    global $db, $logger;
    $db = DB::getInstance();
    $ret = array();
    $errCode=false;

    $query  = " SELECT * ";
    $query .= " FROM " . DB__NEW_DATABASE . ".jobStatus ";
    $query .= " ORDER BY displayOrder ";

    $result = $db->query($query);  // George 2020-08-20. Rewrite if statement.

    if(!$result){
        $logger->errorDB('637335324527698517', "Hard DB error", $db);
        $errCode = true;
    } else {
        while ($row = $result->fetch_assoc()) {
            $ret[] = $row;
        }
    }

    return $ret;
}
// END REMOVED 2020-11-18 JM
*/

// RETURNs an array of months and their abbreviations. The piter array has no zero
/// element, each month gets it's obvious index 1-12. Each element of the array
//  is itself an array with two elements:
//   [0] => full text name of month
//   [1] => three-letter name of month
function selectMonths() {
    return array(
            1 => array('January', 'Jan'),
            2 => array('February', 'Feb'),
            3 => array('March', 'Mar'),
            4 => array('April', 'Apr'),
            5 => array('May', 'May'),
            6 => array('June', 'Jun'),
            7 => array('July', 'Jul'),
            8 => array('August', 'Aug'),
            9 => array('September', 'Sep'),
            10 => array('October', 'Oct'),
            11 => array('November', 'Nov'),
            12 => array('December', 'Dec')
            );
}

/**
    * @param $permissionString, input permisions string from $_REQUEST.
    * @return true if each "digit" from string correspond to a PERMLEVEL, false if not.
*/
function validatePermissionString($permissionString) {
    global $logger;
    // Permission levels
    $permissionLevels = [PERMLEVEL_ADMIN, PERMLEVEL_RWAD, PERMLEVEL_RWA, PERMLEVEL_RW, PERMLEVEL_R, PERMLEVEL_NONE];

    if((strlen($permissionString) != 64) || !is_numeric($permissionString)) {
        $logger->error2("637412988427575397", "Invalid input for Permissions, not numeric or not 64 digits! Input value: " . $permissionString);
        return false;
    }

    $arrayVal = str_split($permissionString); //string to array.

    foreach($arrayVal as $value) {
        if(!in_array($value, $permissionLevels)) { // bad value
            $logger->error2("637412988427575397", "Invalid input for Permissions, not a PERMLEVEL! Input value: " . $value);
            return false;
        }
    }
    return true;
}

/**
    * @param $activeOnly: if truthy, limit to active types. Default is 0 (show only active types).
    * @param bool $errCode, variable pass by reference. Default value is false.
    * $errCode is True on query failed.
    * @return array $dts. RETURNs content of DB table WorkOrderDescriptionType as an array,
    *    each element of which is an associative array giving the canonical representation
    *    of the appropriate row from DB table WorkOrderDescriptionType (column names as indexes).
    *    Order by displayOrder.
*/

function getWorkOrderDescriptionTypes($activeOnly = 0, &$errCode = false) {
    global $logger;
    $errCode=false;
    $db = DB::getInstance();
    $dts = array();

    $query = " select * ";
    $query .= " from " . DB__NEW_DATABASE . ".workOrderDescriptionType ";
    if (intval($activeOnly)){
        $query .= " where active = 1 ";
    }
    $query .= " order by displayOrder ";

    $result = $db->query($query); //George 2020-08-17. Rewrite "if" statement.

    if (!$result) {
        $logger->errorDB('637332700412064243', "Hard DB error", $db);
        $errCode = true;
    } else {
        while ($row = $result->fetch_assoc()) {
            $dts[] = $row;
        }
    }

    return $dts;
}


// INPUT
// $databaseTable - table from which we want to delete
// $primaryKeyName - name of the primary key in the $databaseTable. In practice, this should always be $databaseTable.'Id', but we
//  have chosen not to hardcode that.
// $primaryKeyValue - the value of the primary key for the row we want to delete, identifier for this row
// RETURN
// true - No reference to the primary key of this row is found in the database => it is possible to delete the row
// false - At least on reference to this row exists in the database => hard-delete is probably not a good idea: it
//         would violate database integrity. There theoretically could be circumstances where multiple rows are being
//         deleted, and the references are in that group; that would require further analysis beyond that relatively simple function.
//
// Example: canDelete("personEmail", "personEmailId", 749)
//
// NOTE our DB table integrityData exists because MySQL does not enforce the SQL concept of a 'foreign key'.
//      This function requires that DB table integrityData is complete and up to date.
// NOTE that this assumes that we always find a row by its primary key. It is imaginable that we would find a row only by some other means
//   (e.g. search on a name); this function would fail to detect that.
//
// >>>00001 JM 2020-06-18: Normally, our code refers to tables more along the lines of
//     DB__NEW_DATABASE . ".integrityData"
// rather than just
//    "integrityData"
// Is there a reason this is different, or was that an oversight?
//
// >>>00001 We may want to give more explicit thought to uppercase/lowercase here. Right now we assume -- both in
//  calls to this function and in the content of DB table integrityData -- that all table and column names are
//  written in camelcase (https://en.wikipedia.org/wiki/Camel_case).
//
function canDelete($databaseTable, $primaryKeyName, $primaryKeyValue) {
    global $db, $logger;
    $db=DB::getInstance();

    $signatureForErrorReporting = 'canDelete("' . $databaseTable . '", "' . $primaryKeyName . '", ' . $primaryKeyValue . ')';
    if( ! intval($primaryKeyValue) ) {
        $logger->error2('1592579546', 'primaryKeyValue (third argument) is not a number: ' . $signatureForErrorReporting);
        return false;
    }
    if ($primaryKeyValue == 0) {
        $logger->warn2('1592579548', 'primaryKeyValue (third argument) is zero. Free to delete. ' . $signatureForErrorReporting);
        return true;
    }
    if ($primaryKeyValue < 0) {
        $logger->error2('1592579549', 'primaryKeyValue (third argument) is negative. MUST investigate. ' . $signatureForErrorReporting);
        return false;
    }
    // Select rows from DB table integrityData where either (1) a different table refers to $primaryKeyName by name or
    // (2) a rule (including one for the table itself) mentions $primaryKeyName. There may be false positives here, because one primary key name can imaginably
    // be a prefix of another, but we will filter them out below.
    $query = "SELECT * FROM ".DB__NEW_DATABASE.".integrityData " .
             "WHERE (isPrimaryKey=1) and (externalTableField = '" . $db->real_escape_string($primaryKeyName) . "' AND tableName <> '" . $db->real_escape_string($databaseTable) . "' " .
             "OR (rule IS NOT NULL AND rule LIKE '%" . $db->real_escape_string($primaryKeyName) . "%'));";
    $resultMain = $db->query($query);
    if (!$resultMain) {
        $logger->errorDB('1592579591', "Hard DB error", $db);
        return false;
    }

    while ($row=$resultMain->fetch_assoc()){
        if ($row['rule'] === null) {
            $query = "SELECT ". $db->real_escape_string($primaryKeyName) . " " .
                    "FROM  ". DB__NEW_DATABASE .".". $db->real_escape_string($row['tableName']) . " " .
                    "WHERE ". $db->real_escape_string($primaryKeyName) . "= $primaryKeyValue " .
                    "LIMIT 1;";
            $result = $db->query($query);
            if (!$result) {
                $logger->errorDB('1592579620', "Hard DB error", $db);
                return false;
            }
            if ($result->num_rows > 0) {
                return false;
            }
        } else {
            $rules = json_decode($row['rule'], true);
            foreach ($rules['rules'] as $rule) {
                if ($rule['field'] == $primaryKeyName) { // filter out false positives
                    $query = "SELECT " . $row['tableField'] . " " .
                             "FROM ". DB__NEW_DATABASE . ".". $db->real_escape_string($row['tableName']) . " ".
                             "WHERE " . $row['tableField'] ."=$primaryKeyValue " .
                             "AND ". $db->real_escape_string($rules['fieldName']) . "=". $db->real_escape_string($rule['id']) . " " .
                             "LIMIT 1;";
                    $result = $db->query($query);
                    if (!$result) {
                        $logger->errorDB('1592579775', "Hard DB error", $db);
                        return false;
                    }
                    if ($result->num_rows > 0) {
                        return false;
                    }
                }
            }
        }
    }
    return true;
}

//  handle truncation when an input is too long for the database.
//  INPUT $var: input to truncate.
//    INPUT $var_name: name of variable.
//    INPUT $len: lenght to truncate.
//  RETURNs truncate variable of given length.
//     Log is length of $var > 1000 characters.
//  Call example:  truncate_for_db($var, 'reason', 128);

function truncate_for_db ($var, $var_name, $len, $errorId) {
    global $logger;

    //Remove blanks(spaces) from begining and the end of the given string.
    // E.g. "    Joe Mabel  "; Will become: "Joe Mabel";
    $var = trim($var);

    //Retrive the first $len characters.
    // E.g. "Joe Mabel" , for $len = 4, will be "Joe ";
    $ret = substr($var, 0, $len);

    //Remove blanks(spaces) from the end of the acctual string.
    // E.g. "Joe ", will be "Joe";
    $ret = trim($ret);

    if (strlen($var) > $len) {
        $ellipsis = strlen($var) > 1000 ? '...' : '';
        $message = " Truncating $var_name to $len characters, was " . strlen($var) . " characters: ";
        $message .=  ($len < 1000) ? '['. $ret . '][' . substr($var, $len, 1000) . $ellipsis . ']' : '['. substr($var, 1000, 0) . $ellipsis . ']';

        $logger->info2($errorId,  $message);
    }

    return $ret;
}


    /**
    * The functions validate the app base requirements: DB, customer, customerId.
    * If the validation fail, the application flow stop now.
    * @param string $errorMessage
    * @param bool $useJsonFormat
    * @return void
    */
    function do_primary_validation($errorMessage, $useJsonFormat = false) {
        global $logger;
        $error = '';
        $errorId = 0;

        list($error, $errorId) = Validator2::primary_validation();
        if($error){
            $logger->error2($errorId, "Error found in  primary validation: $error "); //errorId is set in method primary_validation();
            if(!$useJsonFormat)
            {
                $_SESSION["error_message"] = $errorMessage; // Message for end user
                $_SESSION["errorId"] = $errorId;
                header("Location: /error.php");
            }
            else
            {
                $data['status']='fail';
                $data['info']= "Error(s) found in primary validation: ".  $error;
                header('Content-Type: application/json');
                echo json_encode($data);
            }

            // Stop here the application flow!
            die();
        }
    }

    /**
     * Check whether the given field already exists in database.
     *
     * @param string $entityTable table where we search for the entity
     * @param string $entityName column name to be searched. OK to pass in empty string, if you want to use just the $whereCondition.
     * @param string $entityVal value to be searched in that column. NOTE that this can meaningfully be a blank string.
     * @param string $whereCondition value for SQL WHERE condition; default '';
     * @return bool true if entityVal exists, null if input error or database error, false otherwise.
     */
    function entityExists($entityTable, $entityName, $entityVal, $whereCondition = ''){
        global $db, $logger;
        $db=DB::getInstance();

        if (!$entityTable || strlen($entityTable) == 0)
            return NULL;

        $query = "select count(*) as cnt FROM " . DB__NEW_DATABASE . "." . $entityTable; // CP - 2020-11-30 - give name to count(*) in order to be used with fetch_assoc
        $whereAdded = false;

        if ($entityName && strlen($entityName) > 0) {
            if (!$entityVal) {
                $entityVal = '';
            }
            $query .= " where " .$entityName . " = '" . $db->real_escape_string($entityVal) . "'";
            $whereAdded = true;
        }

        if ($whereCondition && strlen($whereCondition) > 0) {
            if (!$whereAdded) {
                $query .= " where ";
            } else {
                $query .= " and ";
            }
            $query .= $whereCondition;
        }

        //$query .= " LIMIT 1"; // added 2020-03-13 JM because we are just looking for existence, so keep query as cheap as possible.
                              // added 2020-11-30 CP I believe a count(*) without a group by will always return 1 row ..

        $query .= ";"; // ensure the EndOfQuery MYSQL markup

        $result = $db->query($query);

        if ($result) {
            $row = $result->fetch_assoc(); // JM 2020-03-13 >>>00001 I would have given the count in the SQL a name and used the more common fetch_assoc
                                                     // But this isn't actively wrong.
                                            // CP 2020-11-30 replace fetch_array => fetch_assoc
            $count = intval($row['cnt']);
            return $count > 0; // THIS IS THE NORMAL RETURN
        }

        // Arrive here only on database error
        $logger->errorDb('1584129619', "In DB::entityExists", $db);
        return NULL; //
    }


    /* As far as I (JM 2020-08) can tell, the only sane way to make a non-AJAX post is with an HTML form; jQuery doesn't
       have a good function for this and you can't do it even from the server side. This should meet that need.

       Assuming this functions.php file is already included:
        * call PHP function includeOurPost() to generate JS function ourPost on document ready
        * then callJS function ourPost from other JS, which must not execute before document ready. E.g.

        <html>
        ...
        <body>
        ...
        <?php
        includeOurPost();
        ?>
        ...
        <script>
        $(
            let targetURL = ...; // whatever URL we want to post to
            let data = {
                'name1': value1,
                'name2': value2,
                ... etc. ...
            };
            ourPost(targetURL, data);
        );
        </script>
        ...

       INPUT targetURL - can be relative or absolute
       INPUT data - object, effectively giving the name-value pairs to pass in the post.
    */
    function includeOurPost() {
    ?>
        <script>
        $(
            function ourPost(targetURL, data) {
                let $form = $('<form style="display:none" method="post" action="' + targetURL + '"></form>');
                for (const name in data) {
                    $form.append('<input type="hidden" name="' + name + '" value="' + data[name] + '">');
                }
                $form.append('<input type="submit">');
                $form.appendTo('body');
                $('input[type="submit"]', $form).click();
            }
        );
        </script>
    <?php
    } // END function includeOurPost


    /**
        *  @param int workOrderId: primary key in DB table workOrder.
        *  Get the data for an contract -> Gantt Tree.
        *  @return array $out for an associative array with the following members:
            * 'out': array. Each element is an associative array with elements:
                * 'elementId': identifies the element.
                * 'elementName': identifies the element name.
                * 'parentId': is null for the element, for a workorderTask is the id of the parent.
                * 'taskId': identifies the task.
                * 'parentTaskId': alias 'parentId', is null for the element, for a workorderTask is the id of the parent.
                * 'workOrderTaskId': identifies the workOrderTask.
                * 'billingDescription':  billing description for a specific workOrderTask.
                * 'icon':  icon for a specific workOrderTask.
                * 'cost':  cost for a specific workOrderTask.
                * 'totCost':  totCost for a specific workOrderTask.
                * 'taskTypeId':  type of a task ( table tasktype ).
                * 'wikiLink':  Link to Wiki for a specific workOrderTask.
                * 'taskStatusId':  status for a specific workOrderTask ( active / inactive ).
                * 'taskContractStatus':  status for a specific workOrderTask ( inactive on arrow down ).
                * 'quantity':  quantity for a specific workOrderTask, default 0.
                * 'hoursTime':  time in minutes for a specific workOrderTask, available in workOrderTaskTime.
                * 'hasChildren': identifies if a element/ woT has childrens.
    */


    function getContractData($workOrderId, &$errCode=false) {
        global $logger;
        $db=DB::getInstance();
        $errCode=false;

        $out=[];
        $parents=[];
        $elements=[];


        $query = "SELECT elementId as id, elementName as Title, null as parentId,
        null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, '' as billingDescription, null as cost, null as quantity,
        null as totCost, null as taskTypeId, '' as icon, '' as wikiLink, null as taskStatusId, 0 as taskContractStatus, null as hoursTime,
        elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren
        from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".intval($workOrderId)." and (invoiceId is null or invoiceId=0))
        UNION ALL
        SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId,
        w.extraDescription as extraDescription, w.billingDescription as billingDescription, w.cost as cost, w.quantity as quantity, w.totCost as totCost,
        w.taskTypeId as taskTypeId, t.icon as icon, t.wikiLink as wikiLink, w.taskStatusId as taskStatusId, 9 as taskContractStatus, wt.tiiHrs as hoursTime,
        getElement(w.workOrderTaskId),
        e.elementName, false as Expanded, false as hasChildren
        from workOrderTask w
        LEFT JOIN task t on w.taskId=t.taskId


        LEFT JOIN (

            SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
            FROM workOrderTaskTime wtH
            GROUP BY wtH.workOrderTaskId
            ) AS wt
            on wt.workOrderTaskId=w.workOrderTaskId


        LEFT JOIN element e on w.parentTaskId=e.elementId
        WHERE w.workOrderId=".intval($workOrderId)." AND w.parentTaskId is not null and (w.invoiceId is null or w.invoiceId=0) ORDER BY FIELD(elementName, 'General') DESC";
        $result=$db->query($query);

        if (!$result) {
            $logger->errorDB('637801897192353045', "getContractData() Hard DB error", $db);
            $errCode = true;
        } else {
            while( $row=$result->fetch_assoc() ) {
                $out[]=$row;
                if( $row['parentId']!=null ) {
                    $parents[$row['parentId']]=1;
                }
                if( $row['taskId']==null)    {
                    $elements[$row['elementId']] = $row['elementName'] ;
                }

            }
        }


        for( $i=0; $i<count($out); $i++ ) {

            if( $out[$i]['Expanded'] == 1 )
            {
                $out[$i]['Expanded'] = true;
            } else {
                $out[$i]['Expanded'] = false;
            }

            if($out[$i]['hasChildren'] == 1)
            {
                $out[$i]['hasChildren'] = true;
            } else {
                $out[$i]['hasChildren'] = false;
            }

            if( isset($parents[$out[$i]['id']]) ) {
                $out[$i]['hasChildren'] = true;

            }
            if ( $out[$i]['elementName'] == null ) {
                $out[$i]['elementName']=(isset($elements[$out[$i]['elementId']])?$elements[$out[$i]['elementId']]:"");
            }



        }

        return $out;
    }

    function getOutOfContractData($workOrderId, &$errCode=false) {
        global $logger;
        $db=DB::getInstance();
        $errCode=false;

        $out=[];
        $parents=[];
        $elements=[];


        $query = "SELECT elementId as id, elementName as Title, null as parentId,
        null as taskId, null as parentTaskId, null as workOrderTaskId, '' as extraDescription, '' as billingDescription, null as cost, null as quantity,
        null as totCost, null as taskTypeId, '' as icon, '' as wikiLink, null as taskStatusId, 0 as taskContractStatus, null as hoursTime,
        elementId as elementId, elementName as elementName, false as Expanded, true as hasChildren, 0 as internalTaskStatus, 0 as invoiceId
        from element where elementId in (SELECT parentTaskId as elementId FROM workOrderTask WHERE workOrderId=".intval($workOrderId).")
        UNION ALL
        SELECT w.workOrderTaskId as id, t.description as Title, w.parentTaskId as parentId, w.taskId as taskId, w.parentTaskId as parentTaskId, w.workOrderTaskId as workOrderTaskId,
        w.extraDescription as extraDescription, w.billingDescription as billingDescription, w.cost as cost, w.quantity as quantity, w.totCost as totCost,
        w.taskTypeId as taskTypeId, t.icon as icon, t.wikiLink as wikiLink, w.taskStatusId as taskStatusId,  9 as taskContractStatus, wt.tiiHrs as hoursTime,
        getElement(w.workOrderTaskId),
        e.elementName, false as Expanded, false as hasChildren, w.internalTaskStatus, w.invoiceId
        from workOrderTask w
        LEFT JOIN task t on w.taskId=t.taskId


        LEFT JOIN (

            SELECT wtH.workOrderTaskId, SUM(wtH.minutes) as tiiHrs
            FROM workOrderTaskTime wtH
            GROUP BY wtH.workOrderTaskId
            ) AS wt
            on wt.workOrderTaskId=w.workOrderTaskId


        LEFT JOIN element e on w.parentTaskId=e.elementId
        WHERE w.workOrderId=".intval($workOrderId)." AND w.parentTaskId is not null ORDER BY FIELD(elementName, 'General') DESC";

        $result=$db->query($query);

        if (!$result) {
            $logger->errorDB('637801897192353045', "getContractData() Hard DB error", $db);
            $errCode = true;
        } else {
            while( $row=$result->fetch_assoc() ) {
                $out[]=$row;
                if( $row['parentId']!=null ) {
                    $parents[$row['parentId']]=1;
                }
                if( $row['taskId']==null)    {
                    $elements[$row['elementId']] = $row['elementName'] ;
                }

            }
        }

        $out1=[];
        for( $i=0; $i<count($out); $i++ ) {
            if( $out[$i]['Expanded'] == 1 )
            {
                $out[$i]['Expanded'] = true;
            } else {
                $out[$i]['Expanded'] = false;
            }

            if($out[$i]['hasChildren'] == 1)
            {
                $out[$i]['hasChildren'] = true;
            } else {
                $out[$i]['hasChildren'] = false;
            }

            if( isset($parents[$out[$i]['id']]) ) {
                $out[$i]['hasChildren'] = true;

            }
            if ( $out[$i]['elementName'] == null ) {
                $out[$i]['elementName']=(isset($elements[$out[$i]['elementId']])?$elements[$out[$i]['elementId']]:"");
            }
        }
        for( $i=0; $i<count($out); $i++ ) {

            if($out[$i]['internalTaskStatus']==5 && !$out[$i]['invoiceId'])
            {
                $found_key = array_search($out[$i]['id'], array_column($out1, 'id'));
                if(!($found_key!==false)){
                    $out1[]=$out[$i];
                    $ffff = array_search($out[$i]['parentId'], array_column($out1, 'id'));
                    if(!($ffff!==false)){
                        $found_parent = array_search($out[$i]['parentId'], array_column($out, 'id'));
                        $out1[]=$out[$found_parent];
                    }
                }
            }
        }



        return $out1;
    }

?>
<?php
/* Crumbs.class.php

EXECUTIVE SUMMARY: Tracks the workOrders, jobs, etc. the user has visited.
This class is overwhelmingly in the constructor.

* Public functions:
** __construct($obj = null, $user)
** deleteCrumbs()
** getNewCrumbs() 
** getCrumbs()  >>>00007 This function should go away, always returns undefined.
** getLinears() >>>00007 This function should go away, always returns undefined.
*/

class Crumbs{	
    private $crumbs;  // >>>00007 JM 2019-02-20 This is no longer used in any relevant way.
    private $linears;  // >>>00007 JM 2019-02-20 This is no longer used in any relevant way.
    
    // private $newCrumbs is an associative array, initially direct from the DB table Person,
    // column crumbs, row for this user; it is serialized in the DB as a JSON blob. 
    // Possible elements of the associative array include 
    // * 'Search' 
    // * any of a number of class names from classes that each have a method getCrumbId, e.g.:
    // ** 'Company'
    // ** 'CompanyPerson'
    // ** 'WorkOrder' 
    // ** (that list is not exhaustive, and is likely to change over time)
    // Each of these elements, including $this->crumbs['Search'] is an array of 
    //  associative arrays with members 'id' and 'time'. 
    // Each $this->crumbs['Search'][$i]['id'] is a string (>>>00001 JM: I believe);
    //  for the others, $this->crumbs[''objectType'][$i]['id'] is the return of 
    //  getCrumbId, typically (probably always) a primary key for an object of 
    //  the specified type.
    // The constructor modifies $this->crumbs and writes it back to the database.
    private $newCrumbs;
    
    private $user;
    
    /* Constructor is explicitly passed an optional object $obj and a User object  
       for the current user $user, and also looks at CGI inputs $_REQUEST['act'] 
       and $_REQUEST['q']. Above all, it sets $this->newCrumbs.  See discusson
       of private $newCrumbs above.
       
       The constructor modifies $this->crumbs and writes it back to the database.
       
       So, although this is a constructor, it's really mostly a database update.
    */       
    public function __construct($obj = null, $user) {
        if (is_object($user)) {
            $crumbs = array();				
            $this->user = $user;
            
            if (get_class($user) == 'User') {				
                $db = DB::getInstance();	
                $row = false;
    
                $query = "SELECT crumbs ";
                $query .= "FROM " . DB__NEW_DATABASE . ".person ";
                $query .= "WHERE personId = " . intval($user->getUserId()) . ";";
                $result = $db->query($query);
                    
                if ($result)  {
                    if ($result->num_rows > 0) {						
                        $row = $result->fetch_assoc();						
                    } else {
                        $this->logger->errorDb('1594220169', "Invalid personId", $this->db);
                    }
                } else {
                    $this->logger->errorDb('1594220184', "Hard DB error", $this->db);
                }
                    
                if ($row) {						
                    $c = unserialize($row['crumbs']);
                    if (is_array($c)) {
                        $crumbs = $c;							
                    }
                } // else error already logged				
            } else {
                // Crumbs object should be created only when we have a logged-in user, so this is an error
                $this->logger->error2('1594220316', "Crumbs constructor not passed a valid user object: " . print_r($user, true));
            }
            
            $act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
            $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';
            $q = trim($q);	
            
            // If the action was 'search' and the associated search string is nonempty
            if (($act == 'search') && strlen($q)) {
                // Save off the previous $crumbs['Search'] (an array of associative arrays, 
                // each containing an ('id', 'time') pair, where 'id' is a search query string).
                $these = array();				
                if (isset($crumbs['Search'])) {				
                    if (is_array($crumbs['Search'])) {							
                        $these = $crumbs['Search'];						
                    }						
                }
                
                // If any element in $these matches this search query string, remove it
                $exists = false;
                $key = false;
                
                foreach ($these as $tkey => $t) {				
                    if ($q == $t['id']) {
                        $exists = true;
                        $key = $tkey;
                    }				
                }
                
                if ($exists) {
                    unset ($these[$key]);
                }
                
                // Add a new element to the end of $these for the current search...
                $these[] = array('id' => $q, 'time' => time());				
                
                // ...then reverse $these and limit it to 75 elements.  
                $reversed = array_reverse($these);
                $reversed = array_slice($reversed,0,75);
                $these = $reversed;
                
                // So the saved array here has the latest search at the front, but on
                // each successive call the order of the rest of the array keeps flipping
                // end-to-end, which is a bit weird.
                $crumbs['Search'] = $these;
            } // END the search case
            
            // If $obj passed in to the constructor is an object (not null) then we perform 
            // an analogous operation on $this->crumbs[''objectType'].
            if (is_object($obj)) {
                $these = array();
                if (isset($crumbs[get_class($obj)])) {
                    if (is_array($crumbs[get_class($obj)])) {					
                        $these = $crumbs[get_class($obj)];				
                    }					
                }
                
                $exists = false;
                $key = false;
                foreach ($these as $tkey => $t) {					
                    if (method_exists ($obj , 'getCrumbId')) {					
                        if ($obj->getCrumbId() == $t['id']) {
                            $exists = true;
                            $key = $tkey;
                        }					
                    }					
                }
                
                if ($exists) {
                    unset ($these[$key]);
                }
                
                if (method_exists($obj , 'getCrumbId')) {					
                    $these[] = array('id' => $obj->getCrumbId(), 'time' => time());					
                }					
                
                $reversed = array_reverse($these);
                $reversed = array_slice($reversed,0,75);
                $these = $reversed;				

                $crumbs[get_class($obj)] = $these;
            }

            $query = "UPDATE " . DB__NEW_DATABASE . ".person SET ";
            $query .= "crumbs = '" . $db->real_escape_string(serialize($crumbs)) . "' ";
            $query .= "WHERE personId = " . intval($user->getUserId()) . ";";

            $result = $db->query($query);
            if (!$result)  {
                $this->logger->errorDb('1594220820', "Hard DB error", $this->db);
            }

            $this->newCrumbs = $crumbs;
        }
    } // END function _construct
    
    // Clear crumbs for the relevant user
    public function deleteCrumbs() {		
        $db = DB::getInstance();
        
        $query = "UPDATE " . DB__NEW_DATABASE . ".person SET ";
        $query .= "crumbs = '" . $db->real_escape_string(serialize(array())) . "' ";
        $query .= "WHERE personId = " . intval($this->user->getUserId()) . ";";
        
        $result = $db->query($query);
        if (!$result)  {
            $this->logger->errorDb('1594220871', "Hard DB error", $this->db);
        }
        
        $this->newCrumbs = array();
    }	
    
    // RETURN the current crumbs for this user. See documentation of private $newCrumbs above.
    public function getNewCrumbs() {		
        return $this->newCrumbs;		
    }

    // >>>00007 This function should go away, always returns undefined.
    public function getCrumbs() {		
        return $this->crumbs;		
    }	
    
    // >>>00007 This function should go away, always returns undefined.
    public function getLinears() {		
        return $this->linears;	
    }
}

?>
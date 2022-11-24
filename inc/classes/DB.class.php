<?php 
/* inc/classes/DB.class.php
   Extends MySQLi, documented at http://php.net/manual/en/book.mysqli.php. 
   Protects construct, clone, and wakeup to limit their use. 
   Implements public static function getInstance, and keeps a static $instance around. 
    Effectively makes this a singleton accessed by that static method: will be constructed 
    if needed, otherwise reused. 
   Also enforces that the DB uses UTF-8 as its character set; see https://en.wikipedia.org/wiki/UTF-8.
   
   Public functions:
   * public static function getInstance()
   * all public functions of MySQLi EXCEPT:
     * construct
     * clone
     * wakeup
*/

// As part of the singleton strategy (mentioned above), let's make it very easy to see if this ever gets constructed in a way
//  we didn't intend, even if there is no way to really prevent such a call (as explained in notes on constructor, below)

define('DB_CLASS_SIGNATURE', 'arbitrary_9879');

class DB extends MySQLi {

	private static $instance;
	private $local_logger;

	/* Original Query saved for troubleshooting */
	private $localQuery;
	
	public static function getInstance() {
		if (null === static::$instance) {			
			static::$instance = new static(DB_CLASS_SIGNATURE); // This is the only place a DB object should actually be created.
			
			/* OLD CODE REMOVED 2019-03-26 JM
			static::$instance->connect(DB__HOST, DB__USER, DB__PASS, DB__DATABASE); // >>>00001 JM 2019-03-26: I see no point
			                                                        // to making sure we can connect to the old (pre-Martin) DB,
			*/
			// BEGIN NEW CODE 2019-03-26 JM
			static::$instance->connect(DB__HOST, DB__USER, DB__PASS, DB__NEW_DATABASE);
			// END NEW CODE 2019-03-26 JM
			if (mysqli_connect_error()) {
				static::$instance->local_logger->fatal2(637171386808375210,
				'Connect Error (' . mysqli_connect_errno() . ') '
				. mysqli_connect_error()."\n".
				print_r(debug_backtrace(), true));
				die('Connect Error (' . mysqli_connect_errno() . ') '
						. mysqli_connect_error());
			}
			static::$instance->query("SET NAMES 'utf8'");
			
		}
	
		return static::$instance;
	}
	/* JM 2019-05-15: REPLACING the following.
	   Inheriting class cannot have more restrictive 'protected' vs. MySQLi 'public'
	   >>>00014 No idea how we got away with this on some systems (including production) but we did.
	   May need to think this through further, because this no longer prevents this being called
	   (though it does make it a no-op if it is).
	protected function __construct() {
	}
	*/
	// BEGIN NEW CODE JM 2019-05-15
	public function __construct($signature) {
	    // BEGIN ADDED BY SOMEONE AT RDC circa 2020-02
	    global $logger;
		// $local_logger = $logger; // >>>00001 JM 2020-03-13. This makes no sense at all: all this does is assign to a local variable of the constructor.
		$this->local_logger = $logger; // JM 2020-03-13; I assume this is what was intended.
		// END ADDED BY SOMEONE AT RDC circa 2020-02
	    // BEGIN ADDED JM 2020-03-13
	    if ($signature != DB_CLASS_SIGNATURE) {
	        $this->local_logger->warning2('1584128496', "Looks like an unintended creation of DB object.");
	    }
	    // END ADDED JM 2020-03-13
	}
	// END NEW CODE JM 2019-05-15
	
	private function __clone(){

	}	
	
	private function __wakeup(){

	}

	/*		
		returns the original query for logging 
	*/
	public function getQuery() {
		return $this->localQuery;
	}

	/*
		rewrite original query method from mysqli in order to save the original query
	*/

	public function query($query, $resultmode = NULL){

		$this->localQuery=$query;

		return parent::query($query);

	}
	
}	
?>
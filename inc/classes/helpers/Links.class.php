<?php
/* Links.class.php
   A very simple class to generate links for the main pages associated with certain objects.
   As of 2019-03 has only one method, for Jobs, but in principle could have others.
   >>>00007 on the other hand, it looks like this is no longer used, and might best go away. - JM 2019-03-07

   * public methods
   * __construct()
   * jobLink($job)
*/


class Links {	
	public function __construct() {
	}

	// INPUT $job: Job object
	// RETURNs a link to the main table for the job.
	public function jobLink($job) {		
		return REQUEST_SCHEME . '://' . HTTP_HOST . '/jobs/' . rawurlencode($job->getRwName());		
	}
}
?>
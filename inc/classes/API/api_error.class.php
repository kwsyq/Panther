<?php
/* api_error.class.php

    EXECUTIVE SUMMARY:
    Pretty trivial class. The constructor sets up a place to store error messages, 
    which in practice are simple strings.
    
    * Public functions:
    ** setError($str)
    ** getErrors()
    */

class api_error {
	private $errors;
	
	function __construct() {	
		$this->errors = array();		
	}

	// INPUT $str: error message.
	// Pushes $str onto private array $errors.
	public function setError($str) {		
		$this->errors[] = $str;		
	}
	
	// RETURNs contents of private array $errors.
	public function getErrors() {		
		return $this->errors;		
	}	
}

?>
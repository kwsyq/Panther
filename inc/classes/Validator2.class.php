<?php
/*
 *
 * inc/classes/Validator2.class.php 
 *
 * Validation Class
 *
 * Validates input against certain criteria
 *
 * Extends the valitron class made available by vlucas.
 * The standard Class documentation url is https://github.com/vlucas/valitron
 *
 * We add the following new methods:
 *
 * validateDivideNumber($field, $value, $params) - which validates whether the $value parameter is multiple of $params
 * validateOptionalDate($field, $value, $params) - which validates whether the $value is a valid date or an empty string
 * validateFormat($field, $value, $accept) - which validates whether the $value is a valid format string.
 * validateFormatRight($field, $value, $accept) - validates in deep (complex vlidation) whether the $value is a valid format string.
 * validateUrlEx($field, $value) - valid URL by regexp even if it does not start with http(s)://
 * validateLongitude($field, $value) - validates for format a given longitude $value 
 * validateLatitude($field, $value) - validates for format a given latitude $value
 * static function primary_validation() - initial validation for DB, customer, customerId
 * getErrorJson() which returns an array with status, info and error keys 
 */

require_once 'Validator.class.php';

class Validator2 extends Validator {
    /**
     * @var string
     */

    /* >>>00007 Someone at RDC wrote (unsigned) "The messages variable will be deleted after release". I (JM 2020-03-16) asssume
       "The messages variable" means $_ruleMessages and "after release" means "after release 2020-2." Makes sense, because it looks like
       ruleMessages is not referenced in this file, and it appears to be an exact duplicate of the same property in the parent class.
       So please kill this as soon as we split off release 2020-2*/
    protected static $_ruleMessages = array(
            'required'       => "is required",
            'equals'         => "must be the same as '%s'",
            'different'      => "must be different than '%s'",
            'accepted'       => "must be accepted",
            'numeric'        => "must be numeric",
            'integer'        => "must be an integer",
            'length'         => "must be %d characters long",
            'min'            => "must be at least %s",
            'max'            => "must be no more than %s",
            'listContains'   => "contains invalid value",
            'in'             => "contains invalid value",
            'notIn'          => "contains invalid value",
            'ip'             => "is not a valid IP address",
            'ipv4'           => "is not a valid IPv4 address",
            'ipv6'           => "is not a valid IPv6 address",
            'emailTrim'      => "is not a valid email address",
            'url'            => "is not a valid URL",
            'urlActive'      => "must be an active domain",
            'alpha'          => "must contain only letters a-z",
            'alphaNum'       => "must contain only letters a-z and/or numbers 0-9",
            'slug'           => "must contain only letters a-z, numbers 0-9, dashes and underscores",
            'regex'          => "contains invalid characters",
            'date'           => "is not a valid date",
            'dateFormat'     => "must be date with format '%s'",
            'dateBefore'     => "must be date before '%s'",
            'dateAfter'      => "must be date after '%s'",
            'contains'       => "must contain %s",
            'boolean'        => "must be a boolean",
            'lengthBetween'  => "must be between %d and %d characters",
            'creditCard'     => "must be a valid credit card number",
            'lengthMin'      => "must be at least %d characters long",
            'lengthMax'      => "must not exceed %d characters",
            'instanceOf'     => "must be an instance of '%s'",
            'containsUnique' => "must contain unique elements only",
            'requiredWith'   => "is required",
            'requiredWithout'=> "is required",
            'subset'         => "contains an item that is not in the list",
            'arrayHasKeys'   => "does not contain all required keys",
            'divideNumber'   => "must be multiple of %s",  
            'optionalDate'   => "if filled must be in a date format YYYY-mm-dd",
            'format'         => "contains invalid characters",
            'formatRight'    => "not a valid input",
            'urlEx'          => "is not a valid URL",
            'Latitude'       => "not a valid format",
            'Longitude'      => "not a valid format"

        );
    /**
     * 
     * @var array
     */
    public function __construct($data = array(), $fields = array(), $lang = null, $langDir = null) {
        
        parent::__construct($data, $fields, $lang, $langDir);

    } 
    /**
     * Get/set language to use for validation messages
     *
     * @param  string $lang
     * @return string
     */
    public static function lang($lang = null) {
        if ($lang !== null) {
            static::$_lang = $lang;
        }
        return static::$_lang ?: 'en';
    }

    /**
     * Get/set language file path >>>00001 JM: what is this used for?
     *
     * @param  string $dir
     * @return string
     */
    public static function langDir($dir = null) {
        if ($dir !== null) {
            static::$_langDir = $dir;
        }
        return static::$_langDir ?: dirname(dirname(__DIR__)) . '/lang';
    }

    /**
     * Validates whether the $value parameter is multiple of $params
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @return bool
     */
    protected function validateDivideNumber($field, $value, $params) {
        if (!is_numeric($value)) {
            return false;
        } else {
            return !($value % $params[0]);
        }
    }


    /**
     * Validate if the input is a correct date, or if not, an empty string
     *
     * @param $field
     * @param $value
     * @param $params
     * @return bool
     */
    protected function validateOptionalDate($field, $value, $params) {
        $isDate=true;

        if($value=="false" || $value=="" || !isset($value)) {
            return $isDate;
        } else {
            $isDate = false;
            if ($value instanceof \DateTime) {
                $isDate = true;
            } else {
                $isDate = strtotime($value) !== false;
            }
            return $isDate;
        }
    }

   /**
     * Validate that a field is a valid URL by regexp even if it does not start with http(s)://
     *
     * @param  string $field
     * @param  mixed $value
     * @return bool
     */
    protected function validateUrlEx($field, $value)
    {
        $result = preg_match_all('#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si', $value, $result);

        if (!$result)
        {
            return false;
        }

        return true;
    }
	
    /**
     * The method returns a standard array containing the status of the validation and the error array in case 
     * there is any. Many of our functions that are called by AJAX are written in a manner where this will 
     * be all or part of what they need to return on failure.
     *
     * @return array()
     */    
    public function getErrorJson(){
        $data = Array();
        $data['status']='success';
        $data['error']="";
        
        if($this->errors()){
            $data['status']='fail';
            $data['info']=$this->errors();
            foreach($this->errors() as $key=>$value){
                $data['error'].=$key."=>".$value[0]."\n";
            }
        }

        return json_encode($data);
    }

    /** 
    * this will render all error messages for a named field in nice html string.
    * Example: add this after the field 'username': <?=($v->render_errors('username'))?>
    * 
    * @param string $field name of the field to show errors
    * @return string
    */
    public function render_errors($field)
    {
        //$this->errors($field) returns an array of error messages for particular field.
        if ($this->errors($field)) {
            return "<small class='text-danger'>" . implode( "<br>", $this->errors($field)) . "</small>" ;
        }
        return "";
    }

    /**
    * Validate that a $value is a clean String by syntax. 
    *
    * @param  string $field
    * @param  mixed $value
    * @param  array $accept. List of special characters we accept.
    * @return bool
    */
    protected function validateFormat($field, $value, $accept=null)
    {

        //$accept is an array. List of special characters we accept.
        $params =  "_$&+,:;=?@#|<>.^*()%!'-0123456789"; // List of special characters
        $params = str_split($params);

        $new = array_diff($params, $accept[0]); // New array with special characters we don't want.

        $value = str_split($value);
  
        $result = !empty(array_intersect($new, $value)); // Check if we still have unwanted special characters in $value.
        
        if($result == true) {
            return false;
        } else {
            return true;
        }

    }

        /**
    * Validate that a $value is a valid format of a string!
    * Accept a list of special characters. Defined in client code.
    * String can not begin or end with accepted special characters.
    * String can not have ONLY accepted special characters (no normal characters).
    * String can not contain more "accepted special characters" than normal characters.
    * Check if the String have different consecutive special characters.
    * Check if the String have the same consecutive special characters.
    *
    * @param  string $field
    * @param  mixed $value
    * @param  array $accept. List of special characters we accept.
    * @return bool
    */
    protected function validateFormatRight($field, $value, $accept=null)
    {
        $first = $last = "";
        //$accept is an array. List of special characters we accept.
        $params = "$&+,:;=?@#|<>.^*()%!_'-0123456789"; // List of special characters
        $params = str_split($params);

        //$new = array_diff($params, $accept[0]); // New array with special characters we don't want.

        $value = str_split($value);

        $first = array_shift($value); //first character of given input
        $last = array_pop($value); //last character of given input


        $onlyChar = array_diff($value, $accept[0]); //if empty contains only elements we accept. Not good!
        $valueWithoutSpecial = count($onlyChar); //count input without special characters.
        

        if(in_array($first, $params)){ //if first of given input is a special character. Not good!
            return false;
        }

        if(in_array($last, $params)){ //if last of given input is a special character. Not good!
            return false;
        }

        $s = 0; 
        foreach($value as $charValue){
            if (in_array($charValue, $params)) {
                $s++; //how many times a special character we accept appears in input!
            }
        }

        foreach($value as $charValue){ //Loop input.
            if (in_array($charValue, $params)) { //check if any character is in the special character list.
                $reps = [];
                if (preg_match_all("/(.) (\\1\s?){1,}/", implode(" ", $value), $charValue)) { //check if we have consecutive special character.
                    $reps = array_combine($charValue[1], array_map(function($v){
                        return count(explode(' ', trim($v)));
                    }, $charValue[0]));
                }
            }
        }

        // We have same consecutive "accepted" special character. Not Good! 
        foreach($reps as $rep){
            if($rep > 1){ 
                return false;
            }   
        }

        // If the number of special characters we accept it's grater than normal characters! Not Good!
        if($valueWithoutSpecial < $s ){ 
            return false;
        }

        $value = implode("",$value); //string
        // Check if We have different consecutive special characters. Not Good!
        $specialNext = preg_match('/[#\'$%^@&*()+=\-\[\]\';,.\/{}|":<>?~\\\\]{2,}/', $value); 
        if($specialNext > 0){
            return false;
        }

        //if true! the input contains ONLY accepted characters! Not Good.
        if(empty($onlyChar)) { 
            return false;
        } else {
            return true;
        }
    }

    /**
     * Validate that a field is a valid e-mail address. Trim the value.
     *
     * @param  string $field
     * @param  mixed $value
     * @return bool
     */
    protected function validateEmailTrim($field, $value)
    {
        return filter_var(trim($value), \FILTER_VALIDATE_EMAIL) !== false;
    }
    

    /**
     * Validates a given latitude $value
     * @param string $field
     * @param float|int|string $value Latitude
     * @return bool `true` if $value is valid, `false` if not
    */
    protected function validateLatitude($field, $value) {
        return preg_match('/^[+-]?(([1-8]?[0-9])(\.[0-9]{1,8})?|90(\.0{1,8})?)$/', $value);

    }
  
    /**
     * Validates a given longitude $value
     * @param string $field
     * @param float|int|string $value Longitude
     * @return bool `true` if $value is valid, `false` if not
    */
    protected function validateLongitude($field, $value) {
        return preg_match('/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $value);
    }
  

    /**
    * this will return any error message for the initial validation required in most pages
    * it includes validation for DB, customer, customerId
    * Note!!: this will be DELETED in the near future as it is replaced by primary_validation() - George 2020-06-05
    */
    public function init_validation() 
    {
        return self::primary_validation();
    }

    /**
    * The method does the initial validation for DB, customer, customerId needed in most pages.
    * @return array($error, $errorId) where
    * string $error is the error message, empty string if no errors 
    * int $errorId - unique error ID, 0 if all ok.
    */
    public static function primary_validation()
    {
        global $customer;
        $error = '';
        $errorId = 0;
        $db = DB::getInstance();
        if (!$db) {
            $errorId = '637166055746657320';
            $error = 'cannot get DB instance.'; 
        }

        if(!$error) {
            if (!isset($customer) || !$customer) {
                // no customer, which would presumably mean either inc/config.php was missing, or the system was running in an unsupported environment.  
                $errorId = '637171403467381004';
                $error = 'cannot get customer.';
            } 
        }
        
        if(!$error) {
            $customerId = intval($customer->getCustomerId());
            if (!$customerId) {
                // customer value is 0 or not an integer; don't know if that's possible, but if it is we should certainly log it!
                // IF access.php is present at top of file that includes this, then that should already have validated customerId  
                //  and redirected if it were not present, but not all pages use access.php, because it restricts to logged-in users.
                $errorId = '637166056117448746';
                $error = 'cannot get customerId.';
            } 
        }

        return array($error, $errorId);
    }


    /**
     * Run validations and return boolean result
     *
     * @return bool
     */
    public function validate()
    {
        $set_to_break = false;
        foreach ($this->_validations as $v) {
            foreach ($v['fields'] as $field) {
                list($values, $multiple) = $this->getPart($this->_fields, explode('.', $field), false);

                // Don't validate if the field is not required and the value is empty and we don't have a conditionally required rule present on the field
                if (($this->hasRule('optional', $field) && isset($values)) 
                    || ($this->hasRule('requiredWith', $field) || $this->hasRule('requiredWithout', $field))) {
                    //Continue with execution below if statement
                } elseif (
                    $v['rule'] !== 'required' && !$this->hasRule('required', $field) &&
                    $v['rule'] !== 'accepted' &&
                    (!isset($values) || $values === '' || ($multiple && count($values) == 0))
                ) {
                    continue;
                }

                // Callback is user-specified or assumed method on class
                $errors = $this->getRules();
                if (isset($errors[$v['rule']])) {
                    $callback = $errors[$v['rule']];
                } else {
                    $callback = array($this, 'validate' . ucfirst($v['rule']));
                }

                if (!$multiple) {
                    $values = array($values);
                } else if (! $this->hasRule('required', $field)){
                    $values = array_filter($values);
                }

                $result = true;
                foreach ($values as $value) {
                    $result = $result && call_user_func($callback, $field, $value, $v['params'], $this->_fields);
                }
    
                
                $userInputValue = ""; //custom error message to get the input value. 
                if(is_array($value)) {
                    foreach($value as $val) {
                        $userInputValue = "Invalid value: {" . $val ."} =>" . $v['message'];
                    }
                } else {
                    $userInputValue = "Invalid value: {" . $value . "} =>" . $v['message'];
                }

                if (!$result) {
                    $this->error($field, $userInputValue, $v['params']);
                    if ($this->stop_on_first_fail) {
                        $set_to_break = true;
                        break;
                    }
                }
            }
            if ($set_to_break) {
                break;
            }
        }

        return count($this->errors()) === 0;
    }

}

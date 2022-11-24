<?php
/* inc/classes/ErrorCodes.php
*/

define ("APPLICATION_FATAL_ERROR",
 "Application fatal error! Contact your administrator to find more details in the Log.");

define("DB_ERR_THRESHOLD", -1000);
define("DB_CONNECT_ERR", -1001);
define("DB_EXECUTION_ERR", -1002);
define("DB_GENERAL_ERR", -1003);
define("DB_CORRUPTED_DB", -1004);

define("COMMON_ERR_THRESHOLD", -2000);
define("PATTERN_MISMATCH", -2001);
define("EMAIL_PATTERN_MISMATCH", -2002);
define("INT_PATTERN_MISMATCH", -2003);
define("FLOAT_PATTERN_MISMATCH", -2004);
define("IP_PATTERN_MISMATCH", -2005);
define("EMPTY_VALUE", -2006);
define("NEGATIVE_VALUE", -2007);
define("ZERO_VALUE", -2008);
define("NULL_VALUE", -2009);
define("OUT_OF_RANGE_VALUE", -2010);
define("DB_ROW_ALREADY_EXIST_ERR", -2011);
define("NOT_AVAILABLE_VALUE", -2012);

function isDbError($errCode)
{
    return $errCode < DB_ERR_THRESHOLD && $errCode > COMMON_ERR_THRESHOLD;
}


?>
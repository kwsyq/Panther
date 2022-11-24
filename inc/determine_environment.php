<?php
/* inc/determine_environment.php */ 

// What environment are we in (dev/test/preproduction/production)? 
//
// This testing is in  a file of its own JM 2019-02-04 because this is often needed
//  to allow certain manipulation *before* we include config.php
// >>>00004 Current code is specific to ssseng. In this case, that may be OK 
//    (since dev environment might remain exclusively 'devssseng'); remove
//    this comment if addressed.
// // >>>00003 Work in progress 2019-02-18 JM. This will need MUCH refinement.
//  We need to work in any new environments that use this, both here and in the
//  places where it is called.
// We may want to have the basis of the decision, come from a text file at a 
//  predictable location.  
// In theory, it could come from a DB, but this should stay a lightweight function to be 
//  called at the very beginning of things, so having to open (or even be aware of)
//  a DB seems a bit heavyhanded. If it ultimately comes from a DB, we should have
//  a cron job or such to extract the text file from the DB.

define ("ENVIRONMENT_DEV_MARTINS_SERVER", 0);
define ("ENVIRONMENT_DEV", 1); // NOT USED as of 2019-05-22. This is the Zwinny server we used briefly; should no longer be referenced in the code.
define ("ENVIRONMENT_TEST", 2);
define ("ENVIRONMENT_PREPRODUCTION", 3);
define ("ENVIRONMENT_PRODUCTION", 4);
define ("ENVIRONMENT_UNAUTHORIZED", 5);
define ("ENVIRONMENT_DEV2", 6);
define ("ENVIRONMENT_RC", 7); // release candidate
define ("ENVIRONMENT_RC2", 8); // release candidate 2 (we ping-pong these)
define ("ENVIRONMENT_RTC", 9); // Raitec
define ("ENVIRONMENT_QA", 10); // QA environment (qa.ssseng.com)

// returns one of the above values to tell us what environment we are running in.
// >>>00003 We need better tests; this was Martin's test (though reworked)
function environment() {
    $hostname = trim(`hostname`);
    if ($hostname == 'devssseng') {
        return ENVIRONMENT_DEV_MARTINS_SERVER;
    } else if ($hostname == 'dev2.ssseng.com') {
        return ENVIRONMENT_DEV2;
    } else if ($hostname == 'qa.ssseng.com') { 
        return ENVIRONMENT_QA;
    } else if ($hostname == 'rc.ssseng.com') {
        return ENVIRONMENT_RC;
    } else if ($hostname == 'rc2.ssseng.com') {
        return ENVIRONMENT_RC2;
    } else if ($hostname == 'sds-linux') {
        return ENVIRONMENT_RTC;
    } else {
        return ENVIRONMENT_PRODUCTION;
    }
}

?>
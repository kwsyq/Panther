<?php 
/*  _admin/employee/menu.php

    EXECUTIVE SUMMARY: PAGE Show info about all employees for this customer, offer certain actions.
    
    No primary input, because it shows all employees for this customer (as of 2019-05, always SSS).
    
    JM 2019-07-08: I've completely replaced Martin's (broken) approach to adding an employee.
    
*/
include '../../inc/config.php';

?>
<html>
<head>
    <style>    
        .autocomplete-wrapper { margin: 2px auto 2px; max-width: 600px; }
        .autocomplete-wrapper label { display: block; margin-bottom: .75em; color: #3f4e5e; font-size: 1.25em; }
        .autocomplete-wrapper .text-field { padding: 0 0px; width: 100%; height: 40px; border: 1px solid #CBD3DD; font-size: 1.125em; }
        .autocomplete-wrapper ::-webkit-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
        .autocomplete-wrapper :-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
        .autocomplete-wrapper ::-moz-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
        .autocomplete-wrapper :-ms-input-placeholder { color: #CBD3DD; font-style: italic; font-size: 18px; }
        
        .autocomplete-suggestions { overflow: auto; border: 1px solid #CBD3DD; background: #FFF; }
        
        .autocomplete-suggestion { overflow: hidden; padding: 5px 15px; white-space: nowrap; }
        
        .autocomplete-selected { background: #F0F0F0; }
        
        .autocomplete-suggestions strong { color: #029cca; font-weight: normal; }    
    </style>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">	
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>	
    <script src="/js/jquery.autocomplete.min.js"></script>	

</head>
<body bgcolor="#cccccc">
    <?php 
    echo '<table border="0" cellspacing="0" cellpadding="3">';
        echo '<tr>';
            // A link labeled "ADMINISTRATORS" to show info about administrators; 
            // added 2019-06-13 JM 
            echo '<td><a target="employee" href="administrators.php">ADMINISTRATORS</a></td>';
        echo '</tr>';
        echo '<tr>';
            // A link labeled "ADMINISTRATORS" to show info about administrators; 
            // added 2019-06-13 JM 
            echo '<td><a target="employee" href="addemployee.php">ADD EMPLOYEE</a></td>';
        echo '</tr>';
        echo '<tr>';
            // In theory, a link labeled "ALL" to show info about all employees; 
            echo '<td><a target="employee" href="employee.php?personId=all">ALL</a></td>';
        echo '</tr>';
        echo '<tr>';
            // Manage stamps 
            echo '<td><a target="employee" href="stamps.php">STAMPS</a></td>';
        echo '</tr>';
        
        echo '<tr>';
            echo '<td>&nbsp;</td>';
        echo '</tr>';
    
        // >>>00012 unnecessary multiplexing of $employees, should just have $es = $customer->getEmployees(); 
        $employees = $customer->getEmployees();
        $es = $employees;
        
        $employees = array();  
        $hired = array();
        $fired = array();     // >>>00012 $fired is a poor choice of variable names, most departures from a company are not firings
        
        // At this point $es is an array of "extended" User objects for these users, ordered by (lastName, firstName); 
        //  "extended" because we play a bit fast and loose with the User objects, adding some properties that 
        //  are not normally part of that class: 
        //  * legacyInitials (perfectly current as of 2019 despite its name, and no plans to remove it)
        //  * terminationDate (which is a distant future date for current employees)
        //  * employeeId
        //  * workerId
        //  * smsPerm - >>>00001 some sort of permissions thing for SMS messages, not well understood 2019-02-20 JM        
        foreach ($es as $e) {
            $term_date = new DateTime($e->terminationDate);
            $current_date = new DateTime();
        
            if ($current_date > $term_date) {
                $fired[] = $e;
            } else {
                $hired[] = $e;
            }
        
        }        
        /* At this point $hired and $fired are each an array of "extended" User objects for these users, ordered by (lastName, firstName);
            $hired lists current employees, $fired lists past employees */
            
        foreach ($hired as $h) {
            $employees[] = $h;
        }
        foreach ($fired as $f) {
            $employees[] = $f;
        }        
        /* At this point $employees is the concatenation of array $hired and array $fired. So it's all employees, with current employees first. */        
        
        foreach ($employees as $user) {
            $term_date = new DateTime($user->terminationDate);
            $current_date = new DateTime();
        
            $bgcolor = "";
            
            if ($current_date > $term_date) {
                // Past employee, different background color
                $bgcolor = "#cc9999";
            } else {
                $bgcolor = "#99cc99";
            }
            echo '<tr bgcolor="' . $bgcolor . '">';
                // In theory, a link labeled with the employee's name, last name first to show more info about the employee; 
                echo '<td><a target="employee" href="employee.php?personId=' . intval($user->getUserId()) . '">' . 
                     $user->getLastName() . ' ' . $user->getFirstName() . '</a></td>';        
            echo '</tr>';        
        }
    echo '</table>';
    ?>

    <script>
        $('#autocomplete').devbridgeAutocomplete({
            serviceUrl: '/ajax/autocomplete_person.php?companyId=<?php echo $customer->getCompanyId(); ?>',
            onSelect: function (suggestion) {
                // if-clause ADDED 2020-04-08 as part of fixing http://bt.dev2.ssseng.com/view.php?id=105 (Person error on company page)
                // Not clear why this would fire with suggestion.data blank or zero, but it clearly did.
                if (suggestion.data) {
                    $("#personId").val(suggestion.data);                
                    $("#jobpersonform" ).submit();
                }
            },
            paramName:'q'
        });        
    </script>
</body>
</html>
<?php 
/*  ticketnew.php

    Auxiliary page for ticket.php, which is where it submits a form (setting 
    "act=addticket" and other associated values), instead of submitting to itself. 
    Similar in other respects to ticketedit.php but to create a new ticket.
    Uses autocomplete everywhere (except of course notes/message). The autocomplete 
    technique for Job here is very tricky. 
    
    NO INPUTS. >>>00007: Reference to $_REQUEST['ticketId'] is vestigial.
*/

include './inc/config.php';
include './inc/access.php';

$ticketId = isset($_REQUEST['ticketId']) ? intval($_REQUEST['ticketId']) : 0; // >>>00007: set but never used
$crumbs = new Crumbs(null, $user);

include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='New ticket - ".str_replace("'", "\'", CUSTOMER_NAME)."';\n</script>\n";

?>

<script src="/js/jquery.autocomplete.min.js"></script>	
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

<div id="container" class="clearfix">
    <div class="main-content">
        <h1>New Ticket</h1>
        <form id="companypersonform" name="companyperson" method="POST" action="/ticket.php">
            <input type="hidden" name="act" value="addticket">
            <input type="hidden" name="suggestJobId" id="suggestJobId" value="">		
            <input type="hidden" name="suggestFromId" id="suggestFromId" value="">		
            <input type="hidden" name="suggestToId" id="suggestToId" value="">
            <table border="1" cellpadding="0" cellspacing="0" width="900">
                <tr>
                    <td>
                        From
                    </td>
                    <td>
                        <div class="autocomplete-wrapper">
                            <input type="text" name="personName" id="autocompleteFrom" size="60"/>
                        </div>
                    </td>	
                </tr>
                <tr>	
                    <td>
                        To 
                    </td>
                    <td>
                        <div class="autocomplete-wrapper">
                            <input type="text" name="personNames" id="autocompleteFor" size="60"/>
                        </div>
                    </td>		
                </tr>
                <tr>	
                    <td>
                        Job
                    </td>
                    <td>
                        <table border="0" cellpadding="0" cellspacing="0">
                            <?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
                            <?php /* ?>
                            <tr>
                                <td>Name</td>
                                <td><input type="radio" name="jobtype" value="name" checked id="nameradio"></td>
                            </tr>
                            <tr>
                                <td>Number</td>
                                <td><input type="radio" name="jobtype" value="number" id="numberradio"></td>
                            </tr>
                            
                            <tr>
                                <td>Address</td>
                                <td><input type="radio" name="jobtype" value="name" id="addressradio"></td>
                            </tr>
                            <?php */ ?>
                            <?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
                            
                            <?php /* JM: quite a complicated mechanism here, see autocomplete-related code below to make sense of it. 
                                     >>>00006 the "jobSuggest" names do NOTHING; this would be clearer without them. */ ?>
                            <tr>
                                <td colspan="2">
                                    <div class="autocomplete-wrapper">
                                        <input type="text"  name="jobSuggest1" id="autocompleteJob1" size="60"/><br>
                                        <input type="hidden"  name="jobSuggest2" id="autocompleteJob2"/><br>
                                        <input type="hidden"  name="jobSuggest3" id="autocompleteJob3"/>
                                    </div>						
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <table border="0" cellpadding="2" cellspacing="1" width="100%">
                                        <tr>
                                            <td id="suggest1" width="300" align="center" bgcolor="#cccccc">Job Name</td>
                                            <td id="suggest2" width="300" align="center" bgcolor="#cccccc">Job Number</td>	
                                            <td id="suggest3" width="300" align="center" bgcolor="#cccccc">Location</td>																
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>		
                </tr>	
                
                <tr>	
                    <td>
                        Message
                    </td>
                    <td>
                        <textarea cols="50" rows="15" id="ticketMessage" name="ticketMessage"></textarea>
                    </td>		
                </tr>		
                
                <?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
                <?php /* ?>
                <tr>	
                    <td>
                        Status
                    </td>
                    <td>
                        <select name="ticketStatusId"><option value="0">-- status --</option>
                        <?php 
                        $db = DB::getInstance();
                        $query = " select * from " . DB__NEW_DATABASE . ".ticketStatus order by ticketStatusId ";
            
                        if ($result = $db->query($query)) {
                            if ($result->num_rows > 0){
                                while ($row = $result->fetch_assoc()){
        
                                    echo '<option value="' . intval($row['ticketStatusId']) . '">' . $row['ticketStatusName'] . '</option>';
                                
                                }
                            }
                        }
                                        
                        ?>
                        </select>
        
                    </td>		
                </tr>
                <?php */?>
                <?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>                
                <tr>
                    <td colspan="2"><input type="submit" id="addTicket" value="add ticket" border="0">
                </tr>
            </table>
        </form>
    
        <?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
        <?php /*?>
        <div id="container2">
            <div id="container1">
                <div id="col1" >
                    <!-- Column one start -->
                hjj
                    <!-- Column one end -->
                </div>
                <div id="col2">
                <div class="siteform">
                <!-- Column two start -->
                <!-- Column two end -->
                </div>
            </div>
        </div>
    </div>	
            
        <?php */?>
        <?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
    </div>
</div>

<script>

// "For" ("To") section autocompletes based on customerPerson;
// no action after autocomplete
$('#autocompleteFor').devbridgeAutocomplete({
    noCache:true,
    serviceUrl: '/ajax/autocomplete_customerperson.php',
    onSelect: function (suggestion) {
        <?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
        //$("#companyId").val(suggestion.data);        
        //$( "#companypersonform" ).submit();
        <?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
        $('#suggestToId').val(suggestion.data);
    },
    paramName:'q'
});

// "From"  section autocompletes based on customerPerson;
// no action after autocomplete
$('#autocompleteFrom').devbridgeAutocomplete({
    noCache:true,
    serviceUrl: '/ajax/autocomplete_person.php',
    onSelect: function (suggestion) {
        <?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
        //$("#companyId").val(suggestion.data);       
        //$( "#companypersonform" ).submit();
        <?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
        $('#suggestFromId').val(suggestion.data);
    },
    paramName:'q'
});

<?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
/*
var url = '/ajax/autocomplete_jobname.php';
$("input[name='jobtype']").click(function(){

        if ($('input[name=jobtype]:checked').val() == 'name'){

            url = '/ajax/autocomplete_jobname.php';
        }
        if ($('input[name=jobtype]:checked').val() == 'number'){

            url = '/ajax/autocomplete_jobnumber.php';
        }
        
        $('#autocompleteJob').devbridgeAutocomplete({
            appendTo: "#ffff",
            serviceUrl: url,
            onSelect: function (suggestion) {
            //$("#companyId").val(suggestion.data);
                
                //$( "#companypersonform" ).submit();
                
            },
                paramName:'q'

        });
    
});
*/
<?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>

// When user types in the INPUT text element with id="autocompleteJob1":
//  * after 0.6 seconds, copy to the hidden text element with id="autocompleteJob2", and
//    perform autocomplete on that (simulating a value change)
//  * after 1.2 seconds, do the same for id="autocompleteJob3"
// The result of this is that we first fill in matches for Job Name, 
//  then 0.6 seconds later for Job Number,
//  the 0.6 seconds later for Location.
$('#autocompleteJob1').on('input',function(e){
    setTimeout(function(){
         $('#autocompleteJob2').val($('#autocompleteJob1').val());
         $('#autocompleteJob2').autocomplete().onValueChange();        
        
<?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
//	     $('#autocompleteJob2').val($(this).val());
    //     $('#autocompleteJob2').autocomplete().onValueChange();
<?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>    

    }, 600);    

    setTimeout(function() {
        $('#autocompleteJob3').val($('#autocompleteJob1').val());
        $('#autocompleteJob3').autocomplete().onValueChange();

<?php /* [BEGIN COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>
//	     $('#autocompleteJob3').val($(this).val());
//	     $('#autocompleteJob3').autocomplete().onValueChange();
<?php /* [END COMMENTED OUT BY MARTIN BEFORE 2019]*/ ?>

    }, 1200);
});

// When focus leaves the INPUT text element with id="autocompleteJob1":
//  after 0.5 seconds, hide all the suggested matches.
// "focusout" here should be the same as "blur" because this has no child elements.
// NOTE that mouse-moving cursor without clicking does NOT cause a focusout, so
//  you can hover in the suggestions lists without this happening.
$('#autocompleteJob1').focusout(function(e) {
    setTimeout(function() {
        $('#autocompleteJob1').devbridgeAutocomplete().hide();
        $('#autocompleteJob2').devbridgeAutocomplete().hide();
        $('#autocompleteJob3').devbridgeAutocomplete().hide();
    }, 500);
});

// Interpret autocompleteJob1 value as a jobName for autocomplete,
//  use it to fill in suggested matches under "Job Name". If user
//  selects from the suggested matches, hide the oter two sets of suggested matches
//  and set hidden suggestJobId based on the data that came back from this.
$('#autocompleteJob1').devbridgeAutocomplete ({
    noCache:true,
    appendTo: "#suggest1",
    width:"280",
    height:"200",
    serviceUrl: '/ajax/autocomplete_jobname.php',
    onSelect: function (suggestion) {
        $('#autocompleteJob2').devbridgeAutocomplete().hide();
        $('#autocompleteJob3').devbridgeAutocomplete().hide();
        $('#suggestJobId').val(suggestion.data);
    },
    paramName:'q'
});

// Interpret autocompleteJob2 value (copied from autocompleteJob1) as a jobNumber for autocomplete,
//  use it to fill in suggested matches under "Job Number". If user
//  selects from the suggested matches, hide the other two sets of suggested matches
//  copy matching suggestion.value to autocompleteJob1 element (tricky, tricky) 
//  and set hidden suggestJobId based on the data that came back from this.
$('#autocompleteJob2').devbridgeAutocomplete({
    noCache:true,		
    appendTo: "#suggest2",
    width:"280",
    height:"200",	
    serviceUrl: '/ajax/autocomplete_jobnumber.php',
    onSelect: function (suggestion) {        
        $('#autocompleteJob1').devbridgeAutocomplete().hide();
        $('#autocompleteJob1').val(suggestion.value);
        $('#autocompleteJob3').devbridgeAutocomplete().hide();
        $('#suggestJobId').val(suggestion.data);
    },
    paramName:'q'
});

// Interpret autocompleteJob3 value (copied from autocompleteJob1) as a job location for autocomplete,
//  use it to fill in suggested matches under "Location". If user
//  selects from the suggested matches, hide the other two sets of suggested matches
//  copy matching suggestion.value to autocompleteJob1 element (tricky, tricky) 
//  and set hidden suggestJobId based on the data that came back from this.
$('#autocompleteJob3').devbridgeAutocomplete({
    noCache:true,		
    appendTo: "#suggest3",
    width:"280",
    height:"200",	
    serviceUrl: '/ajax/autocomplete_joblocation.php',
    onSelect: function (suggestion) {        
        $('#autocompleteJob1').devbridgeAutocomplete().hide();
        $('#autocompleteJob1').val(suggestion.value);
        $('#autocompleteJob2').devbridgeAutocomplete().hide();
        $('#suggestJobId').val(suggestion.data);
    },
    paramName:'q'
});

// Handler to prevent "ENTER" key from having effect on keydown.
$(document).ready(function() {
    $(window).keydown(function(event) {
        if(event.keyCode == 13) {
            event.preventDefault();
            return false;
        }
    });
});
</script>

<?php
include BASEDIR . '/includes/footer.php';
?>
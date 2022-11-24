<?php 
/* panther.php

   EXECUTIVE SUMMARY: Intended as a personal home page + search. Evolving. Notably: includes open tickets. 
   There is a RewriteRule in the .htaccess to allow this to be invoked as "panther/foo" rather than "panther.php".
   
   No primary input.
   
   Optional input $_REQUEST['act']. Only possible value: 'search'.
   * Combines with $_REQUEST['q'] to specify a search on jobs/persons/companies.
*/

include './inc/config.php';
include './inc/perms.php';

// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
//$time = new Time($user,false,'incomplete');
//$workordertasks = $time->getWorkOrderTasksByDisplayType();
// END COMMENTED OUT BY MARTIN BEFORE 2019

$jobs = array();
$persons = array();
$companys = array();
$locations = array();

$crumbs = new Crumbs(null, $user);

// $_SESSION keeps a history of searches. 
if (!isset($_SESSION['searches'])){
    $_SESSION['searches'] = array();
}

if ($act == 'search') {
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    //$crumbs = array();
    //$_SESSION['crumbs'] = $crumbs;
    // end COMMENTED OUT BY MARTIN BEFORE 2019
	
    $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';
    
    $search = new Search('mypage', $user);
    $results = $search->search($q);
    if (isset($results['jobNumbers'])) {
        $jobs = $results['jobNumbers'];
    }
    if (isset($results['persons'])){
        $persons = $results['persons'];
    }
    if (isset($results['companys'])){
        $companys = $results['companys'];
    }
    if (isset($results['locations'])){
        $locations = $results['locations'];
    }
    if (!in_array($q, $_SESSION['searches'])){
        $_SESSION['searches'][] = $q;
    }
    if (count($_SESSION['searches']) > 5) {
        // Cut this to the last 5
        $_SESSION['searches'] = array_slice($_SESSION['searches'],-5,5);
    }	
}
?>

<?php 
include BASEDIR . '/includes/header.php';
echo "<script>\ndocument.title ='Panther Home';\n</script>\n";
?>


<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg " role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title" id="modalTitle"></h1>
        <button type="button" id="closeModal" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
         <iframe src="" width="100%" height="500" frameborder="0" allowtransparency="true"></iframe>
      </div>
    </div>
  </div>
</div>



<?php
// If no logged-in user, make them log in
// Save URI so if we got here via redirect while they were trying to access 
//  another page, we can get back there after login.
if (!$user) {
    $_SESSION['last_uri'] = $_SERVER['REQUEST_URI'];
    header("Location: /login");
} else {
?>	
    <div id="container" class="clearfix">
        <div class="main-content">
            <div class="full-box clearfix">
                <h2 class="heading">Search</h2>
                    <?php
                    // Buttons to add job, person, company, present only if user has the relevant admin-level permission.
                     $checkPerm = checkPerm($userPermissions, 'PERM_PERSON', PERMLEVEL_RWA);
                    if ($checkPerm) {
                    ?>
                        <button type="button" id="addPersonModal" class="btn btn-secondary mr-3" data-toggle="modal" data-custom="person" data-target="#addModal">
                        <i class="fas fa-plus prefix grey-text"></i>
                        Add Person</button>

                    <?php }
                    
                    $checkPerm = checkPerm($userPermissions, 'PERM_JOB', PERMLEVEL_RWA);
                    if ($checkPerm) {                             
                    ?>
                        <button type="button" id="addJobModal" class="btn btn-secondary mr-3" data-toggle="modal" data-custom="job" data-target="#addModal">
                        <i class="fas fa-plus prefix grey-text"></i>
                        Add Job</button>
                    <?php }
                    
                    $checkPerm = checkPerm($userPermissions, 'PERM_COMPANY', PERMLEVEL_RWA);
                    if ($checkPerm) {
                    ?>
                        <button type="button" id="addCompanyModal" class="btn btn-secondary mr-3" data-toggle="modal" data-custom="company" data-target="#addModal">
                        <i class="fas fa-plus prefix grey-text"></i>

                        Add Company</button>
                    <?php }
                    
                    $checkPerm = checkPerm($userPermissions, 'PERM_LOCATION', PERMLEVEL_RWA);
                    if ($checkPerm) {
                    ?>
                        <button type="button" id="addLocationModal" class="btn btn-secondary" data-toggle="modal" data-custom="location" data-target="#addModal">
                        <i class="fas fa-plus prefix grey-text"></i>

                        Add Location</button>
                    <?php }
                    ?>


<script>
    $('#addModal').on('shown.bs.modal', function(event){
        var button = $(event.relatedTarget) // Button that triggered the modal      
        var recipient = button.data('custom') // Extract info from data-* attributes
        $(this).find('iframe').attr('src', 'fb/add'+recipient+'.php');
        $('#modalTitle').html('Add '+ recipient);

    }).on('hidden.bs.modal', function(){
        $(this).find('iframe').attr('src', '');        
        $('#modalTitle').html('');
    });
</script>
                    <?php
                    // Details link moved up 2019-11-18 at Ron's request.
                    ?>
                   <!-- <a target="_blank" href="'.DETAIL_ROOT.'/manage/">Details page</a>   -->            
                    
                    <?php
                    // Table with boxes for job, person, company, present only if user has the relevant admin-level permission.
                    // In each case, show whatever matches the search, and give a link to add something new.
                    ?>
                    <table>
                        <tr>
                            <td>
                                <form name="" action="" method="GET" id="searchForm">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <input type="hidden" name="act" value="search">
                                        <tr>
                                            <td colspan="2">Enter Search Term</td>
                                        </tr>
                                        <tr>
                                            <td><input type="text" name="q" id="qSearch" value="" size="40" maxlength="64"></td>
                                            <td width="100%"><input type="image" src="/cust/<?php echo $customer->getShortName(); ?>/img/button/button_search_32x32.png" border="0"></td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
                        </tr>
                    </table>
                    <link href="/cust/<?php echo $customer->getShortName(); ?>/css/main.css" rel="stylesheet"/>
                    <div class="one-third"> <span class="title">Person</span>
                        <div class="box">
                        <?php
                            foreach ($persons as $person) {
                                echo '<a id="linkPerson' . $person->getPersonId() . '" class="async-personid" rel="' . $person->getPersonId() . '" href="' . $person->buildLink() . 
                                     '" tx="' . $person->getFirstName() . '">' . $person->getFormattedName() . '</a>';
                            }
                        ?>
                        </div>
                        <?php /*Location added JM 2019-11-18, made simpler 2019-12-09*/ ?>
                        <br />
                        <span class="title">Location</span>
                        <div class="box">
                        <?php 
                        foreach ($locations AS $location) {
                            echo '<a id="linkLocation' . $location->getLocationId() . '" class="async-locationid" rel="' . $location->getLocationId() . '" href="' . $location->buildLink() . 
                                 '" tx="' . $location->getAddress1() . '">' . $location->getAddress1() . '</a>';
                        }
                        ?>
                        </div>
                    </div>
                    <div class="one-third middle"> <span class="title">Job</span>
                        <div class="box">
                        <?php
                            /* OLD CODE REMOVED 2019-06-25 JM
                            foreach ($jobs as $jkey => $job){ // $jkey not used, could be just foreach ($jobs as $job)
                                echo '<a class="async-jobid" rel="' . $job->getJobId() . '" href="' . $job->buildLink() . 
                                     '" tx="' . $job->getName() . '">' . $job->getName() . '</a>';
                            } */
                            // BEGIN NEW CODE 2019-06-25 JM
                            if (count($jobs)) {
                                echo '<table id="jobs-table">';
                                    echo '<thead>';
                                        echo '<th>Job name</th><th>Job number</th>';
                                    echo '</thead>';
                                    echo '<tbody>';
                                        foreach ($jobs as $job){
                                            echo '<tr>';
                                                echo '<td><a id="linkJobName' . $job->getJobId() . '" class="async-jobid" rel="' . $job->getJobId() . '" href="' . $job->buildLink() . 
                                                     '" tx="' . $job->getName() . '">' . $job->getName() . '</a></td>';
                                                echo '<td><a id="linkJobNumber' . $job->getNumber() . '" href="' . $job->buildLink() . '">' . $job->getNumber() . '</a></td>';
                                            echo '</tr>';
                                        }
                                    echo '</tbody>';
                                echo '</table>';
                            }
                            // END NEW CODE 2019-06-25 JM
                        ?>
                        </div>
                    </div>
                    <div class="one-third"> <span class="title">Company</span>
                        <div class="box">
                        <?php 
                        foreach ($companys as $company) {
                            echo '<a  id="linkCompany' . $company->getCompanyId() . '" href="' . $company->buildLink() . 
                                 '" tx="' . $company->getCompanyName() . '">' . $company->getCompanyName() . '</a>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
	
<?php 
} // END has a user 
?>

<?php 
include BASEDIR . '/includes/footer.php';
?>

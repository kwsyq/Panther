<?php 
/*  etc/multiple-client-report.php

    EXECUTIVE SUMMARY: Identify (and enable fixing) situations where there are multiple clients for the same job or workOrder
      
    NO INPUTs.
    
    >>> Need to do the workOrder side of this
    
*/

include __DIR__.'/../inc/config.php';
include __DIR__.'/../inc/access.php';

$logger->info2('1600895611', 'Running multiple-client-report.php');

include '../includes/header_fb.php';
?>
<style>
.lightgray td {background-color:lightgray;}
.white td {background-color:white;}
.lightgray.modified td {background-color:LightGreen;}
.white.modified td {background-color:LightGreen;}
</style>
<?php

$db = DB::getInstance();

$query = "SELECT * FROM ( \n" .
             "SELECT inTable, id, active, count(teamId) as c \n" . 
             "FROM team \n" .
             "WHERE teamPositionId= " . TEAM_POS_ID_CLIENT . "\n" .
             "AND active <> 0\n" .
             "GROUP BY inTable, id\n" .
         ") AS t WHERE c>1;";

$result = $db->query($query);
if (!$result) {
    $logger->errorDb('1600895647', 'Hard DB error', $db);
    echo "Hard DB error, see log\n";
    die();
}

$jobIds_with_multiple_clients = Array();
$workorderIds_with_multiple_clients = Array();
while ($row = $result->fetch_assoc()) {
    if ($row['inTable'] == INTABLE_WORKORDER) {
        $workorderIds_with_multiple_clients[] = $row['id'];
    } else {
        $jobIds_with_multiple_clients[] = $row['id'];
    }
}

if (count($jobIds_with_multiple_clients) || count($workorderIds_with_multiple_clients)) {
    $query = "SELECT * FROM teamPosition ORDER BY name;";
    $result = $db->query($query);
    if (!$result) {
        $logger->errorDb('1600899123', 'Hard DB error', $db);
        echo "Hard DB error, see log\n";
        die();
    }
    $teamPositions = Array();
    while ($row = $result->fetch_assoc()) {
        $teamPositions[] = $row;
    }
}

if (count($jobIds_with_multiple_clients)) {
    ?>
    <table><thead>
    <th>JobId</th>
    <th>Name</th>
    <th>Client</th>
    <th>Team position</th>
    <th><!-- Delete / Undelete --></th>
    </thead><tbody>
    <?php
    $bgcolor = "lightgray";
    foreach ($jobIds_with_multiple_clients AS $jobId) {
        if ($bgcolor == "lightgray") {
            $bgcolor = "white";
        } else {
            $bgcolor = "lightgray";
        }
        $query = "SELECT * FROM team WHERE id= $jobId \n" . 
        "AND inTable = " . INTABLE_JOB. " AND teamPositionId= " . TEAM_POS_ID_CLIENT ."\n" .
        "AND active <> 0\n" .
        "ORDER BY id;\n";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1600897203', 'Hard DB error', $db);
            echo "Hard DB error, see log\n";
            die();
        }
        while ($row = $result->fetch_assoc()) {
            echo '<tr class="' . $bgcolor . '">' . "\n";
            $job = new Job($row['id']);
            echo '<td>' . $row['id'] . '</td>' . "\n";
            echo '<td>' . $job->getName() . '</td>' . "\n";
            
            $name = '';
            $companyPersonId = $row['companyPersonId'];
            if (!CompanyPerson::validate($companyPersonId)) {
                $name = 'Invalid companyPersonId ' . $companyPersonId;
            } else {
                $companyPerson = new CompanyPerson($companyPersonId);
                $company = $companyPerson->getCompany();
                $person = $companyPerson->getPerson();
                $name = $person->getFirstName() . ' ' . $person->getLastName() . ' [' . $company->getCompanyName() . ']';
            }
            echo '<td>' . $name. '</td>' . "\n";
            echo '<td>';
                echo '<select class="position" data-teamid="' . $row['teamId'] . '" disabled>'; // disabled until document ready
                foreach ($teamPositions as $teamPosition) {
                    echo '<option value= ' . $teamPosition['teamPositionId'];
                    if ($teamPosition['teamPositionId'] == TEAM_POS_ID_CLIENT) {
                        // initially, everything has this value or we wouldn't be displaying it.
                        echo ' selected';
                    }
                    echo '>';
                    echo$teamPosition['name'];
                    echo '</option>' . "\n";
                }                
                echo '</select>';
            echo '</td>' . "\n";
            echo '<td>';
                echo '<button class="delete" data-teamid="' . $row['teamId'] . '">Delete</button>';
            echo '</td>' . "\n";
            echo '</tr>' . "\n";
        }        
    }
    ?>
    </tbody></table>
    <?php
} else {
    echo '<p style="font-weight:bold">No job has more than one client assigned</p>';
}

if (count($workorderIds_with_multiple_clients)) {
    if (count($jobIds_with_multiple_clients)) {
        echo '<br /><br /><br />';
    }
    ?>
    <table><thead>
    <th>WorkOrderId</th>
    <th>&nbsp;&nbsp;&nbsp;Description</th>
    <th>Client</th>
    <th>Team position</th>
    <th><!-- Delete / Undelete --></th>
    </thead><tbody>
    <?php
    $bgcolor = "lightgray";
    foreach ($workorderIds_with_multiple_clients AS $workOrderId) {
        if ($bgcolor == "lightgray") {
            $bgcolor = "white";
        } else {
            $bgcolor = "lightgray";
        }
        $query = "SELECT * FROM team WHERE id= $workOrderId \n" . 
        "AND inTable = " . INTABLE_WORKORDER. " AND teamPositionId= " . TEAM_POS_ID_CLIENT ."\n" .
        "AND active <> 0\n" .
        "ORDER BY id;\n";
        $result = $db->query($query);
        if (!$result) {
            $logger->errorDb('1600897203', 'Hard DB error', $db);
            echo "Hard DB error, see log\n";
            die();
        }
        while ($row = $result->fetch_assoc()) {
            echo '<tr class="' . $bgcolor . '">' . "\n";
            $workOrder = new WorkOrder($row['id']);
            echo '<td>' . $row['id'] . '</td>' . "\n";
            echo '<td>' . $workOrder->getDescription() . '</td>' . "\n";
            
            $name = '';
            $companyPersonId = $row['companyPersonId'];
            if (!CompanyPerson::validate($companyPersonId)) {
                $name = 'Invalid companyPersonId ' . $companyPersonId;
            } else {
                $companyPerson = new CompanyPerson($companyPersonId);
                $company = $companyPerson->getCompany();
                $person = $companyPerson->getPerson();
                $name = $person->getFirstName() . ' ' . $person->getLastName() . ' [' . $company->getCompanyName() . ']';
            }
            echo '<td>' . $name. '</td>' . "\n";
            echo '<td>';
                echo '<select class="position" data-teamid="' . $row['teamId'] . '" disabled>'; // disabled until document ready
                foreach ($teamPositions as $teamPosition) {
                    echo '<option value= ' . $teamPosition['teamPositionId'];
                    if ($teamPosition['teamPositionId'] == TEAM_POS_ID_CLIENT) {
                        // initially, everything has this value or we wouldn't be displaying it.
                        echo ' selected';
                    }
                    echo '>';
                    echo$teamPosition['name'];
                    echo '</option>' . "\n";
                }                
                echo '</select>';
            echo '</td>' . "\n";
            echo '<td>';
                echo '<button class="delete" data-teamid="' . $row['teamId'] . '">Delete</button>';
            echo '</td>' . "\n";
            echo '</tr>' . "\n";
        }        
    }
    ?>
    </tbody></table>
    <?php
} else {
    echo '<p style="font-weight:bold">No WorkOrder has more than one client assigned</p>';
}

?>
    
<script>
$(function() {
    $('select.position').on('change', function() {
        let $this = $(this);
        let teamPositionId = $this.val();
        let teamId = $this.data('teamid');
        $.ajax({
            url: '/ajax/setTeamPosition.php',
            data:{
                teamId : teamId,
                teamPositionId: teamPositionId
            },
            async:true,
            type:'post',
            success: function(data, textStatus, jqXHR) {    
                if (data['status']) {    
                    if (data['status'] == 'success') {
                        // indicate if row is changed away from 'client' as position
                        if ($this.val() == <?= TEAM_POS_ID_CLIENT ?>) {
                            $this.closest('tr').removeClass('modified');
                        } else {
                            $this.closest('tr').addClass('modified');
                        }
                    } else {    
                        alert('error 1 saving position, please contact administrator or developer to check the log');    
                    }    
                } else {    
                    alert('error 2 saving position, please contact administrator or developer to check the log');
                }    
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error 3 saving position, please contact administrator or developer to check the log');
            }
        });            
    });
    $('select.position').prop('disabled', false);
    
    // This needs to be a delegated handler, since 'delete' buttons can be created on the fly.
    $('body').on('click', 'button.delete', function() {
        let $this = $(this);    
        let teamId = $this.data('teamid');
        $.ajax({
            url: '/ajax/woteam_active_toggle.php',
            data:{
                teamId : teamId,
                active: 0
            },
            async:true,
            type:'post',
            success: function(data, textStatus, jqXHR) {    
                if (data['status']) {    
                    if (data['status'] == 'success') {
                        $this.removeClass('delete').addClass('undelete').html('Undelete');                                
                    } else {    
                        alert('error 1 deleting, please contact administrator or developer to check the log');    
                    }    
                } else {    
                    alert('error 2 deleting, please contact administrator or developer to check the log');
                }    
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error 3 deleting, please contact administrator or developer to check the log');
            }
        });
    });
    $('button.delete').prop('disabled', false);

    // This needs to be a delegated handler, since 'undelete' buttons can be created on the fly.
    $('body').on('click', 'button.undelete', function() {
        let $this = $(this);    
        let teamId = $this.data('teamid');
        $.ajax({
            url: '/ajax/woteam_active_toggle.php',
            data:{
                teamId : teamId,
                active: 1
            },
            async:true,
            type:'post',
            success: function(data, textStatus, jqXHR) {    
                if (data['status']) {    
                    if (data['status'] == 'success') {
                        $this.removeClass('undelete').addClass('delete').html('Delete');                                
                    } else {    
                        alert('error 1 undeleting, please contact administrator or developer to check the log');    
                    }    
                } else {    
                    alert('error 2 undeleting, please contact administrator or developer to check the log');
                }    
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error 3 undeleting, please contact administrator or developer to check the log');
            }
        });
    });
})
</script>
<br/><br/><br/>
<?php
include '../includes/footer_fb.php';
?>


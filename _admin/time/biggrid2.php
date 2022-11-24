<?php
/*  _admin/time/biggrid2.php

    EXECUTIVE SUMMARY: code which may still be under development as of 2019-05.
    Appears to be intended as a replacement for _admin/time/biggrid.php (and possibly also 
    _admin/time/biggrid-week.php). 

    PRIMARY INPUT $_REQUEST['start']: the date the pay period starts ... i.e. "2019-10-16".
    
    Introduces some new (to SSS) technologies:
        * vue framework: (see https://vuejs.org for documentation of the vue framework).
            * >>>00026 When running this at the moment, on the console it writes:
                "You are running Vue in development mode.
                Make sure to turn on production mode when deploying for production.
                See more tips at https://vuejs.org/guide/deployment.html"
          >>>00001 JM: Not at all clear if we are really getting anything useful from that other than
          a sortable grid, and there are certainly "cheaper" ways to get that.
        * axios, a "promise-based HTTP client for the browser and node.js" -- 
          https://github.com/axios -- so we may presume that _admin/axios/getemployees.php
          is somehow related. Like vue, axios is a completely new introduction into this code body. 

    Note the related file _admin/time/biggrid.js. No indication of how we decide what's 
     in which, since this is mainly Javascript as well.
    
JM 2019-02-11:
   This appears to have been Martin's work in progress late December 2018/early January 2019.
   Status unknown. Presumably intended as an eventual replacement for _admin/time/biggrid.php 
    and/or _admin/time/biggrid-week.php.
   Someone will have to decide whether to continue work from here or to start over.

   >>>00015 Largely undocumented code, needs study and documentation. Committed as-is 2019-02-11
   
   Ron wrote the following 2019-02-08, though it's hard to tell just where this file fits the evolution:
        
        The Biggrid was the beginning of a rework to address the hierarchy of tasks as they begin to define the 
        contract and eventually the invoice.

        In Phase 1 we laid out our initial vision for tasks and how they relate to time sheets, contracts and 
        invoices, with Brian Ware.  
        
        In Phase 2 Martin saw a few shortcomings in the first attempt by Brian, but overlooked a few key elements.
        As it developed, it became apparent that the structure wouldn't address our needs.  
        We had already spent a great deal of time waiting for the functionality so Martin patched it together the 
        best he could and that represents our current working version. This is the root of many of our current 
        wish list items. Martin began to read about and study a different structure for handling such data and was 
        working on it. It isn't completed because it was a new type of programing structure for Martin.
        
        In Phase 3 I am looking forward to getting the system under our control and documented. This section related 
        to tasks and such is the first thing I would like to rework. It will take much more time and discussion.
        I think that with the team we have now, it will just be a matter of discussion, planning, programming, etc.
        
        You could leave it out or keep it, I don't know if the problem has been solved, he hasn't asked me enough 
        questions to lead me to believe that he understands completely what we are doing.
   
*/

include '../../inc/config.php';

?>

<script src="https://cdn.jsdelivr.net/npm/vue@2.5.17/dist/vue.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>

<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">

<?php
$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : '';  // the date the week starts ... i.e. 2016-10-20
$time = new Time(0,$start,'payperiod');

/*
// BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
echo '<table border="0" cellpadding="0" width="100%">';
echo '<tr>';
echo '<td colspan="3">';
echo '[<a href="time.php">EMPLOYEES</a>]&nbsp;&nbsp;[<a href="summary.php">SUMMARY</a>]&nbsp;&nbsp;[<a href="pto.php">PTO</a>]&nbsp;&nbsp;[BIG GRID]';
echo '</td>';
echo '</tr>';
echo '</table>';

echo '<p>';

echo '<table border="0" cellpadding="0" cellspacing="0" width="800">';
echo '<tr><td colspan="3">&nbsp;</td></tr>';
echo '<tr>';
//echo '<td style="text-align:left">&#171;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->previous . '">' . $time->previous . '</a>&#171;</td>';
echo '<td style="text-align:left">&#171;<a href="biggrid.php?start=' . $time->previous . '">prev</a>&#171;</td>';

$e = date('Y-m-d', strtotime('-1 day', strtotime($time->next)));

echo '<td style="text-align:center"><span style="font-weight:bold;font-size:125%;">Period: ' . date("m-d", strtotime($time->begin)) . ' thru ' . date("m-d", strtotime($e)) . '-' . date("Y", strtotime($time->begin)) . '</span></td>';
//echo '<td style="text-align:right">&#187;<a href="summary.php?displayType=payperiod&render=' . rawurlencode($render) . '&start=' . $time->next . '">' . $time->next . '</a>&#187;</td>';
echo '<td style="text-align:right">&#187;<a href="biggrid.php?start=' . $time->next . '">next</a>&#187;</td>';

echo '</tr>';
echo '</table>';
// END COMMENTED OUT BY MARTIN BEFORE 2019
*/

?>
<style>

body {
  font-family: Helvetica Neue, Arial, sans-serif;
  font-size: 14px;
  color: #444;
}

table {
  border: 2px solid #4283b9;
  border-radius: 3px;
  background-color: #fff;
}

th {
  background-color: #4283b9;
  color: rgba(255,255,255,0.66);
  cursor: pointer;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}

td {
  background-color: #f9f9f9;
}

th, td {
  min-width: 120px;
  padding: 10px 20px;
}

th.active {
  color: #fff;
}

th.active .arrow {
  opacity: 1;
}

.arrow {
  display: inline-block;
  vertical-align: middle;
  width: 0;
  height: 0;
  margin-left: 5px;
  opacity: 0.66;
}

.arrow.asc {
  border-left: 4px solid transparent;
  border-right: 4px solid transparent;
  border-bottom: 4px solid #fff;
}

.arrow.dsc {
  border-left: 4px solid transparent;
  border-right: 4px solid transparent;
  border-top: 4px solid #fff;
}

.toggleDiv {
    display : block
}
.toggleInput {
    display : none

}

</style>

<?php /* Martin comment: component template */ ?>
<?php /* JM: This template somehow makes it into the 'demo-grid' below. */ ?>
<script type="text/x-template" id="grid-template">
    <table>
        <thead>
            <?php /* 2 rows of "heading", but the first really isn't column headings, it just uses the same table for layout. */ ?>
            <tr>
                <?php /*
                    >>>00001 PRESUMABLY (>>>00026 as of 2019-05-28, I haven't seen any of this actually work - JM): 
                        * Leftmost column, labeled "<<prev", navigates to previous pay period
                        * Rightmost column, labeled "next>>", navigates to next pay period
                        * Columns in between contain some sort of title  that I believe is built off of the date for this period
                    Elsewhere we've used &#171;/&#187; ('«' / '»') where this uses &lt;&lt; / &gt;&gt ('<<' / '>>') 
                */ ?>
                <th colspan="1"><button v-on:click="clickBegin(prevdate)">&lt;&lt;prev</button></th>
                <th v-bind:colspan="(columns.length - 2)"><h2>
                    {{gridtitle}}
                    </h2>
                </th>
                <th colspan="1"><button v-on:click="clickBegin(nextdate)">next&gt;&gt;</button></th>
            </tr>
            <tr>
                <?php /*
                    As of 2019-05, actual headings here come from var demo in _admin/time/biggrid.js:
                     demo.gridcolumns = ['firstName', 'lastName', 'rateDisp', 'iraDisp', 'iraType'].
                    Headers for columns, allows sort by column.
                */ ?>
                <th v-for="key in columns"
                    @click="sortBy(key)"
                    :class="{ active: sortKey == key }">
                        {{ key | capitalize }}
                        <span class="arrow" :class="sortOrders[key] > 0 ? 'asc' : 'dsc'">
                        </span>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="entry in filteredData">
                <td v-for="key in columns">                
                    <div class="toggleDiv" v-on:click="clickEdit()">
                        {{entry[key]}}
                    </div>
                    <input v-if="displayThis(key)" type="text" id="name" class="toggleInput" >
                    <button v-if="0" v-on:click="buttonClick()">press</button>
                </td>
            </tr>
        </tbody>
    </table>
</script>

<?php /* Visible content starts here */ ?>
<div id="demo">
    <?php /* Search */ ?>
    <form id="search">
        Search <input name="query" v-model="searchQuery">
    </form>
    <demo-grid
        :data="gridData"
        :columns="gridColumns"
        :filter-key="searchQuery"
        :gridtitle="gridtitle"
        :nextdate="nextdate"
        :prevdate="prevdate"
        :nexttext="nexttext"
        :prevtext="prevtext"
    >
    </demo-grid>
</div>

<?php /* >>>00014 JM 2019-05: complete mystery to me so far */ ?>
<div id="root">
</div>

<?php
    $db = DB::getInstance();

    // Get all current employees for current customer (as of 2019-05, always SSS).
    $employees = $customer->getEmployees(1);

    /*
    // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
    echo '<table border="0" cellpadding="0" cellspacing="0" id="edit_table">';
    
    foreach ($employees as $ekey => $employee){
        
        echo '<tr>';
    
            echo '<td>' . $employee->getFormattedName(1) . '</td>';
            
            
            
        
        $query = " select * from " . DB__NEW_DATABASE . ".customerPersonPayPeriodInfo  ";
        $query .= " where customerPersonId = (select customerPersonId from " . DB__NEW_DATABASE . ".customerPerson where customerId = " . intval($customer->getCustomerId()) . " and personId = " . $employee->getUserId() . ") ";
        $query .= " and periodBegin = '" . date("Y-m-d", strtotime($time->begin)) . "' ";
        $query .= " limit 1 ";
    
        $cpppi = false;
        
        if ($result = $db->query($query)) {
            if ($result->num_rows > 0){
                while ($row = $result->fetch_assoc()){
                    $cpppi = $row;
                }
            }
        }
    
        $rateDisp = '&nbsp;';
        $iraDisp = '&nbsp;';
        $iraType = 0;
        $customerPersonPayPeriodInfoId = 0;
        $customerPersonId = 0;
        if ($cpppi){
            
            $customerPersonId = $cpppi['customerPersonId'];
            
            $x = $cpppi;
        
            $customerPersonPayPeriodInfoId = $x['customerPersonPayPeriodInfoId'];
            
            
            
            $rate = $x['rate'];
            $salaryHours = $x['salaryHours'];
            $salaryAmount = $x['salaryAmount'];
            
            if (is_numeric($salaryAmount) && ($salaryAmount > 0)){
                $rateDisp = '$' . number_format(($salaryAmount/100),2) . '/yr';
            } else if (is_numeric($rate) && ($rate > 0)){
                $rateDisp = '$' . number_format(($rate/100),2) . '/hr';
            }
            
            $iraType = 	$x['iraType'];
            $ira = 	$x['ira'];
            
            if (is_numeric($ira)){
                
                $iraDisp = $ira;
                
            } else {
                $iraDisp = 0;
            }
            
        }
        
        
        echo '<td align="center">';
        
        echo $rateDisp;
        
        echo '</td>';
        
        echo '<td align="center" class="ieditable" id="' . intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId . '">';
        
        echo $ira;
        
        echo '</td>';
        
        $pselected = ($iraType == IRA_TYPE_PERCENT) ? ' selected ' : '';
        $dselected = ($iraType == IRA_TYPE_DOLLAR) ? ' selected ' : '';
        
        echo '<td align="center"><select name="iraType" id="type_' . intval($customerPersonId) . '_' . $customerPersonPayPeriodInfoId . '" onchange="sendIRAType(this)"><option value="0">--</option>';
        echo '<option value="' . IRA_TYPE_PERCENT . '" ' . $pselected . '>' . $iraTypes[IRA_TYPE_PERCENT] . '</option>';
        echo '<option value="' . IRA_TYPE_DOLLAR . '" ' . $dselected . '>' . $iraTypes[IRA_TYPE_DOLLAR] . '</option>';
        echo '</select></td>';
        
        echo '</tr>';
        
    }
    
    echo '</table>';
    // END COMMENTED OUT BY MARTIN BEFORE 2019
    */
?>
<script>
    /* BEGIN REMOVED JM 2019-12-12; nothing calls this any more.
    $(function () {
        <?php  
        // JM: not sure that anything is currently hooked up to this. This is the same code
        //  to edit an IRA amount in the grid as in _admin/time/biggrid.php.
            
        //  Double-click handler for a cell with class ieditable, to make it editable.
        //     Synchronous POST to _admin/ajax/iraamount.php passing new number (no apparent validity checks).
        //     Updates text (number) on success.
        //     Alerts on failure.            
        // ?>    
        $("#edit_table td.ieditable").dblclick(function () {		
            var OriginalContent = $(this).text();
            var inputNewText = prompt("Enter new content for:", OriginalContent); // >>>00032: could provide more context
            if (inputNewText!=null){
                $.ajax({
                    url: '../ajax/iraamount.php',
                    data:{ id: $(this).attr('id'), value: inputNewText },
                    async:false,
                    type:'post',
                    context: this,
                    success: function(data, textStatus, jqXHR) {
                        if (data['status']){
                            if (data['status'] == 'success') {
                                $(this).text(inputNewText);
                            } else {
                                alert('error not success');
                            }
                        } else {
                            alert('error no status');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert('error');
                    }
                });
            }
        });
    });
    // END REMOVED JM 2019-12-12
    */
    
    <?php
        /* BEGIN REMOVED JM 2019-12-12; nothing calls this any more.
        
        // JM: not sure that anything is currently hooked up to this. This is the same code
        // to change IRA type as in _admin/time/biggrid.php.
        // 
        // INPUT sel: an HTML SELECT object. Its ID should be of the form type_customerPersonId_customerPersonPayPeriodInfoId, where
        //     * type is the literal 'type'.
        //     * customerPersonId is a primary key to DB table CustomerPerson. 
        //     * customerPersonPayPeriodInfoId is a primary key to DB table CustomerPersonPayPeriodInfo. 
        //     * e.g. type_577_6543 would be customerPersonId = 577, customerPersonPayPeriodInfoId = 6543.  
        // The selected OPTION value will be the new value for the corresponding row in CustomerPersonPayPeriodInfo.
        // 
        // (no apparent validity checks).
        // ?>      
    var sendIRAType = function(sel){
        var sel = document.getElementById(sel.id);
        var val = sel.options[sel.selectedIndex].value;
    
        $.ajax({
            url: '../ajax/iratype.php',
            data:{ id: sel.id, value: val },
            async:false,
            type:'post',
            context: this,
            success: function(data, textStatus, jqXHR) {    
                if (data['status']){
                    if (data['status'] == 'success'){
                        $(this).text(inputNewText);
                    } else {
                        alert('error not success');
                    }
                } else {
                    alert('error no status');
                }
    
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('error');
            }
        });	
    }
    // END REMOVED JM 2019-12-12
    */

    /* BEGIN REMOVED JM 2019-12-12: unused variable
    var begin = '<?php echo $time->begin ?>';
    // END REMOVED JM 2019-12-12
    */
?>
</script>

<script src="biggrid.js"></script>

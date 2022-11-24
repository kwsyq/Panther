<?php




?>
<html>
<head> 
<title>
    Panther Database - Integrity Test
</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.5.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" ></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" ></script>
<script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js" ></script>
</head>
<body>   
    <div class="container-fluid">
       <!--  <button type="button" id="start" class="btn btn-secondary">Start</button>     -->
<div class="btn-group btn-group-toggle" data-toggle="buttons">
                <button class="btn btn-secondary active" >
                
                                <input type="radio" name="options" id="option1" autocomplete="off" checked>All rows
                            </button>
            
                            <button class="btn btn-secondary ">
                            
                    <input type="radio" name="options" id="option2" autocomplete="off">Just rows with isssues
                </button>
            </div>
            
        <table class="table table-stripped table-hover my-5" id="mainTable">
            <thead class="thead-dark">
                <tr>
                    <th>Table</td>
                    <th>External Key</td>
                    <th>External Table</td>
                    <th>Status</td>
                    <th>StatusText</td>
                </tr>
            </thead>
            <tbody>

            </tbody>
            <tfoot class="thead-dark">
                <tr>
                    <th>Table</td>
                    <th>External Key</td>
                    <th>External Table</td>
                    <th>Status</td>
                    <th>StatusText</td>
                </tr>
            </tfoot>            
        </table>
    </div>
<?php

include 'alertbox.php';
?>
<style>
.manina {
cursor: pointer;
}
</style>  
<script> 
    var localTimer;
    var id=0;
    var finished=false;
    var table;
    var returnCodes=[
        " ",
        "OK", 
        "Local primary Key", 
        "Integrity issues", 
        "Not corresponding table. Maybe an array in PHP!", 
        "Check not yet implemented"];
    $.fn.dataTable.ext.search.push(
        function( settings, data, dataIndex ) {
            var all = $('#option1').is(':checked');
            var withIssue = $('#option2').is(':checked');
            var status = data[3] ; // use data for the age column
            if(all){
                return true;
            } else {
                
                if(status!=='OK'){
                    return true;
                } else {
                    return false;
                }
            }
        }
    );
//<a href="#" data-toggle="modal" data-target="#exampleModalLong">servizio</a>
    function getUpdate(){
        if(!finished){
            $.post('integrityAjaxStatus.php').done(function(data){
                console.log(data.length);
                if(data.length===0){
                    clearInterval(localTimer);
                    table=$('#mainTable').DataTable({
                        fixedHeader: {
                          header: true,
                          footer: true
                        },        
                        ordering: true,
                        searching: true,
                        columnDefs: [
                            {
                                "targets": [ 4 ],
                                "visible": false,
                                "searchable": false
                            }
                        ],
                        initComplete: function () {
                            this.api().columns().every( function () {
                                var column = this;
                                var select = $('<select><option value=""></option></select>')
                                    .appendTo( $(column.footer()).empty() )
                                    .on( 'change', function () {
                                        var val = $.fn.dataTable.util.escapeRegex(
                                            $(this).val()
                                        );
                 
                                        column
                                            .search( val ? '^'+val+'$' : '', true, false )
                                            .draw();
                                    } );
                                column.data().unique().sort().each( function ( d, j ) {
                                    select.append( '<option value="'+d+'">'+d+'</option>' );
                                } );
                            } );
                        }
                       

                    });
    
                    $('#mainTable tbody').on('click', 'tr', function () {
                        var data = table.row( this ).data();
                        console.log(data);

                        if(data[3]==returnCodes[3]){    
                            var tblissues=JSON.parse(data[4]);
                            var output="<thead>";
                            output+="<tr>";
                            output+="<th> Table Name </th>";
                            output+="<th> PrimaryKey </th>";
                            output+="<th> PrimaryKey Value</th>";
                            output+="<th> External table Name </th>";
                            output+="<th> Foreign Key </th>";
                            output+="<th> Foreign Key Value</th>";
                            output+="</tr>";
                            output+="</thead>";
                            output+="<tbody>";
                            $.each(tblissues, function(key, value){
                                output+="<tr>";
                                output+="<td colspan=6>" + value['tableName'];
                                output+="</td>";
                                output+="</tr>";
                                $.each(value['results'], function(key1, value1){
                                    output+="<tr>";
                                    output+="<td>" + data[0] +  "</td>";
                                    output+="<td>" + data[0] +  "Id</td>";
                                    output+="<td>"+value1[data[0]+'Id']+"</td>";
                                    output+="<td>" + data[2] + "</td>";
                                    output+="<td>" + data[1] + "</td>";
                                    output+="<td class='table-danger'>" + value1[data[1]] + "</td>";
                                    output+="</tr>";
                                    
                                });
                            });
                            
                            output+="</tbody>";
/*  
                            output+="<tr>";
                            output+="<th> Table Name </th>";
                            output+="<th> PrimaryKey </th>";
                            output+="<th> PrimaryKey Value</th>";
                            output+="<th> External table Name </th>";
                            output+="<th> Foreign Key </th>";
                            output+="<th> Foreign Key Value</th>";
                            output+="</tr>";
                            output+="</thead>";
                            output+="<tbody>";
                            $.each(tblissues, function(key, value){
                                output+="<tr>";
                                output+="<td>" + data[0] + "." + data[0] +  "Id = " + value[data[0]+'Id'] +  "</td>";
                                output+="<td>" + data[0] +  "Id</td>";
                                output+="<td>"+value[data[0]+'Id']+"</td>";
                                output+="<td>" + data[2] + "</td>";
                                output+="<td>" + data[1] + "</td>";
                                output+="<td class='table-danger'>" + value[data[1]] + "</td>";
                                output+="</tr>";
                            });
                            
                            output+="</tbody>";
*/
                            $('#modalworkSpace').html(output);
                            $('#modalAlert').modal('show');
                        } else {
                            alert('No integrity issues');
                        }
                    });                     
                
                }
                
                $.each(data, function(key, value){
                    var rowClass="";
                    if(value.checkResultCode==1){
                        rowClass="table-success";
                    }
                    if(value.checkResultCode==2){
                        rowClass="table-info";
                    }
                    if(value.checkResultCode!=2){
                        $('#mainTable tbody').append(' \
                            <tr class="'+rowClass+'"> \
                                <td>'+value.tableName+'</td> \
                                <td>'+value.columnName+'</td> \
                                <td>'+value.externalTableName+'</td> \
                                <td '+ (value.checkResultCode==3?'class="manina" ':'') +'>'+returnCodes[value.checkResultCode]+'</td> \
                                <td>'+value.checkResult+'</td> \
                            </tr> \
                        ');
                    }

                    //console.log(value);
                })
            });
        }
    }

    $(document).ready(function(){        
        localTimer=setInterval(getUpdate, 5000);
    $('input:radio[name=options]').change(function(){
        console.log("option change");
        table.draw();
        
    });
            
        $.post('integrityAjax2.php').done(function(data){

        });
    })


</script>
</body>
</html>
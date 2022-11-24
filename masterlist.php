<?php
    ini_set('memory_limit', '-1');
    include './inc/config.php';
include './inc/perms.php';


$crumbs = new Crumbs(null, $user);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
    <link rel="stylesheet" href="/styles/kendo.common.min.css" />
    <link rel="stylesheet" href="/styles/kendo.default.min.css" />
    <link rel="stylesheet" href="/styles/kendo.default.mobile.min.css" />
    <script src="https://code.jquery.com/jquery-3.4.1.min.js" ></script>
  <script src="/js/jquery.min.js"></script>
  <script src="/js/kendo.all.min.js"></script>

</head>
<body>
<div class="container-fluid px-5">
    <h1>Master List</h1>
</div>

<div class="container-fluid mt-5 px-5">
    <div id="grid" class="stripe row-border cell-border">
    </div>
</div>
<script>
    $(document).ready(function () {
        var crudServiceBaseUrl = "https://ssseng.com/controllers/masterlistController.php",
                dataSource = new kendo.data.DataSource({
                    transport: {
                        read:  {
                            url: crudServiceBaseUrl,
                            dataType: "json"
                        },
                        parameterMap: function(options, operation) {
                            if (operation !== "read" && options.models) {
                                return {models: kendo.stringify(options.models)};
                            }
                        }
                    },
                    batch: true,
                    schema: {
                        model: {
                            fields: {
                                jobNumber: {type: "string"},
                                jobName: {type: "string"},
                                jobStatus: {type: "string"},
                                workOrderName: {type: "string"},
                                workOrderStatus: {type: "string"},
                                client: {type: "string"},
                                designProfessional: {type: "string"},
                                eor: {type: "string"},
                                location: {type: "string"},
                                contractDate:{type: "string"}, //, format: "{0:MMMM/dd/YYYY}"},
                                contractTotal: {type: "number"},
                                contractStatus: {type: "string"},
                                invoiceNumber: {type: "string"},
                                invoiceDate: {type: "string"},
                                invoiceOpenBalance:{type: "string"},
                                invoiceTotal: {type: "number"},
                                invoiceDiscount: {type: "number"},
                                invoiceStatus: {type: "string"},
                                paymentReceivedFrom: {type: "string"},
                                paymentDateReceived: {type: "date"},
                                paymentDateCredited: {type: "date"},
                                paymentRecordType: {type: "string"},
                                paymentReference: {type: "string"},
                                paymentNotes: {type: "string"},
                                paymentAmountPaid: {type: "number"}
                            }
                        }
                    }
                });

        $('#grid').kendoGrid({
            height: "800px",
            dataSource: dataSource,
            toolbar: ["excel", "pdf", "search"],
            columns: [
                { field: "jobNumber", title: "Job Number"},
                { field: "jobName", title: "Job Name"},
                { field: "jobStatus", title: "Job status"},
				{ field: "workOrderName", title: "WO Name"},
				{ field: "workOrderStatus", title: "WO Status"},
                { field: "client", title: "Client"},
                { field: "designProfessional", title: "Design professional"},
				{ field: "location", title: "Location"},
				{ field: "contractDate", title: "Ctr. Date"},
				{ field: "contractTotal", title: "Ctr. Total"},
				{ field: "contractStatus", title: "Ctr. status"}
            ],
            scrollable: {
                virtual: true
            },
            width: "auto",
            height: "700px",
            sortable: true,
            filterable: true,
            reorderable: true,
            resizable: true,
            groupable: true,
            navigatable: true,

        });
    });
    
/*

,
                    schema: {
                        model: {
                            fields: {
                                jobNumber: {type: "string"},
                                jobName: {type: "string"},
                                jobStatusName: {type: "string"},
                                description: {type: "string"},
                                icon: {type: "string",editable: false},
                                billingDescription: {type: "string"},
                                taskType: { defaultValue: {taskTypeId: 1, typeName: "overhead" }},
                                typeName: { type: "string"},
                                active: {type: "boolean"}
                            }
                        }
                    }

                    */

</script>
    <script type="text/x-kendo-template" id="template">
        <div id="details-container">
            <h2>#= taskId # #= description #</h2>
            <em>#= billingDescription #</em>
            <dl>
                <dt>City: #= taskType.taskTypeId #</dt>
            </dl>
        </div>
    </script>
</body>
</html>

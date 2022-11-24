<?php
    require_once '../../inc/config.php';
/*
    $db = DB::getInstance();

    $query = " select taskId, groupName, description, icon, billingDescription, taskTypeId, if(active=1, 'true', 'false') as active from " . DB__NEW_DATABASE . ".task limit 100";

    $result = $db->query($query);
    $rows=[];

    while($row=$result->fetch_assoc()){

        $rows[]=$row;
    }

    if (!$result) { // >>>00019 Assignment inside "if" statement, may want to rewrite.
        $errorId = '637810460319540695';
        $errorWotInternal = 'Cannot retreive tasks';
        $logger->errorDb($errorId, 'We could not retrieve task table', $db);
    }

    $query1 = " select * from " . DB__NEW_DATABASE . ".taskType ";

    $res = $db->query($query1);
    $types[0]='Undefined';
    while($row=$res->fetch_assoc()){
        $types[$row['taskTypeId']]=$row['typeName'];
    }
*/
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
    <h1>Tasks</h1>
</div>

<div class="container-fluid mt-5 px-5">
    <div id="tasklist" class="stripe row-border cell-border">
    </div>
        <div id="details"></div>
</div>
<div id="preview" style="display: none; position: absolute; height: 170px; width: 150px; text-align: center;">
    <div style="color: black; background-color:white; text-weight:400; font-size: 0.9em; border-bottom: 1px solid black; padding:2px; margin: auto" id="iconName"></div>
    <img id="file" src="" style="width: 150px; "/>
</div>
<script>
    var wnd,
        detailsTemplate;
    function viewImage(nomefile, event){
        var x;
        var y;
        x=event.pageX;
        y=event.pageY;
        $('#file').attr('src', nomefile.replace("+", "%20"));
        $('#iconName').html(nomefile.split('\\').pop().split('/').pop().replace("+", "%20"));
        $('#preview').css({'left': x+'px', 'top': y+'px'});
        $('#preview').show(500); //.attr({'display': 'block', 'z-index': '21' });
    }
    function hideImage(){
        $('#file').attr('src', '');     
        $('#preview').hide(); //.attr({'display': 'block', 'z-index': '21' });      
    }   
    function openWiki(nomelink){
        if(nomelink == null || nomelink=='' || nomelink=='null'){
            alert("Link not present!!");
            return;
        } else {

            window.open(nomelink);
        }
        return;
    }

    $(document).ready(function () {
       var crudServiceBaseUrl = "https://ssseng.com/controllers/taskController.php",
        dataSource = new kendo.data.DataSource({
            transport: {
                read:  {
                    url: crudServiceBaseUrl,
                    dataType: "json"
                },
                update: {
                    url: crudServiceBaseUrl ,
                    dataType: "json",
                    contentType: "application/json",
                    method: "put"
                },
                destroy: {
                    url: crudServiceBaseUrl ,
                    dataType: "json",
                    method: "DELETE"
                },
                create: {
                    url: crudServiceBaseUrl ,
                    contentType: "application/json",
                    dataType: "json",
                    method: "POST"
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
                    id: "taskId",
                    fields: {
                        taskId: { type: "string"},
                        groupName: {type: "string"},
                        description: {type: "string"},
                        icon: {type: "string",editable: false},
                        billingDescription: {type: "string"},
                        wikiLink: {type: "string"},
                        taskType: { defaultValue: {taskTypeId: 1, typeName: "overhead" }},
                        typeName: { type: "string"},
                        active: {type: "boolean"}
                    }
                }
            }
        });
        var filtersOp = {
          operators: {
            string: {
              contains: "Contains",
              eq: "Is equal to",
              neq: "Is not equal to"
            },
            date: {
              eq: "Is equal to",
              neq: "Is not equal to"
            },
            enums: {
              eq: "Is equal to",
              neq: "Is not equal to"
            }
          }
        }
        var taskGrid = $('#tasklist').kendoGrid({
            height: "700px",
            toolbar: ["create", "save", "cancel"],
            editable: true,
            dataSource: dataSource,
            filterable: {mode: "row"},
            columns: [
                { field: "taskId", title: "Id", width: "50px"},
                { field: "groupName", title: "Group Name", width: "200px",
                    filterable: {
                        operators: {
                            string: {
                              contains: "Contains",
                              startswith: "Starts with",
                              eq: "Is equal to",
                              neq: "Is not equal to",
                              doesnotcontain: "Does not contain",
                              endswith: "Ends with",
                              isempty: "Is Empty",
                              isnotempty: "Is Not empty"
                            }
                        }
                    }},
                { field: "description", title: "Description", width: "200px",
                    filterable: {
                        operators: {
                            string: {
                              contains: "Contains",
                              startswith: "Starts with",
                              eq: "Is equal to",
                              neq: "Is not equal to",
                              doesnotcontain: "Does not contain",
                              endswith: "Ends with",
                              isempty: "Is Empty",
                              isnotempty: "Is Not empty"
                            }
                        }
                    }
                },
                { field: "icon", title: "Icon", width: "200px", 
                    template: '<img style="cursor: pointer" src="https://ssseng.com/cust/ssseng/img/icons_task/#=icon#" width="30px" onclick="openWiki(\'#=wikiLink#\')" onmouseover="viewImage(\'https://ssseng.com/cust/ssseng/img/icons_task/#=icon#\', event);" onmouseout="hideImage();">', 
                    headerAttributes: {style: "text-align: center"},
                    attributes: {style: "text-align: center"},
                    filterable: {
                        operators: {
                            string: {
                              contains: "Contains",
                              startswith: "Starts with",
                              eq: "Is equal to",
                              neq: "Is not equal to",
                              doesnotcontain: "Does not contain",
                              endswith: "Ends with",
                              isempty: "Is Empty",
                              isnotempty: "Is Not empty"
                            }
                        }
                    }},
                { field: "billingDescription", title: "Billing Description", width: "250px",
                    filterable: {
                        operators: {
                            string: {
                              contains: "Contains",
                              startswith: "Starts with",
                              eq: "Is equal to",
                              neq: "Is not equal to",
                              doesnotcontain: "Does not contain",
                              endswith: "Ends with",
                              isempty: "Is Empty",
                              isnotempty: "Is Not empty"
                            }
                        }
                    }},
                { field: "taskType", title: "Type", width: "100px", editor: taskTypesDropDownEditor, template: '#=taskType.typeName#'},
                { field: "active", title: "Active", width: "100px", 
                    template: '<input type="checkbox" tag=#=active#  #= active==true ? "checked=checked" : "" # ></input>'},
                { field: "fake", title: 'Upload Icon', editor: fileUploadEditor, width: "110px", template: '<button type="button">Upload icon</button>' },
                { field: "wikiLink", title: "Link Wiki", width: "250px",
                    filterable: {
                        operators: {
                            string: {
                              contains: "Contains",
                              startswith: "Starts with",
                              eq: "Is equal to",
                              neq: "Is not equal to",
                              doesnotcontain: "Does not contain",
                              endswith: "Ends with",
                              isempty: "Is Empty",
                              isnotempty: "Is Not empty"
                            }
                        }
                    }
            },
            ],
            scrollable: true,
            sortable: true,
            filterable: true,
            reorderable: true,
            resizable: true
        }).data("kendoGrid");



        function taskTypesDropDownEditor(container, options) {
            $('<input required name="' + options.field + '"/>')
                .appendTo(container)
                .kendoDropDownList({
                    autoBind: false,
                    dataTextField: "typeName",
                    dataValueField: "taskTypeId",
                    dataSource: {
                        type: "json",
                        transport: {
                            read: "https://ssseng.com/controllers/taskTypeController.php"
                        }
                    }
                });
        }   
        function showDetails(e) {
            e.preventDefault();

            var dataItem = this.dataItem($(e.currentTarget).closest("tr"));
            wnd.content(detailsTemplate(dataItem));
            wnd.center().open();
        }

        function onError(e) {
            var files = e.files;
            for (var i = 0; i < files.length; i++) {

              var uid = files[i].uid;
              var entry = $(".k-file[data-uid='" + uid + "']");
              if (entry.length > 0) {
                entry.remove();
              }
            }
        }
 
        function fileUploadEditor(container, options) {
            $('<input type="file" id="files" name="files" />')
            .appendTo(container)
            .kendoUpload({
                async:{
                    saveUrl: "https://ssseng.com/controllers/taskControllerIcon.php",
                    autoUpload: true
                },
                validation: {
                    allowedExtensions: [".jpg", ".png"],
                    maxFileSize: 90000000,
                    minFileSize: 500
                },
                error: onError,
                progress: onProgress,
                upload: function (e) {
                    e.data = { 
                        taskId: options.model.taskId
                    };
                }
            });
        }
        function onProgress(e) {
            var files = e.files;
            if(e.percentComplete==100){
                $("#tasklist").data("kendoGrid").dataSource.read();
            }
        }
    
     
        function changeIcon(e) {
            e.preventDefault();

            var dataItem = this.dataItem($(e.currentTarget).closest("tr"));
            console.log(dataItem);
            alert(dataItem);
        }

    });
    
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

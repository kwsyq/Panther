<?php
/*
    in stanga 2 taburi, unul cu taskuri simple (ca acum) si unul cu tasktemplateuri sub forma de tree cu posibilitatea de a adauga ori unul ori altul
    din task template poti luat tot arborele sau doar un task simplu

    in dreapta se pot edita taskurile si parametri.

    elementele trebuie sa se poata combina. multiselect in loc de combo.

*/
?>


    <link rel="stylesheet" href="../styles/kendo.common.min.css" />
    <link rel="stylesheet" href="../styles/kendo.material-v2.min.css" />

<!-- Modal -->
<div class="modal fade" id="workordertasks" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document" style="max-width: 1200px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">WorkOrder Tasks</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="container-fluid my-6">
            <div class="row">
                <span class="col-sm-3">Element:</span>
                <select id="elementId" name="elementId" class="form-control col-sm-6">
                    <option value="1">Element 1</option>
                    <option value="2">Element 2</option>

                </select>
                <span class="col-sm-3">
                    <button class="btn btn-outline-warning btn-sm">Change Element</button>
                    <button class="btn btn-outline-primary btn-sm">Save</button>
                </span>
            </div>
        </div>
        <div class="card mt-3 pb-2">
            <div class="row">
                <div class="col-sm-6">
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Tasks</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Templates</a>
                                </li>
                            </ul>
                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                                    <div class="card mt-3" style="min-height: 600px; max-height: 600px; override-y: auto;">


                                        <div id="tasksList">
                                            <div class="demo-section wide k-content">
                                                <div class="treeview-flex">
                                                    <div id="treeview-kendo"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                    <div class="card mt-3" style="min-height: 600px; max-height: 600px; override-y: auto;">
                                        <div id="taskTemplateList">
                                            <div class="demo-section wide k-content">
                                                <div class="treeview-flex">
                                                    <div id="treeview-telerik"></div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                </div>
                <div class="col-sm-6">
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#wotasks" role="tab" aria-controls="home" aria-selected="true">WorkOrder Tasks</a>
                        </li>
                        </ul>
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">

                            <div class="card mt-3" style="min-height: 600px; max-height: 600px; override-y: auto;">
                                        <div id="workOrderTaskList">
                                            <div class="demo-section wide k-content">
                                                <div class="treeview-flex">
                                                    <div id="treeview-telerik-wo"></div>
                                                </div>
                                            </div>
                                        </div>
                            </div>
                            </div>
                        </div>
                </div>
            </div>


        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
    </div>
    </div>
  </div>
</div>




<!--
<!DOCTYPE html>
<html>
<head>
    <title></title>
    <link rel="stylesheet" href="styles/kendo.common.min.css" />
    <link rel="stylesheet" href="styles/kendo.default.min.css" />
    <link rel="stylesheet" href="styles/kendo.default.mobile.min.css" />

    <script src="js/jquery.min.js"></script>


    <script src="js/kendo.all.min.js"></script>



</head>
<body>
    <div id="example">
    <div class="demo-section wide k-content">
        <div id="demo-section-title" class="treeview-flex">
            <div>
                <h3>
                    Select nodes, folders and drag them between the TreeViews
                </h3>
            </div>
        </div>
        <div class="treeview-flex">
            <div id="treeview-kendo"></div>
        </div>
        <div class="treeview-flex">
            <div>
                <h4>Drag and Drop</h4>
            </div>
        </div>
        <div class="treeview-flex">
            <div id="treeview-telerik"></div>
        </div>
    </div>
    <script id="treeview" type="text/kendo-ui-template">

        # if (!item.items && item.spriteCssClass) { #
        #: item.text #
        <span class='k-icon k-i-close kendo-icon'></span>
        # } else if(!item.items && !item.spriteCssClass) { #
        <span class="k-sprite pdf"></span>
        #: item.text #
        <span class='k-icon k-i-close telerik-icon'></span>
        # } else if (item.items && item.spriteCssClass){ #
        #: item.text #
        # } else { #
        <span class="k-sprite folder"></span>
        #: item.text #
        # } #
    </script>

    <script>
        $("#treeview-kendo").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            dataSource: [{
                id: 1, text: "My Documents", expanded: true, spriteCssClass: "rootfolder", items: [
                    {
                        id: 2, text: "Kendo UI Project", expanded: true, spriteCssClass: "folder", items: [
                            { id: 3, text: "about.html", spriteCssClass: "html" },
                            { id: 4, text: "index.html", spriteCssClass: "html" },
                            { id: 5, text: "logo.png", spriteCssClass: "image" }
                        ]
                    },
                    {
                        id: 6, text: "Reports", expanded: true, spriteCssClass: "folder", items: [
                            { id: 7, text: "February.pdf", spriteCssClass: "pdf" },
                            { id: 8, text: "March.pdf", spriteCssClass: "pdf" },
                            { id: 9, text: "April.pdf", spriteCssClass: "pdf" }
                        ]
                    }
                ]
            }],
            dragAndDrop: true,
            checkboxes: {
                checkChildren: true
            },
            loadOnDemand: true
        });

        $("#treeview-telerik").kendoTreeView({
            template: kendo.template($("#treeview").html()),
            dataSource: [{
                id: 1, text: "My Documents", expanded: true, items: [
                    {
                        id: 2, text: "New Web Site", expanded: true, items: [
                            { id: 3, text: "mockup.pdf" },
                            { id: 4, text: "Research.pdf" },
                        ]
                    },
                    {
                        id: 5, text: "Reports", expanded: true, items: [
                            { id: 6, text: "May.pdf" },
                            { id: 7, text: "June.pdf" },
                            { id: 8, text: "July.pdf" }
                        ]
                    }
                ]
            }],
            dragAndDrop: true,
            checkboxes: true,
            loadOnDemand: true
        });
        // Delete button behavior
        $(document).on("click", ".kendo-icon", function (e) {
            e.preventDefault();
            var treeview = $("#treeview-kendo").data("kendoTreeView");
            treeview.remove($(this).closest(".k-item"));
        });
        $(document).on("click", ".telerik-icon", function (e) {
            e.preventDefault();
            var treeview = $("#treeview-telerik").data("kendoTreeView");
            treeview.remove($(this).closest(".k-item"));
        });
    </script>
    <style>
        @media screen and (max-width: 680px) {
            .treeview-flex {
                flex: auto !important;
                width: 100%;
            }
        }

        #demo-section-title h3 {
            margin-bottom: 2em;
            text-align: center;
        }

        .treeview-flex h4 {
            color: #656565;
            margin-bottom: 1em;
            text-align: center;
        }

        #demo-section-title {
            width: 100%;
            flex: auto;
        }

        .treeview-flex {
            flex: 1;
            -ms-flex: 1 0 auto;
        }

        .k-treeview {
            max-width: 240px;
            margin: 0 auto;
        }

        #treeview-kendo .k-sprite {
            background-image: url("../content/web/treeview/coloricons-sprite.png");
        }

        #treeview-telerik .k-sprite {
            background-image: url("../content/web/treeview/coloricons-sprite.png");
        }

        .demo-section {
            margin-bottom: 5px;
            overflow: auto;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .rootfolder {
            background-position: 0 0;
        }

        .folder {
            background-position: 0 -16px;
        }

        .pdf {
            background-position: 0 -32px;
        }

        .html {
            background-position: 0 -48px;
        }

        .image {
            background-position: 0 -64px;
        }

    </style>
</div>




</body>
</html>

    -->
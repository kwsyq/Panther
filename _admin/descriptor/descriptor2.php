<?php
/*  _admin/descriptor/descriptor2.php

    EXECUTIVE SUMMARY: Based on http://sssengwiki.com/New+element-descriptor+hierarchy, this provides a means to:
        1) Add a new descriptor subordinate to any other descriptor
        2) Change displayOrder of descriptors
        3) Delete descriptors. NOTE that this is always a soft delete, and you may choose whether or not to view deleted descriptors
           in this admin tool. Soft-deleted descriptors may still be referenced by elements in older jobs.
        4) Undelete descriptors.
        5) Manage icons, details, or tasks for any descriptor.
    
    NO DEFAULT INPUT. This is global.
    
    Other INPUT: Optional $_REQUEST['act']. Possible values:  
        * 'adddescriptor', which requires inputs: 
           * $_REQUEST['descriptorName']
           * $_REQUEST['parentId']
        * 'deletedescriptor', which requires input: 
           * $_REQUEST['descriptor2Id']
           * NOTE: deletion is (1) soft and (2) recursive.
        * 'undeletedescriptor', which requires input: 
           * $_REQUEST['descriptor2Id']
           
    >>>00043 JM tried adopting includes/header_admin2.php in this file 2020-09-21, but it seriously messes up the accordion presentation.
    Someone may want to revisit styles accordingly, but it clearly cannot be done as trivially as adopting that file.
        
*/

include '../../inc/config.php';

if ($act == "adddescriptor") {
    // Insert a new row in DB table descriptor2, with a displayorder that is 1 greater than 
    // the previous maximum displayorder for that parent. 
    // Then fall through and redisplay the page as usual.
    // >>>00016 validate inputs
    
    $descriptorName = isset($_REQUEST['descriptorName']) ? $_REQUEST['descriptorName'] : '';
    $parentId = isset($_REQUEST['parentId']) ? intval($_REQUEST['parentId']) : 0; // 0 is a perfectly good value here, means top-level.
    
    $descriptorName = trim($descriptorName);
    $descriptorName = substr($descriptorName, 0, 64); // >>>00002 truncates silently
    $descriptorName = trim($descriptorName);
    
    if (strlen($descriptorName)) {
        Descriptor2::add($descriptorName, $parentId); // not even looking at return: failure will log, that's all we need.
    }
    
    header("Location: descriptor2.php"); // Reload cleanly so command won't repeat on further reload (e.g. F5)
    die();
} // END if ($act == "adddescriptor")

// This is a recursive soft delete of rows in the Descriptor2 table, and should be read as part of the $act == "deletedescriptor" case below.
// INPUT $descriptor2 is a Descriptor2 object, with a 'children' property.
function deleteWithDescendants($descriptor2) {
    $children = $descriptor2->getChildren();
    foreach ($children AS $childDescriptor2) {
        deleteWithDescendants($childDescriptor2);
    }
    $descriptor2->setActive(false);
    $descriptor2->save();
}

if ($act == "deletedescriptor") {
    // >>>00016 need to validate inputs
    
    // Delete this descriptor and everything subordinate to it.
    // NOTE: deletion is (1) soft and (2) recursive.
    $descriptor2Id = isset($_REQUEST['descriptor2Id']) ? $_REQUEST['descriptor2Id'] : '';

    $descendants = Descriptor2::getDescriptors(true, $descriptor2Id);
    
    foreach ($descendants AS $childDescriptor2) {
        deleteWithDescendants($childDescriptor2);
    }
    
    $descriptor2 = new Descriptor2($descriptor2Id);
    $descriptor2->setActive(false);
    $descriptor2->save();
    
    header("Location: descriptor2.php"); // Reload cleanly so command won't repeat on further reload (e.g. F5)
    die();
} // END if ($act == "deletedescriptor")

if ($act == "undeletedescriptor") {
    // >>>00016 need to validate inputs
    
    // Unlike deletion, this is NOT recursive
    $descriptor2Id = isset($_REQUEST['descriptor2Id']) ? $_REQUEST['descriptor2Id'] : '';
    $descriptor2 = new Descriptor2($descriptor2Id);
    $descriptor2->setActive(true);
    $descriptor2->save();
    
    header("Location: descriptor2.php"); // Reload cleanly so command won't repeat on further reload (e.g. F5)
    die();
}

?>
<!DOCTYPE html>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <style>
        /* In support of accordion */
        a {
            text-decoration: none;
            color: inherit;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        ul.inner {
            padding-left: 1em;
            overflow: hidden;
            display: none;
        }
        ul.inner.show {
            display: block;
        }
        
        /* Show or hide soft-deleted descriptors */
        body.dont-show-deleted li.deleted {
            display: none;
        }
        body.show-deleted li.deleted {
            font-style: italic;
        }
        
        /* Back to the accordion */
        li {
            margin: .2em 0;
            white-space: nowrap;
        }
        li a.toggle {
            width: 70%;
            display: inline-block;
            color: #fefefe;
            padding: .25em;
            border-radius: 0.15em;
            transition: background .3s ease;
            position: relative;
            top: -15px;
        }
        li a.toggle {
            background: rgba(0, 0, 0, 0.78);
        }
        li a.leaf {
            background: rgba(0, 0, 255, 0.2); /* Much lighter background for a leaf */
        }
        body.not-moving li a.toggle:hover {
            background: rgba(99, 99, 99, 0.9); /* Assuming we are not changing a displayOrder, distinct background for hover */
        }

        a.updown-movethis, a.updown-movethis.toggle, a.updown-movethis.toggle:hover  {
            background: rgba(255, 0, 0, 0.4); /* pink for the descriptor we are moving */
        }
        
        /* hide arrows for adjusting displayOrder */
        body.dont-show-displayorder span.updown {
            display: none;
        }

        /* show arrows to allow user to adjust displayOrder */
        body.show-displayorder span.updown {
            display: inline;
        }

        button.delete, button.undelete, span.updown {
            position: relative;
            top: -15px; /* Strictly ad hoc: icons are tall, this isn't */
        }        
        
        body.moving span.updown {
            opacity: 0.0; /* Once we have selected a descriptor to move, we don't want to see the other arrows */
        }
        
        body.moving span.updown.updown-movethis {
            opacity: 1.0;
            color: red;  /* Once we have selected a descriptor to move, we want its arrow to turn red */
        }
        
        li.mark-above {
            border-top: 2px solid red; /* Position to move descriptor whose displayOrder is being changed. */
        }
        li.mark-below {
            border-bottom: 2px solid red; /* Position to move descriptor whose displayOrder is being changed. */
        }

        iframe {
            position:relative; 
            left:25px; 
            width:90%;
        }
        span.updown {
            cursor:pointer; /* to indicate it can be clicked */
            font-weight:bold;
        }
    </style>
</head>
<body bgcolor="#ffffff" class='not-moving dont-show-deleted dont-show-displayorder'>
<h2>Descriptors</h2>
<div id="left-side" style="float:left; width:50%; overflow:auto">
<input type="checkbox" id="show-deleted" />&nbsp;<label for="show-deleted">Show deleted descriptors</label>&nbsp;<input
    type="checkbox" id="show-displayorder" />&nbsp;<label for="show-displayorder">Show arrows to change display order</label><br />
<div id="left-side-main" style="height:90vh; overflow:auto">
<?php
    // for HTML formatting
    function indent($n) {
        $ret = '';
        while ($n-- > 0) {
            $ret .= '    ';
        }
        return $ret;
    }
    
    // Recursively writing nested UL / UI structure for descriptor2s.
    // >>>00001 We probably hang explicit $descriptor2Id more places than we need to.
    // It would be fine if we tightened that, but it would need more machinations in the code.
    function writeHTMLRecursive($descriptor, $level=0) {
        $descriptor2Id = $descriptor->getDescriptor2Id();
        $deleted = !$descriptor->getActive();
        $children = $descriptor->getChildren();
        echo indent(2*$level + 1) . '<li ' .
             ($deleted ? 'class="deleted"' : '') .
             'data-displayorder="' . $descriptor->getDisplayOrder() . '" ' . 
             'data-descriptor2id="' . $descriptor2Id . '">' . "\n";
        echo indent(2*$level + 2) . '<span class="updown">&updownarrow;&nbsp;</span>&nbsp;';       
        echo indent(2*$level + 2) . '<img class="icon" id="icon_' . $descriptor2Id . '" ' .
              'src="/cust/' . CUSTOMER . '/img/icons_desc/d2_' . $descriptor2Id . '.gif?t=' . rand(1000000,9000000) . '" ' .
              'width="40" height="40" />' . "\n";
              
        echo indent(2*$level + 2) . '<a rel="nofollow noreferrer" '. 
             'class="toggle load-pane' . ($children ? '' : ' leaf') . '" ' .
             'data-descriptor2id="' . $descriptor2Id . '" ' .
             'id="descriptor2_' . $descriptor2Id . '" href="javascript:void(0);">' . 
             $descriptor->getName() . '</a>' . "\n";
        if ($deleted) {
            echo indent(2*$level + 2) . '<button class="undelete" data-descriptor2id="' . $descriptor2Id . '" >Undelete</button>' . "\n";
        } else {
            echo indent(2*$level + 2) . '<button class="delete" data-descriptor2id="' . $descriptor2Id . '" >Delete</button>' . "\n";
        }
        echo indent(2*$level + 2) . '<ul id="inner_' . $descriptor2Id . '" class="inner">' . "\n";
        
        foreach ($children as $child) {
            writeHTMLRecursive($child, $level+1);
        }
        
        // Plus a form to add a new child.
        echo indent(2*$level + 3) . '<li class="adddescriptor">' . "\n";
        echo indent(2*$level + 4) . '<form class="adddescriptor" action="descriptor2.php" method="post">' . "\n";
        echo indent(2*$level + 5) . '<input type="hidden" name="act" value="adddescriptor"  />' . "\n";
        echo indent(2*$level + 5) . '<input type="hidden" name="parentId" value="' . $descriptor2Id . '"  />' . "\n";
        echo indent(2*$level + 5) . '<input type="text" name="descriptorName" value="" />' . "\n";
        echo indent(2*$level + 5) . '<input type="submit" value="Add descriptor under ' . $descriptor->getName() . ' " />' . "\n";
        echo indent(2*$level + 4) . '</form>' . "\n";
        echo indent(2*$level + 3) . '</li>' . "\n";
        echo indent(2*$level + 2) . '</ul>' . "\n";
        echo indent(2*$level + 1) . '</li>' . "\n";
    } // END function writeHTMLRecursive   
    
    $descriptors = Descriptor2::getDescriptors();
    if ($descriptors === false) {
        echo 'Getting descriptors failed in ' . __FILE__ . ', please contact administrator or developer.';
    } else {
        echo '<ul class="accordion">' . "\n";
        foreach ($descriptors as $descriptor) { 
            writeHTMLRecursive($descriptor);
        }
        echo indent(1) . '<li>' . "\n";     
        echo indent(2) . '<form action="descriptor2.php" method="post">' . "\n";
        echo indent(3) . '<input type="hidden" name="act" value="adddescriptor"  />' . "\n";
        echo indent(3) . '<input type="hidden" name="parentId" value="0"  />' . "\n";
        echo indent(3) . '<input type="text" name="descriptorName" value="" />' . "\n";
        echo indent(3) . '<input type="submit" value="Add top-level descriptor" />' . "\n";
        echo indent(2) . '</form>' . "\n";
        echo indent(1) . '</li>' . "\n";
        echo '</ul>' . "\n";
    }
    
?>
</div> <!-- end left-side-main -->
</div> <!-- end left-side -->
<div id="right-side" style="height:100vh; width:50%; float:left;">
&nbsp;&nbsp;<span id="iframe-selector"></span><br />
<iframe id="iframe-right" src=""></iframe>
</div>

<script>
function showOrHideDeleted() {
    if ($('#show-deleted').is(':checked')) {
        $('body').addClass('show-deleted');
        $('body').removeClass('dont-show-deleted');
    } else {
        $('body').addClass('dont-show-deleted');
        $('body').removeClass('show-deleted');
    }
}

// Click to show/hide deleted descriptors
$('#show-deleted').change(function() {
    showOrHideDeleted();
});

function showOrHideDisplayOrder() {
    if ($('#show-displayorder').is(':checked')) {
        $('body').addClass('show-displayorder');
        $('body').removeClass('dont-show-displayorder');
    } else {
        $('body').addClass('dont-show-displayorder');
        $('body').removeClass('show-displayorder');
    }
}

// Click to show/hide handles to change display order
// NOTE that elsewhere in the code, this button will be "locked" at checked=true 
//  while we are actually changing a display order
$('#show-displayorder').change(function() {
    showOrHideDisplayOrder();
});

// Save state of #show-deleted, #show-displayorder, accordion, and scrolling
//  so we can restore it after page reload
function saveState() {
    let showDeleted = $('#show-deleted').is(':checked');
    let showDisplayOrder = $('#show-displayorder').is(':checked');
    let $shown = $('ul.inner.show');
    let shown = [];
    $shown.each(function() {
        shown.push($(this).attr('id'));
    });
    let scrollTopMain = $(document).scrollTop();
    let scrollTopLeftSide = $('#left-side-main').scrollTop();
    
    sessionStorage.setItem('adminDescriptor2_showDeleted', (showDeleted ? 'true' : 'false'));
    sessionStorage.setItem('adminDescriptor2_showDisplayOrder', (showDisplayOrder ? 'true' : 'false'));
    sessionStorage.setItem('adminDescriptor2_shown', JSON.stringify(shown));
    sessionStorage.setItem('adminDescriptor2_scrollTopMain', scrollTopMain);
    sessionStorage.setItem('adminDescriptor2_scrollTopLeftSide', scrollTopLeftSide);
}

// Restores state saved with saveState(), then deletes it from sessionStorage 
function restoreState() {
    let showDeleted = sessionStorage.getItem('adminDescriptor2_showDeleted') == 'true';
    let showDisplayOrder = sessionStorage.getItem('adminDescriptor2_showDisplayOrder') == 'true';
    let shown = JSON.parse(sessionStorage.getItem('adminDescriptor2_shown'));
    let scrollTopLeftSide = sessionStorage.getItem('adminDescriptor2_scrollTopLeftSide');
    let scrollTopMain = sessionStorage.getItem('adminDescriptor2_scrollTopMain');
    
    if (showDeleted) {
        $('#show-deleted').prop('checked', true);
        showOrHideDeleted();        
    }
    if (showDisplayOrder) {
        $('#show-displayorder').prop('checked', true);
        showOrHideDisplayOrder();        
    }
    for (let i in shown) {
        let id = shown[i];
        $('#'+id).addClass('show');
    }
    $(document).scrollTop(scrollTopMain);
    $('#left-side-main').scrollTop(scrollTopLeftSide);

    sessionStorage.removeItem('adminDescriptor2_showDeleted');
    sessionStorage.removeItem('adminDescriptor2_showDisplayOrder') == 'true';
    sessionStorage.removeItem('adminDescriptor2_shown');
    sessionStorage.removeItem('adminDescriptor2_scrollTopLeftSide');
    sessionStorage.removeItem('adminDescriptor2_scrollTopMain');
}

// "Delete" buttons

// This is separated out so we can intersperse a dialog before doing multiple deletions.
// We use INPUT $deleteButton to find the row to delete.
function deletePerButton($deleteButton) {
    saveState();
    // Build form dynamically and self-submit
    $('<form action="descriptor2.php" method="post">' +
        '<input type="hidden" name="act" value="deletedescriptor" />' +
        '<input type="hidden" name="descriptor2Id" value="' + $deleteButton.data('descriptor2id') + '" />' + 
        '</form>').appendTo('body').submit();
}

$('button.delete').click(function() {
    var $this = $(this);
    let numChildren = $this.closest('li').find('ul li').not('.deleted').not('.adddescriptor').length;
    if (numChildren) {
        let $popup = $('<div>This descriptor has ' + 
            (numChildren == 1 ? 'an undeleted child. If you delete this and change your mind you will have to undelete that separately. ' : 
                numChildren + ' undeleted children. If you delete this and change your mind you will have to undelete them one by one. ') + 
            'Do you still want to delete?</div>');
        $popup.dialog({
            resizable: false,
            height: "auto",
            width: 400,
            modal: true,
            buttons: {
                "Delete": function() {
                    deletePerButton($this); // NOTE $this is the ORIGINAL delete button that launched the dialog.
                    $(this).dialog( "close" );
                },
                "Cancel": function() {
                    $(this).dialog( "close" );
                }
            }
        });
    } else {
        deletePerButton($this);
    }
});

// "Undelete" buttons
// We use the button to find the row to delete 
$('button.undelete').click(function(){
     // Build form dynamically and self-submit
     var $this = $(this);
     saveState();
     $('<form action="descriptor2.php" method="post">' +
         '<input type="hidden" name="act" value="undeletedescriptor" />' +
         '<input type="hidden" name="descriptor2Id" value="' + $this.data('descriptor2id') + '" />' + 
         '</form>').appendTo('body').submit();
});

// "Add" button has data & a real form, but similar need to save the state
$('form.adddescriptor').submit(function() {
    saveState();
    // and let the 'submit' proceed
});

// Clicking a node to load right pane
$('.load-pane').click(function(e) {
    e.preventDefault();      
    var $this = $(this);
    
    if ($('body').hasClass('not-moving')) { // we don't want to load pane while we are trying to move a descriptor up or down
        loadIframeSelector(
            '/_admin/descriptor/icon.php?type=d2&id=' + $this.data('descriptor2id') + '&name=' + $this.text(),        
            '/_admin/descriptor/detail.php?descriptor2Id=' + $this.data('descriptor2id') + '&name=' + $this.text(),
            '/_admin/descriptor/task.php?descriptor2Id=' + $this.data('descriptor2id') + '&name=' + $this.text() 
        );
    }
});

// Typically these will be the same links affected by the $('.load-pane').click event handler. Both will trigger.
$('.toggle').click(function(e) {
    e.preventDefault();    
    var $this = $(this);
    
    if ($('body').hasClass('not-moving')) { // we don't want to toggle while we are trying to move a descriptor up or down
        /* Handle the accordion toggling */
        $nextLevel = $this.closest('li').children('ul');
        if ($nextLevel.hasClass('show')) {
            $nextLevel.removeClass('show');
        } else {
            $nextLevel.addClass('show');
        }
    }
});

function loadIframeSelector(iconURL, detailsURL, tasksURL) {
    let priorSelection = $('input[type="radio"][name="iframe-choice"]:checked').attr('id');
    if (!priorSelection) {
        priorSelection = 'icon';
    }
    $('#iframe-selector').html(
        '<input type="radio" name="iframe-choice" id="icon" data-url="' + iconURL + '"' + (priorSelection == 'icon' ? ' checked' : '') + '/>&nbsp;' +
            ' <label for="icon">Icon</label>&nbsp;&nbsp;' +
        '<input type="radio" name="iframe-choice" id="details" data-url="' + detailsURL + '"' + (priorSelection == 'details' ? ' checked' : '') + '/>&nbsp;' +
            ' <label for="details">Details</label>&nbsp;&nbsp;' +
        '<input type="radio" name="iframe-choice" id="tasks" data-url="' + tasksURL + '"' + (priorSelection == 'tasks' ? ' checked' : '') + '/>&nbsp;' +
            ' <label for="tasks">Tasks</label>&nbsp;&nbsp;'
        );
    $('#iframe-right')[0].src = $('input[type="radio"][name="iframe-choice"]:checked').data('url');

    $('input[type="radio"][name="iframe-choice"]').change(function(e){
        $('#iframe-right')[0].src = $('input[type="radio"][name="iframe-choice"]:checked').data('url');
    });
}

// Clicking to move (change displayOrder)
$('span.updown').click(function() {
    let $this = $(this);
    
    let $currentMoving = $('body.moving span.updown.updown-movethis');
    if ($currentMoving.length && ! $this.is($currentMoving)) {
        return; // We are currently moving a different descriptor, so this selection is disabled. We will fall through and treat this as 
                // positioning the displayOrder, just like if you clicked elsewhere in the containing LI element. 
    }
    
    let $this_li = $this.closest('li');
    let descriptor2IdToMove = $this_li.data('descriptor2id');
    $this_li.addClass('updown-movethis');
    $this_li.children('a').addClass('updown-movethis');
    $this_li.children('span.updown').addClass('updown-movethis');
    $('body').addClass('moving').removeClass('not-moving');
    $('#show-displayorder').prop('disabled', true); // It's *necessarily* checked right now, don't let it be unchecked.
    
    // Bit of a cheat here: update the tooltip:
    $("#expanddialog").html("Setting display order for this node");
    
    $('<div id="updown-instructions" style="position:fixed; top:10px; left:250px; background-color:rgba(255, 255, 102, 1.0); border:2px solid black;">' + 
        'Move cursor, then click to change display order.<br />ESC to cancel.</div>').appendTo('body');
    // Wait 3 seconds, then fade out for .3 seconds & remove
    window.setTimeout(
        function() {
            $('#updown-instructions').fadeOut(300, function() {
                $(this).remove();
            })
        },            
        3000
    );
    
    // sibling items (descriptor2s) including self 
    let $allSiblings = $this_li.siblings('li');
    // Last sibling is always the one to add a new descriptor, we don't want that.
    $allSiblings = $allSiblings.not(':last'); 
    
    $allSiblings.addClass('updown-sibling');
    
    $this_li.prevAll().on('mouseenter.updown mousemove.updown', event, function() {
        let $this2 = $(this);
        $allSiblings.removeClass('mark-above').removeClass('mark-below'); // leaves $this2 alone
        $this2.addClass('mark-above');
    });
    $this_li.nextAll()
        .not(':last') // Last sibling is always the one to add a new descriptor, we don't want that.
        .on('mouseenter.updown mousemove.updown', event, function() {
            let $this2 = $(this);
            $allSiblings.removeClass('mark-above').removeClass('mark-below'); // leaves $this2 alone
            $this2.addClass('mark-below');
        });
    $allSiblings.on('mouseleave.updown', function() {
        $(this).removeClass('mark-above').removeClass('mark-below');
    });
    
    $this_li.prevAll().on('click.updown', function() {
         changeDisplayOrder(descriptor2IdToMove, 'before', $(this).data('displayorder'));
         closeUpDown(); // not really needed because we'll reload, but clearer.
    });
    $this_li.prevAll().on('click.updown', function() {
         closeUpDown(); // another way to cancel moving: click on the descriptor itself
    });
    $this_li.nextAll().on('click.updown', function() {
         changeDisplayOrder(descriptor2IdToMove, 'after', $(this).data('displayorder'));
         closeUpDown(); // not really needed because we'll reload, but clearer.
    });
    
    $('body').on('keydown.updown', function(e) {
        if (e.key == "Escape") {
            closeUpDown();
        }
    });
    
    $('span.updown.updown-movethis').on('click.updown', closeUpDown);
    
    // turn this all back off
    function closeUpDown() {
        $('#updown-instructions').remove();
        $allSiblings.removeClass('updown-sibling').removeClass('mark-above').removeClass('mark-below');
        $('*').off('.updown');
        $this_li.removeClass('updown-movethis');
        $this_li.children('a').removeClass('updown-movethis');
        $this_li.children('span.updown').removeClass('updown-movethis');
        $('body').removeClass('moving').addClass('not-moving');
        // Bit of a cheat here: update the tooltip:
        $("#expanddialog").html("Click to set display order");
        $('#show-displayorder').prop('disabled', false); // It's necessarily checked right now, let it be unchecked.
    }
});

// INPUT descriptor2IdToMove - identifies what we are moving
// INPUT beforeOrAfter - whether it is before or after the displayOrder indicated in the next parameter
// INPUT relativeTo - current displayOrder of the descriptor2 we've said to place it immediately before or immediately after.
function changeDisplayOrder(descriptor2IdToMove, beforeOrAfter, relativeTo) {
    before = beforeOrAfter == 'before';
    $.ajax({
        url: '/_admin/ajax/changedescriptor2displayorder.php',
        data: {
            descriptor2IdToMove: descriptor2IdToMove,
            before: (before ? 1 : 0),
            relativeTo: relativeTo
        },
        async: false,
        type: 'post',
        context: this,
        success: function(data, textStatus, jqXHR) {
            if (data['status']) {
                if (data['status'] == 'success') {
                    saveState(); // Want to have same state of various settings (e.g. accordion) after reloading.
                    window.location.href='descriptor2.php';
                } else {
                    alert(data['error']);
                }
            } else {
                alert('error no status');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('error in AJAX protocol or in called function, see server log');
        }
    }); // END ajax: clonelocation.php
}

function layout() {
    $('#iframe-right').height(0.85*$(window).height());
}

$('#iframe-right').attr('src', "");

$(function() {
    layout();
});

$(window).resize(function() {
    layout();
});

// Deliberately global function, to be called from a child window
function iconRefresh(descriptor2Id) {
    $("#icon_" + descriptor2Id).attr('src', '/cust/<?php echo CUSTOMER; ?>/img/icons_desc/d2_' + descriptor2Id + '.gif?t=' + 
        (Math.floor(Math.random() * 8000000) + 1000000) );  // roughly equivalent to PHP rand(1000000,9000000)
}

// Make tooltips for the updown arrows using a 
// variant on the "expand" approach described at http://sssengwiki.com/Tooltips
// because for some damned reason I couldn't get it to work in a straightforward manner - JM 2020-01
$(function() {
    $( document ).on('mouseenter', 'body.not-moving span.updown, body.moving span.updown.updown-movethis', function() {
        let $this = $(this);
        let thisPosition = $this.offset();
            
        if ($this.is('.updown-movethis')) {
            $("#expanddialog").html("Setting display order for this node").show();;
        } else {
            $("#expanddialog").html("Click to set display order").show();
        }
        $( "#expanddialog" ).offset({
            left: thisPosition.left,
            top: thisPosition.top-20
        });
    });
    $( "span.updown" ).mouseleave(function() {
        $( "#expanddialog" ).hide();
    });
});

$(function() {
    // Restore state on "document ready"
    restoreState();
});


</script>
<div id="expanddialog" style="background-color:#ffffe0; display:none; width:auto; height:auto; position:absolute; border:1px solid yellow"></div>

</body>
</html> 
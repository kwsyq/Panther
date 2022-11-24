<?php
/*  _admin/employee/administrators.php

    EXECUTIVE SUMMARY: Page to administer the list of administrators.
    At least as of 2019-06-10, this is independent of anything about "permissions": this is about access to the admin side of the system.
    
    >>>00032 This may be combined into _admin/employee/employee.php once we have that back together (though we still would have to deal with
    the tricky case of a non-employee admin). Meanwhile, I (JM) needed somewhere to stick this code & this seemed as good as any.
*/
require_once '../../inc/config.php';

?>
<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">	
</head>
<body>
    <h2>Administrators</h2>
    <p>This page allows you to administer who has access to the administration side of the system.</p>
    <?php
        $administrators = Administrator::getAllAdmins($customer);
        
        if ($administrators === false) {
            echo '<p>Cannot get list! Please contact a developer</p>';
        } else {
            echo '<table border="0" cellpadding="3" cellspacing="2">';
                echo '<thead>';
                    echo '<tr>';
                        echo '<th>Username</th>';
                        echo '<th>Person</th>';
                        echo '<th colspan="2">Take action</th>';
                    echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                    foreach ($administrators as $administrator) {
                        echo '<tr>';
                            // "Username"
                            echo '<td class="username" >' . $administrator['username'] . '</td>';
                            
                            // "Person"
                            $userObjectExists = !!($administrator['user']);
                            if ($userObjectExists) {
                                // >>>00006 Very klugy way to get a link to the person page, not immediately sure how
                                // to do this cleanly, please clean it up if you know how.
                                $personLink = REQUEST_SCHEME . '://' . HTTP_HOST . '/person/' . rawurlencode($user->getUserId());
                            }
                            echo '<td>' . ($userObjectExists ? 
                                                "<a href=\"$personLink\" target=\"_blank\">[PERSON PAGE]</a>" : 
                                                '<span style="color:red">(none)</span>') . 
                                 '</td>';
                            
                            // "Take action" (2 columns)
                            echo '<td><button class="delete-admin" />Remove admin</button></td>';
                            echo '<td><button class="modify-password" />Modify password</button></td>'; // only the button name is different, action is the same
                            
                        echo '</tr>';
                    }
                    echo '<tr>';
                        echo '<td><button id="add-admin">Add administrator</button></td>';
                        echo '<td colspan="3">&nbsp;</td>';
                    echo '</tr>';
                echo '</tbody>';
            echo '</table>';
        }
    ?>
    <script>
        $('#add-admin').click(function() {
            var $this = $(this);
            var $thisRow = $this.closest('tr');
            
            // if this section is not already built...
            if ($('#new-admin').length == 0) {
                var html = '<tr>' +
                               '<td><input type="text" id="new-admin" placeholder="NEW ADMIN"/></td>' +
                               '<td><input type="text" id="new-password" placeholder="NEW PASSWORD"/></td>' +
                               '<td><button id="confirm-new-admin">Go</button></td>' +
                               '<td colspan="1">&nbsp;</td>' +
                           '</tr>';
                html += '<tr>' +
                            '<td colspan="2" align="center"><b>OR</b></td>' +
                            '<td colspan="2">&nbsp;</td>' +
                        '</tr>';
                html += '<tr>' +
                            '<td colspan="2"><input type="text" id="line-for-htpasswords" size="40" placeholder="OUTPUT OF encryptpassword.php"/></td>' +
                            '<td>' + 
                                '<button id="confirm-new-line">Go</button>&nbsp;&nbsp;' +
                                '<a href="" id="encryptpassword-info">' + 
                                    '<img src="/cust/<?php echo $customer->getShortName(); ?>/img/icons/icon_info.gif" width="18" height="18"/>' +
                                '</a>' +    
                            '</td>' +
                            '<td colspan="1">&nbsp;</td>' +
                        '</tr>';
                $thisRow.before(html);
                
                $('#confirm-new-admin').click(function() {
                    var username = $('#new-admin').val().trim();
                    if (!username) {
                        alert ('Empty admin name');
                        return;
                    }
                    if (username.indexOf(' ') >=0) {
                        alert ('Cannot have a space in the admin name');
                        return;
                    }

                    // >>>00006: could put further requirements on password
                    $.ajax({
                        url: '../ajax/usernameexists.php',
                        data:{ username: username},
                        async: false,
                        type: 'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {
                            if (data['exists']==='true') {
                                letUserConfirmAnyOverwrite(username, function() {                    
                                    var password = $('#new-password').val().trim();
                                    if (password.length < 8) {
                                        alert ('Password must be at least 8 characters');
                                        return;
                                    }
                                    // >>>00006: could put further requirements on password
                                    $.ajax({
                                        url: '../ajax/addadmin.php',
                                        data:{ username: username, password: password},
                                        async: false,
                                        type: 'post',
                                        context: this,
                                        success: function(data, textStatus, jqXHR) {
                                            if (data['status']) {    
                                                if (data['status'] == 'success') {    
                                                    window.location.reload(); // refresh the page. 
                                                } else {    
                                                    alert(data['error']);    
                                                }    
                                            } else {
                                                alert('error no status');
                                            }
                                        },
                                        error: function(jqXHR, textStatus, errorThrown) {
                                            alert('AJAX error calling _admin/ajax/addadmin.php');
                                        }
                                    });
                                }); // END letUserConfirmAnyOverwrite
                            } else if (data['exists']==='false') {
                                alert('There is no user with username "' + username + '"');
                            } else {
                                alert('error cannot determine whether this username exists');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('AJAX error calling _admin/ajax/usernameexists.php');
                        }
                    });
                }); // END $('#confirm-new-admin').click
                
                $('#new-admin, #new-password').on('keyup', function(event) {
                    var $this = $(this);
                    if (event.keyCode == 10 || event.keyCode == 13) {
                        // Carriage return. If both values are valid, consider this a "go"
                        if ($('#new-admin').val().trim() && $('#new-password').val().trim()) {
                            $('#confirm-new-admin').click();
                        } else if ($this.is('#new-admin')) {
                            // treat this like tabbing
                            $('#new-password').focus();
                        }
                    }
                });
                
                $('#confirm-new-line').click(function() {
                    var lineForHTPasswords = $('#line-for-htpasswords').val().trim();
                    var colonAt = lineForHTPasswords.indexOf(':');
                    var spaceAt = lineForHTPasswords.indexOf(' ');
                    if (colonAt < 0) {
                        alert('string is invalid, must contain a colon');
                        return;
                    }
                    if (spaceAt != -1) {
                        alert('string is invalid, cannot contain any spaces');
                        return;
                    }
                    if (colonAt == 0) {
                        alert('string appears to have an empty username');
                        return;
                    }
                    if (colonAt == lineForHTPasswords.length-1) {
                        alert('string appears to have an empty encrypted password');
                        return;
                    }
                    var username = lineForHTPasswords.substr(0, colonAt);  
                    $.ajax({
                        url: '../ajax/usernameexists.php',
                        data:{ username: username},
                        async: false,
                        type: 'post',
                        context: this,
                        success: function(data, textStatus, jqXHR) {
                            if (data['exists']==='true') {
                                letUserConfirmAnyOverwrite(lineForHTPasswords.substr(0, colonAt), function() {
                                    // >>>00006 could probably validate more than that....
                                    $.ajax({
                                        url: '../ajax/addadmin.php',
                                        data:{ lineForHTPasswords: lineForHTPasswords},
                                        async: false,
                                        type: 'post',
                                        context: this,
                                        success: function(data, textStatus, jqXHR) {
                                            if (data['status']) {
                                                if (data['status'] == 'success') {    
                                                    window.location.reload(); // refresh the page. 
                                                } else {    
                                                    alert(data['error']);    
                                                }    
                                            } else {
                                                alert('error no status');
                                            }
                                        },
                                        error: function(jqXHR, textStatus, errorThrown) {
                                            alert('AJAX error calling _admin/ajax/addadmin.php');
                                        }
                                    });
                                }); // END letUserConfirmAnyOverwrite
                            } else if (data['exists']==='false') {
                                alert('There is no user with username "' + username + '"');
                            } else {
                                alert('error cannot determine whether this username exists');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('AJAX error calling _admin/ajax/usernameexists.php');
                        }
                    });
                }); // END $('#confirm-new-line').click
                
                $('#line-for-htpasswords').on('keyup', function(event) {
                    var $this = $(this);
                    if (event.keyCode == 10 || event.keyCode == 13) {
                        // Carriage return. If value is of reasonable length, treat this as "go"
                        if ($this.val().trim().length > 15) {
                            $('#confirm-new-line').click();
                        }
                    }
                });
                
                $('#encryptpassword-info').on('click', function(event) {
                    var $this = $(this);
                    event.preventDefault();
                    if ($("#encryptpassword-info-dialog").length) {
                        // dialog already exists
                        $("#encryptpassword-info-dialog").dialog('open');
                    } else {
                        $('<div id="encryptpassword-info-dialog">' +
                            '<p>This feature allows you to invite a user to set their admin password without you ever seeing it.</p>' +
                            '<p>To use this feature, send an email telling the person to use ' +
                            '<?php echo REQUEST_SCHEME.'://'.HTTP_HOST.BASEDIR; ?>/encryptpassword.php, ' +
                            'then email you the string it constructs. When you enter that string at left and click "Go", they will be ' +
                            'set up as an admin with their chosen password.</p>' +
                            '</div>').dialog({
                                autoOpen: true,
                                title: 'Using encryptpassword.php',
                                modal: true,
                                closeOnEscape: true,
                                width: 400,
                                buttons: {
                                    "OK": function() {
                                        $("#encryptpassword-info-dialog").dialog('close');
                                    }
                                }
                            });
                    }
                });
            }
            $('#new-admin').focus();
        }); // END $('#add-admin').click
        
        $('.delete-admin').click(function() {
            var $this = $(this);
            var username = $this.closest('tr').find('td.username').text().trim();
            if ($("#delete-admin-dialog").length) {
                // dialog already existed, possibly for a different admin; we'll want to replace it.
                $("#delete-admin-dialog").dialog('destroy').remove();
            }
            $('<div id="delete-admin-dialog"></div>').html('<p>Remove administrator rights for ' + username + '?</p>').dialog({
                autoOpen: true,
                title: 'Delete administrator',
                modal: true,
                closeOnEscape: true,                
                buttons: {
                    "OK": function() {                        
                        $.ajax({
                            url: '../ajax/deleteadmin.php',
                            data:{ username: username},
                            async: false,
                            type: 'post',
                            context: this,
                            success: function(data, textStatus, jqXHR) {
                                if (data['status']) {    
                                    if (data['status'] == 'success') {    
                                        window.location.reload(); // refresh the page. 
                                    } else {    
                                        alert(data['error']);    
                                    }    
                                } else {
                                    alert('error no status');
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                alert('AJAX error calling _admin/ajax/deleteadmin.php');
                            }
                        });
                    },
                    "Cancel": function() {
                        $("#delete-admin-dialog").dialog('close');
                    }
                }
            });
        }); // END $('.delete-admin').click

        $('.modify-password').click(function() {
            var $this = $(this);
            var username = $this.closest('tr').find('td.username').text().trim();
            if ($("#modify-password-dialog").length) {
                // dialog already existed, possibly for a different admin; we'll want to replace it.
                $("#modify-password-dialog").dialog('destroy').remove();
            }
            $('<div id="modify-password-dialog"></div>').html('<p>New password for ' + username + '</p>' +
                '<input type="text" id="modified-password" placeholder="NEW PASSWORD"/>').dialog(
            {
                autoOpen: true,
                title: 'Modify password',
                modal: true,
                closeOnEscape: true,                
                buttons: {
                    "OK": okAction,
                    "Cancel": function() {
                        $("#modify-password-dialog").dialog('close');
                    }
                }
            });
            $('#modified-password').on('keyup', function() {
                if (event.keyCode == 10 || event.keyCode == 13) {
                    // Carriage return. Consider this the same as clicking "OK".
                    okAction();
                }
            });
            function okAction() {
                var password = $('#modified-password').val().trim();
                if (password.length < 8) {
                    alert ('Password must be at least 8 characters');
                    return;
                }
                // >>>00006: could put further requirements on password
                $.ajax({
                    url: '../ajax/addadmin.php', // This can also be used to modify an existing admin
                    data:{ username: username, password: password},
                    async: false,
                    type: 'post',
                    // no context, that's OK
                    success: function(data, textStatus, jqXHR) {
                        if (data['status']) {    
                            if (data['status'] == 'success') {    
                                alert('(This alert is just to confirm that the password was changed)'); // no need to reload page, nothing visible changes
                                $("#modify-password-dialog").dialog('close');
                            } else {    
                                alert(data['error']);    
                            }    
                        } else {
                            alert('error no status');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert('AJAX error calling _admin/ajax/addadmin.php');
                    }
                });
            }
        }); // END $('.modify-password').click
        
        // Check whether an admin already exists; let the user decide whether to continue anyway
        // INPUT username
        // INPUT callback: function to call if admin does not exist or user says "overwrite"
        // RETURN: true if admin exists AND user wants to bail out
        //         false if admin does not exist OR user says "overwrite anyway"
        function letUserConfirmAnyOverwrite(username, callback) {
            $.ajax({
                url: '../ajax/adminexists.php',
                data:{ username: username},
                async: false,
                type: 'post',
                context: this,
                success: function(data, textStatus, jqXHR) {
                    if (data['exists'] == 'true') {
                        if ($("#overwrite-admin-dialog").length) {
                            // dialog already existed, possibly for a different admin; we'll want to replace it.
                            $("#overwrite-admin-dialog").dialog('destroy').remove();
                        }
                        $('<div id="overwrite-admin-dialog">Admin "' + username + '" already exists. Overwrite?</div>').dialog({
                            autoOpen: true,
                            title: 'Admin already exists',
                            modal: true,
                            closeOnEscape: true,
                            width: 400,
                            buttons: {
                                "Overwrite": function() {
                                    $("#overwrite-admin-dialog").dialog('close');
                                    callback();
                                },
                                "Cancel": function() {
                                    $("#overwrite-admin-dialog").dialog('close');
                                }
                            }
                        });
                    } else {
                        callback();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('AJAX error calling _admin/ajax/adminexists.php ');
                }
            });
        } // END letUserConfirmAnyOverwrite
    </script>
</body>
<html>
<?php
?>
<?php
/*  encryptpassword.php

    EXECUTIVE SUMMARY: 
    Encrypt a password for use in the apache passwords file.
    
    INPUTS: NONE 
*/
require_once './inc/config.php';
include_once './includes/header.php';
echo "<script>\ndocument.title = 'Encrypt Passwords';\n</script>\n";
?>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<h2>Encrypt password</h2>
<p>This page allows you to enter your username and password to generate an encrypted password to
give you access to the Administrator section of the system. When this is completed, you can email the output to a current
administrator or sysadmin, who can add you to the system without having to know the "clear" of your password.</p>
<!-- no form as such, we do it all with jQuery -->
<table><tbody>
    <tr>
        <th align="right">Username (typically an email address):&nbsp;</th>
        <td>
            <input type="text" id="username" value='' />
        </td>
    </tr>
    <tr>
        <th align="right">New password:&nbsp;</th>
        <td>
            <input type="password" id="password1" value='' />
        </td>
    </tr>    
    <tr>
        <th align="right">Repeat password:&nbsp;</th>
        <td>
            <input type="password" id="password2" value='' />
        </td>
    </tr>    
    <tr>
        <td colspan="2"><button id="submit">Submit</button></th>
    </tr>    
</tbody></table>
<div id="status" style="font-weight:8">&nbsp;</div>
<script>
    $('#submit').click(function() {
        var username = $('#username').val();
        var password1 = $('#password1').val();
        var password2 = $('#password2').val();
        if (!username) {
            $('#status').html('Must provide username');
            return;
        }
        if (password1.length < 8) {
            $('#status').html('Password must be at least 8 characters');
            return;
        }
        if (password1 != password2) {
            $('#status').html('Passwords don\'t match');
            return;
        }
        if (password1 != password2) {
            $('#status').html('Passwords don\'t match');
            return;
        }
        $.ajax({
            url: '../ajax/encryptpassword.php',
            data:{ username: username,
                   password: password1
            },
            async: false,
            type: 'post',
            success: function(data, textStatus, jqXHR) {
                if (data['status']) {    
                    if (data['status'] == 'success') {
                        $('#status').html('<p>Copy the following and paste it into an email to the administrator:</p>\n' +
                                          '<p>"' + data['forPasswordFile'] + '"</p>');
                    } else {    
                        $('#status').html('<p>Server-side error: ' + data['error'] + '</p>');     
                    }    
                } else {
                    $('#status').html('<p>Server-side error, no status returned</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#status').html('<p>AJAX error</p>');
            }
        });
    });

    $('#username, #password1, #password2').on('keyup', function(event) {
        var $this = $(this);
        if (event.type == 'keyup' &&  (event.keyCode === 10 || event.keyCode === 13) ) {
            // Carriage return & they all have values, submit
            if ($('#username').val().length && $('#password1').val().length && $('#password2').val().length) { 
                $('#submit').click();
            } else if ($this.is('#username')) {
                $('#password1').focus();
            } else if ($this.is('#password1')) {
                $('#password2').focus();
            }
        }
    });
    
</script>
<?php
include_once './includes/footer.php';
?>
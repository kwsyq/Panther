<?php 
/* /error.php

    EXECUTIVE SUMMARY: Implements error page to end user with error Message and unique ID of the error.

    PRIMARY INPUT: $_SESSION["errorId"].
    For the given errorId we have a message stored in $_SESSION["error_message"], this message will be display to the end user.
    The relevant unique ID of the error ought to be part of that page, so the end user has something they can easily report to the 
    dev/support side, the errorId is pull from the Log.

    Note: User must close this web page from browser and go back in application.

    Note: In the scenario of an error generated in an Iframe, we set $_SESSION["iframe"], 
    in this case the user is advised to: Close the page and try again!

 
*/
session_start();
if(isset($_SESSION["errorId"])) {
?>
    <html>
        <head>
            <title>Panther Error Page </title>
        </head>
        <body>
        
            <div style="padding:15%; text-align:center;">
                <h2> Error found in input: <?php echo $_SESSION["error_message"] . "<br/><br/>". " ErrorId: " . $_SESSION["errorId"]; ?></h2>
                <?php if(isset($_SESSION["iframe"])) {  ?>
                    <h2> <br/><br/>Close the page and try again!<h2>
                <?php } else{?>
                    <h2>Go to the Home page and try again!<h2>
            </div>
            <div style="margin-top:-140px; text-align:center;">
               <a id="linkHome" class="button" href="/" > Home</a>
            </div>
            <?php }?>
        </body>
    </html>
<style>
.button {
  background-color: #008CBA; /* Blue */
  border: none;
  color: white;
  padding: 12px 30px;
  text-decoration: none;
  display: inline-block;
  font-size: 18px;
  border-radius: 3px;
}
</style>
<?php
} else {
    echo "All Good!";
}
unset($_SESSION["errorId"]);
unset($_SESSION["error_message"]);
unset($_SESSION["iframe"]);
?>
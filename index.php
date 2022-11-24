<?php 
/*  PAGE. (Top level). 
    EXECUTIVE SUMMARY Not much used, because for logged-in users panther.php is effectively the home page. 
    Still, at least as of 2019-03, a lot of errors near top of PAGE files redirect here rather than to Panther.
    Unlike panther.php, you can access this page without being logged in.

    If you are not logged in header offers Home meaning index.php, About Us, Our Services, Contact Us, Panther. Panther requires a login.

    If you are logged in, offers the same header (Home meaning Panther, Open WOs nn, Comp Open WOs, WO No Invoice, Crumbs) as Panther. 
    Unlike the useful searches, etc. on Panther, it just gives a big graphic. There is code for a search 
    ($_REQUEST['act']='search', $_REQUEST['q'] triggers some sort of search), but no apparent way to trigger the search.
*/

require_once './inc/config.php';

/*
 [BEGIN Commented out by Martin before 2019] 
 * just testing sending mail
 * 
 *
$mail = new SSSMail();
$mail->setFrom('intake@ssseng.com', 'Sound Structural Solutions');
$mail->addTo('martin@allbsd.com', 'Martin');


$mail->setSubject('test mail');

//$mail->setBodyHtml('');
$mail->setBodyText('hi there nnnew zend path');

$result = $mail->send();

if ($result){
	echo "ok";
} else {
	echo "fail";
}
[END Commented out by Martin before 2019]
*/

$crumbs = new Crumbs(null, isset($user) ? $user : null);

$jobs = array();
if ($act == 'search') {	
	$q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';	
	$search = new Search('mypage', $person);	
	$results = $search->search($q);
	if (isset($results['jobNumbers'])) {
		if (is_array($results['jobNumbers'])) {
			$jobs = $results['jobNumbers'];
		}	
	}
}

include_once BASEDIR . '/includes/header.php';
?>
<script>
    document.title = '<?php echo str_replace("'", "\'", CUSTOMER_NAME); ?>';
</script>

<div id="container" class="clearfix home" >
    <img src="/cust/<?php echo $customer->getShortName(); ?>/img/home_banner.jpg" border="0" />			
</div>
	
<?php 
include_once BASEDIR . '/includes/footer.php';
?>

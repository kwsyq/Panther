<?php 
	require_once './inc/config.php';
	require_once './inc/perms.php';


	require_once './inc/perms.php';
	
	$crumbs = new Crumbs(null, $user);

/*	$db=DB::getInstance();

	$rows=$db->query("call GetInfosJob()")->fetch_all(MYSQLI_ASSOC);

	print_r($rows);
*/
?>
  <link rel="stylesheet" href="https://kendo.cdn.telerik.com/2022.2.621/styles/kendo.default-main.min.css">
<link href="https://unpkg.com/tailwindcss@^2.2.7/dist/tailwind.min.css" rel="stylesheet">

  <script src="https://kendo.cdn.telerik.com/2022.2.621/js/jquery.min.js"></script>
  <script src="https://kendo.cdn.telerik.com/2022.2.621/js/kendo.all.min.js"></script>

<div class="container-fluid mx-3">
	<div id="grid"></div>

</div>

<script >
	
$(document).ready(function(){
	$("#grid").kendoGrid({
     dataSource: {
         transport: {
             read: "/ajax/getJobs.php"
         },
         schema: {
             data: "detail"
         }
     }
});

})	
</script>
<?php 

 ?>
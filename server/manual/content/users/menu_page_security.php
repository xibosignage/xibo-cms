<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<title><?php echo PRODUCT_NAME; ?> Documentation</title>
		<link rel=stylesheet type="text/css" href="../../css/doc.css">
		<meta http-equiv="Content-Type" content="text/html" />
		<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />

		<link href="img/favicon.ico" rel="shortcut icon"/>
		<!-- Javascript Libraries -->
		<script type="text/javascript" src="lib/jquery.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
	</head>

	<body>
		<h2>Page &amp; Menu Security</h2>
		<p><?php echo PRODUCT_NAME; ?> system adminstrator has full control on access right for each user/group on the system. The security components 
    	that are assigned/unassigned to the user/group determine the permissions of the individual or 
    	users belonging to that group.</p>
	
		<p>The list of components available is managed by the <?php echo PRODUCT_NAME; ?> software and cannot be changed.</p>
	
		<p>Editing which components are assigned to is done by ticking the checkboxes next to the assignment to be changed.<br />
    	Note: It is a toggle operation, therefore if the assignment is "unassigned" it will become "assigned" and visa versa.
		Currently assigned rows are indicated with a green mark and unassigned with a red mark.</p>	  

    <blockquote>
    	<a name="Page_Security" id="Page_Security"></a><h3>Page Security</h3>
		<p>Click "Page Security" button for the user you want to edit. A "Page Security" form is loaded for editing.</p>

    	<p><img alt="User Page Security" src="user_page_security.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="510" height="390"></p>

    	<p>User access to the following pages can be individually enabled/disabled. Check one or more items 
    	 and click "Assign/Unassign" button to toggle.</p>
    	<ul>
			<li>Schedule</li>
			<li>Homepage and Login</li>
			<li>Layouts</li>
			<li>Content</li>
			<li>Displays</li>
			<li>Users and Groups</li>
			<li>Report</li>
			<li>License and Settings</li>
			<li>Updates</li>
			<li>Template</li>
			<li>Web Services</li>
			<li>DataSets</li>
		</ul>

		<a name="Menu_Security" id="Menu_Security"></a><h3>Menu Security</h3>
		<p>To add a user click on the "Add User" button found at the top left of the User Administration page. Fill in <strong>all</strong> the 
		fields and click "Save" to add the user.</p>

	  	<p><img alt="User Menu Security" src="user_menu_security.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="510" height="390"></p>
     
   		<p>User view to the following menus can be individually enabled/disabled. First select the Navigation Menu, then check one or more of 
    	the sub-category menu items and click "Assign/Unassign" button to toggle.</p>
    	<ul>
			<li>Administration Menu</li>
			<li>Advanced Menu</li>
			<li>Dashboard</li>
			<li>Design Menu</li>
			<li>Display Menu</li>
			<li>Library Menu</li>
			<li>Top Nav</li>
		</ul>
    </blockquote>

	<?php include('../../template/footer.php'); ?>
	</body>
</html>

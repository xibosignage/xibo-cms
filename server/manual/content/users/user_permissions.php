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
		
		<h1>Permissions Model</h1>
		<h2>Introducing an Improved Permissions Model</h2>

    <p>This section will take a look at the improvements made to the permissions model in <?php echo PRODUCT_NAME; ?>. <?php echo PRODUCT_NAME; ?> has improved the permissions model 
    in one key way - allowed users and administrators to set view, edit and delete permissions on:</p>

    <ul>
    <li>Library Media</li>
    <li>Layouts</li>
    <li>Regions in Layouts</li>
    <li>Media on Region Timelines</li>
    <li>DataSets</li>
    <li>Display Groups</li>
    <li>Displays</li>
    </ul>
 
    <p><img alt="User Permissions" src="user_permissions.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="357" height="306"></p>
	   
    <p>All of these items have the permissions set in the same way, via a simple to use dialog showing groups and users. 
    In addition to this all permissions are validated with each form load, or save button clicked so you can guarentee 
    "real time" modifications to the permissions on items.</p>

    <p>Here are some key facts about the new system:</p>
    <ul>
    <li>The Highest permission is used (if a user belongs to 2 groups, one has edit permissions and the other doesn't, 
    the user will have edit permissions)</li>
    <li>Permissions are tested with each transaction</li>
    <li>Permissions on an item can only be changed by the owner or a super administrator (for displays and display groups that have 
    no owner, this is super admin only)</li>
    </ul>

    <p>These permissions are then reflected in all tables, forms, lists and on the layout designer. The improved permissions hopefully 
    will make it easier to manage <?php echo PRODUCT_NAME; ?>, particularly in environments where there are many users accessing the system.</p>	   

	<?php include('../../template/footer.php'); ?>
	</body>
</html>

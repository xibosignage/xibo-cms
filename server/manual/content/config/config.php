<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
<head>
  	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  	<title><?php echo PRODUCT_NAME; ?> Documentation</title>
  	<link rel="stylesheet" type="text/css" href="../../css/doc.css">
	<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
	<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />  <link href="img/favicon.ico" rel="shortcut icon">
  	<!-- Javascript Libraries -->
  	<script type="text/javascript" src="lib/jquery.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
</head>

<body>
	<h1 lang="en-GB" class="western">Configuring <?php echo PRODUCT_NAME; ?></h1>
	
	<p>After the Installtion process the <?php echo PRODUCT_NAME; ?> Client must be registered with the
	<?php echo PRODUCT_NAME; ?> Admin Panel. This is done using the <?php echo PRODUCT_NAME; ?> Options application.</p>
	
	<p><a href="client.php">Please click here for information regarding the <?php echo PRODUCT_NAME; ?>
	Options and configuring the <?php echo PRODUCT_NAME; ?> client application.</a></p>
	
	<h2>Admin Panel Settings</h2>
	
	<p>The <?php echo PRODUCT_NAME; ?> admin panel can be customised in a number of ways using the
	settings pages. These settings pages are available only to users with
	administrator privilages.</p>
	
	<p><a href="settings.php">Please click here for information regarding the
	<?php echo PRODUCT_NAME; ?> Admin Panel Settings.</a></p>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

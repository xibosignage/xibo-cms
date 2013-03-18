<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>

<html>
<head>
  	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  	<title><?php echo PRODUCT_NAME; ?> Documentation</title>
  	<link rel="stylesheet" type="text/css" href="../../css/doc.css">
	<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
	<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />
  	<link href="img/favicon.ico" rel="shortcut icon">
  	<!-- Javascript Libraries -->
  	<script type="text/javascript" src="lib/jquery.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
</head>

<body>
<?php if (! HOSTED) { ?>
	<h1>Server Installation</h1>
	
	<p>Uncompress the distribution archive on a webserver that meets the requirements.</p>
	<p>Using a web browser, visit http://your.server/path-to-<?php echo PRODUCT_NAME; ?>/install.php</p>
	<p>The installer will help you to check and ensure that <?php echo PRODUCT_NAME; ?> has everything it needs to run. It will also create a database and a new user account.</p>
<?php } ?>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

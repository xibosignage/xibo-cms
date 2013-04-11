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
		<h1>Templates</h1>

		<h2>Overview</h2>

  		<p>Templates allow a user to save a layouts generic properties so that it can be used 
  		to create the same layout with different content. This allows you to create a library of 'Pre-built' layouts for your
  		system.</p>

  		<p><?php echo PRODUCT_NAME; ?> default templates provided are shown below. The dimensions follow commonly available monitor display sizes.</p>

   		<p><img alt="Template_std" src="template_standard.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="810" height="272"></p>

  		<p>Refer to <a href="template_resolution.php">Template Resolution</a> on how to add custom resoluton.</p>

		<?php include('../../template/footer.php'); ?>
	</body>
</html>

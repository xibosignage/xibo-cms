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
	<h1><?php echo PRODUCT_NAME; ?> Client Installation</h1>

	<h2>Windows .Net Client</h2>
	<p>Double click the <?php echo PRODUCT_NAME; ?>Client.msi installation file to start the install process.</p>


	<blockquote>
	<h3>Step 1</h3>

	<p>You may see the following security warning when installing <?php echo PRODUCT_NAME; ?>.</p>

	<p><img alt="Security Warning" src="securitywarning.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="404" height="293"></p>

	<p>Please click on "Run" to begin the installation.</p>

	<h3>Step 2</h3>

	<p>The installer presents a welcome screen:</p>

	<p><img alt="Welcome Screen" src="setup1.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="499" height="392"></p>
	
	<p class="figure_text">Please press "Next".</p>

	<h3>Step 3</h3>

	<p>Choose the location where <?php echo PRODUCT_NAME; ?> should be installed. The default location
	should normally be sufficient, however to change the location click
	browse.</p>

	<p><img alt="Install Location" src="setup2.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="499" height="392"></p>

	<p>After making the selection (or if leaving to the default) click "Next" to continue.</p>

	<h3>Step 4</h3>

	<p>A confirmation message is then shown</p>

	<p><img alt="Begin Installation Confirmation" src="setup3.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="499" height="392"></p>

	<p>When happy with the selections made click "Install" to begin. Otherwise click "Back" to correct any errors.</p>

	<h3>Step 5</h3>

	<p>The Installation is complete. Click finish to exit.</p>

	<p><img alt="Install Complete" src="setup4.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="499" height="392"></p>
	</blockquote>

	<p><a href="../config/config.php">Please click here to proceed to the
	configuration section of this manual.</a></p>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

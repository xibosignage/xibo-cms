<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('template.php'); ?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<title><?php echo PRODUCT_NAME; ?> Documentation</title>
		<link rel=stylesheet type="text/css" href="css/doc.css">
		<meta http-equiv="Content-Type" content="text/html" />
		<meta name="keywords" content="digital signage, signage, narrow-casting, xibo, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />
		<link href="img/favicon.ico" rel="shortcut icon"/>
		<!-- Javascript Libraries -->
		<script type="text/javascript" src="lib/jquery.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
	</head>
	<body>
		
		<h1><?php echo PRODUCT_NAME; ?> Documentation</h1>
		
		<p>Thank you for using <?php echo PRODUCT_NAME; ?>.</p>
		<p>This documentation applies to <?php echo PRODUCT_NAME; ?> Version <?php echo PRODUCT_VERSION; ?></p>

		<h2>Getting Help</h2>
		<p>The official <?php echo PRODUCT_NAME; ?> FAQ is here: <a href="<?php echo PRODUCT_FAQ_URL; ?>" target="_blank"><?php echo PRODUCT_FAQ_URL; ?></a></p>
		<p>If you would like any further help with the information contained in this document, or the software package
		in general, please visit: <a href="<?php echo PRODUCT_SUPPORT_URL; ?>" title="<?php echo PRODUCT_NAME; ?> Support"  target="_blank"><?php echo PRODUCT_SUPPORT_URL; ?></a>.
		
		<h2>License</h2>
		<p><?php echo PRODUCT_NAME; ?> is released under the Affero GNU Public License v3 or any later version.</p>

<?php include('template/footer.php'); ?>
	</body>
</html>

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

<frameset cols="310,*" frameborder="1" framespacing="3"
 border="3">
  <frame src="toc.php" name="index" marginheight="5" marginwidth="0">
  <frame src="intro.php" name="main" marginheight="5" marginwidth="10">
</frameset>

<noframes>
	<body>
		This site only works with frames, please enable frames.
	</body>
</noframes>

</html>

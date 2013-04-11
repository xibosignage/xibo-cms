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
  	<script type="text/javascript" src="lib/jquery.pack.js"> </script>
  	<script type="text/javascript" src="lib/jquery.dimensions.pack.js"> </script>
  	<script type="text/javascript" src="lib/jquery.ifixpng.js"> </script>
</head>

<body>
	<a name="Webpage" id="Webpage"></a><h2>Web Page</h2>

	<blockquote>

	<h3> Webpage scale functionality in Windows Client</h3>
	<p>In <?php echo PRODUCT_NAME; ?> it is possible to show a webpage in a region. Because websites are not build for showing on a big screen, 
	it has a scale functionality in which you can show the webpage in a larger or smaller scale. This is great for 
	showing websites that are too big or too small for the region.</p>

	<p>Add a webpage</p>

	<ul>
		<li>Click the "Add Webpage" icon</li>
		<li>A new dialogue will appear:

		<p><img alt="Ss_layout_designer_add_webpage" src="Ss_layout_designer_add_webpage.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="477" height="232"></p></li>

		<li>Enter all the required information</li>
		<ul>
      		<li><strong>Link:</strong><br />The linked content can be either the full webpage, or an ID defined frame within the webpage
      		e.g. http://www.intl.onkyo.com/#rotateim</li>
      		<li><strong>Duration:</strong><br />Display duration in seconds</li>
      		<li><strong>Offset Top:</strong><br />Webpage top cropping region for display</li>
      		<li><strong>Offset Left:</strong><br />Webpage left cropping region for display</li>
      		<li><strong>Scale Percentage:</strong><br />Scale the original webpage for display (larger or smaller)</li>
     		 <li><strong>Backgroup Transparency:</strong><br />Set background to transparency (python only)</li>
    	</ul>
		<li>Click "Save"</li>
	</ul>
	</blockquote>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

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
	<a name="Image" id="Image"></a><h2>Image</h2>

	<p>This form allows you to add an image to the timeline. Currently JPEG, PNG, BMP and GIF files are supported. 
	Transparency is supported in PNG and GIF files, (but NOT if used as background images).</p>

<blockquote>
	<p>Add an image</p>
	<ul>
		<li>Click the "Add Image" icon</li>
		<li>A new dialogue will appear:

		<p><img alt="Ss_layout_designer_add_image" src="Ss_layout_designer_add_image.png"
			style="display: block; text-align: center; margin-left: auto; margin-right: auto"
			width="458" height="288" border="1px"></p></li>

		<li>Click "Browse" and navigate to the directory to choose image.</li>
		<li>Select the image file you want to upload from your computer. Click OK. This will proceed to upload the 
			content directly to the <?php echo PRODUCT_NAME; ?> servers.</li>
		<li>While the file uploads, give the image a name for use inside <?php echo PRODUCT_NAME; ?>. Type the name in the "Name" box.</li>
		<li>Finally enter a duration in seconds that the image should remain in the region until the next media item should appear.<br />
			<i>Note that if this is the only media item in a region, then this is the minimum amount of time the image will be shown 
			for as the total time shown will be dictated by the total run time of the longest-running region on the layout.</i></li>
		<li>Click "Save"</li>
	</ul>
</blockquote>	

	<?php include('../../template/footer.php'); ?>
</body>
</html>

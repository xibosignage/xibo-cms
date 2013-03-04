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
  	<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
</head>

<body>
	<h1 lang="en-GB" class="western">Layouts</h1>
	<div id="tutorial_icon">
	<a href="../schedule/video_tutorial_overview.html">
    <img src="../../img/video_icon.png" alt="Video Tutorial" width="100px"><br />Video Tutorial</a>
	</div>
	<h2 lang="en-GB" class="western">Overview</h2>

	<p lang="en-GB" class="western">Layouts are the concept <?php echo PRODUCT_NAME; ?> uses to create
	the visual aspect of your digital signage. Layouts are used to enforce a look or
	branding on the display and allow for multiple pieces of information to be
	presented on the display at any one time. Using regions and layouts, it
	provides the ability to group items of content together into an &ldquo;order
	of play&rdquo;. The advantage of this is you are able to synchronise
	different types of content and guarantee that they will be displayed on the
	screen at the same time.</p>

	<p lang="en-GB" class="western">Each Region on your layout has a playlist.
	Playlists allow you to group together any number of different content types
	together into a list which will be displayed in sequence.</p>

	<h2>Layout Creation</h2>

	<p>In this section you will learn how to</p>
	<ul>
	  	<li>Create a Layout</li>
  		<li>Add a background to your layout</li>
  		<li>Learn the importance of setting the correct aspect ratio</li>
  		<li>Adding a Region</li>
  		<li>Populating a Region with content</li>
  		<li>Understanding the Playlist timeline</li>
	</ul>

	<h2>Notice for Opera Users</h2>

	<p>The Opera browser does not allow <?php echo PRODUCT_NAME; ?> to substitute its own context menu when right-clicking in the layout designer. 
	It is therefore not possible to perform certain operations in the Opera Browser. Users are advised to use Mozilla 
	Firefox, Google Chrome, Safari or Internet Explorer instead as these browsers do not have such limitation.</p>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

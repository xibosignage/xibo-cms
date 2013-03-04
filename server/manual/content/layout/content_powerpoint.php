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
	<a name="PowerPoint" id="PowerPoint"></a><h2>PowerPoint</h2>
	<p>You can upload your Microsoft PowerPoint files to show on a <?php echo PRODUCT_NAME; ?> layout.<br />
	<i>Note: PowerPoint media is not supported when a Python Client is used.</i>
	</p>

<blockquote>	
	<p>Add a PowerPoint Presentation</p>
	<ul>
		<li>First prepare the PowerPoint Presentation. PowerPoint will, by default, put scroll bars up the side of your presentation, 
			unless you do the following for each PowerPoint file BEFORE you upload it:
		<ul>
			<li>Open your PowerPoint Document</li>
			<li>Slide Show -&gt; Setup Show</li>
			<li>Under "Show Type", choose "Browsed by an individual (window)" and then untick "Show scrollbar"</li>
			<li>Click OK</li>
			<li>Save the Presentation</li>
			<li>Note also that <?php echo PRODUCT_NAME; ?> will not advance the slides in a Presentation, so you should record automatic slide timings by going 
				to "Slide Show -&gt; Rehearse Timings" and then saving the presentation.</li>
		</ul></li>
		
		<li>Once your PowerPoint file is prepared, click the "Add PowerPoint" icon</li>
		<li>A new dialogue will appear:<br />

		<p><img alt="Ss_layout_designer_add_PowerPoint" src="Ss_layout_designer_add_PowerPoint.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="458" height="288" border="1px"></p></li>
	
		<li>Click "Browse"</li>
		<li>Select the PowerPoint file you want to upload from your computer. Click OK</li>
		<li>While the file uploads, give the presentation a name for use inside <?php echo PRODUCT_NAME; ?>. Type the name in the "Name" box.</li>
		<li>Finally enter a duration in seconds that you want the presentation to play for.<br />
			<i>Note that if this is the only media item in a region, then this is the minimum amount of time the presentation will be 
			shown for as the total time shown will be dictated by the total run time of the longest-running region on the layout.</i></li>
		<li>Click "Save"</li>
		</ul>
</blockquote>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

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
	<a name="Video" id="Video"></a><h2>Video</h2>
	<p>You can upload your videos to show on a <?php echo PRODUCT_NAME; ?> layout. Currently WMV, AVI, and MPEG video files are supported.</p>
	<p>If your client player also supports other video types e.g. mp4, you may include them in the "Valid Extensions" in 
	<a href="../admin/modules.php">Administration->Modules</a> section under "Video" media content.</p>

	<p><i>Note: In Ubuntu Linux, the maximum upload media file size can be configured in /etc/php5/apache2/php.ini by
	changing the "upload_max_filesize" as shown below:<br />
	; Maximum allowed size for uploaded files.<br />
	upload_max_filesize = 200M</i></p>

	<blockquote>
 	<p>Add a video</p>
 	<ul>
 		<li>Click the "Add Video" icon</li>
 		<li>A new dialogue will appear:

		<p><img alt="Ss_layout_designer_add_video" src="Ss_layout_designer_add_video.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="458" height="288" border="1px"></p></li>

		<li>Click "Browse"</li>
		<li>Select the video file you want to upload from your computer. Click OK</li>
		<li>While the file uploads, give the video a name for use inside <?php echo PRODUCT_NAME; ?>. Type the name in the "Name" box.</li>
		<li>Finally enter a duration in seconds that you want the video to play for, or 0 to play the entire video.<br />
		<i>Note that if this is the only media item in a region, then this is the minimum amount of time the video will be shown for as 
			the total time shown will be dictated by the total run time of the longest-running region on the layout. Videos do not loop 
			automatically so you need to add a second media item in the region with the video to cause it to play again.</i></li>
		<li>Click "Save". <br />
			<i>Note:The save button will NOT be enabled until transfer of the file to the server is completed.</i></li>
	</ul>
	</blockquote>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
<head>
  	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  	<title><?php echo PRODUCT_NAME; ?> Documentation</title>
  	<link rel="stylesheet" type="text/css" href="../../css/doc.css">
	<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
	<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />  <link href="img/favicon.ico" rel="shortcut icon">
  	<!-- Javascript Libraries -->
  	<script type="text/javascript" src="lib/jquery.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
</head>

<body>
	<h1>Client Options</h1>
	
	<p>All newly installed client must be configured and registered with the <?php echo PRODUCT_NAME; ?> Server before it can be used. The <?php echo PRODUCT_NAME; ?> Configuration
	Options are accessible on each client installation from the Start Menu -&gt; All programs</p>
	
	<p><img alt="Program Menu" src="pm.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="230" height="62" border="1px"></p>
	
	<p>Select "<?php echo PRODUCT_NAME; ?> Client Options" to register this display on the <?php echo PRODUCT_NAME; ?> Network; or to
	make changes to this display configuration.</p>
	
	<h2>Client Options Form</h2>
	
	<p>The default - after install - options form is shown below.</p>
	
	<blockquote>
	<h3>Options - General</h3>
	
	<p><img alt="<?php echo PRODUCT_NAME; ?> General Settings" src="settings_gen.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="485" height="393"></p>
	
	<br />
	<ul>
	<li>Server Address: Fill in the address of your <?php echo PRODUCT_NAME; ?> server address. If your <?php echo PRODUCT_NAME; ?> server is on the same machine as the client, 
	enter "http://localhost/<?php echo PRODUCT_NAME; ?>". If <?php echo PRODUCT_NAME; ?> server is installed on a different computer, enter the IP address or hostname of the 
	machine the <?php echo PRODUCT_NAME; ?> server is installed on - for example "http://192.168.0.4/<?php echo PRODUCT_NAME; ?>" or "http://www.my-server.com/<?php echo PRODUCT_NAME; ?>" or similar.<br /><br /></li>

	<li>Server Key: Enter your server key in the "Server Key" box. If you cannot remember the key you can find it in the 
 	Settings dialogue on the <a href="../admin/settings.php#server_key">Administration->Settings</a> menu in the
  	web interface on the server.<br /><br /></li>

	<li>Local Library Location: defaults to a folder called "<?php echo PRODUCT_NAME; ?> Library" It is used to cache content from the <?php echo PRODUCT_NAME; ?> server so that
	the client can continue to play if the connection to the <?php echo PRODUCT_NAME; ?> server is lost. If you want to change to a different folder, use 
	the "Browse" button to choose an alternative folder. The library folder must be given write access right to the <?php echo PRODUCT_NAME; ?> client.
	<br />
	Note that you must NOT use the server's library location (if it is on the same PC as the client or via a file share)<br /><br /></li>

	<li>The collection interval for content: is the interval in seconds that the client will poll the server for new content. The more
	frequent the collections, the quicker the client will update when changes are made on the server - but at the expense of bandwidth 
	and possibly minor freezes in things like scrolling text when the collection happens. We don't recommend values lower than 60 seconds.<br /><br /></li>

	<li>The unique key for this client: is a unique identifier for this client machine. It is generated from a mix of Windows system identifiers and
	your hardware. If you are installing for the first time, there is no need to amend this value. If you are changing hardware or want to have
	two clients using the same server account then you can edit the key as required.<br /><br /></li>

	<li>Scroll Step Amount (px): is the number of pixels scrolling text will advance each time scrolling text items are told to move. You should
	leave this set to 1px for smooth viewing.<br /><br /></li>

	<li>Enable Powerpoint: Tick the box if you have the full version of PowerPoint 2003 or later installed and want to use PowerPoint media items.
	Be sure to read the notes on Powerpoint setup here: <a href="windows.php"> Windows Modifications.</a><br /><br /></li>

	<li>Enable Statistics: Tick the box if you want the client to send statistics back to the <?php echo PRODUCT_NAME; ?> server. This will generate alot of data that will be 
	stored in the <?php echo PRODUCT_NAME; ?> database. If you don't have any specific use for statistics, we recommend you turn this option off.

	<p>Click the "Save" button.</p></li>	
	</ul>

	<h3>Options - Proxy</h3>
	
	<p>If you use a proxy server to access your <?php echo PRODUCT_NAME; ?> server, go to the "Proxy" server tab and fill in the details for your network, 
	then click "Save". Make sure to set your proxy information in Internet Explorer too.</p>
	
	<p><img alt="<?php echo PRODUCT_NAME; ?> General Settings" src="settings_proxy.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="485" height="393"></p>	
	
	<h3>Options - Apperance</h3>
	
	<p>Click "Apperance" tab to set the client display window size, and the offsets from the screen origin if so required.</p>
	<p>When the specified client display window size i.e. width &amp; height is different from the original layout intended size, some 
		of the content will be cropped. e.g. embbedded html, linked html, dataset table etc.</p>
	<p>You may use the "Offset X' i.e. set equal to the primary display width, to place the client diplay window on an extended display monitor.</p>
	
	<p><img alt="<?php echo PRODUCT_NAME; ?> General Settings" src="settings_app.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="485" height="393"></p>
	
	<p>The default <?php echo PRODUCT_NAME; ?> Splash screen is display when the client is first launch. You may specify your own Splash Image by entering
	   the image filename in the "Override Splash Screen" box.</p>
	
	<h3>Options - Advanced</h3>
	
	<p><img alt="<?php echo PRODUCT_NAME; ?> General Settings" src="settings_adv.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="485" height="393"></p>
	
	<h3>Options - Register</h3>
	
	<p>Finally go to the "Register" display tab.</p>
	
	<p><img alt="<?php echo PRODUCT_NAME; ?> General Settings" src="settings_reg.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="485" height="393"></p>
	
	<br />
	<p>Optionally rename the client by entering a name in the "Display Name" box. It defaults to the hostname of the PC.
	 Click the "Register" button.</p>
	  
	<p>You should see a message "Display registered and awaiting licensing". If you don't get that message, ensure you entered
	   the correct URL for the <?php echo PRODUCT_NAME; ?> server, and that your server key is entered correctly.</p>
	</blockquote>
	     
	<h2>Client License Enable</h2>
	 
	 <p>Now log in to the server web interface, go to the <a href="../admin/displays.php">&quot;Display-&gt;Displays&quot;</a> page. 
	  You should see your newly registered client in the list. Click the "Edit" button next to the display. The "License Display" 
	  option will automatically change to "Yes". Optionally select a different default layout (the layout the client will play 
	  if nothing is scheduled).</p>
	
	<p>Click "Save" </p>
	
	<h2>Start Client Player</h2>
	
	<p>You can now start the <?php echo PRODUCT_NAME; ?> Client Player. It should show you the <?php echo PRODUCT_NAME; ?> splash screen while the default layout and its media contents
	   (and anything else you have scheduled) are downloaded and then begin playing layouts'contents.

	<?php include('../../template/footer.php'); ?>
</body>
</html>

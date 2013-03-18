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
	<a name="shellcommand" id="shellcommand"></a><h2>Shell Command</h2>

<blockquote>

	<p><?php echo PRODUCT_NAME; ?> Shell Command allows a command with arguments to be specified - this command is then executed on the client 
  	when it appears in a layout in the schedule. This can be used to achieve almost anything by using batch files, 
  	external programs, etc.</p>
	
  	<p><?php echo PRODUCT_NAME; ?> Shell Command provides entry boxes for both the Windows and linux command pending on
    the system where <?php echo PRODUCT_NAME; ?> resides.</P>

	<p>Add Shell Command</p>

	<ul>
		<li>Click the "Add Shell Command" icon</li>
		<li>A new dialogue will appear:

		<p><img alt="Ss_layout_designer_add_shellcommand" src="Ss_layout_designer_add_shellcommand.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="457" height="257"></p></li>

		<li>Enter the approprite command for your sytem on which <?php echo PRODUCT_NAME; ?> runs on.</li>
		<li>Click "Save"</li>
	</ul>

  	<h3>Shell Command Usage</h3>
  	<p>In <?php echo PRODUCT_NAME; ?> client, Shell Command adds ability to control system power management options, and run external commands based
    on the layout's activity. This allows a great deal of flexibility in power options on client-side</p>
    
    <p>e.g. you can have a client hibernate and wake up at noon every day to present lunch related signage. Before it hibernates 
    it can turn off the TV/Display.</p> 
 
  	<p>Using shell command, user can create a special template called "turn off display", which can be scheduled to turn off displays.
    With <?php echo PRODUCT_NAME; ?> client attached to a display, there is a couple of API calls that can be used to ask ACPI to send calls to the 
    video card to trigger monitor power suspension.</P>

  	<p>HDMI-CEC: This is a bus that is implemented on nearly all new large screen TVs that have HDMI connectors. This bus (which 
    is physically connected within normal HDMI cables) supports control signals that can perform power-on, power off, 
    volume adjust, selection of video source and many of the features that are accessible via the TV's remote control. It can 
    also control most other hardware on the HDMI bus.</p>  


  	<p>In <?php echo PRODUCT_NAME; ?> client, user can disallow power management from timing out the display EXCEPT when the "turn off display" layout is being
  	used - this covers cases where <?php echo PRODUCT_NAME; ?> is using a regular computer monitor (and Windows already provides power management).</p>
  
  	<p>By adding two options to <?php echo PRODUCT_NAME; ?> client i.e. "command to run to turn off display" and "command to run to turn on display". 
    These would be used to run custom executables. This would allow full customization of actions on a per-client basis, 
    such as running a program to send RS-232 commands, or a batch file that sets a BIOS wakeup timer and then shuts down the system. 
    Potentially %parameters% can be made available to the command-line, such as the number of minutes until the next scheduled 
    non-"turn off display" template, which would allow full server-side control of these options once the initial setup is done.</p>
 
  	<p>The Windows <a href="http://msdn.microsoft.com/en-us/library/aa373233(v=vs.85).aspx">SetThreadExecutionState</a> function can 
    be used to prevent display timeout. It is designed for apps such as video or presentations, which is exactly what <?php echo PRODUCT_NAME; ?> is.</P>
 
  	<p><?php echo PRODUCT_NAME; ?> client would need to check for transitions between the "turn off display" template and non-"turn off display" templates, 
    and then act appropriately (eg, there is a procedure to run for the transition to "off" and another to run for "on"). At this time, 
    calls to SetThreadExecutionState and any custom commands (if entered) would be run.
</blockquote>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

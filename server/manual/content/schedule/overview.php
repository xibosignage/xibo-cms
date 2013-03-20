<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
    <head>
        <meta name="generator" content="HTML Tidy, see www.w3.org">
        <meta http-equiv="Content-Type" content=
        "text/html; charset=iso-8859-1">

        <title><?php echo PRODUCT_NAME; ?> Documentation</title>
        <link rel="stylesheet" type="text/css" href=
        "../../css/doc.css">
        <meta http-equiv="Content-Type" content="text/html">
		<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />
        <link href="img/favicon.ico" rel="shortcut icon">
        <!-- Javascript Libraries -->
		<script type="text/javascript" src="lib/jquery.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
    </head>

    <body>
		<a name="Scheduling" id="Scheduling"></a><h1>Scheduling</h1>

		<a name="Overview" id="Overview"></a><h2>Overview</h2>

    	<p><?php echo PRODUCT_NAME; ?> is not designed to show a blank screen at any time. In display Management when you set each client up, you are asked 
    	to choose a default layout for that display.</p> 
    	<p>The <?php echo PRODUCT_NAME; ?> Schedule is used to set what will be shown on what display and at what time. The schedule
    	consists of a Month Calendar for each display. When a playlist is scheduled it becomes an event on the calendar.</p>
		
		<p>The features of the schedule	are as follows:</p>
		<ul>
			<li>Events spanning two dates - no maximum or minimum time</li>
			<li>Recurring events</li>
			<li>Priority events</li>
			<li>Schedule an event for more that one display at once</li>
		</ul>
	
		<p>If at any time there are no layouts scheduled to run, the default will be run automatically.</p>

		<h2>What is in this section?</h2>
		<p>This section will:</p>
		<ul>
			<li>Introduce the Calendar</li>
			<li>Explain what the events on the calendar actually mean</li>
			<li>Cover the functionailty available from the calendar</li>
			<li>Quick layout Schedule Now</li>
		</ul>

		<h2>Before you start...</h2>
        <p>Before you can schedule a playlist you must have completed the following tasks:</p>

        <ul>
            <li>Added the playlist</li>
            <li>Created the slide(s) you want on the playlist</li>
            <li>Added or assigned the content you want in the playlist</li>
        </ul>

        <p>Once these tasks have been completed the playlist is ready to be scheduled.</p>	

		<?php include('../../template/footer.php'); ?>
    </body>
</html>


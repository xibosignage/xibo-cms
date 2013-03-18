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
    <a name="Schedule_Now" id="Schedule_Now"></a><h2>Schedule Now</h2>
    <p>Schedule Now is a quick way to schedule a given layout on displays or display group. The is a one time
    sechedule lasted for the duration specified. Normally use for displaying temporary important notices 
    e.g. happy hour offer.</p>
    
   	<p><img alt="Schedule Now" src="schedule_now.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="708" height="362"></p>
    
    <ul>
      <li>From the Layouts screen, choose the "Schedule Now" button next to the layout you want to edit.<br/>
      Note: You may still change the Layout from within the "Schedule Now" form.</li>
      <li>Enter how long you want the layout to be shown for in Hours, Minutes and Seconds. It starts from the time 
      you complete the form.</li>
      <li> Ensure the correct layout is chosen in the Campaign/Layout dropdown box.</li>
      <li> Ticking Priority will give this layout priority over other scheduled layouts that do not have the priority
       tick box checked.</li>
      <li> Select the appropriate Displays or Display Groups you want the layout to be shown on.</li>
      <li> Click Save</li>
    </ul>

	<?php include('../../template/footer.php'); ?>
</body>
</html>

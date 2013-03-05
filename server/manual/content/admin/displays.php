<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<title><?php echo PRODUCT_NAME; ?> Documentation</title>
		<link rel=stylesheet type="text/css" href="../../css/doc.css">
		<meta http-equiv="Content-Type" content="text/html" />
		<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />

		<link href="img/favicon.ico" rel="shortcut icon"/>
		<!-- Javascript Libraries -->
		<script type="text/javascript" src="lib/jquery.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
	</head>
	<body>
	<a name="Displays" id="Displays"></a><h1>Displays</h1>
    
    <p>Displays are how you output your layouts with <?php echo PRODUCT_NAME; ?>.</p>
    <p>Each client application registered with the server creates a new display record in <?php echo PRODUCT_NAME; ?> server. You can then choose a 
    default layout for that display, schedule further layouts on the display and control who has access to the display, 
    as well as monitor its status from the Display Management page.</p>
     
    <p>The Display Manamagement page is accessed from the Nagivation Bar by clicking on "Displays > Displays"</p>

    <p><img alt="SA Displays" src="sa_displays.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="820" height="262"></p>
    <blockquote>	   
		<a name="Display_Edit" id="Display_Edit"></a><h3>Display Edit</h3>
		<p>After a new display client is registered with <?php echo PRODUCT_NAME; ?> server, you need to perform Edit granting license to the client 
    	to work.</p>
    	<p> Click on the 'Edit" button of the Display loads its editing window. You can then proceed to change the settings for
    	the chosen Display. </p>                                                                                         

	   <p><img alt="Display Edit" src="sa_display_edit.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="493" height="231"></p> 

		<a name="Display_Delete" id="Display_Delete"></a><h3>Display Delete</h3>
		<p>After a display client is registered and licensed with <?php echo PRODUCT_NAME; ?> server, you can "unlicense" a client which will prevent it from connecting
			to the server.</p>
    	<p> Click on the 'Delete" button of the Display you want to unlicense; and the below form is loaded. </p>                                                                                         

	   <p><img alt="Display Delete" src="sa_display_delete.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="357" height="217"></p>
	   
	   <p>Note: Delete a display cannot be undone. The client needs to re-regisiter and liscense before it is allowed to connect to the server again.</p>
	   
    	<a name="Media_Inventory" id="Media_Inventory"></a><h3>Media Inventory</h3>
    	<p>When you schedule new content, or change existing content, it is helpful to know that the displays have updated with the new 
    	information. In a <?php echo PRODUCT_NAME; ?> server and client system, the client applications will connect in to the server periodically 
    	and update itself on the media items that they have stored locally. Media Inventory allows you to look at a glance to check if 
    	your updates were pulled by the clients yet.</p>
    	<p>On the display management page, you'll see a column "Status". The status light can be one of either green, amber or red.</p>
    	<p>When you make a change that affects the output of the server to a given client (for example if you modify a layout scheduled 
    	on that client, schedule a new layout or change the default layout), the status light will immediately goes red. That signifies 
    	that as far as the server is aware there are updates pending for that client and it has not received them yet.</p>
    	<p>The client will then connect up to the server on a schedule and will read the new information that is available. If there 
    	are new files to download (for example if you modified a layout or scheduled something completely new), the status light will 
    	turn amber while the files are being downloaded.</p>
    	<p>Once the client is satisfied that all files have been downloaded, it will send an inventory of all the files it has active 
    	in it is cache back to the server, and the server will change the status light to green.</p>
    	<p>If you are curious to see what files the client is actively monitoring, you can click the "Media Inventory" button and a popup 
    	will appear showing the status of each file, along with the last time the client checked it was still stored correctly on disk. 
    	You will also see any files that the client is in the process of downloading. (Note here that what you will not see is files 
    	that the client needs to download, but that it is unaware of at the present time. For example. If you schedule a new layout 
    	and immediately go to the Media Inventory before the client has connected up to the server, you'll see the status light is red, 
   	 	but the content of the media inventory will not show the new files that are required. Once the client connects, those new files 
    	will be included in the inventory automatically.)</p>	   

     	<p><img alt="Display Media Inventory" src="sa_display_media_inventory.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="418" height="268"></p>

   		<a name="Display_Group_Member" id="Display_Group_Member"></a><h3>Group Members</h3>
    	<p>To find the Group that the Display is belonged to, click on the "Group Members" on the corresponding row.</p>
		 
    	<p><img alt="SA Display Group Edit" src="sa_display_groups_edit.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="408" height="218"></p>
    </blockquote>

	<h2>Wake On LAN (WOL)</h2>
    <blockquote>
    	<h3>Introducing Wake On Lan for Display Clients</h3>
 		<p>This section will look at the Wake On Lan (WOL) feature of <?php echo PRODUCT_NAME; ?>.</p>

   	 	<p>There has been a lot of interest over the years <?php echo PRODUCT_NAME; ?> has been running for a solution to be "green", "save power" and 
    	generally not have the <?php echo PRODUCT_NAME; ?> display screen solution on unnecessarily.</p>

    	<p>The WOL function is intended to be used for display clients that are addressable by the server, by this we mean that there 
    	has to be a clear addressable network route between the server and the client that needs to wake up. It is also required that
    	WOL is turned on in any necessary settings on the client PC.</p>

    	<p>The WOL configuration happens on a display by display basis from the Edit Display form. Each display has new settings for:</p>
    	<ul>
      		<li><strong>Enable Wake On LAN</strong> - (Turn WOL on/off for the display)</li>
      		<li><strong>Wake On LAN Time</strong> - (Specify a time for this display to wake, using the 24 hr clock)</li>
      		<li><strong>BroadCast Address</strong> - (The BroadCast address of the client, if unknown use the IP address and fill in the CIDR field)</li>
      		<li><strong>Wake On LAN Secure On</strong> - (The SecureOn password for the client, if supported)</li>
      		<li><strong>Wake On LAN CIDR</strong> - (The CIDR subnet mask, if the BroadCast Address is unknown)</li>
    	</ul>

    	<p>Once the display has these settings it can be woken up in 2 ways:</p>
    	<h3>Wake On LAN Now</h3>
    
   		<p><img alt="SA Display WOL" src="sa_display_wol.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="308" height="158"></p>
     
    	<p>Each display has the "Wake On LAN" button which can be used to send a wake on LAN command on demand. Clicking the button displays 
    	a form for confirmation, once this is pressed the command is send immediately to the client.</p>

    	<h3>Wake On LAN Time - Maintenance Script</h3>
    	<p>In addition to the WOL now function, the <?php echo PRODUCT_NAME; ?> maintenance module has been altered to send a WOL packet to any display which has 
    	a "Wake On LAN Time" specified. It will only send the command once, as the time window is passed.</p>

    	<p>The maintenance script has to be running for this to work correctly. Maintenance instructions can be found on the 
    	<a title="Maintenance Instructions" href="settings.php">Settings -> Maintenance</a>.</p>

    	<h3>Putting the client to sleep</h3>
   	 	<p>There are a few different options for putting the client to sleep - such as a scheduled task. However, the next article in this series 
    	will look at an option built into <?php echo PRODUCT_NAME; ?>. The "Shell Command" module.</p>

    	<p>Note: WOL is not routable. The <?php echo PRODUCT_NAME; ?> server is unable to see clients over the internet that are behind NAT,  
    	or on a different subnet.</p>
    </blockquote>

	<a name="Display_Group" id="Display_Group"></a><h1>Display Groups</h1>
    <p>A group should be added when there is a new set of components required for a particular group of users.
    It could be that you want certain users to only have access to certain components or that certain users 
    should not be able to share their content, playlists and schedules with each other.</p>
    
    <p><strong>Components</strong> refer to parts of <?php echo PRODUCT_NAME; ?>, e.g. Content, or Playlists.</p>
        
    <blockquote>
    	<a name="Adding_a_Group" id="Adding_a_Group"></a><h3>Adding a Group</h3>
    	<p>Click the navigation menu "Displays > Display Groups" to go to Display Groups page. To add a group click on 
    	the "Add Group" button found at the top of the Displays list.</p>
  
    	<a name="Edit_Group" id="Edit_Group"></a><h3>Edit a Group</h3>
    	<p>To edit a group click on the edit button on the row belonging to the group you wish to edit.</p>
		 
    	<p><img alt="SA Display Group" src="sa_display_groups.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="621" height="226"></p>
 
    	<p><strong>Name</strong> is a unique identifier for a group.</p>	   
    	   
    	<a name="Delete_Group" id="Delete_Group"></a><h3>Delete a Group</h3>
    	<p>To delete a group click on the delete button on the row belonging to the group you wish to delete.</p>
    
    	<a name="Group_Component_Security" id="Group_Component_Security"></a><h3>Group / Component Security</h3>
    	<p>When editing a group the components that are assigned / unassigned to that group are show. These
    	determine the permissions of the users belonging to that group.</p>
    	<p>You should always have at least one group.</p>

    	<a name="Group_Members" id="Group_Members"></a><h3>Group Members</h3>

    	<p>Click on "Group Members" load the "Manage Membership" form for editing. All the Displays on the system will be shown. 
    	You can assign or remove group members.</p>

    	<p><img alt="SA Display Group Members" src="sa_display_group_members.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="408" height="270"></p>

    </blockquote>

	<a name="Display_Statistic" id="Display_Statistic"></a><h1>Display Statistic</h1>
    <p>Click on "Statistic" shows a page giving statistic detail of all the system Displys on the followings. You can define
    the filters i.e. Date, Display &amp; Media, for the required statistical data display.</p>

     <ul>
     	<li><h3>Layout ran</h3>Statistical data on layouts run for each Display.</li>
     	<li><h3>Library Media ran</h3>Statistical data on Media played for each Display.</li>
     	<li><h3>Media on Layouts ran</h3>Statistical data on Media played on all layouts run for each Display.</li>
     </ul>

    <p><img alt="SA Display Statistic" src="sa_display_statistic.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="648" height="650"></p>

	<?php include('../../template/footer.php'); ?>
	</body>
</html>

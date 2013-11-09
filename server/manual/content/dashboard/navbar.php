	<h1>Navigation</h1>
	
	<p>The navigation bar appears on every page in the <?php echo PRODUCT_NAME; ?> server interface. It provides user direct access
		to all parts of the <?php echo PRODUCT_NAME; ?> interface from within any part of the <?php echo PRODUCT_NAME; ?> interface.</p>
	 
	<p><img alt="The navigation bar" src="content/dashboard/navbar.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="550" height="149"></p>
	
	<p>Most of the options directly mirror those available on the Dashboard. The main exception to this is the Management Menu - 
		which is only displayed to Admins. It has the following links:</p>
	
	<h2>Dashboard</h2>
	
	<p>The dashboard link returns you to your <?php echo PRODUCT_NAME; ?> dashboard, this will either be
	the default dashboard, or your media manager dashboard.</p>
	
	<h2>Schedule</h2>
	
	<p>The schedule link takes you to the Schedule calendar view, from here you
	can schedule layout onto each client display.</p>
	
	<h2>Design</h2>
	
	<p>The link takes you to the layout design page, from here you can view all
	the current layouts in the system and create new, or maintain your current layouts.</p>
	
	<blockquote>
	<h3>Campaigns</h3>
	<p>Grouping of mulitple Layouts for scheduling.</p>
	
	<h3>Layouts</h3>
	<p>List of user defined layouts. You can </p>
	<ul>
	  <li>Assign schedule for each user layout.</li>
	  <li>Degin/Edit/Delete the layout</li>
	  <li>Assign user access permission for each layout</li>
	</ul>
	
	<h3>Templates</h3>
	<p>A list of the available standard templates for user selection. Currently there are 8 being specified.</p>
	
	<h3>Resolutions</h3>
	<p>Here defines the resoluton settings for each template made available to the users; mainly use for new layout generation. 
	   The final client display resolutions are scaled from these layout resolution.</p>
	</blockquote>
	   
	<h2>Library</h2>
	
	<p>The link takes you to the library page where you can view all the current uploaded media items (images, videos 
		files) on the server. You can create new, update, delete media items or modify media access permissions.</p>
	
	<blockquote>
	<h3>Media</h3>
	<p>List of all the images and media playback files.</p>
	
	<h3>DataSets</h3>
	<p>List of all the datasets items.</p>
	</blockquote>
	
	<h2>Displays</h2>
	<p>List of all the current defined client displays and their status. You can</p>
	<ul>
	  <li>Maintain your licensed displays</li>
	  <li>Revoke the license of a display</li>
	  <li>Rename/Delete a display</li>
	  <li>Change member grouping</li>
	  <li>Change the default layout for the display</li>
	  <li>Toggle Auditing of the display</li>
	  <li>Send a 'Wake On LAN' signal to a client display</li>
	</ul>
	
	<blockquote>
	<h3>Display Groups</h3>
	<p>List of all display groups that have been created. You can</p>
	<ul>
	  <li>Add a new display group</li>
	  <li>Rename a display group</li>
	  <li>Change group members</li>
	  <li>Modify group permissions</li>
	</ul>
	
	<h3>Statistics</h3>
	<p>Provide a statistical data of the client displays' activities.</p>
	</blockquote>
	
	<h2>Administration</h2>
	
	<p>The administration link takes to administration page and user options</p>
	
	<blockquote>
	<h3>Users</h3>
	<p>The user page allows you to maintain all the Users on the system, from here you are able to</p>
	<ul>
	  <li>Add a new user</li>
	  <li>Delete a user</li>
	  <li>Edit a user's permissions setting</li>
	  <li>Set a user's homepage</li>
	  <li>Override a user's password</li>
	</ul>
	
	<h3>User Groups</h3>
	<p>Manage Groups' settings similar to users.</p>
	<ul>
	  <li>Add a new group</li>
	  <li>Delete a group</li>
	  <li>Alter a groups permissions</li>
	  <li>Set a group's homepage</li>
	</ul>
	
	<h3>Settings</h3>
	<p>Settings is used to provide a set of defaults for content and <?php echo PRODUCT_NAME; ?> server configurations including:</p>
	<ul>
	  <li>Error log &amp; actions</li>
	  <li>Server key setting</li>
	  <li>Server library location</li>
	</ul>
	
	<h3>Applications</h3>
	<p>It is a REST API using oauth; the applications menu will contain authourised applications for that server.</p>
	<p>More information is available on <a href="../admin/api.php"><?php echo PRODUCT_NAME; ?> API</a> page.</p>
	
	<h3>Modules</h3>
	<p>Here you can define the parameter settings for each of the <?php echo PRODUCT_NAME; ?> modules e.g. media supported extensions etc.</p>
	</blockquote>
	
	<h2>Advanced</h2>
	<p>The advanced link takes you to the <?php echo PRODUCT_NAME; ?> maintenace page options</p>
	
	<blockquote>
	<h3>Log</h3>
	<p>The log page provides detailed messages about the system. These are
	normally required when reporting bugs or requesting for help.</p>
	
	<h3>Sessions</h3>
	<p>Sessions provide details of all the current users' activities on the network connection with the <?php echo PRODUCT_NAME; ?> Server.</p>
	
	<h3>Report Fault</h3>
	<p>Provide some simple steps to generate debug messages; and to report bugs.</p>
	
	<h3>License</h3>
	<p>The license page provides details of all the relevent licenses for the system.</p>
	</blockquote>
	
	<a name="Navigation_Top" id="Navigation_Top"></a><h2>Navigation-Top</h2>
	<p>Cicking on a menu item brings you to the page relating to that section of the interface.</p>
	
	<blockquote>
	<h3>Login-Account</h3>
	<p>Click on login account to change user password.</p>
	
	<h3>Time Display</h3>
	<p>Click on Time for more details time information.</p>
	
	<h3>About</h3>
	<p>Display <?php echo PRODUCT_NAME; ?> License Information.</p>
	
	<h3>Help</h3>
	<p>Provide links to online help.</p>
	
	<h3>Log Out</h3>
	<p>This link closes and exits the current user session.</p>
	</blockquote>

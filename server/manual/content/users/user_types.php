
		
		<h1>User Types</h1>
    	<p>Each <?php echo PRODUCT_NAME; ?> user can be associated to a specific user type hence inherited the corresponding access right to the system.</p>
 
    	<p><img alt="User Types" src="content/users/user_types.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	  	width="558" height="328"></p>

		<p><?php echo PRODUCT_NAME; ?> features 3 different user type.</p>
		<ul>
			<li>Super Admin</li>
			<li>Group Admin</li>
			<li>User</li>
		</ul>
		<p>Each user type gives an appropriate level of permissions within <?php echo PRODUCT_NAME; ?>.</p>
		
		<h2>Super Admin</h2>
		<p>Super Admin users have access to every part of <?php echo PRODUCT_NAME; ?>. They also have access to all the content, playlists and schedules added
		by other users of the system.</p>
		
		<p>In most cases there will be one or two Super Admins that are the last contact point for the <?php echo PRODUCT_NAME; ?> system. It should be noted
		that the Super Admin has permissions to every part of <?php echo PRODUCT_NAME; ?> regardless of the permissions set on the User group they belong to.</p>
		
		<p>The <?php echo PRODUCT_NAME; ?>_admin account created during the install is a Super Admin.</p>
		
		<h2>Group Admin</h2>
		<p>The group admin only has access to the parts of <?php echo PRODUCT_NAME; ?> assigned to the group they belong to. However they also have access to all 
		the content, playlists and schedule of users within that group. They are able to view shared items that have the public sharing setting.</p>
		
		<h2>Users</h2>
		<p>The user only has access to the parts of <?php echo PRODUCT_NAME; ?> assigned to the group they belong to. They also only have access to their own content, playlists
		and schedules. They are able to view shared items that have the public or group sharing setting.</p>

	

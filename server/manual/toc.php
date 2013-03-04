<?php include('template.php'); ?>

<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<title><?php echo PRODUCT_NAME; ?> Documentation</title>
		<link rel=stylesheet type="text/css" href="css/doc.css">
		<meta http-equiv="Content-Type" content="text/html" />
		<meta name="keywords" content="digital signage, signage, narrow-casting, xibo, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />

		<link href="img/favicon.ico" rel="shortcut icon"/>
		<!-- Javascript Libraries -->
		<script type="text/javascript" src="lib/jquery.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
		<script type="text/javascript" src="lib/jquery.treeview.js"></script>
		<link rel="stylesheet" href="lib/jquery.treeview.css" type="text/css" media="screen" />
		<script type="text/javascript">
			$(document).ready(function() {
				$('#toc').treeview({animated: "fast"});	
			});
		</script>
	</head>

<body bgcolor="#ffffff" text="#000000" link="#cc0000" vlink="#990066" alink="#cc0000">

<div id="preamble" align="center">

<?php
if (is_file('img/logo_custom.png')) {
    $logo = 'img/logo_custom.png';
}
else {
    $logo = 'img/logo.png';
}
?>

<img src="<?php echo $logo; ?>" alt="<?php echo PRODUCT_NAME; ?> Logo" width="150px">
</div>

<div style="width:300px;">
	<ul id="toc" class="filetree">
		<li><span class="folder">Introduction</span>
			<ul>
				<li><a class="file" href="intro.php" target="main">Introduction</a></li>
				<li><a class="file" href="content/license/licenses.php" target="main">Licenses</a></li>
			</ul>
		</li>

		<li><span class="folder">Installation</span>
			<ul>
				<li><a class="file" href="content/install/prerequisites.php" target="main">Prerequisites</a></li>
				<li><a class="file" href="content/install/install_client.php" target="main">Client Installation</a></li>
				<li><a class="file" href="content/install/install_server.php" target="main">Server Installation</a></li>
				<li><a class="file" href="content/install/troubleshooting.php" target="main">Troubleshooting</a></li>
			</ul>
		</li>

		<li><span class="folder">Configuration</span>
			<ul>
				<li><a class="file" href="content/config/config.php" target="main">Configuration</a></li>
				<li><a class="file" href="content/config/client_feature.php" target="main"><?php echo PRODUCT_NAME; ?> Client Features</a></li>
				<li><a class="file" href="content/config/client.php" target="main"><?php echo PRODUCT_NAME; ?> Client</a></li>
				<li><a class="file" href="content/config/windows.php" target="main">Windows Modifications</a></li>
				<li><a class="file" href="content/config/settings.php" target="main">Admin Panel Settings</a></li>
			</ul>
		</li>

		<li><span class="folder">Using <?php echo PRODUCT_NAME; ?>: Components and Navigation</span>
			<ul>
				<li><a class="file" href="content/dashboard/overview.php" target="main">Overview</a></li>
				<li><a class="file" href="content/dashboard/dashboard.php" target="main">Dashboard</a></li>
				<li><a class="file" href="content/dashboard/navbar.php" target="main">Navigation</a></li>
			</ul>
		</li>

		<li><span class="folder">Layouts</span>
			<ul>
				<li><a class="file" href="content/layout/overview.php" target="main">Overview</a></li>
				<li><a class="file" href="content/layout/addlayout.php" target="main">Adding Layouts</a></li>
				<li><span class="folder">Designing Layouts</span>
					<ul>
						<li><a class="file" href="content/layout/layoutdesigner.php" target="main">Layout Designer</a></li>
						<li><a class="file" href="content/layout/addregion.php" target="main">Adding Regions</a></li>
					</ul>
				</li>

				<li><span class="folder">Assigning Contents</span>
					<ul>
						<li><a class="file" href="content/layout/assigncontent.php" target="main">Assigning Region Content</a></li>
						<li><a class="file" href="content/layout/content_video.php" target="main">Content - Video</a></li>
						<li><a class="file" href="content/layout/content_flash.php" target="main">Content - Flash</a></li>
						<li><a class="file" href="content/layout/content_image.php" target="main">Content - Image</a></li>
						<li><a class="file" href="content/layout/content_powerpoint.php" target="main">Content - PowerPoint</a></li>
						<li><a class="file" href="content/layout/content_datasets.php" target="main">Content - Datasets </a></li>
						<li><a class="file" href="content/layout/content_text.php" target="main">Content - Text</a></li>
						<li><a class="file" href="content/layout/content_ticker.php" target="main">Content - Ticker</a></li>
						<li><a class="file" href="content/layout/content_microblog.php" target="main">Content - MicroBlog</a></li>
						<li><a class="file" href="content/layout/content_counter.php" target="main">Content - Counter</a></li>
						<li><a class="file" href="content/layout/content_webpage.php" target="main">Content - Webpage</a></li>
						<li><a class="file" href="content/layout/content_embedded.php" target="main">Content - Embedded</a></li>
						<li><a class="file" href="content/layout/content_shellcommand.php" target="main">Content - Shell Command</a></li>
					</ul>
				</li>

				<li><a class="file" href="content/layout/region_preview_timeline.php" target="main">Region Preview &amp; Timeline</a></li>
				<li><a class="file" href="content/layout/campaign_layout.php" target="main">Campaign Layout</a></li>
			</ul>
		</li>
		
    <li><span class="folder">Scheduling</span>
			<ul>
				<li><a class="file" href="content/schedule/overview.php" target="main">Overview</a></li>
				<li><a class="file" href="content/schedule/schedule_calendar.php" target="main">Calendar Chart View</a></li>
				<li><a class="file" href="content/schedule/schedule_event.php" target="main">Schedule &amp; Edit Event</a></li>
				<li><a class="file" href="content/schedule/schedule_now.php" target="main">Schedule Now</a></li>
			</ul>
		</li>
		
    <li><span class="folder">Content</span>
			<ul>
				<li><a class="file" href="content/content/overview.php" target="main">Overview</a></li>
				<li><a class="file" href="content/content/types.php" target="main">Types of Content</a></li>
				<li><a class="file" href="content/content/adding.php" target="main">Adding Content</a></li>
				<li><a class="file" href="content/content/licensing.php" target="main">Licensing</a></li>
			</ul>
		</li>  

		<li><span class="folder">Templates</span>
			<ul>
				<li><a class="file" href="content/templates/overview.php" target="main">Overview</a></li>
				<li><a class="file" href="content/templates/template_resolution.php" target="main">Template Resolution</a></li>
				<li><a class="file" href="content/templates/choosetemplate.php" target="main">Choosing Template</a></li>
			</ul>
		</li>

		<li><span class="folder">Admin: Users, Types and Groups</span>
			<ul>
				<li><a class="file" href="content/users/overview.php" target="main">Overview</a></li>
				<li><a class="file" href="content/users/users.php" target="main">User Administration</a></li>
				<li><a class="file" href="content/users/menu_page_security.php" target="main">Page &amp; Menu Security</a></li>
				<li><a class="file" href="content/users/user_types.php" target="main">User Types</a></li>
				<li><a class="file" href="content/users/groups.php" target="main">User Groups and Group Permissions</a></li>
				<li><a class="file" href="content/users/user_permissions.php" target="main">Permissions Model</a></li>
			</ul>
		</li>

		<li><span class="folder">System Administration</span>
			<ul>
				<li><a class="file" href="content/admin/overview.php" target="main">Overview</a></li>
				<li><a class="file" href="content/admin/displays.php" target="main">Client Displays</a></li>
				<li><a class="file" href="content/admin/settings.php" target="main">Server Settings</a></li>
				<li><a class="file" href="content/admin/modules.php" target="main"><?php echo PRODUCT_NAME; ?> Modules</a></li>
				<li><a class="file" href="content/admin/api.php" target="main"><?php echo PRODUCT_NAME; ?> API</a></li>
				<li><a class="file" href="content/admin/advanced.php" target="main">Advanced</a></li>
      </ul> 
 	</ul>
</div>
</body>
</html>

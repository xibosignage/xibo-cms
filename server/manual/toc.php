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
<img src="img/logo.png" alt="<?php echo PRODUCT_NAME; ?> Logo" width="150px">
</div>

<div style="width:300px;">
	<ul id="toc" class="filetree">
		<li><span class="folder">Introduction</span>
			<ul>
				<li><a class="file" href="intro.html" target="main">Introduction</a></li>
				<li><a class="file" href="content/license/licenses.html" target="main">Licenses</a></li>
			</ul>
		</li>

		<li><span class="folder">Installation</span>
			<ul>
				<li><a class="file" href="content/install/prerequisites.html" target="main">Prerequisites</a></li>
				<li><a class="file" href="content/install/install_client.html" target="main">Client Installation</a></li>
				<li><a class="file" href="content/install/install_server.html" target="main">Server Installation</a></li>
				<li><a class="file" href="content/install/troubleshooting.html" target="main">Troubleshooting</a></li>
			</ul>
		</li>

		<li><span class="folder">Configuration</span>
			<ul>
				<li><a class="file" href="content/config/config.html" target="main">Configuration</a></li>
				<li><a class="file" href="content/config/client_feature.html" target="main"><?php echo PRODUCT_NAME; ?> Client Features</a></li>
				<li><a class="file" href="content/config/client.html" target="main"><?php echo PRODUCT_NAME; ?> Client</a></li>
				<li><a class="file" href="content/config/windows.html" target="main">Windows Modifications</a></li>
				<li><a class="file" href="content/config/settings.html" target="main">Admin Panel Settings</a></li>
			</ul>
		</li>

		<li><span class="folder">Using <?php echo PRODUCT_NAME; ?>: Components and Navigation</span>
			<ul>
				<li><a class="file" href="content/dashboard/overview.html" target="main">Overview</a></li>
				<li><a class="file" href="content/dashboard/dashboard.html" target="main">Dashboard</a></li>
				<li><a class="file" href="content/dashboard/navbar.html" target="main">Navigation</a></li>
			</ul>
		</li>

		<li><span class="folder">Layouts</span>
			<ul>
				<li><a class="file" href="content/layout/overview.html" target="main">Overview</a></li>
				<li><a class="file" href="content/layout/addlayout.html" target="main">Adding Layouts</a></li>
				<li><span class="folder">Designing Layouts</span>
					<ul>
						<li><a class="file" href="content/layout/layoutdesigner.html" target="main">Layout Designer</a></li>
						<li><a class="file" href="content/layout/addregion.html" target="main">Adding Regions</a></li>
					</ul>
				</li>

				<li><span class="folder">Assigning Contents</span>
					<ul>
						<li><a class="file" href="content/layout/assigncontent.html" target="main">Assigning Region Content</a></li>
						<li><a class="file" href="content/layout/content_video.html" target="main">Content - Video</a></li>
						<li><a class="file" href="content/layout/content_flash.html" target="main">Content - Flash</a></li>
						<li><a class="file" href="content/layout/content_image.html" target="main">Content - Image</a></li>
						<li><a class="file" href="content/layout/content_powerpoint.html" target="main">Content - PowerPoint</a></li>
						<li><a class="file" href="content/layout/content_datasets.html" target="main">Content - Datasets </a></li>
						<li><a class="file" href="content/layout/content_text.html" target="main">Content - Text</a></li>
						<li><a class="file" href="content/layout/content_ticker.html" target="main">Content - Ticker</a></li>
						<li><a class="file" href="content/layout/content_microblog.html" target="main">Content - MicroBlog</a></li>
						<li><a class="file" href="content/layout/content_counter.html" target="main">Content - Counter</a></li>
						<li><a class="file" href="content/layout/content_webpage.html" target="main">Content - Webpage</a></li>
						<li><a class="file" href="content/layout/content_embedded.html" target="main">Content - Embedded</a></li>
						<li><a class="file" href="content/layout/content_shellcommand.html" target="main">Content - Shell Command</a></li>
					</ul>
				</li>

				<li><a class="file" href="content/layout/region_preview_timeline.html" target="main">Region Preview &amp; Timeline</a></li>
				<li><a class="file" href="content/layout/campaign_layout.html" target="main">Campaign Layout</a></li>
			</ul>
		</li>
		
    <li><span class="folder">Scheduling</span>
			<ul>
				<li><a class="file" href="content/schedule/overview.html" target="main">Overview</a></li>
				<li><a class="file" href="content/schedule/schedule_calendar.html" target="main">Calendar Chart View</a></li>
				<li><a class="file" href="content/schedule/schedule_event.html" target="main">Schedule &amp; Edit Event</a></li>
				<li><a class="file" href="content/schedule/schedule_now.html" target="main">Schedule Now</a></li>
			</ul>
		</li>
		
    <li><span class="folder">Content</span>
			<ul>
				<li><a class="file" href="content/content/overview.html" target="main">Overview</a></li>
				<li><a class="file" href="content/content/types.html" target="main">Types of Content</a></li>
				<li><a class="file" href="content/content/adding.html" target="main">Adding Content</a></li>
				<li><a class="file" href="content/content/licensing.html" target="main">Licensing</a></li>
			</ul>
		</li>  

		<li><span class="folder">Templates</span>
			<ul>
				<li><a class="file" href="content/templates/overview.html" target="main">Overview</a></li>
				<li><a class="file" href="content/templates/template_resolution.html" target="main">Template Resolution</a></li>
				<li><a class="file" href="content/templates/choosetemplate.html" target="main">Choosing Template</a></li>
			</ul>
		</li>

		<li><span class="folder">Admin: Users, Types and Groups</span>
			<ul>
				<li><a class="file" href="content/users/overview.html" target="main">Overview</a></li>
				<li><a class="file" href="content/users/users.html" target="main">User Administration</a></li>
				<li><a class="file" href="content/users/menu_page_security.html" target="main">Page &amp; Menu Security</a></li>
				<li><a class="file" href="content/users/user_types.html" target="main">User Types</a></li>
				<li><a class="file" href="content/users/groups.html" target="main">User Groups and Group Permissions</a></li>
				<li><a class="file" href="content/users/user_permissions.html" target="main">Permissions Model</a></li>
			</ul>
		</li>

		<li><span class="folder">System Administration</span>
			<ul>
				<li><a class="file" href="content/admin/overview.html" target="main">Overview</a></li>
				<li><a class="file" href="content/admin/displays.html" target="main">Client Displays</a></li>
				<li><a class="file" href="content/admin/settings.html" target="main">Server Settings</a></li>
				<li><a class="file" href="content/admin/modules.html" target="main"><?php echo PRODUCT_NAME; ?> Modules</a></li>
				<li><a class="file" href="content/admin/api.html" target="main"><?php echo PRODUCT_NAME; ?> API</a></li>
				<li><a class="file" href="content/admin/advanced.html" target="main">Advanced</a></li>
      </ul> 
 	</ul>
</div>
</body>
</html>

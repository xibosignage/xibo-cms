<div class="list-group">
	<a class="list-group-item" href="index.php?toc=getting_started&p=intro">Introduction</a>
	<a class="list-group-item" href="index.php?toc=getting_started&p=license/licenses">Licenses</a>
	<?php
	if (! HOSTED) {
	?>
	<a class="list-group-item" href="index.php?toc=getting_started&p=install/install_server">Server Installation</a>
	<?php
	}
	?>
	<a class="list-group-item" href="index.php?toc=getting_started&p=install/install_client">Client Installation</a>
	<a class="list-group-item" href="index.php?toc=getting_started&p=config/client_feature"><?php echo PRODUCT_NAME; ?> Client Features</a>
	<a class="list-group-item" href="index.php?toc=getting_started&p=config/client"><?php echo PRODUCT_NAME; ?> Client</a>
	<a class="list-group-item" href="index.php?toc=getting_started&p=config/windows">Windows Modifications</a>
	<a class="list-group-item" href="index.php?toc=getting_started&p=install/troubleshooting">Troubleshooting</a>
	<a class="list-group-item" href="index.php?toc=getting_started&p=config/settings">CMS Settings</a>
</div>
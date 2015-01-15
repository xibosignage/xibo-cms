
	<h1>Installation</h1>

	<p>The <?php echo PRODUCT_NAME; ?> Client should be installed on each display. It is the <?php echo PRODUCT_NAME; ?> client
	that collects and displays the scheduled content on the output device (tv,
	projector, etc).</p>

	<p>The <?php echo PRODUCT_NAME; ?> Server should run on any platform that Apache, PHP and MySQL are available for. It has been tested on GNU/Linux (Ubuntu Precise 12.04) and Microsoft Windows platforms.</p>

	<h2>Prerequisites</h2>  

	<blockquote>
	<h3><?php echo PRODUCT_NAME; ?> Client Requirements</h3>
	<p>Before attempting to install the <?php echo PRODUCT_NAME; ?> Client please ensure the following minimum	prerequisites are met.</p>
	<ul>
		  <li>A network connection to the <?php echo PRODUCT_NAME; ?> Server (possibly over the Internet)</li>
		  <li>Microsoft Windows XP/Vista/7</li>
		  <li>.NET Framework v3.5 and latest service packs</li>
		  <li>Internet Explorer 7 or later</li>
		  <li>Flash Player Version 11 32bit or later (for Flash Support)</li>
		  <li>Windows Media Player 11 or later</li>
	</ul>

	<p lang="en-GB" class="western">Minimum Recommended Hardware Specifications are:</p>
	<ul>
	  <li>Processor: 1.5GHz</li>
	  <li>RAM: 2GB</li>
	  <li>Hard drive Space: 40 GB</li>
	</ul>

<?php
if (! HOSTED) {
?>	
	<h3 lang="en-GB" class="western"><?php echo PRODUCT_NAME; ?> Server Requirements</h3>
	<p>The <?php echo PRODUCT_NAME; ?> server requires a PHP enabled webserver (e.g. Apache), PHP v5.1.4 or later and MySQL, as well as some SQL extensions.</p>
	<p>Minimum specification depends largely on the number of clients to be supported, and the frequency of client updates. Any hardware capable of running
	a reasonably modern Linux distribution, or Microsoft Windows 7/XP should be capable of supporting a few clients.</p>
	<p><?php echo PRODUCT_NAME; ?> stores all the content you schedule for display on the server. Disk space is therefore a function of the amount of content you upload.</p>
<?php
}
?>
</blockquote>


	<h1 lang="en-GB" class="western">Windows Modifications</h1>
	<p>Here are some suggested settings for Windows / PowerPoint for a Display Client:</p>
	<ul>
	<li>Turn off all <a href="http://www.microsoft.com/windowsxp/using/setup/personalize/screensaver.mspx" 
		title="http://www.microsoft.com/windowsxp/using/setup/personalize/screensaver.mspx" rel="nofollow">screensavers</a></li>
	
	<li>Turn off screen <a href="http://www.microsoft.com/windowsxp/using/setup/tips/sleep.mspx" 
		title="http://www.microsoft.com/windowsxp/using/setup/tips/sleep.mspx" rel="nofollow">power saving</a></li>
	
	<li>Load the "No Sounds" <a href="http://www.microsoft.com/windowsxp/using/accessibility/soundscheme.mspx"
		title="http://www.microsoft.com/windowsxp/using/accessibility/soundscheme.mspx" rel="nofollow">Sound Scheme</a>
		(Control Panel -&gt; Sounds and Audio Devices Properties)</li>

	<li>Set a plain wallpaper (Hopefully nobody will see it, but you might need to reboot the client, or restart <?php echo PRODUCT_NAME; ?> and a sane wallpaper is a help)</li>
	
	<li>If the client is accessible from where you manage your displays from, you might want to install <a href="http://www.uvnc.com/" 
		title="http://www.uvnc.com/" rel="nofollow">UltraVNC</a> server so you can connect in and check on the client from time to time. 
		Use the "View only" option in the VNC client to avoid disturbing the display.</li>
	
	<li>Set Windows to <a href="http://www.mvps.org/marksxp/WindowsXP/welskip.php" title="http://www.mvps.org/marksxp/WindowsXP/welskip.php" 
		rel="nofollow">log on as your display client user automatically</a></li>
	
	<li>Disable <a href="http://support.microsoft.com/kb/307729" title="http://support.microsoft.com/kb/307729" rel="nofollow">
		balloon tips in the notification area</a></li>
	
	<li>Disable Windows Error Reporting. Occasionally PowerPoint seems to "crash" when <?php echo PRODUCT_NAME; ?> closes it. Unfortunately this leaves an unsightly
		"PowerPoint has encountered a problem and needs to close" message on the display. Follow the steps 
		<a href="http://www.windowsnetworking.com/articles_tutorials/Disable-Error-Reporting-Windows-XP-Server-2003.html"
		title="http://www.windowsnetworking.com/articles_tutorials/Disable-Error-Reporting-Windows-XP-Server-2003.html" rel="nofollow">here</a>
		to disable Windows Error Reporting completely - including notifications.</li>
		
	<li>Also disable Office Application Error reporting. Follow instructions at <a href="http://support.microsoft.com/kb/325075"
		title="http://support.microsoft.com/kb/325075" rel="nofollow">KB325075</a> or merge <a href="DWNeverUpload.reg"
		title="DWNeverUpload.reg"> this registry patch</a>.</li>
	</ul>

	<p>If you're using PowerPoint, then there are a couple of extra steps:</p>
    <p>First consider if you would be better converting your PowerPoint content to video files. PowerPoint 2010 and later can "Save As" a WMV file
       which can be loaded straight in to Xibo and is far more reliable. If however you still need to play PowerPoint files, please ensure you action
       the following:</p>

	<ul>
	<li>The first time you run <?php echo PRODUCT_NAME; ?> with a PowerPoint, you might get a popup appear that asks what <?php echo PRODUCT_NAME; ?> should do with the PowerPoint file.
		The popup actually originates from Internet Explorer. Choose to "Open" the file, and untick the box so you won't be prompted again.</li>
		
	<li>In some circumstances, you may find that PowerPoint, the application, loads instead of the file opening within <?php echo PRODUCT_NAME; ?> itself. If that happens,
		try merging <a href="Powerpoint-fix.reg" title="Powerpoint-fix.reg"> this registry patch</a>. (Taken from 
		<a href="http://www.pptfaq.com/FAQ00189.htm" title="http://www.pptfaq.com/FAQ00189.htm" rel="nofollow">pptfaq.com</a>).
		Users of Powerpoint 2007 should go to Microsoft <a href="http://support.microsoft.com/kb/927009" 
		title="http://support.microsoft.com/kb/927009" rel="nofollow">KB927009</a> and run the FixIT application instead. Users of PowerPoint 2010 
		should go here instead <a href="http://support.microsoft.com/kb/982995/en-us" title="http://support.microsoft.com/kb/982995/en-us" 
		rel="nofollow">KB982995</a></li>
		
	<li>Note also that PowerPoint will put scroll bars up the side of your presentation, unless you do the following for each PowerPoint file BEFORE you upload it:</li>
	</ul>

	<ul>
		o Open your PowerPoint Document<br />
 		o Slide Show -&gt; Setup Show<br />
 		o Under "Show Type", choose "Browsed by an individual (window)" and then untick "Show scrollbar"
	</ul>

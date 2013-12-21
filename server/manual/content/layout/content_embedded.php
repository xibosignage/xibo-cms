
	<a name="embedded" id="embedded"></a><h2>Embedded HTML</h2>



	<p>In <?php echo PRODUCT_NAME; ?> it is possible to embed html code as content in a region e.g. a clock or weather forcast</p>
	<p>To get <?php echo PRODUCT_NAME; ?> to show embedded HTML with Active-X content, you would need to adjust the security settings of IE 
	so that local files were allowed to run active content by default. This can be done in 
	Tools -> Internet Options -> Advanced -> Security -> "Allow Active content to run in files on My Computer"</p>

	<p>Add an Embedded</p>

	<ul>
		<li>Click the "Add Embedded" icon</li>
		<li>A new dialogue will appear:

		<p><img alt="Ss_layout_designer_add_embedded" src="content/layout/Ss_layout_designer_add_embedded.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="678" height="485"></p></li>

		<li>Enter the embedded html source. The example given above is for a digital Date/Time Display.<p>
 
<script src="http://www.clocklink.com/embed.js"></script>
<script type="text/javascript" language="JavaScript">
obj=new Object;
obj.clockfile="5001-blue.swf";
obj.TimeZone="GMT0800";
obj.width=300;
obj.height=25;
obj.Place="";
obj.DateFormat="mm-DD-YYYY";
obj.wmode="transparent";
showClock(obj);
</script>
</p></li>

	<li>Click "Save"
	<p>Below is another example to display an analogue clock face:</p>

  	<table border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center">
      <embed src="http://www.worldtimeserver.com/clocks/wtsclock001.swf?color=FF9900&wtsid=SG" width="200" height="200" 
      wmode="transparent" type="application/x-shockwave-flash" />
      </td>
   	</tr>
  	</table>

	<p>You can view the script source by right click on the page and select "View source"</p>
	</li></ul>


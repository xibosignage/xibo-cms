
	<h1>Layout Designer</h1>

	<h2>Add Layout</h2>
	<p>To add a new layout:</p>
	<ul>
		<li>From the dashboard, click on "Layouts" or<br />
  		From Navigation Menu, clcik "Design > Layouts"</li> 
		<li>Click "Add Layout"</li>
		<li>A new layout form will appear</li> 
	</ul>

	<p><img alt="Add layout form" src="content/layout/addlayout.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="457" height="434"></p>

	<ul>
		<li>Fill in all the required fields, and any other fields you wish to complete</li> 
		<li>Click "Save"</li>
	</ul>

	<blockquote>
  	<p>The fields on the Add/Edit Layout form are described as follows:</p>

  	<h3>Name</h3>
  	<p>A name for this layout. It provides a reference for which it can be located for later scheduling or editing. 
	This is the information which will appear in the name column of the layout list.</p>

  	<h3>Description</h3>
  	<p>The optional description field is used to add additional information about the layout, for user later recap the purpose of the layout.</p>

  	<h3>Tags</h3>
  	<p>A space-separated list of keywords to apply to the layout. These could be used to identify groups of layouts 
	or sometimes have special meaning if you have specialised versions of the <?php echo PRODUCT_NAME; ?> client. Tags are used to help 
	search for the layout, and it is a good idea to provide as much detail as possible in case you require searching
	for it at a later time.</p>

  	<h3>Template</h3>
  	<p>Optionally you can choose a template to base your new layout on (defaults or templates that you have already saved).
	This can help reduce the time it takes to setup a new layout. Please go to the <a href="../templates/overview.php">Template</a> 
  	section for more details on how to create or choose a template.</p>
	</blockquote>

	<a name="Edit_Layout" id="Edit_Layout"></a><h2>Retire Layout</h2>
	<p>When you are finished with an old layout, you can optionally hide it in the <?php echo PRODUCT_NAME; ?> Server (incase you want to reuse it at a later date).
		We call this retiring a layout - or you can delete it completely.</p>

	<p><img alt="Edit Layout Form" src="content/layout/Ss_layout_edit.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="358" height="283"></p>
	
	<ul>
		<li> Choose the Edit button next to the layout you want to retire in the Layouts screen.</li>
		<li> Select "Yes" from the Retired dropdown box.</li>
		<li> Click Save</li>
	</ul>

	<p>The layout will disappear from the Layouts screen. Use the Filter options to show retired layouts if you want to view retired layouts. 
		You can then edit a retired layout to un-retire it at a later date.</p>

	<a name="Delete_Layout" id="Delete_Layout"></a><h2>Delete Layout</h2>
	<p>The delete functionality would delete an existing layout from the server. Any layout specific data that are 
		associated with the layout will be lost.</p>

	<p><img alt="Delete Layout Form" src="content/layout/Ss_layout_delete.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="307" height="207"></p>
	
	<ul>
		<li> Choose the Delete button next to the layout you want to delete in the Layouts screen.</li>
		<li> Note that deleting a layout is irreversible so be very sure that you do not want the layout before you delete it.</li>
		<li> Press Yes to delete the Layout or No to cancel the deletion.</li>
	</ul>

	<a name="Copy_Layout" id="Copy_Layout"></a><h2>Copy Layout</h2>
	<p>The copy functionality would duplicate the layout to be copied, link all the existing media and permissions. 
	The only difference would be the new name.</p>

	<p>To copy layout:</p>
	<ul>
		<li>Click "Copy" button</li>
		<li>A new layout form will appear</li> 
	</ul>

	<p><img alt="Copy Layout Form" src="content/layout/Ss_layout_copy.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="358" height="174"></p>

	<ul>
		<li>Enter Name for the new layout</li>
		<li>Check "Make new copies of all media on this layout?" if required</li> 
		<li>Click "Copy" button</li>
	</ul>

	<p>Notes:</p>
	<ul>
  	<li>Copy media option duplicate both the embedded contents of the regions in layout and media contents 
  	that are saved in the <?php echo PRODUCT_NAME; ?> library.</li>
 	<li>It would not attempt to copy any of the scheduling information.</li>
  	<li>After copying there would be no link between the two layouts.</li>
	</ul>

	<h2>Design</h2>
	<p>Once you have saved the "Add/Edit Layout" form; clicked on &ldquo;Design&rdquo; button and you will be taken to the 
	<a href="layoutdesigner.html">Layout Designer</a> page where the specific details for the layout are shown.</p>

	<a name="Layout_Permission" id="Layout_Permission"></a><h2>Permissions</h2>
	<p>Click "Permissions" button to define the access right for other <?php echo PRODUCT_NAME; ?> system users on the created layout.</p>

	<p><img alt="Layout Permission" src="content/layout/layoutpermission.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="358" height="275"></p>

	<p>Tick the appropriate access rights in the form and click "save".</p>

	<ul>
		<li>Everyone: all users that have been defined in the <?php echo PRODUCT_NAME; ?> server database.</li>
		<li>Users: All members that are included in the "Users" group.</li>
		<li><?php echo PRODUCT_NAME; ?>_admin: Individual user of <?php echo PRODUCT_NAME; ?></li>
	</ul>

	<p>Leaving all boxes unticked means only you (and Admins) have access to the layout.</p>
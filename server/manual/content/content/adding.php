
        <h1>Adding Content</h1>
        
        <p>In order to display content on <?php echo PRODUCT_NAME; ?> it first needs to be added to the system. The
        following instructions deal with adding content. This section deals only with media
        content that are stored in the Media Library.</p>
                
        <p><img alt="Library Add Media" src="content/content/library_media_add.png"
	       style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	       width="425" height="225"></p>        
          
        <p>In order to add content you must be at the "Media" page by click "Library -> Media". When at the Media 
        page click the "Add Media" button at top. This will take you to the following screen:</p>
        
        <p>Note: The media can also be added when creating layouts using the layout designer.</p>

        <p>You can add different types of content by clicking on the media icon in the form. Doing this will 
        show you the correct form for the content you have selected and allow <?php echo PRODUCT_NAME; ?> to collect the appropriate information.</p>

 	<blockquote>
        <h3>Add Image</h3>
        <p>Following form show the required information when adding new image</p>
 
        <p><img alt="Library Add Image" src="content/content/library_add_image.png"
	       style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	       width="458" height="226"></p>        
 
        <h3>Add DataSet</h3>
        <p>Following form show the required information when adding DataSet</p>
 
        <p><img alt="Library Add Image" src="content/content/library_add_dataset.png"
	       style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	       width="358" height="179"></p>        

        <p>Refef to <a href="../layout/assigncontent.php#datasets">Assigning Content - DataSets</a> in Layouts section for further information on
        defining DataSets.</p>
 
				<a name="To_edit_content" id="To_edit_content"></a><h3>Edit Content</h3>
				<p>Click on the "Edit" button in the action column.<br />
				<strong>Note:</strong> If there is no edit button then you do not	have permission to edit that content.</p>

        <p>Once you have entered the information <?php echo PRODUCT_NAME; ?> requires, click on the "Save" button and your 
        content will be added to <?php echo PRODUCT_NAME; ?>.</p>
	</blockquote>

        <h2>Non-Media Content</h2>
        <p>Following contents are not saved in the <?php echo PRODUCT_NAME; ?> media library. Instead they are embbedded within the Regions in which they are
        assigned. Refef to <a href="../layout/assigncontent.php#text">Assigning Content - Text...</a> sections in Layout on adding 
        these items to regions</p>
        
        <ul>
	        <li>Text</li>
	        <li>Ticker</li>
    	   	<li>MicroBlog</li>
 	       	<li>Counter</li>
        </ul>

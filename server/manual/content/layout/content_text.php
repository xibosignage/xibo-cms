
	<a name="Text" id="Text"></a><h2>Text</h2>
	<p>Adding a piece of text to a layout provides a form within which you can write your message, style your message, choose a 
	direction animation which will place a scrolling feature on your message.</p>

	<p>Text is specific to the layout it is added to; and it is not saved in the library. So you will need to copy/paste between 
	layouts if you want to use text on more than one layout. The reason for this is that it very quickly 
	becomes unmanageable to have named text strings in the library - especially when you have minor variations.</p>

	<p>Note: <i>A certain amount of experimentation is required when sizing text. The text preview in the web interface can be misleading 
	about how the text will finally fit on the layout. If possible, preview a new layout on a display to see how the text fits, and 
	make any adjustments required to get the layout as you want it.</i></p>

<blockquote>
	<p>Add Text</p>
	<ul>
		<li>Click the "Add Text" icon</li>
		<li>A new dialogue will appear:<br />

		<p><img alt="Ss_layout_designer_add_text" src="content/layout/Ss_layout_designer_add_text.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="631" height="442"></p></li>
	
		<li>You'll see the text editor. <?php echo PRODUCT_NAME; ?> uses CKeditor for text input. The format is very similar to many word processing applications 
			you may have used in the past. Complete documentation for all the buttons is available over at CKEditor's website here: 
	
			<a href="http://docs.cksource.com/CKEditor_3.x/Users_Guide" target="_blank">http://docs.cksource.com/CKEditor_3.x/Users_Guide</a></li>

		<li>Type in the text you want to add.</li>
		<li>To change the font, highlight your text and choose a new font from the "Font" dropdown menu.</li>
		<li>To change the size, highlight your text and choose a new size from the "Size" dropdown menu.</li>
		<li>To change the colour, highlight your text and choose a new colour from the font colour pallet icon 
	
		<a href="Textcolor.gif" class="image" title="Textcolor.gif"><img alt="Textcolor.gif" src="content/layout/Textcolor.gif" width="28" height="20" border="0" /></a></li>

		<li>Bold, italic and underline are available using the respective icon:		
		<a href="TextProp.gif" class="image" title="TextProp.gif"><img alt="TextProp.gif" src="content/layout/TextProp.gif" width="66" height="20" border="0" /></a>

		<li>Enter a duration in seconds for the text to be on the layout.<br />
			<i>Note that if this is the only media item in a region, then this is the minimum amount of time the text will be shown 
			for as the total time shown will be dictated by the total run time of the longest-running region on the layout.</i></li>
		<li>Optionally select a direction for the text to scroll in. Available options are Up, Down, Left and Right.</li>
		<li>If you have selected to scroll the text, you can control the speed of the scrolling by editing the "Scroll Speed" value. 
			Lower numbers cause the text to scroll faster.</li>
		<li>When you are happy with your text, click the "Save" button.</li>
	</ul>
</blockquote>



	<a name="MicroBlog" id="MicroBlog"></a><h2>MicroBlog</h2>
	<p>The MicroBlog module searches Identi.ca and/or Twitter for the search term you specify and then displays posts that 
	contain that term one at a time with a fade transition between items.</p>

<blockquote>
	<p>Adding a MicroBlog search:</p>
	<ul>
		<li>Click the "Add MicroBlog" button</li>
		<li>A new dialogue will appear:<br />
	
		<p><img alt="Ss_layout_designer_add_microblog" src="content/layout/Ss_layout_designer_add_microblog.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="662" height="697"></p></li>			

		<li>First choose which service or services you want to search. Tick either the Twitter or Identica boxes, or both.</li>
		<li>Next enter a search term. This is the word you want to search for.</li>
		<li>Enter a duration in seconds. This is the total time you want the media to be shown for (as you would for any other <?php echo PRODUCT_NAME; ?> media item)</li>
		<li>The Fade Interval box controls how long in seconds the fade in and fade out animations run for. 1 second is a sensible default.</li>
		<li>Speed controls how long each post is displayed for in seconds. 5 seconds is a sensible default.</li>
		<li>Update Interval controls how often the client will connect to the microblog services and search for new content in minutes. 
		Twitter limits the number of searches you can do over a fixed period so I'd suggest setting this to around 10 minutes. 
		Cached content is shown between updates.</li>
		<li>History Size tells the client how many items to hold in local cache. When the client connects to the microblog services for new 
			content, all new posts that match the search term are collected and displayed, however only the newest number of posts specified 
			by History Size are cached to disk for display later.</li>
		<li>The Template tells the client how to format the posts. You can put in several tags to represent content from the posts.
		<ul>
			<li>[text] - The main message from the post</li>
			<li>[from_user] - The username of the person that made the post</li>
			<li>[service] - The service that the post came from</li>
			<li>[profile_image_url] - The url of the posts authors avatar image. You can put the template editor in to source mode to 
				add in an image using that url directly. Note that those images are nOt cached to disk so require a working internet 
				connection to be displayed.</li>
			<li>You can extract any value returned by the Twitter or Identica API. 
				<a href="http://search.twitter.com/search.json?q=<?php echo PRODUCT_NAME; ?>" class="external text" title="http://search.twitter.com/search.json?q=<?php echo PRODUCT_NAME; ?>"
        		rel="nofollow" target="_blank">Twitter Search for <?php echo PRODUCT_NAME; ?></a> will show you the JSON result of a query on their API for the term "<?php echo PRODUCT_NAME; ?>". 
				You can use this to get the names of any other tags you may want to display.</li>
		</ul></li>
		<li>The Default block is what the client will display if the search returns no results or if there is no cached content and the 
			services could not be contacted.</li>
	</ul>
</blockquote>


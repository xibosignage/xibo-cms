<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */ 
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<h1>Video</h1>
<p>You can upload your videos to show on a <?php echo PRODUCT_NAME; ?> layout. Currently WMV, AVI, and MPEG video files are supported.</p>
<p>If your client player also supports other video types e.g. mp4, you may include them in the "Valid Extensions" in 
<a href="../admin/modules.php">Administration->Modules</a> section under "Video" media content.</p>

<p><i>Note: In Ubuntu Linux, the maximum upload media file size can be configured in /etc/php5/apache2/php.ini by
changing the "upload_max_filesize" as shown below:<br />
; Maximum allowed size for uploaded files.<br />
upload_max_filesize = 200M</i></p>

<p>Add a video</p>
<ul>
<li>Click the "Add Video" icon</li>
<li>A new dialogue will appear:

<p><img alt="Ss_layout_designer_add_video" src="content/layout/Ss_layout_designer_add_video.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="458" height="288" border="1px"></p></li>

<li>Click "Browse"</li>
<li>Select the video file you want to upload from your computer. Click OK</li>
<li>While the file uploads, give the video a name for use inside <?php echo PRODUCT_NAME; ?>. Type the name in the "Name" box.</li>
<li>Finally enter a duration in seconds that you want the video to play for, or 0 to play the entire video.<br />
<i>Note that if this is the only media item in a region, then this is the minimum amount of time the video will be shown for as 
the total time shown will be dictated by the total run time of the longest-running region on the layout. Videos do not loop 
automatically so you need to add a second media item in the region with the video to cause it to play again.</i></li>
<li>Click "Save". <br />
<i>Note:The save button will NOT be enabled until transfer of the file to the server is completed.</i></li>
</ul>

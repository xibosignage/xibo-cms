<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
<h2> <span class="mw-headline" id="Request_Formats"> Request Formats </span></h2>
<p>Xibo supports the following request formats
</p>
<ul><li> REST
</li></ul>
<h3> <span class="mw-headline" id="REST"> REST </span></h3>
<p>A simple POST or GET.
</p><p>To request the Xibo Version method:
</p>
<pre>services.php?service=rest&amp;method=version
</pre>
<p>By default the response type is xml. To get a different response type send "&amp;response="
</p><p><br />
</p>
<h2> <span class="mw-headline" id="Response_Types"> Response Types </span></h2>
<p>Xibo supports the following response types
</p>
<ul><li> JSON (not implemented)
</li><li> XML
</li></ul>
<h3> <span class="mw-headline" id="JSON"> JSON </span></h3>
<p>To return a JSON object specify the response to be JSON (response="json")
</p><p>A method call returns:
</p>
<pre>xiboApi({
  "stat":"ok",
  "response": {...}
})
</pre>
<p><br />
A failure call returns:
</p>
<pre>xiboApi({
  "stat":"error",
  "error": {
     "code": "[error-code]",
     "message": "[error-message]"
  }
})
</pre>
<h3> <span class="mw-headline" id="XML"> XML </span></h3>
<p>A successful call returns this:
</p>
<pre>&lt;?xml version="1.0" encoding="utf-8"&#160;?&gt;
&lt;rsp status="ok"&gt;
	[xml-payload-here]
&lt;/rsp&gt;
</pre>
<p>A failure call returns this:
</p>
<pre>&lt;?xml version="1.0" encoding="utf-8"&#160;?&gt;
&lt;rsp status="error"&gt;
    &lt;error code="[error-code]" message="[error-message]"
&lt;/rsp&gt;
</pre>
<h2> <span class="mw-headline" id="Error_Codes">General Error Codes </span></h2>
<p>A list of the potential error codes from each method call can be found with the documentation of that call.
</p>

<table border="1">
<caption> Error code numbers and explanations
</caption>
<tr>
<th>Error Code</th>
<th>Explanation</th>
<th>Use case
</th></tr>
<tr>
<td> 1 </td>
<td>Access Denied</td>
<td>OAuth was successful, but Xibo denied access to that functionality for the user
</td></tr>
<tr>
<td> 2 </td>
<td>Checksum does not match with the generated checksum for the current payload</td>
<td>Used when Uploading data or requesting file offset
</td></tr>
<tr>
<td> 3 </td>
<td>Unable to add File record to the Database</td>
<td>
</td></tr>
<tr>
<td> 4 </td>
<td>Library location does not exist</td>
<td>
</td></tr>
<tr>
<td> 5 </td>
<td>Unable to create file in the library location</td>
<td>
</td></tr>
<tr>
<td> 6 </td>
<td>Unable to write to file in the library location</td>
<td>
</td></tr>
<tr>
<td> 7 </td>
<td>File does not exist</td>
<td>
</td></tr>
<tr>
<td>10</td>
<td>The Layout Name cannot be longer than 100 characters</td>
<td>
</td></tr>
<tr>
<td>11</td>
<td>You must enter a duration</td>
<td>
</td></tr>
<tr>
<td>12</td>
<td>The user already owns media with this name</td>
<td>
</td></tr>
<tr>
<td>13</td>
<td>Error inserting media into the database</td>
<td>
</td></tr></table>
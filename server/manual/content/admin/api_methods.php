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
<h2 id="Request_Formats">Request Formats</h2>
<p><?php echo PRODUCT_NAME; ?> supports the following request formats</p>

<ul><li>REST</li></ul>


<a name="REST" id="REST"></a><h3>REST</h3>
<p>A simple POST or GET.</p>
<p>To request the <?php echo PRODUCT_NAME; ?> Version method:</p>
<pre>services.php?service=rest&amp;method=version</pre>
<p>By default the response type is xml. To get a different response type send "&amp;response="</p>


<a name="Response_Types" id="Response_Types"></a><h2>Response Types</h2>
<p><?php echo PRODUCT_NAME; ?> supports the following response types</p>


<ul><li>JSON</li>
<li> XML</li></ul>

<a name="JSON" id="JSON"></a><h3>JSON</h3>
<p>To return a JSON object specify the response to be JSON (response="json")</p>
<p>A method call returns:</p>
<pre><?php echo PRODUCT_NAME; ?>Api({
"stat":"ok",
"response": {...}
})
</pre>

<p>A failure call returns:</p>
<pre><?php echo PRODUCT_NAME; ?>Api({
"stat":"error",
"error": {
"code": "[error-code]",
"message": "[error-message]"
}
})
</pre>

<a name="XML" id="XML"></a><h3>XML</h3>
<p>A successful call returns this:</p>
<pre>&lt;?xml version="1.0" encoding="utf-8"&nbsp;?&gt;
&lt;rsp status="ok"&gt;
[xml-payload-here]
&lt;/rsp&gt;
</pre>
<p>A failure call returns this:</p>
<pre>&lt;?xml version="1.0" encoding="utf-8"&nbsp;?&gt;
&lt;rsp status="error"&gt;
&lt;error code="[error-code]" message="[error-message]"
&lt;/rsp&gt;
</pre>


<a name="Error_Codes" id="Error_Codes"></a><h2>Error Codes</h2>
<table class="table">
<caption> Error code numbers and explanations</caption>
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
<p>A list of the potential error codes from each method call can be found with the documentation of that call.</p>

<a name="Methods" id="Methods"></a><h2>Methods</h2>
<p>Transactions supported by <?php echo PRODUCT_NAME; ?></p>


<a name="Displays" id="Displays"></a><h3>Displays</h3>
<ul><li>DisplayList</li>
<li>DisplayEdit</li>
<li>DisplayRetire</li>
<li>DisplayDelete</li>
<li>DisplayUserGroupSecurity</li>
<li>DisplayUserGroupEdit</li>
</ul>

<a name="DisplayGroups" id="DisplayGroups"></a><h3>DisplayGroups</h3>
<ul><li> DisplayGroupList</li>
<li> DisplayGroupAdd</li>
<li> DisplayGroupEdit</li>
<li> DisplayGroupDelete</li>
<li> DisplayGroupMembersList</li>
<li> DisplayGroupMembersEdit</li>
<li> DisplayGroupUserGroupList</li>
<li> DisplayGroupUserGroupEdit</li></ul>

<a name="Layout" id="Layout"></a><h3>Layout</h3>
<ul><li> LayoutList</li>
<li> LayoutAdd</li>
<li> LayoutEdit</li>
<li> LayoutCopy</li>
<li> LayoutDelete</li>
<li> LayoutRetire</li>
<li> LayoutBackgroundList</li>
<li> LayoutBackgroundEdit</li>
<li> LayoutGetXlf</li>
<li> LayoutRegionList</li>
<li> LayoutRegionAdd</li><li> LayoutRegionEdit</li>
<li> LayoutRegionPosition</li><li> LayoutRegionTimelineList</li>
<li> LayoutRegionMediaAdd</li>
<li> LayoutRegionMediaReorder</li>
<li> LayoutRegionMediaDelete</li>
<li> LayoutRegionLibraryAdd</li>
<li> LayoutRegionMediaEdit</li>
<li> LayoutRegionMediaDetails</li></ul>

<a name="Library" id="Library"></a><h3>Library</h3>
<ul><li> LibraryMediaFileUpload</li>
<li> LibraryMediaFileRevise</li>
<li> LibraryMediaAdd</li>
<li> LibraryMediaEdit</li>
<li> LibraryMediaRetire</li>
<li> LibraryMediaDownload</li>
<li> LibraryMediaList</li></ul>

<a name="Schedule" id="Schedule"></a><h3>Schedule</h3>
<ul><li> ScheduleList</li>
<li> ScheduleAdd</li>
<li> ScheduleEdit</li>
<li> ScheduleDelete</li></ul>

<a name="Template" id="Template"></a><h3>Template</h3>
<ul><li> TemplateList</li>
<li> TemplateDelete</li></ul>

<a name="Resolution" id="Resolution"></a><h3>Resolution</h3>
<ul><li> ResolutionList</li></ul>

<a name="Modules" id="Modules"></a><h3>Modules</h3>
<ul><li> ModuleList</li></ul>

<a name="Other" id="Other"></a><h3>Other</h3>
<ul><li> Version</li>
<li> ServerStatus</li></ul>


<a name="Method_Calls" id="Method_Calls"></a><h2>Method Calls</h2>


<a name="LayoutAdd" id="LayoutAdd"></a><h3>LayoutAdd</h3>
<p>Parameters</p>
<ul><li> layout - The Name of the Layout</li>
<li> description - The Description of the Layout</li>
<li> permissionid - PermissionID for the layout</li>
<li> tags - Tags for the Layout</li>
<li> templateid - Template for the Layout</li>
</ul>

<p>Response</p>
<ul><li> layout - The ID of the layout</li></ul>

<p>Errors</p>
<ul><li> Code 1 - Access Denied</li>
<li> Code 25001 - Layout Name must be between 1 and 50 characters</li>
<li> Code 25002 - Description must be less than 254 characters</li>
<li> Code 25003 - All tags combined must be less that 254 characters</li>
<li> Code 25004 - User already has a layout with this name</li>
<li> Code 25005 - Database error adding layout</li>
<li> Code 25006 - Failed to Parse Tags</li>
<li> Code 25007 - Unable to update layout xml</li>
<li> Code 25008 - Unable to Delete layout on failure</li>
</ul>

<a name="LayoutEdit" id="LayoutEdit"></a><h3> <span class="mw-headline"> LayoutEdit </span></h3>
<p>Not implemented</p>

<a name="LayoutUpdateXlf" id="LayoutUpdateXlf"></a><h3> <span class="mw-headline"> LayoutUpdateXlf </span></h3>
<p>Not Implemented</p>

<a name="LayoutBackground" id="LayoutBackground"></a><h3> <span class="mw-headline"> LayoutBackground </span></h3>
<p>Not Implemented</p>

<a name="LayoutDelete" id="LayoutDelete"></a><h3> <span class="mw-headline"> LayoutDelete </span></h3>
<p>Parameters</p>
<ul><li> layoutId - The ID of the layout to delete</li></ul>
<p>Response</p>
<ul><li> success = true</li></ul>
<p>Errors</p>
<ul><li> Code 1 - Access Denied</li><li> Code 25008 - Unable to delete layout</li></ul>

<a name="TemplateDelete" id="TemplateDelete"></a><h3> <span class="mw-headline"> TemplateDelete </span></h3>
<p>Parameters</p>
<ul><li> templateId - The ID of the template to delete</li></ul>
<p>Response</p>
<ul><li> success = true</li></ul>
<p>Errors</p>
<ul><li> Code 1 - Access Denied</li><li> Code 25105 - Unable to delete template</li></ul>

<a name="LibraryMediaFileUpload" id="LibraryMediaFileUpload"></a><h3> <span class="mw-headline"> LibraryMediaFileUpload </span></h3>
<p>Parameters</p>
<ul>
<li> FileID - Null for 1st call</li><li> Chunk Offset</li>
<li> Check Sum (MD5)</li></ul>
<p>Response</p>
<ul><li> FileID</li>
<li> Offset (file length)</li></ul>
<p>Errors</p>
<ul><li> 1 - Access Denied</li>
<li> 2 - Payload Checksum doesn't match provided checksum</li>
<li> 3 - Unable to add File record to the Database</li>
<li> 4 - Library location does not exist</li>
<li> 5 - Unable to create file in the library location</li>
<li> 6 - Unable to write to file in the library location</li>
<li> 7 - File does not exist</li></ul>

<a name="LibraryMediaAdd" id="LibraryMediaAdd"></a><h3> <span class="mw-headline"> LibraryMediaAdd </span></h3>
<p>Parameters	</p>
<ul><li> fileId</li>
<li>type (image|video|flash|ppt)</li>
<li> name</li>
<li>duration</li>
<li> permissionId (1|2|3)</li>
<li> fileName (including extension)</li></ul>
<p>Response</p>
<ul><li> MediaID</li></ul>
<p>Errors</p>
<ul><li> Code 1 - Access Denied</li>
<li> Code 10 - The Name cannot be longer than 100 characters</li>
<li> Code 11 - You must enter a duration</li>
<li> Code 12 - This user already owns media with this name</li>
<li> Code 13 - Error inserting media into the database</li>
<li> Code 14 - Cannot clean up after failure</li>
<li> Code 15 - Cannot store file</li>
<li> Code 16 - Cannot update stored file location</li>
<li> Code 18 - Invalid File Extension</li></ul>

<a name="LibraryMediaEdit" id="LibraryMediaEdit"></a><h3> <span class="mw-headline"> LibraryMediaEdit </span></h3>
<p>Parameters</p>
<ul><li> mediaId</li>
<li> name</li>
<li>duration</li>
<li> permissionId (1|2|3)</li>
</ul>
<p>Response</p>
<ul><li> success</li></ul>
<p>Errors</p>
<ul><li> 1 - Access Denied</li>
<li> 10 - The Name cannot be longer than 100 characters</li>
<li> 11 - You must enter a duration</li>
<li> 12 - This user already owns media with this name</li>
<li> 30 - Database failure updating media</li>
</ul>

<a name="LibraryMediaFileRevise" id="LibraryMediaFileRevise"></a><h3> <span class="mw-headline"> LibraryMediaFileRevise </span></h3>
<p>Parameters</p>
<ul><li> mediaId</li>
<li> fileId</li>
<li> fileName (including extension)</li></ul>
<p>Response</p>
<ul><li> mediaId</li></ul>
<p>Errors</p>
<ul><li> 1 - Access Denied</li>
<li> 13 - Error inserting media into the database</li>
<li> 14 - Cannot clean up after failure</li>
<li> 15 - Cannot store file</li>
<li> 16 - Cannot update stored file location</li>
<li> 18 - Invalid File Extension</li>
<li> 31 - Unable to get information about existing media record</li>
<li> 32 - Unable to update existing media record</li>
</ul>

<a name="LibraryMediaRetire" id="LibraryMediaRetire"></a><h3> <span class="mw-headline"> LibraryMediaRetire </span></h3>
<p>Parameters</p>
<ul><li> mediaId</li></ul>
<p>Response</p>
<ul><li> success</li></ul>
<p>Error Codes</p>
<ul><li> 1 - Access Denied</li>
<li> 19 - Error retiring media</li></ul>

<a name="LibraryMediaDelete" id="LibraryMediaDelete"></a><h3> <span class="mw-headline"> LibraryMediaDelete </span></h3>
<p>Parameters</p>
<ul><li> mediaId</li></ul>
<p>Response</p>
<ul><li> Success = True</li></ul>
<p>Error Codes</p>
<ul><li> 1 - Access Denied</li>
<li> 20 - Cannot check if media is assigned to layouts</li>
<li> 21 - Media is in use</li>
<li> 22 - Cannot locate stored files, unable to delete</li>
<li> 23 - Database error deleting media</li>
</ul>

<a name="ModuleList" id="ModuleList"></a><h3> <span class="mw-headline"> ModuleList </span></h3>
<p>Response A list of modules with the following attributes:</p>
<ul><li> module - The Module Name</li>
<li> layoutOnly- Whether the module is a library based module or only available for layouts</li>
<li> description - A description of the module</li>
<li> extensions - Extensions allowed by this module</li>
</ul>
<p>Error Codes</p>
<ul><li> 40 - Unable to query for modules</li></ul>

<a name="Version" id="Version"></a><h3> <span class="mw-headline"> Version </span></h3>
<p>Response</p>
<pre>&lt;?xml version="1.0"?&gt;
&lt;rsp status="ok"&gt;
&lt;version app_ver="1.1.1" XlfVersion="1" XmdsVersion="2" DBVersion="22"/&gt;
&lt;/rsp&gt;
</pre>




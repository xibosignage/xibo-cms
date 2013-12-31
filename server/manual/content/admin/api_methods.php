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
<h2> <span class="mw-headline" id="Error_Codes"> Error Codes </span></h2>
<p>A complete list of error codes can be found here: <a rel="nofollow" class="external free" href="http://wiki.xibo.org.uk/wiki/Error_codes">http://wiki.xibo.org.uk/wiki/Error_codes</a>
</p><p>A list of the potential error codes from each method call can be found with the documentation of that call.
</p>
<h2> <span class="mw-headline" id="Methods"> Methods </span></h2>
<p>A list of transactions that we intend to support
</p>
<h3> <span class="mw-headline" id="Displays"> Displays </span></h3>
<ul><li> DisplayList
</li><li> DisplayEdit
</li><li> DisplayRetire
</li><li> DisplayDelete
</li><li> DisplayUserGroupSecurity
</li><li> DisplayUserGroupEdit
</li></ul>
<h3> <span class="mw-headline" id="DisplayGroups"> DisplayGroups </span></h3>
<ul><li> DisplayGroupList
</li><li> DisplayGroupAdd
</li><li> DisplayGroupEdit
</li><li> DisplayGroupDelete
</li><li> DisplayGroupMembersList
</li><li> DisplayGroupMembersEdit
</li><li> DisplayGroupUserGroupList
</li><li> DisplayGroupUserGroupEdit
</li></ul>
<h3> <span class="mw-headline" id="Layout"> Layout </span></h3>
<ul><li> LayoutList
</li><li> LayoutAdd
</li><li> LayoutEdit
</li><li> LayoutCopy
</li><li> LayoutDelete
</li><li> LayoutRetire
</li><li> LayoutBackgroundList
</li><li> LayoutBackgroundEdit
</li><li> LayoutGetXlf
</li><li> LayoutRegionList
</li><li> LayoutRegionAdd
</li><li> LayoutRegionEdit
</li><li> LayoutRegionPosition
</li><li> LayoutRegionTimelineList
</li><li> LayoutRegionMediaAdd
</li><li> LayoutRegionMediaReorder
</li><li> LayoutRegionMediaDelete
</li><li> LayoutRegionLibraryAdd
</li><li> LayoutRegionMediaEdit
</li><li> LayoutRegionMediaDetails
</li></ul>
<h3> <span class="mw-headline" id="Library"> Library </span></h3>
<ul><li> LibraryMediaFileUpload
</li><li> LibraryMediaFileRevise
</li><li> LibraryMediaAdd
</li><li> LibraryMediaEdit
</li><li> LibraryMediaRetire
</li><li> LibraryMediaDownload
</li><li> LibraryMediaList
</li></ul>
<h3> <span class="mw-headline" id="Schedule"> Schedule </span></h3>
<ul><li> ScheduleList
</li><li> ScheduleAdd
</li><li> ScheduleEdit
</li><li> ScheduleDelete
</li></ul>
<h3> <span class="mw-headline" id="Template"> Template </span></h3>
<ul><li> TemplateList
</li><li> TemplateDelete
</li></ul>
<h3> <span class="mw-headline" id="Resolution"> Resolution </span></h3>
<ul><li> ResolutionList
</li></ul>
<h3> <span class="mw-headline" id="Modules"> Modules</span></h3>
<ul><li> ModuleList
</li></ul>
<h3> <span class="mw-headline" id="Other"> Other </span></h3>
<ul><li> Version
</li><li> ServerStatus
</li></ul>
<h1> <span class="mw-headline" id="Method_Calls"> Method Calls </span></h1>
<p>Currently supported transactions
</p>
<h2> <span class="mw-headline" id="Library_2"> Library </span></h2>
<p>Transactions related to the Library
</p>
<h3> <span class="mw-headline" id="LibraryMediaFileUpload"> LibraryMediaFileUpload </span></h3>
<p>Parameters
</p>
<ul><li> FileID - Null for 1st call
</li><li> Chunk Offset
</li><li> Check Sum (MD5)
</li></ul>
<p>Response
</p>
<ul><li> FileID
</li><li> Offset (file length)
</li></ul>
<p>Errors
</p>
<ul><li> 1 - Access Denied
</li><li> 2 - Payload Checksum doesn't match provided checksum
</li><li> 3 - Unable to add File record to the Database
</li><li> 4 - Library location does not exist
</li><li> 5 - Unable to create file in the library location
</li><li> 6 - Unable to write to file in the library location
</li><li> 7 - File does not exist
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaAdd"> LibraryMediaAdd </span></h3>
<p>Parameters
</p>
<ul><li> fileId
</li><li> type (image|video|flash|ppt)
</li><li> name
</li><li> duration
</li><li> fileName (including extension)
</li></ul>
<p>Response
</p>
<ul><li> MediaID
</li></ul>
<p>Errors
</p>
<ul><li> Code 1 - Access Denied
</li><li> Code 10 - The Name cannot be longer than 100 characters
</li><li> Code 11 - You must enter a duration
</li><li> Code 12 - This user already owns media with this name
</li><li> Code 13 - Error inserting media into the database
</li><li> Code 14 - Cannot clean up after failure
</li><li> Code 15 - Cannot store file
</li><li> Code 16 - Cannot update stored file location
</li><li> Code 18 - Invalid File Extension
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaEdit"> LibraryMediaEdit </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li><li> name
</li><li> duration
</li></ul>
<p>Response
</p>
<ul><li> success
</li></ul>
<p>Errors
</p>
<ul><li> 1 - Access Denied
</li><li> 10 - The Name cannot be longer than 100 characters
</li><li> 11 - You must enter a duration
</li><li> 12 - This user already owns media with this name
</li><li> 30 - Database failure updating media
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaFileRevise"> LibraryMediaFileRevise </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li><li> fileId
</li><li> fileName (including extension)
</li></ul>
<p>Response
</p>
<ul><li> mediaId
</li></ul>
<p>Errors
</p>
<ul><li> 1 - Access Denied
</li><li> 13 - Error inserting media into the database
</li><li> 14 - Cannot clean up after failure
</li><li> 15 - Cannot store file
</li><li> 16 - Cannot update stored file location
</li><li> 18 - Invalid File Extension
</li><li> 31 - Unable to get information about existing media record
</li><li> 32 - Unable to update existing media record
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaRetire"> LibraryMediaRetire </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li></ul>
<p>Response
</p>
<ul><li> success
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li><li> 19 - Error retiring media
</li></ul>
<h3> <span class="mw-headline" id="LibraryMediaDelete"> LibraryMediaDelete </span></h3>
<p>Parameters
</p>
<ul><li> mediaId
</li></ul>
<p>Response
</p>
<ul><li> Success = True
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li><li> 20 - Cannot check if media is assigned to layouts
</li><li> 21 - Media is in use
</li><li> 22 - Cannot locate stored files, unable to delete
</li><li> 23 - Database error deleting media
</li></ul>
<h2> <span class="mw-headline" id="Layout_2"> Layout </span></h2>
<p>Transactions Related to Layouts
</p>
<h3> <span class="mw-headline" id="LayoutAdd"> LayoutAdd </span></h3>
<p>Parameters
</p>
<ul><li> layout - The Name of the Layout
</li><li> description - The Description of the Layout
</li><li> permissionid - PermissionID for the layout
</li><li> tags - Tags for the Layout
</li><li> templateid - Template for the Layout
</li></ul>
<p>Response
</p>
<ul><li> layout - The ID of the layout
</li></ul>
<p>Errors
</p>
<ul><li> Code 1 - Access Denied
</li><li> Code 25001 - Layout Name must be between 1 and 50 characters
</li><li> Code 25002 - Description must be less than 254 characters
</li><li> Code 25003 - All tags combined must be less that 254 characters
</li><li> Code 25004 - User already has a layout with this name
</li><li> Code 25005 - Database error adding layout
</li><li> Code 25006 - Failed to Parse Tags
</li><li> Code 25007 - Unable to update layout xml
</li><li> Code 25008 - Unable to Delete layout on failure
</li></ul>
<h3> <span class="mw-headline" id="LayoutDelete"> LayoutDelete </span></h3>
<p>Parameters
</p>
<ul><li> layoutId - The ID of the layout to delete
</li></ul>
<p>Response
</p>
<ul><li> success = true
</li></ul>
<p>Errors
</p>
<ul><li> Code 1 - Access Denied
</li><li> Code 25008 - Unable to delete layout
</li></ul>
<h3> <span class="mw-headline" id="TemplateDelete"> TemplateDelete </span></h3>
<p>Parameters
</p>
<ul><li> templateId - The ID of the template to delete
</li></ul>
<p>Response
</p>
<ul><li> success = true
</li></ul>
<p>Errors
</p>
<ul><li> Code 1 - Access Denied
</li><li> Code 25105 - Unable to delete template
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionList"> LayoutRegionList </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li></ul>
<p>Response
</p><p>A list of region timelines. Each item will have the following values:
</p>
<ul><li> regionid
</li><li> width
</li><li> height
</li><li> top
</li><li> left
</li><li> ownerid
</li><li> permission_edit
</li><li> permission_del
</li><li> permission_update_permissions
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionAdd"> LayoutRegionAdd </span></h3>
<p>Adds a new Region Timeline to a Layout
</p><p>Parameters
</p>
<ul><li> layoutId
</li><li> width
</li><li> height
</li><li> top
</li><li> left
</li><li> name
</li></ul>
<p>Response
</p>
<ul><li> success = true
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionEdit"> LayoutRegionEdit </span></h3>
<p>Edits an existing Region Timeline on a Layout
</p><p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li><li> width
</li><li> height
</li><li> top
</li><li> left
</li><li> name
</li></ul>
<p>Response
</p>
<ul><li> success = true
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionDelete"> LayoutRegionDelete </span></h3>
<p>Deletes an existing Region Timeline on a Layout
</p><p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li></ul>
<p>Response
</p>
<ul><li> success = true
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h2> <span class="mw-headline" id="Layout_Timelines"> Layout Timelines </span></h2>
<p>Transactions related to layout timelines
</p>
<h3> <span class="mw-headline" id="LayoutRegionTimelineList"> LayoutRegionTimelineList </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li></ul>
<p>Response
</p><p>A list of media items on a region timeline. Each item will have the following values:
</p>
<ul><li> mediaid
</li><li> lkid
</li><li> type
</li><li> duration
</li><li> permission_edit
</li><li> permission_del
</li><li> permission_update_duration
</li><li> permission_update_permissions
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionMediaDetails"> LayoutRegionMediaDetails </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li><li> mediaId
</li></ul>
<p>Response
The XLF for the provided media id (XML format)
</p><p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionMediaAdd"> LayoutRegionMediaAdd </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li><li> type (the type of media item being added)
</li><li> xlf (the xibo layout file xml representing the media to add)
</li></ul>
<p>The XLF will be checked for the attributes that are required for all media type. It is the callers responsibility to ensure media type specific attributes are set correctly.
</p><p>Response
</p>
<pre> The Media ID added
</pre>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionLibraryAdd"> LayoutRegionLibraryAdd </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li><li> mediaList (A list of media id's from the library that should be added to to supplied layout/region)
</li></ul>
<p>Response
</p>
<pre>success (true|error)
</pre>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionMediaEdit"> LayoutRegionMediaEdit </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li><li> mediaId
</li><li> xlf (the xibo layout file xml representing the media to add)
</li></ul>
<p>The XLF will be checked for the attributes that are required for all media type. It is the callers responsibility to ensure media type specific attributes are set correctly.
</p><p>Response
</p>
<pre>success (true|error)
</pre>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionMediaReorder"> LayoutRegionMediaReorder </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li><li> mediaList (array('mediaid' =&gt; <i>, 'lkid' =&gt; 0))</i>
</li></ul>
<p>Response
</p>
<ul><li> success (true|false)
</li></ul>
<p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="LayoutRegionMediaDelete"> LayoutRegionMediaDelete </span></h3>
<p>Parameters
</p>
<ul><li> layoutId
</li><li> regionId
</li><li> mediaId
</li></ul>
<p>Response
success (true|error)
</p><p>Error Codes
</p>
<ul><li> 1 - Access Denied
</li></ul>
<h3> <span class="mw-headline" id="ModuleList"> ModuleList </span></h3>
<p>Response
A list of modules with the following attributes:
</p>
<ul><li> module - The Module Name
</li><li> layoutOnly- Whether the module is a library based module or only available for layouts
</li><li> description - A description of the module
</li><li> extensions - Extensions allowed by this module
</li></ul>
<p>Error Codes
</p>
<ul><li> 40 - Unable to query for modules
</li></ul>
<h3> <span class="mw-headline" id="Version"> Version </span></h3>
<p>Response
</p>
<pre>&lt;?xml version="1.0"?&gt;
&lt;rsp status="ok"&gt;
    &lt;version app_ver="1.1.1" XlfVersion="1" XmdsVersion="2" DBVersion="22"/&gt;
&lt;/rsp&gt;
</pre>
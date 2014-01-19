
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
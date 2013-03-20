<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<title><?php echo PRODUCT_NAME; ?> Documentation</title>
		<link rel=stylesheet type="text/css" href="../../css/doc.css">
		<meta http-equiv="Content-Type" content="text/html" />
		<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />

		<link href="img/favicon.ico" rel="shortcut icon"/>
		<!-- Javascript Libraries -->
		<script type="text/javascript" src="lib/jquery.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
	</head>
	<body>
	<h1><?php echo PRODUCT_NAME; ?> API</h1>

    <p><img alt="SA <?php echo PRODUCT_NAME; ?> API" src="sa_api.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="805" height="149"></p>

    <a name="Authorization" id="Authorization"></a><h2>Authorization</h2>
    <p>OAuth will be used to provide authorization for access to the <?php echo PRODUCT_NAME; ?> API.</p>
 
<blockquote>    
    <a name="OAuth_Information" id="OAuth_Information"></a><h3>OAuth Information</h3>
    <p>Service location: services.php</p>
    <p>OAuth methods:</p>

    <ul>
	    <li> XRDS: services.php?xrds</li>
	    <li> Request Token: services.php?service=oauth&amp;method=request_token</li>
	    <li> Authorize Token: index.php?p=oauth&amp;q=authorize</li>
	    <li> Access Token: services.php?service=oauth&amp;method=access_token</li>
    </ul>

    <a name="XRDS" id="XRDS"></a><h3>XRDS</h3>
    <pre>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
      &lt;XRDS xmlns="xri://$xrds"&gt;
      &lt;XRD xmlns:simple="<a href="http://xrds-simple.net/core/1.0" class="external free" title="http://xrds-simple.net/core/1.0" 
      rel="nofollow">http://xrds-simple.net/core/1.0</a>"
      xmlns="xri://$XRD*($v*2.0)"  xmlns:openid="<a href="http://openid.net/xmlns/1.0" class="external free" title="http://openid.net/xmlns/1.0" rel="nofollow">http://openid.net/xmlns/1.0</a>" version="2.0" xml:id="main"&gt; 
      &lt;Type&gt;xri://$xrds*simple&lt;/Type&gt;
    &lt;Service&gt;
	    &lt;Type&gt;<a href="http://oauth.net/discovery/1.0" class="external free" title="http://oauth.net/discovery/1.0" rel="nofollow">http://oauth.net/discovery/1.0</a>&lt;/Type&gt;
	    &lt;URI&gt;#main&lt;/URI&gt;
    &lt;/Service&gt;
    &lt;Service&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/endpoint/request" class="external free" title="http://oauth.net/core/1.0/endpoint/request" rel="nofollow">http://oauth.net/core/1.0/endpoint/request</a>&lt;/Type&gt;
 	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/parameters/auth-header" class="external free" title="http://oauth.net/core/1.0/parameters/auth-header" rel="nofollow">http://oauth.net/core/1.0/parameters/auth-header</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/parameters/uri-query" class="external free" title="http://oauth.net/core/1.0/parameters/uri-query" rel="nofollow">http://oauth.net/core/1.0/parameters/uri-query</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/signature/HMAC-SHA1" class="external free" title="http://oauth.net/core/1.0/signature/HMAC-SHA1" rel="nofollow">http://oauth.net/core/1.0/signature/HMAC-SHA1</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/signature/PLAINTEXT" class="external free" title="http://oauth.net/core/1.0/signature/PLAINTEXT" rel="nofollow">http://oauth.net/core/1.0/signature/PLAINTEXT</a>&lt;/Type&gt;
	    &lt;URI&gt;http://<a href="/index.php?title=Template:XRDS_LOCATION&amp;action=edit&amp;redlink=1" class="new" title="Template:XRDS LOCATION (page does not exist)">Template:XRDS LOCATION</a>/services.php?service=oauth&amp;method=request_token&lt;/URI&gt;
	&lt;/Service&gt;
	&lt;Service&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/endpoint/authorize" class="external free" title="http://oauth.net/core/1.0/endpoint/authorize" rel="nofollow">http://oauth.net/core/1.0/endpoint/authorize</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/parameters/uri-query" class="external free" title="http://oauth.net/core/1.0/parameters/uri-query" rel="nofollow">http://oauth.net/core/1.0/parameters/uri-query</a>&lt;/Type&gt;
	    &lt;URI&gt;http://<a href="/index.php?title=Template:XRDS_LOCATION&amp;action=edit&amp;redlink=1" class="new" title="Template:XRDS LOCATION (page does not exist)">Template:XRDS LOCATION</a>/index.php?p=oauth&amp;q=authorize&lt;/URI&gt;
	&lt;/Service&gt;
	&lt;Service&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/endpoint/access" class="external free" title="http://oauth.net/core/1.0/endpoint/access" rel="nofollow">http://oauth.net/core/1.0/endpoint/access</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/parameters/auth-header" class="external free" title="http://oauth.net/core/1.0/parameters/auth-header" rel="nofollow">http://oauth.net/core/1.0/parameters/auth-header</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/parameters/uri-query" class="external free" title="http://oauth.net/core/1.0/parameters/uri-query" rel="nofollow">http://oauth.net/core/1.0/parameters/uri-query</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/signature/HMAC-SHA1" class="external free" title="http://oauth.net/core/1.0/signature/HMAC-SHA1" rel="nofollow">http://oauth.net/core/1.0/signature/HMAC-SHA1</a>&lt;/Type&gt;
	    &lt;Type&gt;<a href="http://oauth.net/core/1.0/signature/PLAINTEXT" class="external free" title="http://oauth.net/core/1.0/signature/PLAINTEXT" rel="nofollow">http://oauth.net/core/1.0/signature/PLAINTEXT</a>&lt;/Type&gt;
	    &lt;URI&gt;http://<a href="/index.php?title=Template:XRDS_LOCATION&amp;action=edit&amp;redlink=1" class="new" title="Template:XRDS LOCATION (page does not exist)">Template:XRDS LOCATION</a>/services.php?service=oauth&amp;method=access_token&lt;/URI&gt;
	&lt;/Service&gt;
    &lt;/XRD&gt;
  &lt;/XRDS&gt;
  </pre>

  	<a name="Registered_Applications" id="Registered_Applications"></a><h3>Registered Applications</h3>
  	<p>You must obtain a consumer_key and consumer_secret for your application. Keys for "Supported" applications 
  	will be shipped with <?php echo PRODUCT_NAME; ?> meaning no extra steps are required. If you have made your own application you will need
 	to register it with your <?php echo PRODUCT_NAME; ?> Server using the following address:</p>
  	<p>Register Service: index.php?p=oauth</p>
  	<p>You will be asked for:</p>
  	<ul>
  		<li>Your Name</li>
 	 	<li> Your email address</li>
  		<li> A URL for your Application</li>
  		<li> A Callback URL</li>
 	</ul>
  	<p><?php echo PRODUCT_NAME; ?> will (at a later date) have a UI for displaying all registered applications.</p>

  	<a name="Callback_URL" id="Callback_URL"></a><h3>Callback URL</h3>
   	<p>The Callback URL will be automatically called by <?php echo PRODUCT_NAME; ?> on a completely Authorize request. It will be called regardless 
   	of whether the authorization was successful and will contain an OAuth message indicating the authorize success.</p>
   	<p>If you do not specify a Callback URL <?php echo PRODUCT_NAME; ?> will show a message requesting the user return to the application once authorized.</p>
</blockquote>    

    <a name="Request_Formats" id="Request_Formats"></a><h2>Request Formats</h2>
    <p><?php echo PRODUCT_NAME; ?> supports the following request formats</p>

    <ul><li>REST</li></ul>

<blockquote>    
    <a name="REST" id="REST"></a><h3>REST</h3>
    <p>A simple POST or GET.</p>
    <p>To request the <?php echo PRODUCT_NAME; ?> Version method:</p>
    <pre>services.php?service=rest&amp;method=version</pre>
    <p>By default the response type is xml. To get a different response type send "&amp;response="</p>
</blockquote>    

    <a name="Response_Types" id="Response_Types"></a><h2>Response Types</h2>
    <p><?php echo PRODUCT_NAME; ?> supports the following response types</p>

    <blockquote>    
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
</blockquote>    

      <a name="Error_Codes" id="Error_Codes"></a><h2>Error Codes</h2>
      <p>A complete list of error codes can be found here: <a href="http://wiki.<?php echo PRODUCT_NAME; ?>.org.uk/wiki/Error_codes" 
      class="external free" title="http://wiki.<?php echo PRODUCT_NAME; ?>.org.uk/wiki/Error_codes" rel="nofollow">http://wiki.<?php echo PRODUCT_NAME; ?>.org.uk/wiki/Error_codes</a></p>
      <p>A list of the potential error codes from each method call can be found with the documentation of that call.</p>

      <a name="Methods" id="Methods"></a><h2>Methods</h2>
      <p>Transactions supported by <?php echo PRODUCT_NAME; ?></p>

  <blockquote>    
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
	</blockquote>    

    <a name="Method_Calls" id="Method_Calls"></a><h2>Method Calls</h2>

    <blockquote>    
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
	</blockquote>    

	<?php include('../../template/footer.php'); ?>
	</body>
</html>

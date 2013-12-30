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
<h1>API Authentication <small>oAuth</small></h1>
<h2 id="Authorization">Authorization</h2>
    <p>OAuth will be used to provide authorization for access to the <?php echo PRODUCT_NAME; ?> API.</p>
 
<p><img class="img-thumbnail" alt="API" src="content/admin/sa_api.png"></p>
    
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
    
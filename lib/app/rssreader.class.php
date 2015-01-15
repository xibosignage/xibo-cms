<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
 
class rssreader 
{
	private $mXml;
	private $mXsl;
	private $length; //total play length of this rss object

	// Constructor - creates an XML object based on the specified feed
	function __construct($feed, $xsl, $length, $javascript) 
	{
		$allow_url_fopen = ini_get("allow_url_fopen");
		
		if ($allow_url_fopen != 1) 
		{
			trigger_error('You must have "allow_url_fopen = On" in your PHP.ini file for RSS to function', E_USER_ERROR);
		}
	
		// retrieve the RSS feed in a SimpleXML object
		if (!$this->mXml = simplexml_load_file($feed)) 
		{
			trigger_error("This RSS feed [$feed] can not be located", E_USER_ERROR);
		}

		$this->mXsl = simplexml_load_string($xsl);// retrieve the XSL contents in a SimpleXML object

		$this->length = $length;
		$this->javascript = $javascript;

		return true;
	}

	// Extracts a single item from the RSS
	function extract_item($itemnumber) 
	{
		$xml = &$this->mXml;

		$items = $xml->xpath('//item');

		return $items[$itemnumber]->asXML();
	}

	function num_items() 
	{
			$xml = &$this->mXml;

			$items = $xml->xpath('//item');
			return count($items);
	}

	// Creates a formatted XML document based on retrieved feed
	function getFormattedXML($item) 
	{
		
			$xml = @ simplexml_load_string($item,'SimpleXMLElement',LIBXML_NOWARNING);

			// create the XSLTProcessor object
			$proc = new XSLTProcessor;

			// attach the XSL
			$proc->importStyleSheet($this->mXsl);

			// apply the transformation and return formatted data as XML string
			return $proc->transformToXML($xml);
	}

	function DisplayFeed() 
	{
		/**
		 * We need to decide what to display (obviously)
		 * Either we display ALL at once, or we display a set number at once
		 * 
		 * All at once is quite easy:
		 * 		Get the feed (already done)
		 * 		Apply the XSL with $this->mXml (ie the entire feed)
		 * 
		 * A set number at once is harder.
		 * 		Get the feed (already done)
		 * 		Get the number of items
		 * 		Put the number to display in their own div
		 * 		Cycle these divs
		 */
		$output = <<<END
			<script type="text/javascript">
				$this->javascript
			</script>
			<style type="text/css">
				body {
					cursor:url("img/transparent.cur");
					background: none transparent;
				}
			</style>
END;
			//output the entire feed
		$output .= htmlspecialchars_decode($this->getFormattedXML($this->mXml->asXML()));
	
		//work out the length per item, for the javascript
		$num_items = $this->num_items();
		$length = $this->length;

		$length_per_item = $length / $num_items;
		
		$output .= <<< END
		<input type='hidden' value='$length_per_item' id='length_per_item'>
		<input type='hidden' value='$num_items' id='num_items'>
END;
		return $output;
	}
}

?>
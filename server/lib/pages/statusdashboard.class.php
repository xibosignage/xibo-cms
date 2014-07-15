<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
 
class statusdashboardDAO 
{
	private $db;
	private $user;

	function __construct(database $db, user $user) {
		$this->db 	=& $db;
		$this->user =& $user;
	}

	function displayPage() {

		// Get some data for a bandwidth chart
		try {
		    $dbh = PDOConnect::init();
		
		    $sth = $dbh->prepare('SELECT MONTHNAME(FROM_UNIXTIME(month)) AS month, IFNULL(SUM(Size), 0) AS size FROM `bandwidth` WHERE month > :month GROUP BY MONTHNAME(FROM_UNIXTIME(month)) ORDER BY MIN(month);');
		    $sth->execute(array('month' => time() - (86400 * 365)));

		    $results = $sth->fetchAll();

		    $points = array();

		    foreach ($results as $row) {
		        
		        $points['data'][] = array($row['month'], ((double)$row['size']) / 1024 / 1024 / 1024);
		    }

		    $points['label'] = __('GB');

		    $output = array();
		    $output['points'][] = $points;

		    // Some config options
		    $output['config']['series']['bars']['show'] = true;
		    $output['config']['series']['bars']['barWidth'] = 0.6;
		    $output['config']['series']['bars']['align'] = "center";
		    $output['config']['xaxis']['mode'] = "categories";
		    $output['config']['xaxis']['tickLength'] = 0;

		    // Monthly bandwidth - optionally tested against limits
            $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

            if ($xmdsLimit > 0) {
            	// Convert to MB
            	$xmdsLimit = $xmdsLimit / 1024 / 1024;

            	// Plot as a line
            	$markings = array();

            	$markings[] = array('color' => '#FF0000', 'lineWidth' => 2, 'yaxis' => array('from' => $xmdsLimit, 'to' => $xmdsLimit));

            	$output['config']['grid']['markings'] = $markings;
            }
		  
		  	// Set the data
		  	Theme::Set('bandwidth-widget', 'var flot_bandwidth_chart = ' . json_encode($output));

		  	// We would also like a library usage pie chart!
		  	$libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

            // Library Size in Bytes
            $sth = $dbh->prepare('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media;');
            $sth->execute();
            $librarySize = $sth->fetchColumn();

		  	if ($libraryLimit == 0) {

		  		Theme::Set('library-widget', '<p class="bold-counter text-center">' . Kit::formatBytes($librarySize) . '</p>');
		  	}
		  	else {
			    // Pie chart
			    $output = array();
			    $output['points'][] = array('label' => 'Used', 'data' => (double)$librarySize);

			    if ($libraryLimit > 0) {
			    	$libraryLimit = $libraryLimit * 1024;
			    	$output['points'][] = array('label' => 'Available', 'data' => ((double)$libraryLimit - $librarySize));
			    }
			    
			    $output['config']['series']['pie']['show'] = true;
			    $output['config']['legend']['show'] = false;

			    Theme::Set('library-widget', '<div id="flot_library_chart" style="height: 400px;" class="flot-chart"></div>');
			    Theme::Set('library-widget-js', 'var flot_library_chart = ' . json_encode($output));
		  	}

		    // Also a display widget
		    $sort_order = array('display');
		    $displays = $this->user->DisplayList($sort_order);

		    $rows = array();

	        if (is_array($displays) && count($displays) > 0) {
	            // Output a table showing the displays
	            foreach($displays as $row) {
					$row['licensed'] = ($row['licensed'] == 1) ? 'icon-ok' : 'icon-remove';
		            $row['loggedin'] = ($row['loggedin'] == 1) ? 'icon-ok' : 'icon-remove';
		            $row['mediainventorystatus'] = ($row['mediainventorystatus'] == 1) ? 'success' : (($row['mediainventorystatus'] == 2) ? 'error' : 'warning');
					
					// Assign this to the table row
					$rows[] = $row;
	            }
	        }

	        Theme::Set('display-widget-rows', $rows);
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    // Show the error in place of the bandwidth chart
		    Theme::Set('widget-error', 'Unable to get widget details');
		}

		// Do we have an embedded widget?
		Theme::Set('embedded-widget', html_entity_decode(Config::GetSetting('EMBEDDED_STATUS_WIDGET')));

		// Render the Theme and output
        Theme::Render('status_dashboard');
	}
}
?>

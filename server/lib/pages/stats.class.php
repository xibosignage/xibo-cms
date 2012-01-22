<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2012 Daniel Garner
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

class statsDAO
{
	private $db;
	private $user;
	
	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
	}
	
	function displayPage() 
	{
	
		include("template/pages/stats_view.php");
		
		return false;
	}
	
	function on_page_load() 
	{
		return '';
	}
	
	function echo_page_heading() 
	{
		echo 'Display Statistics';
		return true;
	}

    /**
     * Shows the stats form
     */
    public function StatsForm()
    {
        $db =& $this->db;
        $user =& $this->user;

        $fromdt = date("Y-m-d", time() - 86400);
        $todt = date("Y-m-d");
        $display_list = dropdownlist("SELECT 'All', 'All' UNION SELECT displayID, display FROM display WHERE licensed = 1 ORDER BY 2", "displayid");

        // List of Media this user has permission for
        $media = $this->user->MediaList();
        $media[] = array('mediaid' => 0, 'media' => 'All');
        $mediaList = Kit::SelectList('mediaid', $media, 'mediaid', 'media', 0);

        // We want to build a form which will sit on the page and allow a button press to generate a CSV file.
        $output = '';
        $output .= '<div id="StatsFilter">';
        $output .= ' <form onsubmit="return false">';
        $output .= ' <input type="hidden" name="p" value="stats">';
        $output .= ' <input type="hidden" name="q" value="StatsGrid">';
        $output .= ' <table>';
        $output .= '  <tr>';
        $output .= '   <td>From Date</td>';
        $output .= '   <td><input type="text" class="date-pick" name="fromdt" value="' . $fromdt . '"/></td>';
        $output .= '   <td>To Date</td>';
        $output .= '   <td><input type="text" class="date-pick" name="todt" value="' . $todt . '" /></td>';
        $output .= '  </tr>';
        $output .= '  <tr>';
        $output .= '   <td>' . __('Display') . '</td>';
        $output .= '   <td>' . $display_list . '</td>';
        $output .= '   <td>' . __('Media') . '</td>';
        $output .= '   <td>' . $mediaList . '</td>';
        $output .= '  </tr>';
        $output .= ' </table>';
        $output .= '</form>';
        $output .= '</div>';

        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
            <div class="XiboFilter">
                $output
            </div>
            <div class="XiboData"></div>
        </div>
HTML;
        echo $xiboGrid;
    }

    /**
     * Shows the stats grid
     */
    public function StatsGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $fromDt = Kit::GetParam('fromdt', _POST, _STRING);
        $toDt = Kit::GetParam('todt', _POST, _STRING);
        $displayId = Kit::GetParam('displayid', _POST, _INT);
        $mediaId = Kit::GetParam('mediaid', _POST, _INT);

        $output = '';

        // Output CSV button
        $output .= '<p>' . __('Export raw data to CSV') . '</p>';
        $output .= '<form action="index.php" method="post">';
        $output .= ' <input type="hidden" name="p" value="stats" />';
        $output .= ' <input type="hidden" name="q" value="OutputCSV" />';
        $output .= ' <input type="hidden" name="displayid" value="' . $displayId . '" />';
        $output .= ' <input type="hidden" name="fromdt" value="' . $fromDt . '" />';
        $output .= ' <input type="hidden" name="todt" value="' . $toDt . '" />';
        $output .= ' <input type="submit" value="Export" />';
        $output .= '</form>';

        // 3 grids showing different stats.

        // Layouts Ran
        $SQL =  'SELECT display.Display, layout.Layout, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= ' WHERE 1 = 1 ';
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= 'GROUP BY display.Display, layout.Layout ';
        $SQL .= 'ORDER BY display.Display, layout.Layout';

        $output .= '<p>' . __('Layouts ran') . '</p>';
        $output .= '<table>';
        $output .= '<thead>';
        $output .= '<th>' . __('Display') . '</th>';
        $output .= '<th>' . __('Layout') . '</th>';
        $output .= '<th>' . __('Number of Plays') . '</th>';
        $output .= '<th>' . __('Total Duration (s)') . '</th>';
        $output .= '<th>' . __('Total Duration') . '</th>';
        $output .= '<th>' . __('First Shown') . '</th>';
        $output .= '<th>' . __('Last Shown') . '</th>';
        $output .= '</thead>';
        $output .= '<tbody>';

        if (!$results = $this->db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get Layouts Ran'), E_USER_ERROR);
        }

        while ($row = $db->get_assoc_row($results))
        {
            $output .= '<tr>';
            $output .= '<td>' . Kit::ValidateParam($row['Display'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['Layout'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['NumberPlays'], _INT) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['Duration'], _INT) . '</td>';
            $output .= '<td>' . sec2hms(Kit::ValidateParam($row['Duration'], _INT)) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['MinStart'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['MaxEnd'], _STRING) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';

        // Media Ran
        $SQL =  'SELECT display.Display, media.Name, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  INNER JOIN  media ON media.MediaID = stat.MediaID ';
        $SQL .= ' WHERE 1 = 1 ';
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);

        if ($mediaId != 0)
            $SQL .= sprintf("  AND media.MediaID = %d ", $mediaId);

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= 'GROUP BY display.Display, media.Name ';
        $SQL .= 'ORDER BY display.Display, media.Name';

        $output .= '<p>' . __('Library Media ran') . '</p>';
        $output .= '<table>';
        $output .= '<thead>';
        $output .= '<th>' . __('Display') . '</th>';
        $output .= '<th>' . __('Media') . '</th>';
        $output .= '<th>' . __('Number of Plays') . '</th>';
        $output .= '<th>' . __('Total Duration (s)') . '</th>';
        $output .= '<th>' . __('Total Duration') . '</th>';
        $output .= '<th>' . __('First Shown') . '</th>';
        $output .= '<th>' . __('Last Shown') . '</th>';
        $output .= '</thead>';
        $output .= '<tbody>';

        if (!$results = $this->db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get Library Media Ran'), E_USER_ERROR);
        }

        while ($row = $db->get_assoc_row($results))
        {
            $output .= '<tr>';
            $output .= '<td>' . Kit::ValidateParam($row['Display'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['Name'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['NumberPlays'], _INT) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['Duration'], _INT) . '</td>';
            $output .= '<td>' . sec2hms(Kit::ValidateParam($row['Duration'], _INT)) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['MinStart'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['MaxEnd'], _STRING) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';

        // Media on Layouts Ran
        $SQL =  "SELECT display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage') AS Name, COUNT(StatID) AS NumberPlays, SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration, MIN(start) AS MinStart, MAX(end) AS MaxEnd ";
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  LEFT OUTER JOIN media ON media.MediaID = stat.MediaID ';
        $SQL .= ' WHERE 1 = 1 ';
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromDt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $toDt);

        if ($mediaId != 0)
            $SQL .= sprintf("  AND media.MediaID = %d ", $mediaId);

        if ($displayId != 0)
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayId);

        $SQL .= "GROUP BY display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage') ";
        $SQL .= "ORDER BY display.Display, layout.Layout, IFNULL(media.Name, 'Text/Rss/Webpage')";

        $output .= '<p>' . __('Media on Layouts ran') . '</p>';
        $output .= '<table>';
        $output .= '<thead>';
        $output .= '<th>' . __('Display') . '</th>';
        $output .= '<th>' . __('Layout') . '</th>';
        $output .= '<th>' . __('Media') . '</th>';
        $output .= '<th>' . __('Number of Plays') . '</th>';
        $output .= '<th>' . __('Total Duration (s)') . '</th>';
        $output .= '<th>' . __('Total Duration') . '</th>';
        $output .= '<th>' . __('First Shown') . '</th>';
        $output .= '<th>' . __('Last Shown') . '</th>';
        $output .= '</thead>';
        $output .= '<tbody>';

        if (!$results = $this->db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get Library Media Ran'), E_USER_ERROR);
        }

        while ($row = $db->get_assoc_row($results))
        {
            $output .= '<tr>';
            $output .= '<td>' . Kit::ValidateParam($row['Display'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['Layout'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['Name'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['NumberPlays'], _INT) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['Duration'], _INT) . '</td>';
            $output .= '<td>' . sec2hms(Kit::ValidateParam($row['Duration'], _INT)) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['MinStart'], _STRING) . '</td>';
            $output .= '<td>' . Kit::ValidateParam($row['MaxEnd'], _STRING) . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';

        $response->SetGridResponse($output);
        $response->Respond();
    }
	
	/**
	 * Outputs a CSV of stats
	 * @return 
	 */
	public function OutputCSV()
	{
		$db 		=& $this->db;
		$output		= '';
		
		// We are expecting some parameters
		$fromdt		= Kit::GetParam('fromdt', _POST, _STRING);
		$todt		= Kit::GetParam('todt', _POST, _STRING);
		$displayID	= Kit::GetParam('displayid', _POST, _INT);
		
		// We want to output a load of stuff to the browser as a text file.
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="stats.csv"');
		header("Content-Transfer-Encoding: binary");
		header('Accept-Ranges: bytes');
		
		$SQL =  'SELECT stat.*, display.Display, layout.Layout, media.Name AS MediaName ';
		$SQL .= '  FROM stat ';
		$SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
		$SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
		$SQL .= '  LEFT OUTER JOIN media ON media.mediaID = stat.mediaID ';
		$SQL .= ' WHERE 1=1 ';
		$SQL .= sprintf("  AND stat.end > '%s' ", $fromdt);
		$SQL .= sprintf("  AND stat.start <= '%s' ", $todt);

		if ($displayID != 0)
		{
			$SQL .= sprintf("  AND stat.displayID = %d ", $displayID);
		}

                $SQL .= " ORDER BY stat.start ";
		
		Debug::LogEntry($db, 'audit', $SQL, 'Stats', 'OutputCSV');
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error('Failed to query for Stats.', E_USER_ERROR);
		}
		
		// Header row
		$output		.= "Type, FromDT, ToDT, Layout, Display, Media, Tag\n";
		
		while($row = $db->get_assoc_row($result))
		{
			// Read the columns
			$type		= Kit::ValidateParam($row['Type'], _STRING);
			$fromdt		= Kit::ValidateParam($row['start'], _STRING);
			$todt		= Kit::ValidateParam($row['end'], _STRING);
			$layout		= Kit::ValidateParam($row['Layout'], _STRING);
			$display	= Kit::ValidateParam($row['Display'], _STRING);
			$media		= Kit::ValidateParam($row['MediaName'], _STRING);
			$tag		= Kit::ValidateParam($row['Tag'], _STRING);
			
			$output		.= "$type, $fromdt, $todt, $layout, $display, $media, $tag\n";
		}
		
		//Debug::LogEntry($db, 'audit', 'Output: ' . $output, 'Stats', 'OutputCSV');
		
		echo $output;
		exit;
	}
}
?>
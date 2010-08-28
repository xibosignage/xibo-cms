<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
 
class reportDAO 
{
	private $db;
	private $user;
	private $has_permissions = true;
	private $sub_page;

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
		
		$this->sub_page = Kit::GetParam('sp', _GET, _WORD, 'sessions');
	}
	
	function on_page_load() 
	{
		$onload = '';		
	}
	
	function echo_page_heading() 
	{
		echo "Reports";
		return true;
	}

	function displayPage() 
	{
		$db =& $this->db;
		
		if (!$this->has_permissions) 
		{
			displayMessage(MSG_MODE_MANUAL, __('You do not have permissions to access this page'));
			return false;
		}
		
		switch ($this->sub_page) 
		{	
			case "sessions":
				include("template/pages/sessions_view.php");
				break;
			
			case "log":
				include("template/pages/log_view.php");
				break;
				
			case "whatson":
				include("template/pages/whatson_view.php");
				break;
				
			default:
				break;
		}

	}
	
	function SessionFilter()
	{
		$db 	=& $this->db;
		
		$type 	= Kit::GetParam('type', _SESSION, _STRING, 'active');
		$fdate 	= date("d/m/Y", time() + 86400);
		
		$type_list = listcontent("all|All,active|Active,guest|Guest,expired|Expired", "type", $type);
		
		$msgType		= __('Type');
		$msgFromDT		= __('From Date');
		
		$output = <<<END
		<div class="FilterDiv" id="SessionFilter">
			<form>
			<table class="filterform" id="report_filterform">
				<input type="hidden" name="p" value="report">
				<input type="hidden" name="q" value="SessionGrid">
				<tr>
					<td>$msgType</td>
					<td>
						<select name="type">
							<option value="all" >All</option>
							<option value="active" selected>Active</option>
							<option value="guest">Guest</option>
							<option value="expired">Expired</option>
						</select>
					</td>
					<td>$msgFromDT</td>
					<td><input id="fromdt" class="date-pick" name="fromdt" value="$fdate" /></td>
				</tr>
			</table>
			</form>
		</div>

END;
		$id = uniqid();
		
		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$output
			</div>
			<div class="XiboData">
			
			</div>
		</div>
HTML;
		echo $xiboGrid;
	}
	
	function SessionGrid() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$type 		= Kit::GetParam('type', _POST, _WORD); //all, active, guest, expired
		$fromdt 	= Kit::GetParam('fromdt', _POST, _STRING);
		
		//get the dates and times
		$start_date = explode('/', $fromdt); //	dd/mm/yyyy
		$starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . " 00:00:00");

		
		$_SESSION['type'] = $type;
		
		$SQL  = "SELECT session.userID, 
					user.UserName, 
					CASE WHEN IsExpired = 1 
							THEN '<img src=\"img/disact.gif\">'
				        	ELSE '<img src=\"img/act.gif\">'
				    END AS IsExpired,
					LastPage, 
					session.LastAccessed, 
					RemoteAddr, 
					UserAgent ";
		$SQL .= "FROM `session` LEFT OUTER JOIN user ON user.userID = session.userID ";
		$SQL .= "WHERE 1=1";
		$SQL .= sprintf(" AND session_expiration < '%s' ", $starttime_timestamp);
		if ($type == "active")
		{
			$SQL .= " AND IsExpired = 0 ";
		}
		if ($type == "expired")
		{
			$SQL .= " AND IsExpired = 1 ";
		}
		if ($type == "guest")
		{
			$SQL .= " AND session.userID IS NULL ";
		}
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if(!$results = $db->query($SQL))  
		{
			trigger_error($db->error());
			trigger_error(__("Can not query for current sessions"), E_USER_ERROR);
		}	
		
		// Translation Messages
		$msgLastAccessed		= __('Last Accessed');
		$msgActive				= __('Active');
		$msgUser				= __('User Name');
		$msgLastPage			= __('Last Page');
		$msgIP					= __('IP Address');
		$msgBrowser				= __('Browser');
		$msgAction				= __('Action');
			
		$output = <<<END
		<div class="info_table">
			<table style="width:100%">
				<thead>
					<tr>
						<th>$msgLastAccessed</th>
						<th>$msgActive</th>
						<th>$msgUser</th>
						<th>$msgLastPage</th>
						<th>$msgIP</th>
						<th>$msgBrowser</th>
						<th>$msgAction</th>
					</tr>	
				</thead>
				<tbody>
END;
		
		while ($row = $db->get_row($results))
		{
			$userID 		= Kit::ValidateParam($row[0], _INT);
			$userName 		= Kit::ValidateParam($row[1], _STRING);
			$isExpired 		= Kit::ValidateParam($row[2], _HTMLSTRING);
			$lastPage 		= Kit::ValidateParam($row[3], _STRING);
			$lastAccessed 	= Kit::ValidateParam($row[4], _STRING);
			$ip 			= Kit::ValidateParam($row[5], _STRING);
			$browser 		= Kit::ValidateParam($row[6], _STRING);
			
			$output .= <<<END
			<tr>
			<td>$lastAccessed</td>
			<td>$isExpired</td>
			<td>$userName</td>
			<td>$lastPage</td>
			<td>$ip</td>
			<td>$browser</td>
			<td>
				<button class="XiboFormButton" href="index.php?p=report&q=ConfirmLogout&userid=$userID"><span>Logout</span></a>
			</td>
			</tr>
END;
		}
		
		$output .= "</tbody></table></div>";
		
		$response->SetGridResponse($output);
		$response->Respond();
	}
	
	function ConfirmLogout()
	{
		$db 	=& $this->db;
		
		//ajax request handler
		$arh 	= new ResponseManager();
		
		$userID = Kit::GetParam('userid', _GET, _INT);
		
		$msgLogout	= __('Are you sure you want to logout this user?');
		$msgYes		= __('Yes');
		$msgNo		= __('No');
		
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=report&q=LogoutUser">
			<input type="hidden" name="userid" value="userid" />
			<p>$msgLogout</p>
			<input type="submit" value="$msgYes">
			<input type="submit" value="$msgNo" onclick="$('#div_dialog').dialog('close');return false; ">
		</form>
END;

		$arh->SetFormRequestResponse($form, 'Logout User', '450px', '300px');
		$arh->Respond();
	}
	
	/**
	 * Logs out a user
	 * @return 
	 */
	function LogoutUser()
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh 	= new ResponseManager();
		$userID = Kit::GetParam('userid', _POST, _INT);
		
		$SQL = sprintf("UPDATE session SET IsExpired = 1 WHERE userID = %d", $userID);
		
		if (!$db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__("Unable to log out this user"), E_USER_ERROR);
		}
		
		$arh->SetFormSubmitResponse(__('User Logged Out.'));
		$arh->Respond();
	}
	
	/**
	 * The Log table
	 * @return 
	 */
	function LogFilter() 
	{
		$db 			=& $this->db;
		
		$fdate 			= date("d/m/Y", time());
		
		$page_list 		= dropdownlist("SELECT 'All', 'All' UNION SELECT DISTINCT page, page FROM log ORDER BY 2","page",'All');
		$function_list 	= dropdownlist("SELECT 'All', 'All' UNION SELECT DISTINCT function, function FROM log ORDER BY 2","function",'All');
		$display_list 	= dropdownlist("SELECT 'All', 'All' UNION SELECT displayID, display FROM display WHERE licensed = 1 ORDER BY 2", "displayid");
				
		$xiboGrid = <<<END
		<div class="XiboGrid" id="LogGridId">
			<div class="XiboFilter">
				<div class="FilterDiv" id="LogFilter">
					<form>
						<table class="filterform" id="report_filterform">
							<input type="hidden" name="p" value="report">
							<input type="hidden" name="q" value="LogGrid">
							<tr>
								<td>Type</td>
								<td>
									<select name="type">
										<option value="all" selected>All</option>
										<option value="audit">Audit</option>
										<option value="error">Error</option>
									</select>
								</td>
								<td>From DT</td>
								<td><input id="fromdt" class="date-pick" name="fromdt" value="$fdate" /></td>
								
							</tr>
							<tr>
								<td>Page</td>
								<td>$page_list</td>
								<td>Seconds back</td>
								<td><input id="seconds" name="seconds" value="60" /></td>
							</tr>
							<tr>
								<td>Function</td>
								<td>$function_list</td>
							</tr>
							<tr>
								<td>Display</td>
								<td>$display_list</td>
							</tr>
						</table>
					</form>
				</div>
			</div>
			<div class="XiboData">
			
			</div>
		</div>

END;
		echo $xiboGrid;
	}
	
	function LogGrid() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$type 		= Kit::GetParam('type', _REQUEST, _STRING, 'all');
		$function 	= Kit::GetParam('function', _REQUEST, _STRING, 'All');
		$page 		= Kit::GetParam('page', _REQUEST, _STRING, 'All');
		$fromdt 	= Kit::GetParam('fromdt', _REQUEST, _STRING);
		$displayid	= Kit::GetParam('displayid', _REQUEST, _STRING, 'All');
		
		// The number of seconds to go back
		$seconds 	= Kit::GetParam('seconds', _POST, _INT);
		
		//get the dates and times
		$start_date = explode("/",$fromdt); //		dd/mm/yyyy
		
		$starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . ' ' . date("H", time()) . ":" . date("i", time()) . ':59');

		$todt = date("Y-m-d H:i:s", $starttime_timestamp);
		$fromdt = date("Y-m-d H:i:s", $starttime_timestamp - $seconds);
		
		$SQL  = "";
		$SQL .= "SELECT logdate, page, function, message FROM log ";
		$SQL .= " WHERE 1=1 ";
		if ($type != "all") 
		{
			$SQL .= sprintf("AND type = '%s' ", $type);
		}
		$SQL .= sprintf("AND logdate > '%s' ", $fromdt);
		$SQL .= sprintf("AND logdate <= '%s' ", $todt);
		if($page != "All") 
		{
			$SQL .= sprintf("AND page = '%s' ", $page);
		}
		if($function != "All") 
		{
			$SQL .= sprintf("AND function = '%s' ", $function);
		}
		if($displayid != "All") 
		{
			$SQL .= sprintf("AND displayID = '%s' ", $displayid);
		}
		$SQL .= "ORDER BY logid DESC ";

		if(!$results = $db->query($SQL))  
		{
			trigger_error($db->error());
			trigger_error(__("Can not query the log"), E_USER_ERROR);
		}
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		$logDate_t 		= __('Log Date');
		$msgPage		= __('Page');
		$msgFunction	= __('Function');
		$msgMessage		= __('Message');
		
		$output = <<<END
		<div class="info_table">
			<table style="width:100%">
				<thead>
					<tr>
						<th>$logDate_t</th>
						<th>$msgPage</th>
						<th>$msgFunction</th>
						<th>$msgMessage</th>
					</tr>	
				</thead>
				<tbody>
END;
		
		while ($row = $db->get_row($results)) 
		{
			$logdate 	= Kit::ValidateParam($row[0], _STRING);
			$page 		= Kit::ValidateParam($row[1], _STRING);
			$function 	= Kit::ValidateParam($row[2], _STRING);
			$message 	= nl2br(htmlspecialchars($row[3]));
			
			$output .= <<<END
			<tr>
				<td>$logdate</td>
				<td>$page</td>
				<td>$function</td>
				<td>$message</td>
			</tr>	
END;
		}
		
		if ($db->num_rows($results) == 0) 
		{
			$output .= '<tr><td></td><td></td><td></td><td>None</td></tr>';
		}
		
		$output .=  '</tbody></table></div>';
		
		$response->SetGridResponse($output);
		$response->sortable = false;
		$response->Respond();
	}
	
	function display_log_settings() 
	{
		$db =& $this->db;
		
		/*Audit and error settings */
		$SQL = "";
		$SQL.= "SELECT settingid, setting, value, helptext, options FROM setting WHERE type = 'dropdown' AND cat = 'error' ";

		if(!$results = $db->query($SQL)) trigger_error("Can not get settings:".$db->error(), E_USER_ERROR);

		echo "<div class='log_settings'>";
		
		while($row = $db->get_row($results)) 
		{
			$settingid 	= Kit::ValidateParam($row[0], _INT);
			$setting 	= Kit::ValidateParam($row[1], _STRING);
			$value 		= Kit::ValidateParam($row[2], _STRING);
			$helptext	= Kit::ValidateParam($row[3], _STRING);
			$options	= Kit::ValidateParam($row[4], _STRING);

			$select = '';
			
			$options = explode("|", $options);
			
			foreach ($options as $option) 
			{
				if($option == $value) 
				{
					$select.="<option value='$option' selected>$option</option>";
				}
				else 
				{
					$select.="<option value='$option'>$option</option>";
				}
			}
			
			$output = <<<END
			<div class="log_setting">
				<form method="post" action="index.php?p=admin&q=modify&id=$settingid">
					<input type="hidden" name="refer" value="index.php?p=report&sp=log">
					<h5>$setting</h5>
					<select name="value">$select</select>
					<input type="submit" value="Change">
				</form>
			</div>
END;
			echo $output;
		}
		echo "</div>";
	
		return true;
	}
		
	function delete_log() 
	{
		$db =& $this->db;
		
		$db->query("DELETE FROM log");
		
		setMessage("Log Truncated");
		
		return "index.php?p=report&sp=log";
	}

	function stats_info() 
	{
		$db =& $this->db;
		global $user;
		
		$SQL = "";
		$SQL .= "SELECT  DATE_FORMAT(stat.stat_date, '%D %M %Y') AS day_group, ";
		$SQL .= "		        MAX(stat.stat_date) AS day_order, ";
		$SQL .= "		        media.medianame, ";
		$SQL .= "		        media.userid, ";
		$SQL .= "		        stat.event_creatorid, ";
		$SQL .= "		        stat.displayid, ";
		$SQL .= "		        count(media.mediaid)        as times_played, ";
		$SQL .= "		        sum(stat.duration)          as duration, ";
		$SQL .= "		        round(avg(stat.duration),1) as avg_duration ";
		$SQL .= "		FROM    stat ";
		$SQL .= "		INNER JOIN media ";
		$SQL .= "		ON      stat.mediaid = media.mediaid ";
		$SQL .= "		GROUP BY DATE_FORMAT(stat.stat_date, '%D %M %Y'), ";
		$SQL .= "								stat.mediaid, stat.displayid ";
		$SQL .= "		ORDER BY day_order DESC, ";
		$SQL .= "				 media.medianame, ";
		$SQL .= "				 media.userid, ";
		$SQL .= "				 stat.event_creatorid ";
		$SQL .= " LIMIT 0,20 ";
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error("Error in the stats retrival", E_USER_ERROR);
		}
	
		$header = <<<END
	<table>
		<tr>
			<th>Day Group</th>
			<th>Display</th>
			<th>Media</th>
			<th>Owner</th>
			<th>Scheduler</th>
			<th>Times Played</th>
			<th>Duration</th>
			<th>Average</th>
		</tr>
END;
		echo $header;

		while ($row = $db->get_row($results)) 
		{
			$day_group 	= Kit::ValidateParam($row[0], _WORD);
			$media 		= Kit::ValidateParam($row[2], _STRING);
			$owner 		= $user->getNameFromID(Kit::ValidateParam($row[3], _INT));
			$scheduler 	= $user->getNameFromID(Kit::ValidateParam($row[4], _INT));
			$displayid 	= Kit::ValidateParam($row[5], _INT);
			$count_played = Kit::ValidateParam($row[6], _INT);
			$duration 	= sec2hms(Kit::ValidateParam($row[7], _INT));
			$avg_duration = sec2hms(Kit::ValidateParam($row[8], _INT));
			
			$tr = <<<END
		<tr>
			<td>$day_group</td>
			<td>$displayid</td>
			<td>$media</td>
			<td>$owner</td>
			<td>$scheduler</td>
			<td>$count_played</td>
			<td>$duration</td>
			<td>$avg_duration</td>
		</tr>
END;
			echo $tr;
		}
		
		$end = <<<END
	</table>
END;
		echo $end;
		
		return true;
	}
}
?>
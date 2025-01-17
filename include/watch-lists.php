<?php
	#
	# $Id: watch-lists.php,v 1.4 2012-09-18 20:51:39 dan Exp $
	#
	# Copyright (c) 1998-2007 DVL Software Limited
	#

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/watch_lists.php');

	function freshports_WatchListDDLB($dbh, $UserID, $selected = '', $size = 0, $multiple = 0, $show_active = 1, $element_id = 0) {
		# return the HTML which forms a dropdown list box.
		# optionally, select the item identified by $selected.
	
		$Debug = 0;
	
		$HTML = '<select name="wlid';
		if ($multiple) {
			$HTML .= '[]';
		}
		
		$HTML .= '" title="Select a watch list"';
	
		if ($size) {
			$HTML .= ' size="' . $size . '"';
		}
		if ($multiple) {
			$HTML .= ' multiple';
		}
		$HTML .= ">\n";
	
		$WatchLists = new WatchLists($dbh);
		$NumRows = $WatchLists->Fetch($UserID, $element_id);
	
		if ($Debug) {
			echo "$NumRows rows found!<br>";
			echo "selected = '$selected'<br>";
		}
	
		if ($NumRows) {
			for ($i = 0; $i < $NumRows; $i++) {
				$WatchList = $WatchLists->FetchNth($i);
				$HTML .= '<option value="' . htmlspecialchars(pg_escape_string($WatchList->id)) . '"';
				if ($selected == '') {
					if ($element_id && $WatchList->watch_list_count > 0) {
						$HTML .= ' selected';
					}
				} else {
					if ($WatchList->id == $selected) {
						$HTML .= ' selected';
					}
				}
				$HTML .= '>' . htmlspecialchars(pg_escape_string($WatchList->name));
				if ($show_active && $WatchList->in_service == 't') {
					$HTML .= '*';
				}
				if ($element_id && $WatchList->watch_list_count) {
					$HTML .= " +";
				}
				$HTML .= "</option>\n";
			}
		}
	
		$HTML .= '</select>';

		if (!$NumRows) {
			$HTML .= '<br><h2> You have no watch lists.  You must <a href="watch-list-maintenance.php">create one</a>.</h2>';
		}
	
		return $HTML;
	}

function freshports_WatchListSelectGoButton($name = 'watch_list_select') {
	return '	<input type="image" name="' . $name . '" value="GO" src="/images/go.gif" alt="Go" align="middle" title="Display the selected watch list">';
}

function freshports_WatchListDDLBForm($db, $UserID, $WatchListID, $Extra = '') {
	
	$HTML = '
<form action="' . $_SERVER["PHP_SELF"] . '" method="POST" NAME=f>
<table border="0">
<tr>
<td valign="top" nowrap align="right">
<small>
';

	$HTML .= freshports_WatchListDDLB($db, $UserID, $WatchListID);

$HTML .=  '
</small>
</td>
<td valign="top" nowrap align="left">
'  . freshports_WatchListSelectGoButton() . $Extra .
'</td></tr></table></form>
';

	return $HTML;

}

function freshports_WatchListCountDefault($db, $UserID) {
	$sql = "select WatchListCountDefault(" . pg_escape_string($UserID) . ") as count";

#	echo $sql;

	$result = pg_exec($db, $sql);
	if (!$result) {
		echo "error " . pg_errormessage();
		exit;
	}

	$myrow = pg_fetch_array($result, 0);

#	echo $myrow["count"];

	return $myrow["count"];
}

# XXX I have no idea why UPDATING is in watch-lists.php
function freshports_UpdatingOutput($NumRowsUpdating, $PortsUpdating, $port) {
	$HTML = '';
	
	if ($NumRowsUpdating > 0) {
		$HTML .= '<TABLE BORDER="1" width="100%" CELLSPACING="0" CELLPADDING="5">' . "\n";
		$HTML .= "<TR>\n";
		$HTML .= freshports_PageBannerText('<a id="updating">Notes from UPDATING</a>', 1);
		$HTML .= "<tr><td><dl>\n";
		$HTML .= "<dt>These upgrade notes are taken from <a href=\"/UPDATING\">/usr/ports/UPDATING</a></dt>";
		$HTML .= "<dd><ul>\n";

		$Hiding = false;
		for ($i = 0; $i < $NumRowsUpdating; $i++) {
			$PortsUpdating->FetchNth($i);
			if ($i == 1) {
				$Hiding = true;
				# end the old list, start a new list
				$HTML .= "</ul></dd>\n";
				$HTML .= '<dt><a href="#" id="UPDATING-Extra-show" class="showLink" onclick="showHide(\'UPDATING-Extra\');return false;">Expand this list (' . ($NumRowsUpdating - 1) . ' items)</a></dt>';
				$HTML .= '<dd id="UPDATING-Extra" class="more UPDATING">';

				# start the new list of all hidden items
				$HTML .= "<ul>\n";
			}

			$HTML .= '<li>' . freshports_PortsUpdating($port, $PortsUpdating) . "</li>\n";
		}
		if ($Hiding) {
			$HTML .= '<li class="nostyle"><a href="#" id="UPDATING-Extra-hide2" class="hideLink" onclick="showHide(\'UPDATING-Extra\');return false;">Collapse this list.</a></li>';
		}

		$HTML .= "</ul></dd>";
		$HTML .= "</dl></td></tr>\n";
		$HTML .= "</table>\n";
	}

	return $HTML;
}

function freshports_WatchListVerifyToken($db, $token) {
	$id = '';

	$sql = "SELECT id from watch_list where token = '" . pg_escape_string($token) . "'";

#	echo $sql;

	$result = pg_exec($db, $sql);
	if ($result) {
		$numrows = pg_numrows($result);
		switch ($numrows) {
			case 0:
				// nothing found, do nothing
				break;

			case 1:
				$row = pg_fetch_array($result, 0);
				$id = $row['id'];
				break;

			default:
				syslog(LOG_ERR, __FILE__ . '::' . __LINE__ . ' more than one watch list with this token ' . $token);
				header('HTTP/1.1 500 OK******');
				exit;
		}
	}

	return $id;
}


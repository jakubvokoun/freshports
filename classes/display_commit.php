<?php
	#
	# $Id: display_commit.php,v 1.14 2012-12-21 18:20:53 dan Exp $
	#
	# Copyright (c) 2003-2007 DVL Software Limited
	#

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/constants.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/htmlify.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/commit_ports.php');

// base class for displaying commits
class DisplayCommit {

	var $Debug = 0;
	var $dbh;

	var $result;
	var $MaxNumberOfPorts;

	var $BranchName;
	var $WatchListAsk    = '';	// either default or ask.  the watch list to which add/remove works.
	var $UserID          = 0;
	var $DaysMarkedAsNew = 10;
	var $LocalResult;
	var $HTML;
	
	var $FlaggedCommits;
	
	var $ShowAllPorts     = FALSE;	# by default we show only the first few ports.
	var $ShowEntireCommit = 0;		# by default we show only the first few lines of the commit message.
	
	var $ShowLinkToSanityTestFailure = FALSE;

	# the message_id for all the emails which originated from subversion contain freebsd.org
	# For git commits, we put the full has into message_id . Commits from git do not contain that value.
	# This is used to decide if commits are from svn or from git.
	# Commits imported before we started saving message_id are in the null.freshports.org
	# so we just look for .org
	const MESSAGE_ID_DOMAIN = '.org';

	function __construct($dbh, $result, $BranchName = BRANCH_HEAD) {
		$this->dbh        = $dbh;
		$this->result     = $result;
		$this->BranchName = $BranchName;
	}

	function IsGitCommit($message_id) {
		return strpos($message_id,self::MESSAGE_ID_DOMAIN) == false;
	}

	function SetDaysMarkedAsNew($DaysMarkedAsNew) {
		$this->DaysMarkedAsNew = $DaysMarkedAsNew;
	}

	function SetUserID($UserID) {
		$this->UserID = $UserID;
	}

	function SetWatchListAsk($WatchListAsk) {
		$this->WatchListAsk = $WatchListAsk;
	}
	
	function SetShowAllPorts($ShowAllPorts) {
		$this->ShowAllPorts = $ShowAllPorts;
	}

	function SetShowEntireCommit($ShowEntireCommit) {
		$this->ShowEntireCommit = $ShowEntireCommit;
	}

	function CreateHTML() {
		GLOBAL	$freshports_CommitMsgMaxNumOfLinesToShow;
		
		$Debug = $this->Debug;

		$URLBranchSuffix = BranchSuffix($this->BranchName);

		if (!$this->result) {
			syslog(LOG_ERR, __FILE__ . '::' . __LINE__ . ': no result set supplied');
			die("read from database failed");
			exit;
		}

		$NumRows = pg_numrows($this->result);
		if ($this->Debug) echo __FILE__ . ':' . __LINE__ . " Number of rows = $NumRows<br>\n";
		if (!$NumRows) { 
			$this->HTML = "<TR><TD>\n<P>Sorry, nothing found in the database....</P>\n</td></tr>\n";
			return $this->HTML;
		}
		
		# if we have a UserID, but no flagged commits, grab them
		#
		if ($this->UserID && !IsSet($this->FlaggedCommits)) {
			require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/commit_flag.php');

			$FlaggedCommits = new CommitFlag($this->dbh);
			$NumFlaggedCommits = $FlaggedCommits->Fetch($this->UserID);
			for ($i = 0; $i < $NumFlaggedCommits; $i++) {
				$FlaggedCommits->FetchNth($i);
				$this->FlaggedCommits[$FlaggedCommits->commit_log_id] = $FlaggedCommits->commit_log_id;
				if ($this->Debug) echo "fetching record # $i -> $FlaggedCommits->commit_log_id<br>";
			}
		}
	
		$GlobalHideLastChange = "N";

		$this->HTML = "";

		# leave it all empty as a comparison point
		$PreviousCommit = new Commit_Ports($this->dbh);

		$NumberOfPortsInThisCommit = 0;
		$MaxNumberPortsToShow      = 10;
		$TooManyPorts = false;	# we might not show all of a commit, just for the really big ones.
		for ($i = 0; $i < $NumRows; $i++) {
			$myrow = pg_fetch_array($this->result, $i);
			if ($Debug) echo 'processing row ' . $i . ' ' . $myrow['commit_log_id'] . ' ' . $myrow['message_id'] . "<br>\n";
			unset($mycommit);
			$mycommit = new Commit_Ports($this->dbh);
			$mycommit->PopulateValues($myrow);


			// OK, while we have the log change log, let's put the port details here.

			if ($mycommit->commit_log_id != $PreviousCommit->commit_log_id) {
				if ($Debug) echo "This commit_log_id is different\n";
				if (($NumberOfPortsInThisCommit > $MaxNumberPortsToShow) && !$this->ShowAllPorts) {
					$this->HTML .= '<BR>' . freshports_MorePortsToShow($PreviousCommit->message_id, $NumberOfPortsInThisCommit, $MaxNumberPortsToShow);
				}
				$TooManyPorts = false;
				if ($i > 0) {
					$this->HTML .= "\n<BLOCKQUOTE>";
					$this->HTML .= freshports_CommitDescriptionPrint(
			                    $PreviousCommit->commit_description,
			                    $PreviousCommit->encoding_losses,
			                    $Lines,
			                    freshports_MoreCommitMsgToShow($PreviousCommit->message_id, $Lines));
					# close off the previous commit first
					$this->HTML .= "\n</BLOCKQUOTE>\n</TD></TR>\n\n\n";
				}
				# count the number of ports in this commit.
				# first time into the loop, this will be executed.
				$NumberOfPortsInThisCommit = 0;
				$MaxNumberPortsToShow      = 10;

				if ($mycommit->commit_date != $PreviousCommit->commit_date) {
					$this->HTML .= '<TR><TD COLSPAN="3" BGCOLOR="' . BACKGROUND_COLOUR . '" HEIGHT="0">' . "\n";
					$this->HTML .= '   <FONT COLOR="#FFFFFF"><BIG>' . FormatTime($mycommit->commit_date, 0, "D, j M Y") . '</BIG></FONT>' . "\n";
					$this->HTML .= '</TD></TR>' . "\n\n";
				}

				GLOBAL $freshports_mail_archive;

				$this->HTML .= "<TR><TD>\n";

				$this->HTML .= '<SMALL>';
				$this->HTML .= '[ ' . $mycommit->commit_time . ' ' . freshports_CommitterEmailLink($mycommit->committer);
				if (!empty($mycommit->committer_name) && ($mycommit->committer_name != $mycommit->committer)) {
					$this->HTML .= ' (' . $mycommit->committer_name . ')';
				}
				$this->HTML .= ' ]';
				$this->HTML .= '</SMALL>';
				$this->HTML .= '&nbsp;';
				if ($this->IsGitCommit($mycommit->message_id)) {
					# do nothing
				} else {
					$this->HTML .= freshports_Email_Link($mycommit->message_id);
				}

				$this->HTML .= '&nbsp;';
				if ($this->UserID) {
					if (IsSet($this->FlaggedCommits[$mycommit->commit_log_id])) {
						$this->HTML .= freshports_Commit_Flagged_Link($mycommit->message_id);
					} else {
						$this->HTML .= freshports_Commit_Flagged_Not_Link($mycommit->message_id);
					}
				}

				if ($mycommit->EncodingLosses()) {
						$this->HTML .= '&nbsp;' . freshports_Encoding_Errors();
				}

				if ($mycommit->stf_message != '' && $this->ShowLinkToSanityTestFailure) {
					$this->HTML .= '&nbsp;' . freshports_SanityTestFailure_Link($mycommit->message_id);
				}
				
#        			echo '<pre>' . print_r($mycommit, true) . '</pre>';

					
				if ($mycommit->svn_revision != '') {
					if ($this->IsGitCommit($mycommit->message_id)) {
						$this->HTML .= '&nbsp; ' .       freshports_git_commit_Link($mycommit->svn_revision,                               $mycommit->repo_hostname, $mycommit->path_to_repo);
						$this->HTML .= '&nbsp; ' .  freshports_git_commit_Link_Hash($mycommit->svn_revision, $mycommit->commit_hash_short, $mycommit->repo_hostname, $mycommit->path_to_repo). '&nbsp';
					} else {
						$this->HTML .= '&nbsp; ' . freshports_svnweb_ChangeSet_Link($mycommit->svn_revision, $mycommit->repo_hostname, $mycommit->path_to_repo);
					}
				}
				$this->HTML .= "<br>\n";

			}

			$NumberOfPortsInThisCommit++;
			if (($NumberOfPortsInThisCommit > $MaxNumberPortsToShow) && !$this->ShowAllPorts) {
				$TooManyPorts = true;
			}

			if (!$TooManyPorts) {
				if (IsSet($mycommit->category) && $mycommit->category != '') {
				if ($this->UserID) {
					if ($mycommit->watch) {
						$this->HTML .= ' '. freshports_Watch_Link_Remove($this->WatchListAsk, $mycommit->watch, $mycommit->element_id) . ' ';
					} else {
						$this->HTML .= ' '. freshports_Watch_Link_Add   ($this->WatchListAsk, $mycommit->watch, $mycommit->element_id) . ' ';
					}
				}

				$this->HTML .= '<BIG><B>';
				$this->HTML .= '<A HREF="/' . $mycommit->category . '/' . $mycommit->port . '/' . $URLBranchSuffix . '">';
				$this->HTML .= $mycommit->port;
				$this->HTML .= '</A>';

				$PackageVersion = freshports_PackageVersion($mycommit->version, $mycommit->revision, $mycommit->epoch);
				if (strlen($PackageVersion) > 0) {
					$this->HTML .= ' ' . $PackageVersion;
				}

				$this->HTML .= "</B></BIG>\n";

				$this->HTML .= '<A HREF="/' . $mycommit->category . '/'  . $URLBranchSuffix . '">';
				$this->HTML .= $mycommit->category. "</A>";
				$this->HTML .= '&nbsp;';

				// indicate if this port has been removed from cvs
				if ($mycommit->status == "D") {
					$this->HTML .= " " . freshports_Deleted_Icon_Link() . "\n";
				}

				// indicate if this port needs refreshing from CVS
				if ($mycommit->needs_refresh) {
					$this->HTML .= " " . freshports_Refresh_Icon_Link() . "\n";
				}
				if ($mycommit->date_added > Time() - 3600 * 24 * $this->DaysMarkedAsNew) {
					$MarkedAsNew = "Y";
					$this->HTML .= freshports_New_Icon() . "\n";
				}

				if ($mycommit->forbidden) {
					$this->HTML .= ' ' . freshports_Forbidden_Icon_Link() . "\n";
				}

				if ($mycommit->broken) {
					$this->HTML .= ' '. freshports_Broken_Icon_Link() . "\n";
				}

				if ($mycommit->deprecated) {
					$this->HTML .= ' '. freshports_Deprecated_Icon_Link() . "\n";
				}

				if ($mycommit->expiration_date) {
					if (date('Y-m-d') >= $mycommit->expiration_date) {
						$this->HTML .= freshports_Expired_Icon_Link($mycommit->expiration_date) . "\n";
					} else {
						$this->HTML .= freshports_Expiration_Icon_Link($mycommit->expiration_date) . "\n";
					}
				}

				if ($mycommit->ignore) {
					$this->HTML .= ' '. freshports_Ignore_Icon_Link() . "\n";
				}

				$this->HTML .= freshports_Commit_Link_Port($mycommit->message_id, $mycommit->category, $mycommit->port);
				$this->HTML .= "&nbsp;";

				if ($mycommit->vulnerable_current) {
					$this->HTML .= '&nbsp;' . freshports_VuXML_Icon() . '&nbsp;';
				} else {
					if ($mycommit->vulnerable_past) {
						$this->HTML .= '&nbsp;' . freshports_VuXML_Icon_Faded() . '&nbsp;';
					}
				}

				if ($mycommit->restricted) {
					$this->HTML .= freshports_Restricted_Icon_Link($mycommit->restricted) . '&nbsp;';
				}

				if ($mycommit->no_cdrom) {
					$this->HTML .= freshports_No_CDROM_Icon_Link($mycommit->no_cdrom) . '&nbsp;';
				}

				if ($mycommit->is_interactive) {
					$this->HTML .= freshports_Is_Interactive_Icon_Link($mycommit->is_interactive) . '&nbsp;';
				}

				$this->HTML.=  freshports_Fallout_Link($mycommit->category, $mycommit->port) . '&nbsp;';
			} else {
				# This is a non-port element... 
				$this->HTML .= $mycommit->revision . ' ';
				$this->HTML .= '<big><B>';
				$PathName = preg_replace('|^/?ports/|', '', $mycommit->element_pathname);
#				echo "'$PathName' " . "'" . $mycommit->repo_name . "'";
				switch($mycommit->repo_name)
				{
				    case 'ports':
				        $PathName = preg_replace('|^head/|', '', $PathName);
				        break;
				}
				if ($PathName != $mycommit->element_pathname) {
					$this->HTML .= '<a href="/' . str_replace('%2F', '/', urlencode($PathName)) . '">' . $PathName . '</a>';
					$this->HTML .= "</B></BIG>\n";
				} else {
					$this->HTML .= '<a href="' . FRESHPORTS_FREEBSD_CVS_URL . $PathName . '#rev' . $mycommit->revision . '">' . $PathName . '</a>';
					$this->HTML .= "</B></BIG>\n";
				}
			}
			$this->HTML .= htmlify(_forDisplay($mycommit->short_description)) . "\n";

			$this->HTML .= "<BR>\n";

			GLOBAL $freshports_CommitMsgMaxNumOfLinesToShow;			
			if ($this->ShowEntireCommit) {
				$Lines = 0;
			} else {
				$Lines = $freshports_CommitMsgMaxNumOfLinesToShow;
			}
			} # !$TooManyPorts
			

			$PreviousCommit = $mycommit;
		}
		
		if (($NumberOfPortsInThisCommit > $MaxNumberPortsToShow) && !$this->ShowAllPorts) {
			$this->HTML .= '<BR>' . freshports_MorePortsToShow($PreviousCommit->message_id, $NumberOfPortsInThisCommit, $MaxNumberPortsToShow);
		}
		$this->HTML .= "\n<BLOCKQUOTE>";
		$this->HTML .= freshports_CommitDescriptionPrint(
                    $PreviousCommit->commit_description,
                    $PreviousCommit->encoding_losses,
                    $Lines,
                    freshports_MoreCommitMsgToShow($PreviousCommit->message_id, $Lines));
		# close off the last commit
		$this->HTML .= "\n</BLOCKQUOTE>\n</TD></TR>\n\n\n";

		unset($mycommit);
		
		return $this->HTML;
	}

	function SetBranch($BranchName) {
		# usually, this is set during __construct
		$this->BranchName = $BranchName;
	}
	
}

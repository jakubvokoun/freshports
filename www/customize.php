<?php
	#
	# $Id: customize.php,v 1.3 2008-08-06 13:36:16 dan Exp $
	#
	# Copyright (c) 1998-2004 DVL Software Limited
	#

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/common.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/freshports.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/databaselogin.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/getvalues.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/htmlify.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/page_options.php'); # needed to validate page_size
	
	if (IN_MAINTENCE_MODE) {
                header('Location: /' . MAINTENANCE_PAGE, TRUE, 307);
	}

	GLOBAL $User;

	$errors          = 0;
	$AccountModified = 0;

if (IsSet($_REQUEST['submit'])) $submit = $_REQUEST['submit'];
$visitor = pg_escape_string($_COOKIE['visitor']);

// if we don't know who they are, we'll make sure they login first
if (!$visitor) {
	header('Location: /login.php');  /* Redirect browser to PHP web site */
	exit;  /* Make sure that code below does not get executed when we redirect. */
}

if (IsSet($submit)) {
	$Debug = 0;

	// process form

	$email				= pg_escape_string($_POST['email']);
	$Password1			= $_POST['Password1'];
	$Password2			= $_POST['Password2'];
	$numberofdays			= pg_escape_string($_POST['numberofdays']);
	$page_size			= pg_escape_string($_POST['page_size']);

	# this is a checkbox
	if (IsSet($_POST['set_focus_search'])) {
		$set_focus_search = 'true';
	} else {
		$set_focus_search = 'false';
	}

	if (!is_numeric($numberofdays) || $numberofdays < 0 || $numberofdays > 9) {
		$numberofdays = 9;
	}

	$PageOptions = new ItemsPerPage();
	if (!is_numeric($page_size) || !array_key_exists($page_size, $PageOptions->Choices)) {
		$page_size = DEFAULT_NUMBER_OF_COMMITS;
	}

	if ($Debug) {
		foreach ($HTTP_POST_VARS as $name => $value) {
			echo "$name = $value<br>\n";
		}
	}

	$OK = 1;

	$errors = '';

	if (!freshports_IsEmailValid($email)) {
		$errors .= 'That email address doesn\'t look right to me<BR>';
		$OK = 0;
	}

	if ($Password1 != $Password2) {
		$errors .= 'The password was not confirmed.  It must be entered twice.<BR>';
		$OK = 0;
	}

	if ($OK) {
		// get the existing email in case we need to reset the bounce count
		$sql = "select email from users where cookie = '$visitor'";
		$result = pg_exec($db, $sql);
		if ($result) {
			$myrow = pg_fetch_array ($result, 0);

			$sql = "
UPDATE users
   SET email            = '$email',
       number_of_days   = $numberofdays,
       page_size        = $page_size,
       set_focus_search = $set_focus_search";

			// if they are changing the email, reset the bouncecount.
			if ($myrow["email"] != $email) {
				$sql .= ", emailbouncecount = 0 ";
			}

			if ($Password1 != '') {
				$sql .= ", password_hash = crypt('" . 	pg_escape_string($Password1) . "', gen_salt('md5'))";
			}

			$sql .= " where cookie = '$visitor'";

			if ($Debug) {
#			phpinfo();
				echo '<pre>' . htmlentities($sql) . '</ore>';
			}

			$result = pg_exec($db, $sql);
			if ($result) {
				$AccountModified = 1;
			}
		}

		if ($AccountModified == 1) {
			if ($Debug) {
				echo "I would have taken you to '' now, but debugging is on<br>\n";
			} else {
				header("Location: /");
				exit;  /* Make sure that code below does not get executed when we redirect. */
			}
		} else {
			$errors .= 'Something went terribly wrong there.<br>';
			$errors .= $sql . "<br>\n";
			$errors .= pg_errormessage();
		}
	}
} else {

	$email            = $User->email;
	$numberofdays     = $User->number_of_days;
	$page_size        = $User->page_size;
	$set_focus_search = $User->set_focus_search;
}

#	echo '<br>the page size is ' . $page_size . ' : ' . $email;

	$Title = 'Customize User Account';
	freshports_Start($Title,
						$Title,
						'FreeBSD, index, applications, ports');
?>

<TABLE WIDTH="<? echo $TableWidth; ?>" BORDER="0" ALIGN="center">
<TR><TD VALIGN="top" width="100%">
<TABLE width="100%" border="0">
  <TR>
    <TD height="20"><?php


if ($errors) {
echo '<TABLE CELLPADDING="1" BORDER="0" BGCOLOR="' . BACKGROUND_COLOUR . '" width="100%">
<TR>
<TD>
<TABLE width="100%" BORDER="0" CELLPADDING="1">
<TR BGCOLOR="' . BACKGROUND_COLOUR . '"><TD><b><font color="#ffffff" size=+0>Access Code Failed!</font></b></TD>
</TR>
<TR BGCOLOR="#ffffff">
<TD>
  <TABLE width="100%" CELLPADDING="3" BORDER="0">
  <TR VALIGN=top>
   <TD><img src="/images/warning.gif"></TD>
   <TD width="100%">
  <p>Some errors have occurred which must be corrected before your login can be created.</p>';

echo $errors;

echo '<p>If you need help, please email postmaster@. </p>
 </TD>
 </TR>
 </TABLE>
</TD>
</TR>
</TABLE>
</TD>
</TR>
</TABLE>
<br>';
}
if ($AccountModified) {
   echo "Your account details were successfully updated.";
} else {

echo '<TABLE CELLPADDING="1" BORDER="0" BGCOLOR="' . BACKGROUND_COLOUR . '" WIDTH="100%">
<TR>
<TD VALIGN="top">
<TABLE WIDTH="100%" BORDER="0" CELLPADDING="1">
<TR>
<TD BGCOLOR="' . BACKGROUND_COLOUR . '" HEIGHT="29" COLSPAN="1"><FONT COLOR="#FFFFFF"><BIG><BIG>Customize</BIG></BIG></FONT></TD>
</TR>
<TR BGCOLOR="#ffffff">
<TD>';

echo '<p>If you wish to change your password, supply your new password twice.  Otherwise, leave it blank.</p><br>';
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/getvalues.php');

$Customize=1;
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/new-user.php');

echo "</TD>
</TR>
</TABLE>
</TD>
</TR>
</TABLE>";
}

?>

<p>

<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/spam-filter-information.php'); ?>

</TD>
</TABLE>
</TD>

  <TD VALIGN="top" WIDTH="*" ALIGN="center">
	<?
	echo freshports_SideBar();
	?>
  </td>

</TR>
</TABLE>

<?
echo freshports_ShowFooter();
?>

</body>
</html>

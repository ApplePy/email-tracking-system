<?php
//THIS FILE MUST BE SECURED! IT CONTAINS PLAINTEXT PASSWORDS!
    
    
function initializeDatabase ($username)
{
	$database = NULL;
	
	if ($username == "redirector")
	{
		$database = new PDO("mysql:host=localhost;dbname=mailing", "redirector", "bdaT5gTgZgWrrhntY8skVK88");
	}
	else if ($username == "imapper")
	{
		$database = new PDO("mysql:host=localhost;dbname=mailing", "imapper", "sFHvmgpfXL5FDds3ZmmsnQdM");
	}
	
	if (is_object($database))
	{
		$database->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //WARNING! This can cause SQL credentials to be dumped to the screen if exception is not caught!
		return $database;
	}
	else
	{
		return FALSE;
	}
}
    
function populateMailInfo(&$IMAPInfo, &$SMTPInfo)
{
    $IMAPInfo->replyAddress = "darrylmurray@sympatico.ca";
    $IMAPInfo->domain = "sympatico.ca";
    $IMAPInfo->server = "imap-mail.outlook.com";
    $IMAPInfo->port = 993;
    $IMAPInfo->serverOptions = "/imap/ssl";
    $IMAPInfo->waitingMailbox = "Newsletter Outbox";
    $IMAPInfo->sentMailbox = "Newsletter Sent";
    $IMAPInfo->rejectedMailbox = $IMAPInfo->sentMailbox."/Rejected";
    $IMAPInfo->username = "darrylmurray@sympatico.ca";
    $IMAPInfo->password = "hwrcijnwqpzevthh";
    $IMAPInfo->newsIDLocationTemplate = "Newsletter \$newsID -- ";
    
    //Requires PHP 5.3.0 formatting, spacing, and padding around this command MUST be kept, or the nowdoc will get invalidated
    $IMAPInfo->regex = <<<'eod'
/^(Newsletter )("|“|”|'|‘|’)?((\d+)|((\D\w)+))("|“|”|'|‘|’)?( -- )/
eod;
 
    //The old regex: "/^(Newsletter )(\\d+|\"\\D\\S(.)+\"|\"\\d+\"){1}( -- )/"
    $IMAPInfo->variableDelimiter = "%%";
    
    $SMTPInfo->auth = true;
    $SMTPInfo->host = "smtp.live.com";
    $SMTPInfo->port = 587;
    $SMTPInfo->username = $IMAPInfo->username;
    $SMTPInfo->password = $IMAPInfo->password;
}

$redirectPath = "http://10.211.55.3/redirect.php";
    
?>
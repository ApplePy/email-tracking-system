<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Untitled Document</title>
</head>

<body>
<?php
	//NOTES: 	-need the Mail package from PEAR   
	//			-Haven't tested if it can handle really complex MIME emails (basic attachments have been successfully tested)
	//			-Causes the email to disappear from web interfaces when the email is moved from Newsletter Outbox to Newsletter Sent (still visible in email programs)


    require_once 'Mail.php';
    require_once 'database_connect.php';
    
    
    
    
    
    function NewGuid() {
        $s = strtoupper( md5( uniqid( rand(), true ) ) );
        $guidText =
        substr( $s, 0,  8).'-'.
        substr( $s, 8,  4).'-'.
        substr( $s, 12, 4).'-'.
        substr( $s, 16, 4).'-'.
        substr( $s, 20);
        return $guidText;
    }
    //From http://phpgoogle.blogspot.ca/2007/08/four-ways-to-generate-unique-id-by-php.html
    
    
    function generateMessageID( $domain ) {
        return "<".sha1( microtime() )."@".$domain.">";
    }
    
    
    function addIDToDB( &$database, $trackingID, $idtype, $emailAddress, $newsletterID, $IssueID, $url = "") {
        
        //Decide whether an ID is click or open type
        $clickOrOpen = NULL;
        switch( strtoupper( $idtype ) ) {
                
            case "CLICK":
                $clickOrOpen = "click";
                break;
                
            case "OPEN":
                $clickOrOpen = "open";
                break;
                
            default:
                throw new Exception( "Improper idtype!" );
                break;
        }
        
        
        //Submit information to database
        try {
            $query = $database->prepare("INSERT INTO TrackingIDs SET ID = :trackingID, ClickOrOpenType = :clickType, EmailAddress = :email, NewsletterID = :nID, NewsletterIssue = :nIssue, LinkedURL = :URL");
            
            $query->bindParam( ":trackingID",   $trackingID );
            $query->bindParam( ":clickType",    $clickOrOpen );
            $query->bindParam( ":email",        $emailAddress );
            $query->bindParam( ":nID",          $newsletterID );
            $query->bindParam( ":nIssue",       $IssueID );
            $query->bindParam( ":URL",          $url );
            
            $query->execute();
        }
        catch (PDOException $e) {
            throw new Exception( "Database submission error! Error: ".$e->getMessage() );
        }
    }
    
    
    function retrieveTextReplacement(&$database, $variableToReplace, $emailAddress, $ID) {
        
        //Ensure that the variables passed aren't arrays
        if ( is_array($variableToReplace) || is_array($emailAddress) || is_array($ID) || is_array($database) )
            throw new Exception ( "retrieveTextReplacement does not support arrays!" );
        
        
        //Retrieve the information about the requested text replacement variable from the database
        try {
            $query = $database->prepare( "SELECT * FROM TextReplacementVariables WHERE VariableName = :theVariable" );
            $query->bindParam( ":theVariable", $variableToReplace );
            $query->execute();
            
            $trInfo = $query->fetch( PDO::FETCH_ASSOC ); //There will be no duplicate variables because VariableName is a Primary Key
            
            
            if ( $trInfo === FALSE )
                throw new Exception( "Variable does not exist!" );
        }
        catch (PDOException $e) {
            throw new Exception( "PDO Text Replacement Search error! Error: ".$e->getMessage() );
        }
        
        
        
        //Find out what term we need to search by
        $searchTerm = NULL;
        switch( $trInfo["WhereTerm"] ) {
                
            case "EmailAddress":
                $searchTerm = $emailAddress;
                break;
                
            case "NewsletterID":
                $searchTerm = $ID;
                break;
                
            default:
                throw new Exception( "Database corruption!" );
                break;
        }
        
        
        
        //Remove backticks to help prevent SQL injection attacks against MySQL
        $trInfo = str_ireplace( "`", "", $trInfo );
        
        //Retrieve the value for the requested text replacement variable
        try {
            $replacementTextQuery = $database->prepare("SELECT `".$trInfo["ColumnName"].
                                                       "` FROM `".$trInfo["TableLocation"].
                                                       "` WHERE `".$trInfo["WhereTerm"]."` = :searchTerm");
            $replacementTextQuery->bindParam( ":searchTerm", $searchTerm );
            $replacementTextQuery->execute();
            
            $replacementTextArray = $replacementTextQuery->fetchAll( PDO::FETCH_ASSOC );
        }
        catch (PDOException $e) {
            throw new Exception( "PDO Text Replacement retrieval error! Error: ".$e->getMessage() );
        }
        
        
        
        if ( sizeof( $replacementTextArray ) > 1 ) {
            
            if ( strtoupper( $trInfo["TableLocation"] ) == "ISSUES" ) {
                $lastEntry = sizeof( $replacementTextArray ) - 1;
                return ( $replacementTextArray[ $lastEntry ][ $trInfo["ColumnName"] ] ); //Take the last entry in the list
            }
            else
                throw new Exception("Database text replacement request returned too many results!");
        }
        
        else if ( sizeof( $replacementTextArray ) == 0 ) {
            throw new Exception("The requested variable does not have a replacement value.", 1000);
        }
        
        else {
            return ( $replacementTextArray[0][ $trInfo["ColumnName"] ] );
        }
    }
    
    
    
    
    
    
    
    
    
    //IMAP and SMTP Login Information
    
    class imap {
        public $replyAddress;
        public $username;
        public $password;
        public $domain;
        public $server;
        public $port;
        public $serverOptions;
        public $waitingMailbox;
        public $sentMailbox;
        public $rejectedMailbox;
        public $regex;
        public $newsIDLocationTemplate;
        public $variableDelimiter;
        
        public function simpleServer()
        {
            return ("{".$this->server.":".$this->port.$this->serverOptions."}");
        }
    }
    
    class smtp {
        public $auth;
        public $username;
        public $password;
        public $host;
        public $port;
    }
    
    
    //Stores mail settings
    $IMAPInfo = new imap;
    $SMTPInfo = new smtp;
    
    populateMailInfo($IMAPInfo, $SMTPInfo);
    
    ////////////////////////////////////
    
    
    
    
    //Start connections to database, IMAP, and SMTP
    
    //Initialize database
    try {
        $database = initializeDatabase( "imapper" );
        
        if ( is_object( $database ) === FALSE )
            die ( "Database did not initialize!" );
    }
    catch ( PDOException $e ) {
        http_response_code ( 500 );
        echo ( "PDO connection error: ".$e->getMessage() );
        die();
    }
    
    
    //Open SMTP connection for use later
    $smtp = Mail::factory( 'smtp',
                          array ( 'host' => $SMTPInfo->host,
                                 'port' => $SMTPInfo->port,
                                 'auth' => $SMTPInfo->auth,
                                 'username' => $SMTPInfo->username,
                                 'password' => $SMTPInfo->password,
                                 'persist' => true ) ) or die ( "SMTP initialization failed!" );
    
    
    //Open mailbox to the specified folder
    $mbox = imap_open ( $IMAPInfo->simpleServer().$IMAPInfo->waitingMailbox,
                       $IMAPInfo->username,
                       $IMAPInfo->password ) or die ( "Can't connect: ".imap_last_error() );
    
    ////////////////////////////////////////////////
    
    
    
    //Retrieve properties of mailbox
    $mboxProps = imap_check ( $mbox ) or die ( "IMAP Error: ".imap_last_error() );
	
    
    if ( $mboxProps->Nmsgs <= 0 ) {
    	
    	echo "No messages to process.";
    	imap_close( $mbox );
    	die();
    	
    }
	else {
		//Get list of emails from mailbox
		$mailOverview = imap_fetch_overview ( $mbox, "1:{$mboxProps->Nmsgs}", 0 ) or die ( "IMAP Error: ".imap_last_error() );
        
        //Iterate through emails, finding emails that match the search pattern
        foreach ( $mailOverview as $newsletterDetails ) {
            
        	//Match not found
        	if ( preg_match( $IMAPInfo->regex, $newsletterDetails->subject ) != 1 ) {
        		//TO-DO: Add code to automatically make subfolder if it doesn't exist
        	
        		//Move the email out to the Rejected folder
        		imap_mail_move ( $mbox,
        		$newsletterDetails->msgno,
        		$IMAPInfo->rejectedMailbox ) or die ( "ERROR: ".imap_last_error() );
        	}
            //Match found
            else if ( preg_match( $IMAPInfo->regex, $newsletterDetails->subject ) == 1 ) {
                
                //Extract friendly name or ID from email subject here
                $startOfNewsID = strpos( $IMAPInfo->newsIDLocationTemplate, "\$newsID" ) or die ( "\$NewsID not found in template!" );
                
                $stringAfterNewsID = substr( $IMAPInfo->newsIDLocationTemplate, $startOfNewsID + strlen( "\$newsID" ) );
                
                $endOfNewsID = strpos( $newsletterDetails->subject,
                                      $stringAfterNewsID,
                                      $startOfNewsID );
                
                $newsIdentifier = substr( $newsletterDetails->subject,
                                         $startOfNewsID,
                                         $endOfNewsID-$startOfNewsID ) or die ( "Could not extract NewsID from email subject line!" );
                
                
                
                $isIDExist = NULL; //Holds array of query
                $newsIdentifier = str_ireplace( array("\"","“","”","\'","‘","’"), "", $newsIdentifier ); //Remove quotations from search string
                
                //test if friendly name or id (using is_numeric)
                if ( is_numeric($newsIdentifier) ) {
                    try {
                        //search via id
                        $isIDExistQuery = $database->prepare( "SELECT NewsletterID FROM Newsletters WHERE NewsletterID = :newsID" );
                        $isIDExistQuery->bindParam( ":newsID", $newsIdentifier );
                        $isIDExistQuery->execute();
                        $isIDExist = $isIDExistQuery->fetchAll();
                    }
                    catch ( PDOException $e ) {
                    	die( "PDO Error at newsIdentifier searching. Error: ".$e->getMessage() );
                    }
                }
                else {
                    try {
                        //search via friendlyName
                        $isIDExistQuery = $database->prepare( "SELECT NewsletterID FROM Newsletters WHERE FriendlyName = :newsID" );
                        $isIDExistQuery->bindParam(":newsID", $newsIdentifier);
                        $isIDExistQuery->execute();
                        $isIDExist = $isIDExistQuery->fetchAll();
                    }
                    catch ( PDOException $e ) {
                    	die( "PDO Error at newsIdentifier searching. Error: ".$e->getMessage() );
                    }
                }
                
                //There was no newsletter that matches the identifier
                if ( sizeof ( $isIDExist ) == 0 ) {
                    imap_mail_move ($mbox, $newsletterDetails->msgno, $IMAPInfo->rejectedMailbox) or die ("IMAP Error: ".imap_last_error()); //Move the email out to the Rejected folder
                    continue;
                }
                
                
                
                
                $newsletterID = $isIDExist[0]["NewsletterID"]; //For use later on, don't forget about it
                
                
                
                
                //Get the subscribers to the newsletter
                try {
                    $subscribersQuery = $database->prepare( "SELECT EmailAddress FROM Subscriptions WHERE NewsletterID = :newsID" );
                    $subscribersQuery->bindParam( ":newsID", $newsletterID );
                    $subscribersQuery->execute();
                    $subscribers = $subscribersQuery->fetchAll( PDO::FETCH_ASSOC );
                }
                catch ( PDOException $e ) {
                	die( "PDO email address selection error. Error: ".$e->getMessage() );
                }
                
                //If nobody is subscribed to the newsletter
                if ( sizeof( $subscribers ) == 0 ) {
                	echo "Nobody is subscribed to newsletter {$newsletterID}!";
                    imap_mail_move( $mbox,
                                   $newsletterDetails->msgno,
                                   $IMAPInfo->rejectedMailbox ) or die( "ERROR: ".imap_last_error() ); //Move the email out to the Rejected folder
                    continue;
                }
                
                
                
                //Create new newletter issue ID
                try {
                	$database->beginTransaction();
                	
                    $newIssueIDStatement = $database->prepare( "INSERT INTO Issues SET NewsletterID = :newsID" );
                    $newIssueIDStatement->bindParam( ":newsID", $newsletterID );
                    $newIssueIDStatement->execute();
                    
                    $getCreatedIssueID = $database->prepare( "SELECT IssueID FROM Issues WHERE NewsletterID = :newsID ORDER BY NewsletterIssue DESC LIMIT 1" );
                    $getCreatedIssueID->bindParam( ":newsID", $newsletterID );
                    $getCreatedIssueID->execute();
                    $issueIDArray = $getCreatedIssueID->fetch();
                    
                    //Leave the transaction open - just in case the program crashes before it hits the emailing portion
                }
                catch ( PDOException $e ) {
                	die( "PDO Newsletter issue creation error. Error: ".$e->getMessage() );
                }
                
                $issueID = $issueIDArray[0]; //Holds the created issueID
                
                
                
                
                
                
                
                //Generate new Subject line
                $match = array();
                if ( preg_match( $IMAPInfo->regex, $newsletterDetails->subject, $match ) == FALSE )
                    die( "Subject line check failed." );
                				
                $offset = strlen( $match[0] ); //Find the end of the newsletter identifier in order to strip it out
                $genericSubject = substr( $newsletterDetails->subject, $offset ) or exit ( "Creation of generic subject line failed!" );
                
                
                
                
                
                
                
                
                
                //Generate generic header, making sure to grab the MIME-Version and Content-Type headers
                $header = imap_headerinfo( $mbox, $newsletterDetails->msgno ) or die ( "ERROR: ".imap_last_error() );
                $rawheader = explode( "\n", imap_fetchheader( $mbox, $newsletterDetails->msgno ) );           
                
                $extraHeaders = array();
                foreach ( $rawheader as $headerpart )
                {
                	/*if ( $headerpart[0] == "X" || $headerpart[0] == "x" ) {
                		$endOfHeaderKey = strpos( $headerpart, ": " );
                		if ($endOfHeaderKey != FALSE)
                			$extraHeaders[ substr( $headerpart, 0, $endOfHeaderKey ) ] = substr( $headerpart, $endOfHeaderKey + 2 );
                	}*/ //A block to add arbitrary other headers - scrapped due to the highly variant nature of email headers
                		
                    if ( strpos($headerpart, "Content-Type:") === FALSE)
                        NULL;
                    else
                        $extraHeaders["Content-Type"] = substr($headerpart, strlen("Content-Type: "));
                    
                    if (strpos($headerpart, "boundary=") === FALSE)
                        NULL;
                    else
                        $extraHeaders["boundary"] = $headerpart;
                }
                
                //Concatenate the Content-Type and boundary
                if (array_key_exists("boundary", $extraHeaders))
                {
                    $extraHeaders["Content-Type"] = $extraHeaders["Content-Type"].$extraHeaders["boundary"];
                    unset ($extraHeaders["boundary"]);
                }            
                
                //Assemble the header
                $genericHeader = array_merge ( array( 'return_path'=>$IMAPInfo->replyAddress,
                									  'date'=>$header->date,
                									  'fromaddress'=>$header->fromaddress,
                									  'from'=>$header->fromaddress,
                									  'reply_to'=>$IMAPInfo->replyAddress,
                									  'reply_toaddress'=>$IMAPInfo->replyAddress, 
                									  'subject'=>$genericSubject,
                									  'toaddress'=>'', 
                									  'message_id'=>generateMessageID( $IMAPInfo->domain ) ), $extraHeaders);

                

                
                
                
                
                //WARNING: This body retrieval function does not grab all the mime bits, it just outputs the final formatted email! ...Although, it doesn't appear to hurt anything?
                //Retrieve body
                $genericBody = imap_body( $mbox, 
                						  $newsletterDetails->msgno, 
                						  FT_PEEK ) or die ( "ERROR: ".imap_last_error() );
                
                $database->commit(); //commits the Issue ID to database
                
                $successfulEmailSent = FALSE; //holds if an email was sent successfuly - signals the deletion of the created Issue ID if no emails managed to successfully send
                //Start making in-memory copies, changing To: headers, modifying <a href> tags, adding open-tracking images, etc.
                foreach ($subscribers as $addressLine) {
                    
                	$database->beginTransaction();
                	
                	$emailAddress = $addressLine["EmailAddress"];
                    
                    //Copy the generic headers for customization
                    $customSubject = $genericSubject;
                    $customHeader = $genericHeader;
                    $customBody = $genericBody;
                    
                    //Add new email address to header
                    $customHeader['toaddress'] =  $emailAddress;
                    
                    

                    
                    
                    
                    
                    //Replace all variables in the subject/body
                    	//Method: Generate an array of all the possible variables and another array of all possible replacements, then run the two arrays against the text in question
                    try {
                        $searchQuery = $database->query( "SELECT VariableName FROM TextReplacementVariables" );
                        $searchArray = $searchQuery->fetchAll( PDO::FETCH_NUM ); //Must retrieve in numbers because str_ireplace will iterate through the resulting array indiscriminently
                    }
                    catch ( PDOException $e ) {
                        die( "Variable table retrieval failure. Error: ".$e->getMessage() );
                    }
                    
                    $replaceArray = array();
                    $variablesArray = array();
                    for ( $iterator = 0; $iterator < sizeof( $searchArray ); $iterator++ ) {
                        $variablesArray[] = $IMAPInfo->variableDelimiter.
                        					$searchArray[$iterator][0].
                        					$IMAPInfo->variableDelimiter; //Append the variable delimeters to the search string
                        try {
                            $replaceArray[] = retrieveTextReplacement($database, $searchArray[$iterator][0], $emailAddress, $newsletterID); //NOTE: what happens if he wants the issue number?
                        }
                        catch ( Exception $e ) {
                            die( "Generate text replacement error: ".$e->getMessage() );
                        }
                    }
                                     
                    //Start text replacements
                    $customArray = str_ireplace ( $variablesArray, $replaceArray, array( $customBody, $customSubject ) ) or die ( "str_ireplace text replacement failure." );
                    $customBody = $customArray[0]; //completely assuming on these numbers that they correlate correctly
                    $customSubject = $customArray[1];
                    $customHeader["subject"] = $customSubject;
                    
                    
                    
                    
                    
                    
                    
                    
                    //Attach open ID to email - start by finding a good position in which to put the 
                    $location = strpos( $customBody, "<body" ) + strpos( $customBody, ">", strpos( $customBody, "<body" ) ) - strpos( $customBody, "<body" ) + 1;
                    if ( $location === FALSE ) {
                        $location = strpos( $customBody, "<div" ) + strpos( $customBody, ">", strpos( $customBody, "<div" ) ) - strpos( $customBody, "<div" ) + 1;
                        if ( $location === FALSE ) {
                            $location = strpos( $customBody, "<html" ) + strpos( $customBody, ">", strpos( $customBody, "<html" ) ) - strpos( $customBody, "<html" ) + 1;
                            if ( $location === FALSE ) {
                            	$location = 0; 
                            }
                        }
                    }
                    //NOTE: the redirect path is available from the database_connect.php file
                    $openID = NewGuid();
                    $trackingTag = "<div><img src=\"".$redirectPath."?id=".$openID."\" alt=\"\"></div>";
                    $customBody = substr_replace($customBody, $trackingTag, $location, 0) or die ("str_ireplace openID failure.");
                    
                    //Add open ID to DB
                    addIDToDB( $database, $openID, "open", $emailAddress, $newsletterID, $issueID );
                    
                    
                    
                    
                    
                    //start inserting click links
                    $location = 0;
                    while ( $location < strlen( $customBody ) ) {
                    	
                    	//hunt for a href tags
                        $locationTemporary = strpos( $customBody, "<a href=\"http", $location ); //http must stay in to avoid mailto links
                        if ( $locationTemporary === FALSE ) {
                            $locationTemporary = strpos( $customBody, "<a href=3D\"http", $location );
                            if ( $locationTemporary === FALSE )
                                break; //no more a href tags
                        }
                        
                        
                        
                        //start extracting address
                        $startingQuote = strpos( $customBody, "\"", $locationTemporary );
                        $endingQuote = strpos( $customBody, "\"", $startingQuote + 1 );
                        if ( $startingQuote === FALSE or $endingQuote === FALSE ) {
                            die ( "StartingQuote or EndingQuote error!" );
                        }
                        $originalURL = substr( $customBody, 
                        						$startingQuote + 1, 
                        						$endingQuote - $startingQuote - 1 ) or die( "originalURL substr error!" ); //Subtract 1 to take out the ending quote
                        
                        
                        
                        //Insert new address where the old address used to be, and update database
                        $clickID = NewGuid();
                        $newURL = $redirectPath."?id=".$clickID;
                        $customBody = substr_replace( $customBody, 
                        								$newURL, 
                        								$startingQuote + 1, 
                        								strlen( $originalURL ) ) or die("clickID substring replace error!");
                        addIDToDB( $database, $clickID, "click", $emailAddress, $newsletterID, $issueID, $originalURL );
                        
                        
                        
                        //update location with the new starting location to continue searching
                        $location = $locationTemporary + strlen("a href=3D\"http"); //Not quite precise, but good enough.
                    }
                    
                    
                    
                    
                    
                    //Send out the email
                    $mail = $smtp->send($customHeader['toaddress'], $customHeader, $customBody);
                    
                    if ( PEAR::isError( $mail ) ) {
                    	$database->rollback();
                        echo ("<p>" . $mail->getMessage() . "</p>" );
                    }
                    else {
                    	$database->commit();
                    	$successfulEmailSent = TRUE;
                        echo( "<p>Message successfully sent!</p>" );
                    }
                    
                }
                
                
                //If no messages successfully sent, remove the issueID created earlier 
                if ( !$successfulEmailSent ) {
                	try {
                		$database->prepare( "DELETE FROM Issues WHERE IssueID = :IssueID" );
                		$database->bindParam( ":IssueID", $issueID );
                		$database->execute();
                	}
                	catch ( PDOException $e ) {
                		die( "PDO IssueID removal failure. Error: ".$e->getMessage() );
                	}
                }
                
                //All emails sent, move completed newsletter to sent folder
                imap_mail_move ( $mbox, 
                					$newsletterDetails->msgno, 
                					$IMAPInfo->sentMailbox ) or die ( "ERROR: ".imap_last_error() );
            }
        }
    }
    
    echo "Operation completed.";
    imap_close( $mbox );
    die();
?>
</body>
</html>

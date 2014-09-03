<?php

require_once "database_connect.php";


function redirect( $url, $statusCode = 303 ) {
   header( 'Location: '.$url, true, $statusCode );
   
   echo "
		  <html>
		  <head>
		  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		  <title>Oops!</title>
		  </head>
		  
		  <body>
		  Our apologies - it appears that your computer has not properly redirected you to the appropriate page. Please click the following link to continue to your intended destination: <a href=".$url.">".$url."</a>
		  
		  </body>
		  </html>"; //This holds a redirect page in-case the header function fails to be read by the browser!
   die(); //An absolute must! DO NOT REMOVE!
}


$ID = $_GET['id'];

//SANITIZE THOSE VARIABLES!

if ( preg_match( "/[A-Za-z0-9]{8}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{12}/", $ID ) == 1 ) {
	try {
	//Open database connection
		$database = initializeDatabase( "redirector" );
	}
	catch ( PDOException $e ) {
		http_response_code (500);
		echo "
		 			<html>
					<head>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		 			<title>Oops!</title>
		 			</head>
			
		 			<body>
		 			Our apologies - it appears that the server has encountered an error.
		  			</body>
		  			</html>";
			die();
	}
    
	try {
		$query = $database->prepare("SELECT Activated, ClickOrOpenType, LinkedURL FROM TrackingIDs WHERE ID=:theID");
		$query->bindParam(':theID', $ID); //Helps prevent SQL injection attacks
		$query->execute();
		$queryResult = $query->fetchAll();
	
		$query->closeCursor();
		$query = NULL; //close query
	}
	catch ( PDOException $e ) {
		http_response_code ( 500 );
		error_log( "PDO query error: ".addslashes( $e->getMessage() ) );
		echo "
		 			<html>
					<head>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		 			<title>Oops!</title>
		 			</head>
			
		 			<body>
		 			Our apologies - it appears that the server has encountered an error.
		  			</body>
		  			</html>";
			die();
	}
    
	//An error occured with fetch
	if ( $queryResult === FALSE ) {
		http_response_code ( 500 ); //these error codes don't get seen as a proper "error page" because Apache is intercepting them and being generally bad.
		echo "
		 			<html>
					<head>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		 			<title>Oops!</title>
		 			</head>
		
		 			<body>
		 			Our apologies - it appears that the server has encountered an error.
		  			</body>
		  			</html>";
		die();
	}
	//That ID does not exist
	else if ( sizeof ( $queryResult ) == 0 ) {
		http_response_code ( 404 );
		echo "
		 			<html>
					<head>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		 			<title>Oops!</title>
		 			</head>
		
		 			<body>
		 			Our apologies - it appears that your destination cannot be found.
		  			</body>
		  			</html>"; //This holds a message just in case an ID has gone missing
		die();
	}
	else {
		try {
        	$query = $database->prepare( "UPDATE TrackingIDs SET Activated=1 WHERE ID=:theID" ); //Marks the link as activated
        	$query->bindParam( ':theID', $ID ); //Helps prevent SQL injection attacks
        	$query->execute();
        
			$query->closeCursor();
			$query = NULL; //close query
		}
		catch (PDOException $e) {
			error_log( "SQL Update Error: ".addslashes( $e->getMessage() ) );
			echo "
		 			<html>
					<head>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		 			<title>Oops!</title>
		 			</head>
			
		 			<body>
		 			Our apologies - it appears that the server has encountered an error.
		  			</body>
		  			</html>";
			die();
		}
        
		if ( $queryResult[0]['ClickOrOpenType'] == 'click' ) {		
			$database = NULL; //Close database connection
			
			if ( sizeof( $queryResult[0]['LinkedURL'] ) == 0 ) {
				echo "
		 			<html>
					<head>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		 			<title>Oops!</title>
		 			</head>
		  
		 			<body>
		 			Our apologies - it appears that your destination cannot be found. 
		  			</body>
		  			</html>"; //This holds a message just in case a 'click' link is missing its url to link to
   				die();
			}
			else {
				redirect ( $queryResult[0]['LinkedURL'] ); //Send user to requested url
			}
		}
		//Generate image
		else {
            //http://php.net/manual/en/function.imagefill.php
            //http://php.net/manual/en/function.imagejpeg.php
            
            // Create a blank image and add some text
            $im = imagecreatetruecolor(1, 1);
            $color = imagecolorallocatealpha($im, 255, 255, 255, 127);
            imagefill($im, 0, 0, $color);
            imagesavealpha($im, TRUE);
            
            // Set the content type header - in this case image/png
            header('Content-Type: image/png');
            
            // Output the image
            if (!imagepng($im)) {
				http_response_code (500); //The image generation failed, mark the failure but DON'T send anything back
				error_log("Image generation failed!");
			}
            
            // Free up memory
            imagedestroy($im);
		}
	}
	
	$database = NULL; //Close database connection
}
else {
	http_response_code (404);
	echo "
		 			<html>
					<head>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		 			<title>Oops!</title>
		 			</head>
	
		 			<body>
		 			Our apologies - it appears that your destination cannot be found.
		  			</body>
		  			</html>";
	die();
}
?>
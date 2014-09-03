<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Untitled Document</title>
</head>

<body>
<?php
class dbCreds {
	public $host;
	public $dbName;
	public $dbType;
	public $username;
	public $password;
	
	public function __construct($dbTypec, $hostc, $dbNamec, $usernamec, $passwordc) {
		$this->host = $hostc;
		$this->dbName = $dbNamec;
		$this->dbType = $dbTypec;
		$this->username = $usernamec;
		$this->password = $passwordc;
	}	
}

$mysqlCreds = new dbCreds ("mysql", "localhost", "mailing", "root", "root");

//Initialize database
try
{
	//Research PHP library Files to encapsulate the password sections
	global $database;
	//$database = new PDO("mysql:host=localhost;dbname=mailing", "root", "root");
	$database = new PDO($mysqlCreds->dbType.":host=".$mysqlCreds->host.";dbname=".$mysqlCreds->dbName, $mysqlCreds->username, $mysqlCreds->password);
	$database->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
}
catch (PDOException $e)
{
	print "Failure!";
	//Empty - ignore error
	//BAD IDEA
}

$replacementQuery = $database->query("SELECT VariableName FROM TextReplacementVariables");
$replacementArray = $replacementQuery->fetchAll(PDO::FETCH_NUM);

for ($iterator = 0; $iterator < sizeof ($replacementArray); $iterator++)
{
	$replacementArray[$iterator] = $replacementArray[$iterator][0];
}

print (nl2br (print_r ($replacementArray, true)));


?>
</body>
</html>
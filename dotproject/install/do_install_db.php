<?php // $Id$

!is_file( "../includes/config.php" )
	or die("Security Check: dotProject seems to be already configured. Communication broken for Security Reasons!");

#
# function to return a default value if a variable is not set
#

function defVal($var, $def) {
	return isset($var) ? $var : $def;
}

/**
* Utility function to return a value from a named array or a specified default
*/
function dPgetParam( &$arr, $name, $def=null ) {
	return isset( $arr[$name] ) ? $arr[$name] : $def;
}

/*
* Utility function to split given SQL-Code
* @param $sql string SQL-Code
*/
function splitSql($sql) {
	$sql = trim($sql);
	$sql = ereg_replace("\n#[^\n]*\n", "\n", $sql);

	$buffer = array();
	$ret = array();
	$in_string = false;

	for($i=0; $i<strlen($sql)-1; $i++) {
		if($sql[$i] == ";" && !$in_string) {
			$ret[] = substr($sql, 0, $i);
			$sql = substr($sql, $i + 1);
			$i = 0;
		}

		if($in_string && ($sql[$i] == $in_string) && $buffer[1] != "\\") {
			$in_string = false;
		}
		elseif(!$in_string && ($sql[$i] == '"' || $sql[$i] == "'") && (!isset($buffer[0]) || $buffer[0] != "\\")) {
			$in_string = $sql[$i];
		}
		if(isset($buffer[1])) {
			$buffer[0] = $buffer[1];
		}
		$buffer[1] = $sql[$i];
	}

	if(!empty($sql)) {
		$ret[] = $sql;
	}
	return($ret);
}
######################################################################################################################

$baseDir = str_replace( DIRECTORY_SEPARATOR.'install', '', dirname(__FILE__));
$baseUrl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
$baseUrl .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : getenv('HTTP_HOST');
$baseUrl .= isset($_SERVER['SCRIPT_NAME']) ? str_replace( DIRECTORY_SEPARATOR.'install', '', dirname($_SERVER['SCRIPT_NAME'])) : str_replace( DIRECTORY_SEPARATOR.'install', '', dirname(getenv('SCRIPT_NAME')));

$dbMsg = "";
$cFileMsg = "Not Created";
$dbErr = false;
$cFileErr = false;

$dbtype = trim( dPgetParam( $_POST, 'dbtype', 'mysql' ) );
$dbhost = trim( dPgetParam( $_POST, 'dbhost', '' ) );
$dbname = trim( dPgetParam( $_POST, 'dbname', '' ) );
$dbuser = trim( dPgetParam( $_POST, 'dbuser', '' ) );
$dbpass = trim( dPgetParam( $_POST, 'dbpass', '' ) );
$dbdrop = trim( dPgetParam( $_POST, 'dbdrop', false ) );
//$dbpersist = trim( dPgetParam( $_POST, 'dbpersist', false ) );

//convert values of null to 'false'
$dbdrop = defVal($dbdrop, "false");
$dbpersist = defVal($dbpersist, "false");

require_once( "$baseDir/lib/adodb/adodb.inc.php" );

$db = NewADOConnection($dbtype);

if(!empty($db)) {
		$dbc = $db->Connect($dbost,$dbuser,$dbpass,$dbname);
} else { $dbc = false; }

if ($do_db || $do_db_cfg) {

	if ($dbdrop) { $db->Execute("DROP DATABASE IF EXISTS ".$dbname); }

	$db->Execute("CREATE DATABASE ".$dbname);
        $dbError = $db->ErrorNo();

        if ($dbError <> 0 && $dbError <> 1007) {
                $dbErr = true;
              	$dbMsg .= "A Database Error occurred. Database has not been created! The provided database details are probably not correct.<br>".$db->ErrorMsg()."<br>";

        }

	$db->Execute("USE " . $dbname);
	$sqlfile = "../db/dotproject.sql";

	$mqr = @get_magic_quotes_runtime();
	@set_magic_quotes_runtime(0);
	$query = fread(fopen($sqlfile, "r"), filesize($sqlfile));
	@set_magic_quotes_runtime($mqr);
	$pieces  = splitSql($query);
	$errors = array();
	for ($i=0; $i<count($pieces); $i++) {
		$pieces[$i] = trim($pieces[$i]);
		if(!empty($pieces[$i]) && $pieces[$i] != "#") {
			if (!$result = $db->Execute($pieces[$i])) {
				//$errors[] = array ( $db->ErrorMsg(), $pieces[$i] );
				$dbErr = true;
				$dbMsg .= $db->ErrorMsg().'<br>';
			}
		}
	}


        if ($dbError <> 0 && $dbError <> 1007) {
		$dbErr = true;
                $dbMsg .= "A Database Error occurred. Database has probably not been populated completely!<br>".$db->ErrorMsg()."<br>";
        }
	if ($dbErr) {
		$dbMsg = "DB setup incomplete - the following errors occured:<br>".$dbMsg;
	} else {
		$dbMsg = "Database successfully setup<br>";
	}
} else {
$dbMsg = "Not Created";
}

// always create the config file content

	$config = "<?php \n";
	$config .= "### Copyright (c) 2004, The dotProject Development Team dotproject.net and sf.net/projects/dotproject ###\n";
	$config .= "### All rights reserved. Released under BSD License. For further Information see ./includes/config-dist.php ###\n";
	$config .= "\n";
	$config .= "### CONFIGURATION FILE AUTOMATICALLY GENERATED BY THE DOTPROJECT INSTALLER ###\n";
	$config .= "### FOR INFORMATION ON MANUAL CONFIGURATION AND FOR DOCUMENTATION SEE ./includes/config-dist.php ###\n";
	$config .= "\n";
	$config .= "\$dPconfig['dbtype'] = \"$dbtype\";\n";
	$config .= "\$dPconfig['dbhost'] = \"$dbhost\";\n";
	$config .= "\$dPconfig['dbname'] = \"$dbname\";\n";
	$config .= "\$dPconfig['dbuser'] = \"$dbuser\";\n";
	$config .= "\$dPconfig['dbpass'] = \"$dbpass\";\n";
	$config .= "\$dPconfig['dbpersist'] = $dbpersist;\n";
	$config .= "\$dPconfig['root_dir'] = \$baseDir;\n";
	$config .= "\$dPconfig['base_url'] = \$baseUrl;\n";
	$config .= "?>";
	$config = trim($config);

if ($do_cfg || $do_db_cfg){
	if (is_writable("../includes/config.php") && ($fp = fopen("../includes/config.php", "w"))) {
		fputs( $fp, $config, strlen( $config ) );
		fclose( $fp );
		$cFileMsg = "Config file written successfully\n";
	} else {
		$cFileErr = true;
		$cFileMsg = "Config file could not be written\n";
	}
}

if ($dobackup){

	if( $dbc ) {
		require_once( "$baseDir/lib/adodb/adodb-xmlschema.inc.php" );

		$schema = new adoSchema( $db );

		$sql = $schema->ExtractSchema($content);

		header('Content-Disposition: attachment; filename="dPdbBackup'.date("Ymd").date("His").'.xml"');
		header('Content-Type: text/xml');
		echo $sql;
	} else {
		$msg = "ERROR: No Database Connection available!";
		header('Content-Disposition: attachment; filename="dPdbBackup'.date("Ymd").date("His").'.xml"');
		header('Content-Type: text/xml');
		echo $msg;
	}
}
//echo $msg;
?>
<html>
<head>
	<title>dotProject Installer</title>
	<meta name="Description" content="dotProject Installer">
 	<link rel="stylesheet" type="text/css" href="../style/default/main.css">
</head>
<body>
<h1><img src="dp.png" align="middle" alt="dotProject Logo"/>&nbsp;dotProject Installer</h1>
<form name="instFrm" action="do_install_db.php" method="post">
<table cellspacing="0" cellpadding="3" border="0" class="tbl" width="100%" align="left">
        <tr>
            <td class="title" valign="top">Database Installation Feedback:</td>
	    <td class="item"><b style="color:<?php echo $dbErr ? 'red' : 'green'; ?>"><?php echo $dbMsg; ?></b></td>
         <tr>
	 <tr>
            <td class="title">Config File Creation Feedback:</td>
	    <td class="item" align="left"><b style="color:<?php echo $cFileErr ? 'red' : 'green'; ?>"><?php echo $cFileMsg; ?></b></td>
	 </tr>
<?php if(!(($do_cfg || $do_db_cfg) && $cFileErr)){ ?>
	<tr>
	    <td class="item" align="left" colspan="2">The following Content should go to ./includes/config.php. Create that text file manually and copy the following lines in by hand.
		This file should be readable by the webserver.</td>
	 </tr>
         <tr>
            <td align="center" colspan="2"><textarea class="button" name="dbhost" cols="100" rows="20" title="Content of config.php for manual creation." /><?php echo $msg.$config; ?></textarea></td>
         </tr>
<?php } ?>
	<tr>
            <td class="title" valign="top" colspan="2">Upgrade from 1.0.2</td>
         <tr>
	<tr>
	    <td class="item" valign="top">In case of upgrading from 1.0.2 you should run the permissions upgrade script:</td>
	    <td class="item" align="left"><b><a href="<?php echo $baseUrl.'/db/upgrade_permissions.php';?>">Run permissions upgrade script</a></b></td>
	 </tr>
	<tr>
	    <td class="item" align="center" colspan="2"><br/><b><a href="<?php echo $baseUrl.'/index.php?m=system&a=systemconfig';?>">Login and Configure the dotProject System Environment</a></b></td>
	 </tr>
        </table>
</body>
</html>
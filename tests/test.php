<?php
ini_set("max_execution_time", 600);
if(!isset($_GET["address"])) $_GET["address"] = "";
if(!isset($_GET["target"])) $_GET["target"] = "STEST";
if(!isset($_GET["measuredSampleCount"])) $_GET["measuredSampleCount"] = "10000";
if(!isset($_GET["delaySampleCount"])) $_GET["delaySampleCount"] = "1000";
if(!isset($_GET["db"])) $_GET["db"] = "no"; //yes/no
if(!isset($_GET["table"])) $_GET["table"] = "data";
if(!isset($_GET["engine"])) $_GET["engine"] = "MOD PHP"; //MOD PHP/CGI PHP/MOD Python
if(!isset($_GET["readFunction"])) {
	if ($_GET["engine"] == "MOD Python") $_GET["readFunction"] = "recv"; //recv (change of read function not implemented)
	else $_GET["readFunction"] = "fread"; //fread/fgets
}
$timeout = 0;
$port1 = 0;
$port2 = 0;
//$phpAddress = "http://localhost/rtaixml/tests/test01.php"; //communication performance
$phpAddress = "http://localhost/rtaixml/tests/test02.php"; //statistical measurements - average sample count in one packet, average packet count in time
$cgiAddress = "http://localhost/rtaixml/tests/test01.cgi"; //communication performance
//$pythonAddress = "http://localhost/rtaixml/tests/test01.py"; //communication performance
$pythonAddress = "http://localhost/rtaixml/tests/test02.py"; //statistical measurements - average sample count in one packet, average packet count in time

require_once("../backend/xmlrpc-3.0.0.beta/lib/xmlrpc.inc");
session_start();
header("Content-type: text/plain");

echo "*RTAI-XML data exchange performance test*\nEngine:                      ".$_GET["engine"]."\nSocket stream read function: ".$_GET["readFunction"]."\nRead lower boundary:         ".$_GET["measuredSampleCount"]." samples\nDelay lower boundary:        ".$_GET["delaySampleCount"]." samples\nDatabase:                    ".$_GET["db"]."\n\n";

echo "CONNECT\n\n";

echo "Connection_Request\n";
$m = new xmlrpcmsg('Connection_Request', array(new xmlrpcval($_GET["target"], "none"), new xmlrpcval("", "none")));
$c = new xmlrpc_client("", $_GET["address"], 29500, "http11");
$r = $c->send($m, $timeout);
if ($r->faultCode()) {
	print "Fault \n";
	print "Code: " . htmlentities($r->faultCode()) . "\n" . "Reason: '" . htmlentities($r->faultString()) . "'\n";
	die("ERROR: Connection_Request");
} else {
	$v = $r->value();
	// iterating over all values of a struct object
	$v->structreset();
	while (list($key, $val) = $v->structEach()) {
		echo "'$key': ";
		print_r($val->scalarval());
		echo "\n";
		
		//set session id if found
		if($key == "id_session" && $val->scalarval()) {
			$_SESSION["sessionId"] = $val->scalarval();
		}

		//set port if found
		if($key == "port") {
			$port1 = $val->scalarval();
		}
	}	
	
	if(!$_SESSION["sessionId"]) die("ERROR: Session not attached.");
	if(!$port1) die("ERROR: Port1 not set.");
}

echo "tConnect\n";
$clientControl = new xmlrpc_client("", $_GET["address"], $port1, "http11");
$m = new xmlrpcmsg('tConnect', array(new xmlrpcval($_SESSION["sessionId"], "none")));
$rr = $clientControl->send($m, $timeout);
if ($rr->faultCode()) {
	print "Fault \n";
	print "Code: " . htmlentities($rr->faultCode()) . "\n" . "Reason: '" . htmlentities($rr->faultString()) . "'\n";
	die("ERROR: tConnect");
} else {
	$v = $rr->value();

	// iterating over all values of a struct object
	$v->structreset();
	while (list($key, $val) = $v->structEach()) {
		echo "'$key':";
		print_r($val->scalarval());
		echo "\n";
		
		//set session id if found
		if($key == "id_session" && $val->scalarval()) {
			$_SESSION["sessionId"] = $val->scalarval();
		}

		//set port if found
		if($key == "port") {
			$port2 = $val->scalarval();
		}
	}
	
	if(!$port2) die("ERROR: Port2 not set.");
	
}

echo "Get_Param\n";
$m = new xmlrpcmsg('Get_Param', array(new xmlrpcval($_SESSION["sessionId"], "none")));
$rr = $clientControl->send($m, $timeout);
if ($rr->faultCode()) {
	print "Fault \n";
	print "Code: " . htmlentities($rr->faultCode()) . "\n" . "Reason: '" . htmlentities($rr->faultString()) . "'\n";
	die("ERROR: Get_Param");
}

echo "Get_Signal_Structure\n";
$m = new xmlrpcmsg('Get_Signal_Structure', array(new xmlrpcval($_SESSION["sessionId"], "none")));
$rr = $clientControl->send($m, $timeout);
if ($rr->faultCode()) {
	print "Fault \n";
	print "Code: " . htmlentities($rr->faultCode()) . "\n" . "Reason: '" . htmlentities($rr->faultString()) . "'\n";
	die("ERROR: Get_Signal_Structure");
}

echo "Start_Data\n";
$m = new xmlrpcmsg('Start_Data', array(new xmlrpcval($_SESSION["sessionId"], "none"), new xmlrpcval(0, "int"), new xmlrpcval(1, "int")));
$rr = $clientControl->send($m, $timeout);
if ($rr->faultCode()) {
	print "Fault \n";
	print "Code: " . htmlentities($rr->faultCode()) . "\n" . "Reason: '" . htmlentities($rr->faultString()) . "'\n";
	die("ERROR: Start_Data");
} else {
	$v = $rr->value();

	// iterating over all values of a struct object
	$v->structreset();
	while (list($key, $val) = $v->structEach()) {
		echo "'$key': ";
		print_r($val->scalarval());
		echo "\n";
	}	
}

echo "\nREAD DATA\n\n";

//start timer
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;	

switch($_GET["engine"]) {
	case "MOD PHP":
		$ch =  curl_init($phpAddress."?address=".$_GET["address"]."&port=$port2&measuredSampleCount=".$_GET["measuredSampleCount"]."&readFunction=".$_GET["readFunction"]."&db=".$_GET["db"]."&table=".$_GET["table"]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		echo curl_exec($ch);
		//echo file_get_contents($phpAddress."?address=".$_GET["address"]."&port=$port2&measuredSampleCount=".$_GET["measuredSampleCount"]."&readFunction=".$_GET["readFunction"]."&db=".$_GET["db"]);
		break;
		
	case "CGI PHP":
		$ch =  curl_init($cgiAddress."?address=".$_GET["address"]."&port=$port2&measuredSampleCount=".$_GET["measuredSampleCount"]."&readFunction=".$_GET["readFunction"]."&db=".$_GET["db"]."&table=".$_GET["table"]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		echo curl_exec($ch);
		//echo file_get_contents($cgiAddress."?address=".$_GET["address"]."&port=$port2&measuredSampleCount=".$_GET["measuredSampleCount"]."&readFunction=".$_GET["readFunction"]."&db=".$_GET["db"]);
		break;
		
	case "MOD Python":
		$ch =  curl_init($pythonAddress."?address=".$_GET["address"]."&port=$port2&measuredSampleCount=".$_GET["measuredSampleCount"]."&readFunction=".$_GET["readFunction"]."&db=".$_GET["db"]."&table=".$_GET["table"]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		echo curl_exec($ch);
		//echo file_get_contents($pythonAddress."?address=".$_GET["address"]."&port=$port2&measuredSampleCount=".$_GET["measuredSampleCount"]."&readFunction=".$_GET["readFunction"]."&db=".$_GET["db"]);
		break;
}

//end timer
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$finish = $time;
//$total_time = round(($finish - $start), 4);
$total_time = $finish - $start;
echo "\nPage generated in ".$total_time." seconds.\n\n";

echo "\nDISCONNECT\n\n";

echo "Stop_Data\n";
$clientControl = new xmlrpc_client("", $_GET["address"], $port1, "http11");
$m = new xmlrpcmsg('Stop_Data', array(new xmlrpcval($_SESSION["sessionId"], "none"), new xmlrpcval(0, "int")));
$rr = $clientControl->send($m, $timeout);
if ($rr->faultCode()) {
	print "Fault \n";
	print "Code: " . htmlentities($rr->faultCode()) . "\n" . "Reason: '" . htmlentities($rr->faultString()) . "'\n";
	die("ERROR: Stop_Data");
} else {
	$v = $rr->value();

	// iterating over all values of a struct object
	$v->structreset();
	while (list($key, $val) = $v->structEach()) {
		echo "'$key': ";
		print_r($val->scalarval());
		echo "\n";
	}	
}

echo "Stop\n";
$clientControl = new xmlrpc_client("", $_GET["address"], $port1, "http11");
$m = new xmlrpcmsg('Stop', array(new xmlrpcval($_SESSION["sessionId"], "none")));
$rr = $clientControl->send($m, $timeout);
if ($rr->faultCode()) {
	print "Fault \n";
	print "Code: " . htmlentities($rr->faultCode()) . "\n" . "Reason: '" . htmlentities($rr->faultString()) . "'\n";
	die("ERROR: Stop");
} else {
	$v = $rr->value();

	// iterating over all values of a struct object
	$v->structreset();
	while (list($key, $val) = $v->structEach()) {
		echo "'$key': ";
		print_r($val->scalarval());
		echo "\n";
	}	
}

echo "\nEND\n"; 

?>
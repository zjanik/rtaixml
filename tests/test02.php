<?php
header("Content-type: text/plain");

ini_set("memory_limit", "512M");
ini_set("max_execution_time", 3600);

if(!isset($_GET["address"])) $_GET["address"] = "";
if(!isset($_GET["port"])) $_GET["port"] = 29502;
if(!isset($_GET["measuredSampleCount"])) $_GET["measuredSampleCount"] = 10000;
if(!isset($_GET["delaySampleCount"])) $_GET["delaySampleCount"] = 1000;
//if(!isset($_GET["readFunction"])) $_GET["readFunction"] = "fread";
if(!isset($_GET["db"])) $_GET["db"] = "no";
if(!isset($_GET["table"])) $_GET["table"] = "data";

$server = $_GET["address"];

if($_GET["db"] == "yes") {
	mysql_connect("localhost", "root", "aaa");
	mysql_select_db("rtaixml");
	mysql_query("DELETE FROM ".$_GET["table"]." WHERE server = '$server';");
	//mysql_query("TRUNCATE TABLE data;");
	//mysql_query("ALTER TABLE data AUTO_INCREMENT = 1;");
}

$errno = 0;
$errstr = "";

$executionTime = 0;

$fp = fsockopen($_GET["address"], $_GET["port"], $errno, $errstr);
if (!$fp) {
    echo "ERROR: $errno - $errstr<br />\n";
} else {
	$seqLoop = array();
	$seqLoopChangeIndicator = array();
	$sampleCounts = array();
	$totalSampleCount = 0;
	$delayedSampleCount = 0;
	$clockStarted = false;
	
    while($samples = fread($fp, 8096)) {
    	
    	if(!$clockStarted && $delayedSampleCount >= $_GET["delaySampleCount"]) {
    		$executionTime = -microtime(true);
    		$clockStarted = true;
    	}

    	$samplesArray = explode("\n", $samples);
    	array_pop($samplesArray); //remove last (blank) line;
    	
    	$queryValues = array();
    	foreach($samplesArray as $sample) {
    		//create mysql string
    		$value = explode(" ", $sample);
    		if(count($value) >= 3) {
    			//set sequence loop default values
	    		if(!isset($seqLoop[$value[0]])) {
	    			$seqLoop[$value[0]] = 0;
	    			$seqLoopChangeIndicator[$value[0]] = false;
	    		}
	    		
	    		if((int) $value[1] < 32768) {
	    			if($seqLoopChangeIndicator[$value[0]] == true) { //increase sequence loop number after sequence number restart
	    				$seqLoop[$value[0]]++;
	    			}
	    			$seqLoopChangeIndicator[$value[0]] = false; //we do not need to watch for sequence number restart
	    		} else {
	    			$seqLoopChangeIndicator[$value[0]] = true; //we need to watch for restart
	    		}
    			$queryValues[] = "('$server', '".$value[0]."', '".$seqLoop[$value[0]]."', '".$value[1]. "', '".$value[2]."')";
    		}
    	}

    	$sampleCount = count($queryValues); 
    	if($sampleCount > 0) {
    		if($clockStarted) {
	    		if($_GET["db"] == "yes") {
	    			mysql_query("INSERT INTO ".$_GET["table"]." (server, signalId, seqLoop, seq, value) VALUES ".implode(",", $queryValues).";");
	    		}
	    		$sampleCounts[] = $sampleCount;
	    		$totalSampleCount += $sampleCount;
    		} else {
    			$delayedSampleCount += $sampleCount;
    		}
    	}
    	
    	if($_GET["measuredSampleCount"] > 0 && $totalSampleCount >= $_GET["measuredSampleCount"]) break;
    }
    $executionTime += microtime(true);		
	fclose($fp);
	
	//average sample count in one packet
    $count = count($sampleCounts); //total numbers in array
    $total = 0;
    foreach($sampleCounts as $value) {
        $total = $total + $value; // total value of array numbers
    }
    $average = ($total/$count); // get average value
    echo "\nAverage sample count in one packet: ".str_replace(".", ",", $average)."\n";
	
	//average packet count in time
    echo "\nAverage packet count in one second: ".str_replace(".", ",", $count/$executionTime)."\n\n";
    
}

echo "Sample count: $totalSampleCount\n";
echo "Execution time: ".str_replace(".", ",", $executionTime)." s\n";
?>
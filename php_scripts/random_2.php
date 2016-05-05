<?php


$server1 = '104.196.102.223';
$server2 = '104.196.35.22';
$server3 = '104.196.38.180';
$server4 = '104.196.4.182';

$c0 = new Memcached();
$c0->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
$c0->addServer($server1, 11211);
$c0->addServer($server2, 11211);
$c0->addServer($server3, 11211);
$c0->addServer($server4, 11211);

$c1 = new Memcached();
$c1->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
$c1->addServer($server2, 11211);
$c1->addServer($server3, 11211);
$c1->addServer($server4, 11211);

$c2 = new Memcached();
$c2->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
$c2->addServer($server1, 11211);
$c2->addServer($server3, 11211);
$c2->addServer($server4, 11211);

$c3 = new Memcached();
$c3->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
$c3->addServer($server1, 11211);
$c3->addServer($server2, 11211);
$c3->addServer($server4, 11211);

$c4 = new Memcached();
$c4->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
$c4->addServer($server1, 11211);
$c4->addServer($server2, 11211);
$c4->addServer($server3, 11211);

/* Generating request keys which follows zipf distribution */

echo "calling zipf.py to generate distribution ";

$zipf_string = file_get_contents('anshul.txt');
$zipf_array = array_map('intval', explode(' ', $zipf_string));

$count = count($zipf_array);
$p = $count * 0.6;
/*$server1keyCount = shell_exec("echo stats cachedump 1 50000 | nc $server1 11211 | wc -l");
$server1keyCount = intval($server1keyCount);
$server1keyCount--;
print "<br>keys in server 1 = ".$server1keyCount;

$server2keyCount = shell_exec("echo stats cachedump 1 50000 | nc $server2 11211 | wc -l");
$server2keyCount = intval($server2keyCount);
$server2keyCount--;
print "<br>keys in server 2 = ".$server2keyCount;

$server3keyCount = shell_exec("echo stats cachedump 1 50000 | nc $server3 11211 | wc -l");
$server3keyCount = intval($server3keyCount);
$server3keyCount--;
print "<br>keys in server 3 = ".$server3keyCount;

$server4keyCount = shell_exec("echo stats cachedump 1 50000 | nc $server4 11211 | wc -l");
$server4keyCount = intval($server4keyCount);
$server4keyCount--;
print "<br>keys in server 4 = ".$server4keyCount;

$minCount = min($server1keyCount,  $server2keyCount, $server3keyCount, $server4keyCount);
$minServer = "";
/*
$minCount = $server2keyCount; //remove this later.used for testing
$minServer = $server2;
$currCluster = $c2;

if ($minCount == $server1keyCount) {
	$minServer = $server1;
	$currCluster = $c1;
} elseif ($minCount == $server2keyCount) {
        $minServer = $server2;
	$currCluster = $c2;
} elseif ($minCount == $server3keyCount) {
        $minServer = $server3;
	$currCluster = $c3;
} elseif ($minCount == $server4keyCount) {
        $minServer = $server4;
	$currCluster = $c4;
}



echo "<br>";
echo "The server with minimum unique keys = ".$minServer." with count = ".$minCount."<br>";
*/

$fh = fopen("/tmp/randNo.txt","r");
$randNo = intval(fgetc($fh));
fclose($fh);
echo "found rand no of srever = ".$randNo;
//$randNo = rand(1,4); //this generates random no between 1 and 4 (inclusive). This will be server to SCALE DOWN

if ($randNo == 1) {
	$minServer = $server1;
        $currCluster = $c1;
} elseif ($randNo == 2) {
	$minServer = $server2;
        $currCluster = $c2;
} elseif ($randNo == 3) {
	$minServer = $server3;
        $currCluster = $c3;
} elseif ($randNo == 4) {
	$minServer = $server4;
        $currCluster = $c4;
}

echo "The cluster has been changed by removing $minServer<br>";
//var_dump($currCluster);

/* Setting up things */
for($x = 0; $x < $p; $x++) {
   $result = $c0->get("key_".$zipf_array[$x]);
   if (!$result) {
        $c0->set("key_".$zipf_array[$x], "value_".$zipf_array[$x]) or die("Couldn't save anything to memcached $x...<br />");
   } else {
       // print "data found in memcache";
    }
}

// Now migrating keys
$no_of_hot_keys = 250;
$hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $minServer 11211");
print "got data from minServer<br>";
$result = explode("\n", $hot_kv);
echo "<br>";
$size = sizeof($result);
$cnt = 1;

foreach($result as $kvpair) {
    if ($kvpair == "\r")
	continue;
    if ($kvpair == "END\r")
	break;
    $parsed_str = explode(":", $kvpair);
    if($cnt < $size-1) {
//            print "key is: ".$parsed_str[0]."<br>";
//          print "value is: ".$parsed_str[1]."<br>";
	    $currCluster->set($parsed_str[0], $parsed_str[1]);
    } else {
            //Received END so it's time to wrap it up!
            //print "got: ".$kvpair."<br>";
            //print "reached at the end of kv pairs.<br>";
            break;
    }
    $cnt++;
}

echo "done migrating hot keys. Now running same training data \n\n";

$post_migration_miss = 0;
$post_migration_hits = 0;

$rt = "/tmp/randomserver.txt";
$fh = fopen($rt, 'w') or die("can't open file");
$avgStartTime = microtime(true);
$avg500 = 0;
for($x = $p; $x < $count; $x++) {

   $startTime = microtime(true);
   $result = $currCluster->get("key_".$zipf_array[$x]);
   if (!$result) {
        $post_migration_miss++;
        $currCluster->set("key_".$zipf_array[$x], "value_".$zipf_array[$x]);
        $avg500 = $avg500 + microtime(true) - $startTime + 0.008;
        if ($x%500 == 0) {
		$tmp = $avg500/500;
		fwrite($fh,$tmp."\n");
   		$avg500 = 0;
	}
   }
   else {
        $post_migration_hits++;
        $avg500 = $avg500 + microtime(true) - $startTime;
	if ($x%500 == 0) {
		$tmp = $avg500/500;
		fwrite($fh,$tmp."\n");
   		$avg500 = 0;
	}
   }
}
fclose($fh);

//total execution time = (end time) - (start time) + performance penalty (8ms in this case)

$responseTime = microtime(true) + 0.008*$post_migration_miss - $avgStartTime;
$avgResponseTime = $responseTime / ($count-$p);

echo "After migration of hot keys, avg response time = ".$avgResponseTime."<br>";
echo "After migration of hot keys, total  response  = ".$responseTime."<br>";
echo "After migration of hot keys, hits = ".$post_migration_hits."<br>";
echo "After migration of hot keys, miss = ".$post_migration_miss."<br>";

//Now compare efficiency of hits and miss

//echo "<br>% hits before migration = ".($pre_migration_hits*100/$count);
echo "<br>% hits after migration = ".($post_migration_hits*100/($count-$p));

//echo "<br>% miss before migration = ".($pre_migration_miss*100/$count);
echo "<br>% miss after migration = ".($post_migration_miss*100/($count-$p));

?>

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
$p = $count * 0.4;

$noDist = array(); //array to determine frequency of each number of zipf dist
$currCluster = $c0; //c0 has all the servers

for($x = 0; $x < $p; $x++) {
        $currCluster->set("key_".$zipf_array[$x], "value_".$zipf_array[$x]) or die("Couldn't save anything to memcached $x and count is $count...<br />");
}

echo "all keys set in memcache<br>";
echo "<br><br>";

/** Getting 1 kv pair from each server so that we can find maximum time **/
$no_of_hot_keys = 1;
$maxTime = -1;
$minIndex = 0;

for($i = 1; $i < 5; $i++) {
    if ($i == 1) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server1 11211");
    } else if ($i == 2) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server2 11211");
    } else if ($i == 3) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server3 11211");
    } else if ($i == 4) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server4 11211");
    }
    $result = explode("\n", $hot_kv);
    echo "<br>";
    $time_val = 0;
    $cnt = 0;
    foreach($result as $kvpair) {
        if ($kvpair == "END\r")
            break;
        $parsed_str = explode(":", $kvpair);
	$maxTime = max($maxTime, intval($parsed_str[2]));
   }
}

echo "the maximum time found is ".$maxTime."<br>";


$maxTime = $maxTime - 5; // we need all keys within 15 seconds of most recent access time
$keyCnt = array();
$no_of_hot_keys = 1100;
$minIndex = -1;
$minValue = 100000;

for($i = 1; $i < 5; $i++) {
    if ($i == 1) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server1 11211");
    } else if ($i == 2) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server2 11211");
    } else if ($i == 3) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server3 11211");
    } else if ($i == 4) {
        $hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $server4 11211");
    }
    $result = explode("\n", $hot_kv);
    $time_val = 0;
    $cnt = 0;
    foreach($result as $kvpair) {
        if ($kvpair == "END\r")
            break;
        $parsed_str = explode(":", $kvpair);
        if ($parsed_str[2] > $maxTime)
		$keyCnt[$i-1]++;
	else
		break;
   }
   $minValue = min($minValue, $keyCnt[$i-1]);
   if ($minValue == $keyCnt[$i-1])
	$minIndex = $i;

    echo "total count of keys having value above ".$maxTime." = ".$keyCnt[$i-1]."<br>";
}

$minServer = "";
if ($minIndex == 1) {
        $currCluster = $c1;
        $minServer = $server1;
} elseif ($minIndex == 2) {
        $currCluster = $c2;
        $minServer = $server2;
} elseif ($minIndex == 3) {
        $currCluster = $c3;
        $minServer = $server3;
} elseif ($minIndex == 4) {
        $currCluster = $c4;
        $minServer = $server4;
} else{
        echo "No matching server.";
}

echo "The cluster has been changed by removing $minServer<br>";

/* Now migrating keys */
$no_of_hot_keys = 600;
$hot_kv = shell_exec("echo stats gethotkeys $no_of_hot_keys | nc $minServer 11211");
print "got data from minServer<br>";
$result = explode("\n", $hot_kv);
echo "<br>";
$size = sizeof($result);

foreach($result as $kvpair) {
    if ($kvpair == "\r")
        continue;
    if ($kvpair == "END\r")
        break;
    $parsed_str = explode(":", $kvpair);
//            print "key is: ".$parsed_str[0]."<br>";
//          print "value is: ".$parsed_str[1]."<br>";
    $currCluster->set($parsed_str[0], $parsed_str[1]);
}

echo "done migrating hot keys. Now running same training data \n\n";

$post_migration_miss = 0;
$post_migration_hits = 0;

$rt = "/tmp/access_time.txt";
$fh = fopen($rt, 'w') or die("can't open file");
$avgStartTime = microtime(true);
$avg500 = 0;

for ($x = $p; $x < $count; $x++) {
   $startTime = microtime(true);
   $result = $currCluster->get("key_".$zipf_array[$x]);
   if (!$result) {
        $post_migration_miss++;
        $currCluster->set("key_".$zipf_array[$x], "value_".$zipf_array[$x]);
        $avg500 = $avg500 + microtime(true) - $startTime + 0.008;
        if ($x%500 == 0) {
                $tmp=$avg500/500;
                fwrite($fh,$tmp."\n");
                $avg500=0;
        }
   }
   else {
        $post_migration_hits++;
        $avg500 = $avg500 + microtime(true) - $startTime;
        if ($x%500 == 0) {
                $tmp=$avg500/500;
		fwrite($fh,$tmp."\n");
		$avg500=0;
	}
   }
}
fclose($fh);
/*
total execution time = (end time) - (start time) + performance penalty (8ms in this case)
*/
$responseTime = microtime(true) + 0.008*$post_migration_miss - $avgStartTime;
//$avgResponseTime = $responseTime / $count;
$avgResponseTime = $responseTime / ($count - $p);

echo "Before migration of hot keys, per request avg response time = ".$avgResponseTime."<br>";
echo "After migration of hot keys, avg response  = ".$responseTime."<br>";
echo "After migration of hot keys, hits = ".$post_migration_hits."<br>";
echo "After migration of hot keys, miss = ".$post_migration_miss."<br>";

/*Now compare efficiency of hits and miss*/

//echo "<br>% hits after migration = ".($post_migration_hits*100/$count);
//echo "<br>% miss after migration = ".($post_migration_miss*100/$count);
echo "<br>% hits after migration = ".($post_migration_hits*100/ ($count - $p));
echo "<br>% miss after migration = ".($post_migration_miss*100/ ($count - $p));
?>


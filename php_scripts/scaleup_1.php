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

$c1 = new Memcached();
$c1->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
$c1->addServer($server1, 11211);
$c1->addServer($server2, 11211);
$c1->addServer($server3, 11211);
$c1->addServer($server4, 11211);

/* Generating request keys which follows zipf distribution */

echo "calling zipf.py to generate distribution ";

$zipf_string = file_get_contents('trace.txt');
$zipf_array = array_map('intval', explode(' ', $zipf_string));

$count = count($zipf_array);
$p = $count * 0.6;

$noDist = array(); //array to determine frequency of each number of zipf dist
$currCluster = $c0; //c0 has all the servers

for($x = 0; $x < $p; $x++) {
        $currCluster->set("key_".$zipf_array[$x], "value_".$zipf_array[$x]) or die("Couldn't save anything to memcached $x and count is $count...<br />");
}

echo "all keys set in memcache<br>";
echo "<br><br>";
$currCluster = $c1;
echo "cluster changed.<br>";
$post_migration_miss = 0;
$post_migration_hits = 0;

$rt = "/tmp/scaleup_1.txt";
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
echo "Before migration of hot keys, avg response  = ".$responseTime."<br>";
echo "Before migration of hot keys, hits = ".$post_migration_hits."<br>";
echo "Before migration of hot keys, miss = ".$post_migration_miss."<br>";

/*Now compare efficiency of hits and miss*/

//echo "<br>% hits after migration = ".($post_migration_hits*100/$count);
//echo "<br>% miss after migration = ".($post_migration_miss*100/$count);
echo "<br>% hits before migration = ".($post_migration_hits*100/ ($count - $p));
echo "<br>% miss before migration = ".($post_migration_miss*100/ ($count - $p));
?>


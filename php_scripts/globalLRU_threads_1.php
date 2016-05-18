<?php

/**
 * GlobalLRU - with migration via thread simulation
 *	     - should be executed simultaneously with globalMigration.php
 *
 * This policy selectes a server having minimum number of keys
 * accessed in last 5 sec window. To get this, we follow
 * below mentioned procedure
 * a) Select a default cluster with 4 servers
 * b) Set keys from first p% of trace - called warmup period
 * c) Create a file, warmup_done.txt after completion of warmup period
 * d) Start counting cache hits and misses for next (1-p)% of traces in
 *	old cluster, if migration_done.txt doesn't exists
 * e) Change cluster, i.e. remove server mentioned in migration_done.txt,
 *	when migration_done.txt is created by globalMigration.php
 * c) Get number of keys accessed in last 5 sec / 15 sec window
 * d) Find one server with minimum number of such keys
 * e) Get 'n' number of hot keys from selected server and 
 *      perform pro-active data migration for these hot keys
 * d) Remove this server and check performance for next (1-p)%
 *      of trace after migration
 */

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

$zipf_string = file_get_contents('trace.txt');
$zipf_array = array_map('intval', explode(' ', $zipf_string));

$count = count($zipf_array);
$p = $count * 0.9;

$noDist = array(); //array to determine frequency of each number of zipf dist
$currCluster = $c0; //c0 has all the servers

for($x = 0; $x < $p; $x++) {
        $currCluster->set("key_".$zipf_array[$x], "value_".$zipf_array[$x]) or die("Couldn't save anything to memcached $x and count is $count...<br />");
}
touch("/tmp/warmup_done.txt");

echo "all keys set in memcache<br>";
echo "<br><br>";

echo "starting get and set<br>";
$post_migration_miss = 0;
$post_migration_hits = 0;

$rt = "/tmp/globalThread.txt";
$fh = fopen($rt, 'w') or die("can't open file");
$avgStartTime = microtime(true);
$avg500 = 0;

/* initialize need_to_check flag with 1, as we need to change
 * $currCluster only once on the basis of migration_done.txt
 * once this is done, we set this flag to 0 to avoid further checks
 */
$need_to_check = 1;

for ($x = $p; $x < $count; $x++) {
   /* Before each get check if migration_done file exists,
    * if yes, then get new cluster number from that file and change
    * $currCluster to new one, else continue doing get in old cluster
    */
   if ($need_to_check == 1 && file_exists("/tmp/migration_done.txt")) {
	$f = fopen("/tmp/migration_done.txt","r");
	$clusterNo = intval(fgetc($f));
	echo "cluster no: ".$clusterNo."<br>";

	if ($clusterNo == 1) {
        	$currCluster = $c1;
	} elseif ($clusterNo == 2) {
        	$currCluster = $c2;
	} elseif ($clusterNo == 3) {
        	$currCluster = $c3;
	} elseif ($clusterNo == 4) {
        	$currCluster = $c4;
	} else{
        	echo "No matching server.";
	}

	fclose($f);
	$need_to_check = 0;
   }
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
$avgResponseTime = $responseTime / ($count - $p);

echo "After migration of hot keys, per request avg response time = ".$avgResponseTime."<br>";
echo "After migration of hot keys, avg response  = ".$responseTime."<br>";
echo "After migration of hot keys, hits = ".$post_migration_hits."<br>";
echo "After migration of hot keys, miss = ".$post_migration_miss."<br>";

/*Now compare efficiency of hits and miss*/
echo "<br>% hits after migration = ".($post_migration_hits*100/ ($count - $p));
echo "<br>% miss after migration = ".($post_migration_miss*100/ ($count - $p));

/* delete both files after 1 iteration of experiment */
unlink("/tmp/warmup_done.txt");
unlink("/tmp/migration_done.txt");

?>


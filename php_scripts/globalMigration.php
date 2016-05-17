<?php

/**
 * GlobalLRU - migration (gets executed simultaneously with globalMain.php)
 *
 * This policy selectes a server having minimum number of keys
 * accessed in last 5 sec window. To get this, we follow
 * below mentioned procedure
 * a) Select a default cluster with 4 servers
 * b) Keep checking if globalMain.php has created warmup_done.txt
 * c) Continue when warmup_done.txt is found
 * d) Get number of keys accessed in last 5 sec window from all the servers
 *	in current cluster
 * e) Find one server with minimum number of such keys
 * f) Get 'n' number of hot keys from selected server and 
 *      perform pro-active data migration for these hot keys, to new cluster
 * g) Create migration_done.txt after migration is completed. Write number of
 *      to-be-scaled-down server in this file, so that it can be used by
 *	globalMain.php to change the cluster
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


$currCluster = $c0; //c0 has all the servers

/* Do nothing if warmup_done.txt does not exists */
while (file_exists("/tmp/warmup_done.txt") == FALSE);

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
$no_of_hot_keys = 330;
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
    $currCluster->set($parsed_str[0], $parsed_str[1]);
}
echo "done migrating hot keys. Creating file.<br>";

$fh = fopen("/tmp/migration_done.txt",'w') or die("cant open migration done file");
fwrite($fh,$minIndex."\n");
fclose($fh);

?>


/******************************************************************************
 *		  CSE 591 - EEC Class Project, Spring'16
 *
 *		Proactive Data Migration Before CacheScale
 *
 *				Submitted By
 *    Harsh Kumar Patel, Jasmit Kaur Saluja, Nirmit Desai, Shubhada Patil
 *
 *		Under the guidance of - Prof. Anshul Gandhi
 *****************************************************************************/

## INTRODUCTION:
	In a multi-tier cloud service during low utilization, we can reduce the
	service’s operational cost by scaling ‘always on’ MemCache servers.
	Turning down inappropriate server might affect performance negatively.
	Even if to-be-scaled-down server is selected, migration of “hot” data
	from this selected server should be given importance in order to avoid 
	increase in cache-miss. Hence retaining hot data in active MemCache
	servers is the key requirement while scaling down.

	Our objective is to devise and compare various policies using LRU, for
	selection of appropriate cache instance to scale down. Proactive 
	migration of ‘hot’ data from to-be-scaled-down cache server is being 
	done to avoid increase in average response time.

## MEMCACHED BUILD & INSTALLATION:
1. clone memcached
2. make && make install
3. install dependencies i.e. m4, autoconf, automake, libtool in case of error, if any

## PHP SCRIPTS:
1. Per policy scripts are attached, please check comments in scripts for more details
2. Three policies for scale down
	a) Minimum average access time
	b) Random server policy
	c) Global LRU
3. One policy for scale up - Global LRU
4. Details of scripts are as follows:
	a) access_time_1.php: Minimum average access time baseline policy without migration
	b) access_time_2.php: Minimum average access time policy with migration
	c) random_1.php: Random policy without migration
	d) random_2.php: Random policy with migration
	e) globalLRU_1.php: Global LRU baseline policy for scale down without migration
	f) globalLRU_2.php: Global LRU policy for scale down with migration
	g) globalLRU_Main_threads_1.php: Threaded version of global LRU for scale down (warm up and observation)
	h) globalLRU_Migration_threads_1.php: Threaded version of global LRU for scale down (migration)
	i) scaleup_1.php: Global LRU for scale up without data population
	j) scaleup_2.php: Global LRU for scale up with data population

## MODIFICATIONS IN MEMCACHED:
	- items.c:	added definition for get_hot_keys API 
	- items.h:	added declaration of get_hot_keys API
	- memcached.c:	how to call get_hot_keys

## MODIFICATION IN PHP.INI
	In file '/etc/php5/apache2/php.ini', change max_execution_time = 300.
	This is because our experiments takes longer time than default timeout settings.
	

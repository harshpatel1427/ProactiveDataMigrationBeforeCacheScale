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

INTRODUCTION:
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

MEMCACHED BUILD & INSTALLATION:
	- clone memcached
	- make && make install
	- install dependencies i.e. m4, autoconf, automake, libtool in case of
	  error, if any

PHP SCRIPTS:
	- Per policy scripts are attached, please check comments in scripts
	  for more details
	- 3 policies for scale down
		a) Minimum average access time
		b) Random server policy
		c) Global LRU
	- 1 policy for scale up - Global LRU

MODIFICATIONS IN MEMCACHED:
	- items.c:	added definition for get_hot_keys API 
	- items.h:	added declaration of get_hot_keys API
	- memcached.c:	how to call get_hot_keys

	

#!/usr/bin/php
<?php
	$options = getopt("hf:");

	$STATE_OK=0;
	$STATE_WARNING=1;
	$STATE_CRITICAL=2;
	$STATE_UNKNOWN=3;

	function usage()
	{
		echo "Usage: check_munin_rrd.php <options>\n";
		echo "Check Munin RRD databases\n";
		echo "Options:\n";
		echo "-f file name (required)\n";
		echo "-h shows this help message\n";
	}

	if(in_array('h', array_keys($options)))
	{
		usage();
		return 0;
	}
	if(!in_array('f', array_keys($options)))
	{
		echo "No file specified.\n";
		return 1;
	}

	exec("rrdtool dump ".$options['f']." > /tmp/".$options['f'].".tmp");

	$rrdxml = simplexml_load_file("/tmp/".$options['f'].".tmp");
	if($rrdxml == FALSE)
	{
		echo "UNKNOWN: Could not load file\n";
		return $STATE_UNKNOWN;
	}

	var_dump($rrdxml);


?>
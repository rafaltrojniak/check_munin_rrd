#!/usr/bin/php
<?php
	$options = getopt("hf:t:n::");
	$nanlevel = "warn";

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
		echo "-t x:y, if last update was more than x secs ago, WARN, if more than y, CRIT\n";
		echo "-n [lvl] check for NaN values between correct entries, lvl can be warn or crit (defaults to warn)\n";
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

	if(in_array('t', array_keys($options)))
	{
		$times = explode(':', $options['t']);
		if((time() - $rrdxml->lastupdate) > $times[1])
		{
			echo "CRITICAL: Last update was at ".date("D d-m-Y H:i:s O", (int)$rrdxml->lastupdate)."\n";
			return $STATE_CRITICAL;
		}
		if((time() - $rrdxml->lastupdate) > $times[0])
		{
			echo "WARNING: Last update was at ".date("D d-m-Y H:i:s O", (int)$rrdxml->lastupdate)."\n";
			return $STATE_WARNING;
		}
	}
	if(in_array('n', array_keys($options)))
	{
		if($options['n']) $nanlevel = $options['n'];
		$nan_ok = false;
		$nancount = 0;
		$rracount = count($rrdxml->rra);
		for($i = 0; $i < $rracount; $i++)
		{
			$rowcount = count($rrdxml->rra[$i]->database->row);
			for($j = 0; $j < $rowcount; $j++)
			{
				if($rrdxml->rra[$i]->database->row[$j]->v == "NaN")
				{
					if($j == 0) $nan_ok = true;
					if(!$nan_ok) $nancount++;
				}
				else $nan_ok = false;
			}
		}
		if($nancount != 0)
		{
			if($nanlevel == "crit")
			{
				echo "CRITICAL: $nancount NaNs found.\n";
				return $STATE_CRITICAL;
			}
			else
			{
				echo "WARNING: $nancount NaNs found.\n";
				return $STATE_WARNING;
			}
		}
	}
	echo "OK: All RRD checks passed.\n";
	return $STATE_OK;

?>
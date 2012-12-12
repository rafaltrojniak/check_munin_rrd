#!/usr/bin/php
<?php

	$STATE_OK=0;
	$STATE_WARNING=1;
	$STATE_CRITICAL=2;
	$STATE_UNKNOWN=3;

	function usage()
	{
		echo "Usage: ".$argv[0]." <options>\n";
		echo "Check Munin RRD databases\n";
		echo "Options:\n";
		echo "-f file name (required)\n";
		echo "-t x:y, if last update was more than x secs ago, WARN, if more than y, CRIT\n";
		echo "-n x:y:z:name, check for NaN values at the end of database, if last NaN was less than x entries ago - CRIT\n";
		echo "                                                            if less than y - WARN, 0 means last entry\n";
		echo "                                                            z - which database (3600 - hourly, etc.)\n";
		echo "                                                            name - name of database (AVERAGE, MIN, MAX...)\n";
		echo "-h shows this help message\n";
		echo "Example: ".$argv[0]." -f test.rrd -t 300:600 -n 0:3:3600";
	}

	$options = getopt("hf:t:n:");
	foreach($options as $option=>$val)
	{
		switch($option)
		{
			case 'f':
				$file = $val;
				break;

			case 't':
				$times = explode(':', $val);
				break;

			case 'n':
				$report_nans = explode(':', $val);
				break;

			case 'h':
				usage();
				return 0;
				break;
			default:
				echo "Unknown option: -$option\n";
				return 1;
				break;
		}
	}

	if(!$file )
	{
		echo "No file name specified.\n";
		return 1;
	}

	exec("rrdtool dump ".escapeshellarg($file), $dump);
	$rrdxml = simplexml_load_string(implode('', $dump));
	if($rrdxml == FALSE)
	{
		echo "UNKNOWN: Could not parse XML\n";
		return $STATE_UNKNOWN;
	}

	if(isset($times))
	{
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

	if(isset($report_nans))
	{
		$rracount = count($rrdxml->rra);
		for($i = 0; $i < $rracount; $i++)
		{
			if($rrdxml->rra[$i]->pdp_per_row == $report_nans[2]/$rrdxml->step)
			{
				if($rrdxml->rra[$i]->cf == $report_nans[3])
				{
					$rowcount = count($rrdxml->rra[$i]->database->row)-1;
					for($j = 0; $j <= $report_nans[1]; $j++)
					{
						if($rrdxml->rra[$i]->database->row[$rowcount - $j]->v == "NaN")
						{
							if($j <= $report_nans[0])
							{
								echo "CRITICAL: NaN at $j in ".$rrdxml->rra[$i]->cf."\n";
								return $STATE_CRITICAL;
							}
							if($j <= $report_nans[1])
							{
								echo "WARNING: NaN at $j in ".$rrdxml->rra[$i]->cf."\n";
									return $STATE_WARNING;
							}
						}
					}
				}
			}		
		}
	}
	echo "OK: All RRD checks passed.\n";
	return $STATE_OK;
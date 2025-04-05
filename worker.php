#!/usr/bin/env php
<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz


// Checking if we are in CLI
if (PHP_SAPI != "cli") {
    exit;
}


// Settings
include("/etc/phbackup/opt.php");

// Functions
try {
    require '/etc/phbackup/functions.php';
}
catch (Error $e) {
    // debugging example:
    die('Caught error => ' . $e->getMessage());
}


$rsync_opts = "-vbrltz";
$ticker_step=5;

preg_match('/phb-worker-(\d+)/', getenv('SUPERVISOR_PROCESS_NAME'), $matches);
$worker_id = $matches[1];
//$worker_id=22;
cli_set_process_title("phbackup-$worker_id [idle]");

$datestamp = date("Y-m-d_H:i:s");
echo "$datestamp - [$worker_id] Started worker #$worker_id\n"; 


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


$busy=0;

// Entering main cycle
while(true) 
{
    // Check if we are a real worker, not dispatcher
    if($busy==0)
    {
        $db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);

    // Finding stale hosts
        $db->begin_transaction();
        $sql = "SELECT hosts.id, hosts.name, hosts.enabled, hosts.worker, host_vars.value, hosts.last_backup, hosts.backup_started, TIMESTAMPDIFF(hour, hosts.last_backup, now()) as hours 
        FROM `hosts` left JOIN host_vars on hosts.id=host_vars.host          
        WHERE ( host_vars.var='backup_period' AND hosts.worker>=0 AND hosts.enabled=1)
        ORDER BY RAND() LIMIT 0,1 FOR UPDATE;";

    try {
	    $res = $db->query($sql);
        if ($res === FALSE) {
	    throw new Exception($db->error);
        }
	}
	catch(Exception $e) {
            echo "$datestart - [$worker_id] SQL error: ".$db->error."\n"; 
    }

        if( mysqli_num_rows($res)>0 && !$db->error )
        {
            while($row = $res->fetch_array())
            {
                if($row['hours'] > $row['value']) 
                {
                    $datestart = date("Y-m-d_H:i:s");
//                    echo "$datestart - [$worker_id] Host ".$row['name']." - Host looks stale, checking ps\n";
                    $cmd = "ps ax|grep phbackup | grep \"".$row['name']."]\" | grep -v 'ps ax' |wc -l";
                    $output="";
                    exec($cmd, $output, $return_code);
                    if ($return_code>0) echo "Failed: $cmd\n";
                    if ($output[0] == "0") 
                    {
                        echo "$datestart - [$worker_id] Host ".$row['name']." - Host is stale, unlocking\n";
                        $sql="UPDATE hosts set worker=-1, status=2, next_try=DATE_ADD(NOW(), INTERVAL 1 HOUR), backup_now=0 where id=".$row['id'];
                        $db->query($sql);
                    }
                }
            }
        }
        else 
        {
	    $db->rollback();
//    	    echo "$datestart - [$worker_id] No hosts for backup, skipping turn\n";
	}




	// Cron scripts installation

        $db->begin_transaction();

        $sql="SELECT hosts.id, hosts.name, hosts.enabled, hosts.worker, host_vars.value, hosts.pre_install
    	    FROM `hosts` left JOIN host_vars on hosts.id=host_vars.host 
    	    WHERE ( host_vars.var='pre_script' OR host_vars.var='pre_schedule' )
    	    AND hosts.pre_install=1 
    	    FOR UPDATE";
	try {
    	    $res = $db->query($sql);
	    if ($res === FALSE) {
    		throw new Exception($db->error);
	    }
    	}
    	catch(Exception $e) {
            echo "$datestart - [$worker_id] SQL error: ".$db->error."\n"; 
	}

        if( mysqli_num_rows($res)>0 && !$db->error )
        {
            while($row = $res->fetch_array())
            {
	        $host_id=$row['id'];
		$datestart = date("Y-m-d_H:i:s");

		echo "$datestart - [$worker_id] Host ".$row['name']." - Starting cron script installation\n";

                // Getting host vars
                $sql="SELECT hosts.*, host_groups.path FROM `hosts`,host_groups WHERE hosts.id=".$host_id." and hosts.group_id=host_groups.id;";
                $res = $db->query($sql);
                $host_data = $res->fetch_array();

                $sql="select * from host_vars where host=".$host_id.";";
                $res = $db->query($sql);
                while ($row = $res->fetch_array()) {
                    $host_vars[$row['var']] = $row['value'];
                }

                $ssh_opts="-ocompression=no -oLogLevel=ERROR -oServerAliveInterval=3 -oServerAliveCountMax=806400 -oStrictHostKeyChecking=no -oUserKnownHostsFile=/dev/null -p ".$host_data['port'];
                $scp_opts="-ocompression=no -oLogLevel=ERROR -oServerAliveInterval=3 -oServerAliveCountMax=806400 -oStrictHostKeyChecking=no -oUserKnownHostsFile=/dev/null -P ".$host_data['port'];


                $bkpath = $backup_path."/".$host_data['path']."/".$host_data['name'];
		if (!is_dir($bkpath)) exec("mkdir -p $bkpath");
                file_put_contents("$bkpath/tmp.sh", base64_decode($host_vars['pre_script']));
                file_put_contents("$bkpath/tmp.cron", "# Updated at ".$datestart."\n".base64_decode($host_vars['pre_schedule'])."\n");
	        exec("tr -d '\r' < $bkpath/tmp.sh > $bkpath/phbackup.sh && rm $bkpath/tmp.sh");
	        exec("tr -d '\r' < $bkpath/tmp.cron > $bkpath/phbackup && rm $bkpath/tmp.cron");

	        $updateok=0;
                $cmd = "ssh $ssh_opts ".$host_data['user']."@".$host_data['ip']." \"mkdir -p /opt > /dev/null\"";
                exec($cmd, $output, $return_code);
                if ($return_code>0) echo "Failed: $cmd\n";
                $cmd = "chmod 750 $bkpath/phbackup.sh && scp $scp_opts $bkpath/phbackup.sh ".$host_data['user']."@".$host_data['ip'].":/opt/ > /dev/null";
                exec($cmd, $output, $return_code);
                if ($return_code>0) echo "Failed: $cmd\n";
                $updateok += $return_code;
                $cmd = "ssh $ssh_opts ".$host_data['user']."@".$host_data['ip']." \"rm -f /etc/cron.d/phbackup.cron\"";
                exec($cmd, $output, $return_code);
                if ($return_code>0) echo "Failed: $cmd\n";
                $updateok += $return_code;
                $cmd = "scp $scp_opts $bkpath/phbackup ".$host_data['user']."@".$host_data['ip'].":/etc/cron.d/ > /dev/null";
                exec($cmd, $output, $return_code);
                if ($return_code>0) echo "Failed: $cmd\n";
                $updateok += $return_code;

	        if($updateok==0)
	        {
        	    $sql="UPDATE hosts set pre_install=0 where id=".$host_id;
		    $db->query($sql);
    		    echo "$datestart - [$worker_id] Host ".$host_data['name']." - updated pre-backup script\n"; 
		    $db->commit();
	        }
	        else
	        {
    		    echo "$datestart - [$worker_id] Host ".$host_data['name']." - failed to update pre-backup script\n"; 
    		    $db->rollback();
        	    $sql="UPDATE hosts set pre_install=2 where id=".$host_id;
		    $db->query($sql);
	        }
	    }
        }
        else 
        {
    	    $db->rollback();
//    	    echo "$datestart - [$worker_id] No hosts for backup, skipping turn\n";
    	}





	// Looking for host to backup

        $host_id=0;
        $db->begin_transaction();

        $sql="SELECT hosts.id, hosts.name, hosts.enabled, hosts.worker, hosts.last_backup, host_vars.value, hosts.time_slots, hosts.next_try, hosts.backup_now, host_groups.path as path
	    FROM `hosts` 
            LEFT JOIN host_vars on hosts.id=host_vars.host 
            LEFT JOIN host_groups on hosts.group_id=host_groups.id
	    WHERE host_vars.var='backup_period' 
	    AND hosts.enabled=1 
	    AND hosts.worker=-1 
	    AND ( hosts.next_try<NOW()
	    OR hosts.backup_now=1 )
	    ORDER BY RAND() LIMIT 0,1 FOR UPDATE";
	try {
    	    $res = $db->query($sql);
	    if ($res === FALSE) {
    		throw new Exception($db->error);
	    }
    	    $row = $res->fetch_array();
    	}
    	catch(Exception $e) {
            echo "$datestart - [$worker_id] SQL error: ".$db->error."\n"; 
	}

        $datestart = date("Y-m-d H:i:s");
        $nextbackup = date("Y-m-d H:i:00");
	$timeok=false;
	$backupnow=false;


	// Checking time slots
        if( mysqli_num_rows($res)>0 && !$db->error ){

	    $times=explode(",",$row['time_slots']);
	    $curhour=date("G");
	    foreach ($times as $time) {
	        if( list($h_start,$h_end)=explode("-",$time) )
	        {
	    	    if ($curhour>=$h_start && $curhour<$h_end ) $timeok=true;
		}
	    }

	    // Handling "Backup now"
	    if ($row['backup_now']==1) { $timeok=true; $backupnow=true; echo "$datestart - [$worker_id] Host ".$row['name']." - found Backup Now flag\n"; }
            

	}


        if( mysqli_num_rows($res)>0 && !$db->error && $timeok==true ){
            $sql="UPDATE hosts set worker=$worker_id, backup_started=NOW(), status=1 where id=".$row['id']." AND worker=-1;";
	    $db->query($sql);
            echo "$datestart - [$worker_id] Host ".$row['name']." - starting backup\n"; 
            $host_id = $row['id'];
	    $busy=1;
	    $db->commit();
        }
        else 
        {
    	    $db->rollback();
//    	    echo "$datestart - [$worker_id] No hosts for backup, skipping turn\n";
    	}

	// Got host, starting backup
        if ($host_id>0)
        {
            // Getting host vars
            $sql="SELECT hosts.*, host_groups.path FROM `hosts`,host_groups WHERE hosts.id=".$host_id." and hosts.group_id=host_groups.id;";
            $res = $db->query($sql);
            $host_data = $res->fetch_array();

            $sql="select * from host_vars where host=".$host_id.";";
            $res = $db->query($sql);
            while ($row = $res->fetch_array()) {
                $host_vars[$row['var']] = $row['value'];
            }


            run_backup ($db, $host_data, $host_vars);
            $busy=0;

        }

        $db->close();


        sleep($ticker_step);

    }

    // We are busy now, skipping turn
    else
    {
	sleep($ticker_step);
    }

}

?>

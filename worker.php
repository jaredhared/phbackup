#!/usr/bin/env php
<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz
// Version 0.1.0


// Checking if we are in CLI
if (PHP_SAPI != "cli") {
    exit;
}


// Settings
include("/etc/phbackup/opt.php");

$rsync_opts = "-vbrltz";
$ticker_step=5;
$worker_id=$argv[1];

$mytitle=cli_get_process_title();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);




// Entering main cycle
while(true) 
{
    // Check if we are a real worker, not dispatcher
    if($worker_id>1)
    {
        cli_set_process_title("phbackup-$worker_id-free");
        $db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);

        $host_id=0;

        $sql="SELECT hosts.id, hosts.enabled, hosts.worker, hosts.last_backup, host_vars.value, assigned_worker 
    	    FROM `hosts` left JOIN host_vars on hosts.id=host_vars.host 
    	    WHERE host_vars.var='backup_period' 
    	    AND hosts.enabled=1 
    	    AND hosts.worker=0 
    	    AND assigned_worker=$worker_id
    	    AND hosts.next_try<NOW()
	    AND DATE_SUB(NOW(), INTERVAL host_vars.value HOUR)>hosts.last_backup
    	    LIMIT 0,1;";
        $res = $db->query($sql);
        $row = $res->fetch_array();

        $datestart = date("Y-m-d H:i:s");

        if($res->num_rows > 0){
            $sql="UPDATE hosts set worker=$worker_id, backup_started=NOW(), status=1 where id=".$row['id']." AND worker=0;";
	    $db->query($sql);

            if(!$db->query($sql)) {
    	        echo "$datestart - Unable to lock host for backup!\n"; 
    	        exit;
    	    }
            else { 
    	        echo "$datestart - Preparing to backup host #".$row['id']."\n"; 
    	        $host_id = $row['id']; 
    	    }
        }
        else 
        {
//    	    echo "$datestart - No hosts for backup, skipping turn\n";
    	}

	// Got host, starting backup
        if ($host_id>0)
        {
            // Getting host vars
            $sql="select * from hosts where id=".$host_id.";";
            $res = $db->query($sql);
            $host_data = $res->fetch_array();

            $sql="select * from host_vars where host=".$host_id.";";
            $res = $db->query($sql);
            while ($row = $res->fetch_array()) {
                $host_vars[$row['var']] = $row['value'];
            }

            // Setting process title
    	    cli_set_process_title("phbackup-$worker_id [".$host_data['id']."]");

            // Check if directory exists
            if (!is_dir("$backup_path/".$host_data['name'])) system ("mkdir -p $backup_path/".$host_data['name']);

            $datestamp = date("Y-m-d_H:i:s");
            $bkpath = $backup_path."/".$host_data['name'];

            // Generating include/exclude files
            file_put_contents("$bkpath/exclude.txt", base64_decode($host_vars['exclude_paths']));
            file_put_contents("$bkpath/files.txt", base64_decode($host_vars['include_paths']));

            // Backup itself
            system("truncate -s 0 $bkpath/backup.log");
            //        $cmd = "/bin/rsync $rsync_opts --relative --log-file=$bkpath/backup.log --progress -e \"/usr/bin/ssh -oStrictHostKeyChecking=no -p ".$host_data['port']."\" --delete --timeout=600 --ignore-errors --exclude-from=$bkpath/exclude.txt --files-from=$bkpath/files.txt --link-dest=../111-Latest ".$host_data['user']."@".$host_data['ip'].":/ $bkpath/processing-$datestamp/ > /dev/null 2>&1";
            $cmd = "/bin/rsync $rsync_opts --relative --log-file=$bkpath/backup.log --progress -e \"/usr/bin/ssh -oConnectTimeout=10 -oPasswordAuthentication=no -oStrictHostKeyChecking=no -p ".$host_data['port']."\" --delete --timeout=600 --ignore-errors --exclude-from=$bkpath/exclude.txt --files-from=$bkpath/files.txt --link-dest=../111-Latest ".$host_data['user']."@".$host_data['ip'].":/ $bkpath/processing-$datestamp/";
            system($cmd, $return_code);

            $dateend = date("Y-m-d H:i:s");

            // Checking results
            if ($return_code>0 && $return_code!=23 && $return_code!=24) {
            	    system ("rm -rf $bkpath/processing-$datestamp");
            	    echo "$dateend - Something was wrong, backup failed!\n";
                    $sql="UPDATE hosts set worker=0, status=2, assigned_worker=0, next_try=DATE_ADD(NOW(), INTERVAL 1 HOUR) where id=$host_id";
                    $db->query($sql);
            }
            else {
                system("mv $bkpath/processing-$datestamp $bkpath/$datestamp && rm -f $bkpath/111-Latest && ln -s $bkpath/$datestamp $bkpath/111-Latest");
                $sql="UPDATE hosts set worker=0, last_backup='$datestart', assigned_worker=0, status=0 where id=$host_id";
                $db->query($sql);
    	        echo "$dateend - Host ".$host_data['name']." was successfully backed up!\n";
    	        echo "$dateend - Host ".$host_data['name']." - cleaning old backups\n";
		system("find $bkpath -maxdepth 1 -type d -mtime ".$host_vars['backup_keep_period']." -print -exec rm -rvf '{}' \\;");
    	        echo "$dateend - Host ".$host_data['name']." - cleaned old backups\n";
            }
        }

        $db->close();


        sleep($ticker_step);

    }

    // Dispatching tasks
    elseif ($worker_id==1)
    {
        cli_set_process_title("phbackup-master");
	$execstring='ps ax 2>&1';
        $output="";
	exec($execstring, $output);
//	print_r($output);
	$workers = array();
	foreach ($output as $str) {
	    preg_match('/^.*phbackup-(\d+)-free/', $str, $matches);
	    if(!empty($matches[1])) $workers[$matches[1]]['id'] = $matches[1];
	}
//	print_r($workers);

	if (count($workers)>0)
	{

    	    $db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);
            $dateend = date("Y-m-d H:i:s");

	    $sql="SELECT hosts.id, hosts.name, hosts.enabled, hosts.worker, hosts.last_backup, host_vars.value, assigned_worker 
    	        FROM `hosts` left JOIN host_vars on hosts.id=host_vars.host 
    		WHERE host_vars.var='backup_period' 
    	        AND hosts.enabled=1 
    	        AND hosts.worker=0 
    	        AND assigned_worker=0
    	        AND hosts.next_try<NOW()
	        AND DATE_SUB(NOW(), INTERVAL host_vars.value HOUR)>hosts.last_backup
    	        LIMIT 0,1;";
    	    $res = $db->query($sql);
//    	    echo "$sql";
    	    if($res->num_rows > 0){
    		while ($row = $res->fetch_array()) 
    		{
    		    $worker_assigned=false;;
    		    foreach($workers as $worker) {
    		        if( !isset($worker['busy']) && !$worker_assigned )
    		        {
    		    	    $sql="UPDATE hosts set assigned_worker=".$worker['id']." WHERE id=".$row['id']." AND assigned_worker=0";
	            	    if($db->query($sql)) {
    				echo "$dateend - assigned worker ".$worker['id']." to host ".$row['id']." (".$row['name'].")\n";
    			        $worker['busy'] = true;
    			        $worker_assigned=true;
			    }
    			}
    		    }
//    		    if($free_workers==0) {echo "$dateend - No free workers, skipping\n"; break;}
    		}
    	    }
//    	    else echo "No hosts for backup, sleeping\n";

    	    $db->close();
	}

	
	sleep($ticker_step);
    }

    // Catching wrong ID from systemd
    else
    {
	sleep($ticker_step);
    }

}

?>
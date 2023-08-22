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

$rsync_opts = "-vbrltz";
$ticker_step=5;

preg_match('/phb-worker-(\d+)/', getenv('SUPERVISOR_PROCESS_NAME'), $matches);
$worker_id = $matches[1];
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

        $host_id=0;
        $db->begin_transaction();

        $sql="SELECT hosts.id, hosts.enabled, hosts.worker, hosts.last_backup, host_vars.value, assigned_worker 
    	    FROM `hosts` left JOIN host_vars on hosts.id=host_vars.host 
    	    WHERE host_vars.var='backup_period' 
    	    AND hosts.enabled=1 
    	    AND hosts.worker=-1 
    	    AND hosts.next_try<NOW()
	    AND DATE_SUB(NOW(), INTERVAL host_vars.value HOUR)>hosts.last_backup
    	    LIMIT 0,1 FOR UPDATE";
        $res = $db->query($sql);
        $row = $res->fetch_array();

        $datestart = date("Y-m-d H:i:s");

        if( mysqli_num_rows($res)>0 && !$db->error ){
            $sql="UPDATE hosts set worker=$worker_id, backup_started=NOW(), status=1 where id=".$row['id']." AND worker=-1;";
	    $db->query($sql);
            echo "$datestart - [$worker_id] Preparing to backup host #".$row['id']."\n"; 
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
            $sql="select * from hosts where id=".$host_id.";";
            $res = $db->query($sql);
            $host_data = $res->fetch_array();

            $sql="select * from host_vars where host=".$host_id.";";
            $res = $db->query($sql);
            while ($row = $res->fetch_array()) {
                $host_vars[$row['var']] = $row['value'];
            }

            // Setting process title
    	    cli_set_process_title("phbackup-$worker_id [processing ".$host_data['id']."]");

            // Check if directory exists
            if (!is_dir("$backup_path/".$host_data['name'])) system ("mkdir -p $backup_path/".$host_data['name']);

            $datestamp = date("Y-m-d_H:i:s");
            $bkpath = $backup_path."/".$host_data['name'];

            // Generating include/exclude files
            file_put_contents("$bkpath/exclude.txt", base64_decode($host_vars['exclude_paths']));
            file_put_contents("$bkpath/files.txt", base64_decode($host_vars['include_paths']));

            // Backup itself
            system("truncate -s 0 $bkpath/backup.log");
            $cmd = "/bin/rsync $rsync_opts --relative --log-file=$bkpath/backup.log --progress -e \"/usr/bin/ssh -oConnectTimeout=10 -oPasswordAuthentication=no -oStrictHostKeyChecking=no -p ".$host_data['port']."\" --delete --timeout=600 --ignore-errors --exclude-from=$bkpath/exclude.txt --files-from=$bkpath/files.txt --link-dest=../111-Latest ".$host_data['user']."@".$host_data['ip'].":/ $bkpath/processing-$datestamp/ > /dev/null 2>&1";
            system($cmd, $return_code);

            $dateend = date("Y-m-d H:i:s");

            // Checking results
            if ($return_code>0 && $return_code!=23 && $return_code!=24) {
            	    system ("rm -rf $bkpath/processing-$datestamp");
            	    echo "$dateend - [$worker_id] Something was wrong, backup failed!\n";
                    $sql="UPDATE hosts set worker=-1, status=2, next_try=DATE_ADD(NOW(), INTERVAL 1 HOUR) where id=$host_id";
                    $db->query($sql);
    	    	    cli_set_process_title("phbackup-$worker_id [idle]");
                    $busy=0;
            }
            else {
                system("mv $bkpath/processing-$datestamp $bkpath/$datestamp && rm -f $bkpath/111-Latest && ln -s $bkpath/$datestamp $bkpath/111-Latest");
                $sql="UPDATE hosts set worker=-1, last_backup='$datestart', status=0 where id=$host_id";
                $db->query($sql);
    	        echo "$dateend - [$worker_id] Host ".$host_data['name']." - successfully backed up!\n";
    	        echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaning old backups\n";
    	        cli_set_process_title("phbackup-$worker_id [cleaning]");
		system("find $bkpath -maxdepth 1 -type d -mtime ".$host_vars['backup_keep_period']." -print -exec rm -rvf '{}' \\;");
    	        echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaned old backups\n";
    	        cli_set_process_title("phbackup-$worker_id [idle]");
    	        $busy=0;
            }
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
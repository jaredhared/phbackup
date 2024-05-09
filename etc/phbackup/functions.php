<?php 

function get_script_vars($db) {
    $script_vars = array();
    $sql="select * from host_vars where host=10000;";
    $res = $db->query($sql);
    while ($row = $res->fetch_array()) {
        $script_vars[$row['var']] = $row['value'];
    }

    return $script_vars;
}


function is_upgraded($db) {

    $script_vars = get_script_vars($db);
    isset($script_vars['version']) ? $script_ver = $script_vars['version'] : $script_ver = 1;
    isset($script_vars['version_text']) ? $script_ver_text = $script_vars['version_text'] : $script_ver_text = "pre-1.5.0";
    $max_ver=$script_ver;
    $max_ver_text=$script_ver_text;

    $upgrade_versions = array();

    $functions = get_defined_functions();
    foreach ($functions['user'] as $func)
    {
        preg_match ('/upgrade_(\d+)_(\d+)_(\d+)_(\d+)/', $func, $matches);
        if (!empty($matches)) {
            $upgrade_versions[$matches[1]] = $matches[2].".".$matches[3].".".$matches[4];
        }
    }

    ksort($upgrade_versions,SORT_NUMERIC);
    $upgrade_path = $script_ver_text;
    foreach ($upgrade_versions as $short => $version )
    {
        if ( $short > $max_ver) {
            $max_ver = $short;
            $max_ver_text = $version;
            if ($short > $script_ver) $upgrade_path .= " => $version";
        }
    }

    if ($script_ver == $max_ver) {
//        echo "Ok, max version is $max_ver_text\n";
        return true;
    }
    else {
//        echo "Max version $max_ver_text is higher then current $script_ver_text ($max_ver > $script_ver). \nUpgrade path is $upgrade_path\n";
        return $upgrade_versions;
    }


}



function run_backup ($db, $host_data, $host_vars) {

    global $worker_id, $datestart;

    $function=$host_vars['backup_function'];
    if(function_exists($function)) {
        echo "$datestart - [$worker_id] Host ".$host_data['name']." - executing $function\n";
        $function($db, $host_data, $host_vars);
    }


}



function backup_server_via_ssh ($db, $host_data, $host_vars) {

        global $backup_path, $worker_id, $cmd_rsync, $rsync_opts, $nextbackup, $datestart, $host_id;

        // Setting process title
        cli_set_process_title("phbackup-$worker_id [backing ".$host_data['name']."]");

        // Check if directory exists
        if (!is_dir("$backup_path/"."/".$host_vars['backup_dir']."/".$host_data['name'])) exec("mkdir -p $backup_path/"."/".$host_vars['backup_dir']."/".$host_data['name']);

        $datestamp = date("Y-m-d_H:i:s");
        $bkpath = $backup_path."/".$host_vars['backup_dir']."/".$host_data['name'];
        $backup_period=$host_vars['backup_period'];

        // Generating include/exclude files
        file_put_contents("$bkpath/exclude.txt", base64_decode($host_vars['exclude_paths']));
        file_put_contents("$bkpath/files.txt", base64_decode($host_vars['include_paths']));

        // Backup itself
        exec("truncate -s 0 $bkpath/backup.log; $cmd_rsync $rsync_opts --partial --relative --log-file=$bkpath/backup.log --progress -e \"nice -n 19 /usr/bin/ssh -ocompression=no -oServerAliveInterval=3 -oServerAliveCountMax=806400 -oStrictHostKeyChecking=no -oUserKnownHostsFile=/dev/null -p ".$host_data['port']."\" --delete --timeout=600 --ignore-errors --exclude-from=$bkpath/exclude.txt --files-from=$bkpath/files.txt --link-dest=../111-Latest ".$host_data['user']."@".$host_data['ip'].":/ $bkpath/processing-$datestamp/ >/dev/null 2>/dev/null");
//            $return_code = exec("tail -n 1 $bkpath/backup.log|grep code|sed 's/.*code\s//;s/).*//'");
        exec("tail -n 1 $bkpath/backup.log|grep code|sed 's/.*code\s//;s/).*//'", $output, $return_code);
        if(empty($return_code)) { $return_code = '0'; };

        $dateend = date("Y-m-d H:i:s");

        // Checking results
        if ($return_code>0 && $return_code!=23 && $return_code!=24) {
	    exec("rm -rf $bkpath/processing-$datestamp");
	    echo "$dateend - [$worker_id] Something was wrong, backup failed!\n";
            $sql="UPDATE hosts set worker=-1, status=2, next_try=DATE_ADD(NOW(), INTERVAL 1 HOUR), backup_now=0 where id=$host_id";
            $db->query($sql);
	    cli_set_process_title("phbackup-$worker_id [idle]");
            $busy=0;
        }
        else {
            exec("mv $bkpath/processing-$datestamp $bkpath/$datestamp && rm -f $bkpath/111-Latest && ln -s $bkpath/$datestamp $bkpath/111-Latest");
            echo "$dateend - [$worker_id] Host ".$host_data['name']." - successfully backed up!\n";
            echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaning old backups\n";
            cli_set_process_title("phbackup-$worker_id [cleaning - ".$host_data['name']."]");
            exec("find $bkpath -maxdepth 1 -type d -mtime +".$host_vars['backup_keep_period']." -exec rm -rf '{}' \\;");
            echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaned old backups\n";

            $next_try_str="DATE_ADD('$nextbackup', INTERVAL $backup_period HOUR)";
            if ($host_data['backup_now']==1) $next_try_str="NOW()";
            $sql="UPDATE hosts set worker=-1, last_backup='$datestart', status=0, next_try=$next_try_str, backup_now=0 where id=$host_id";
            $db->query($sql);

            cli_set_process_title("phbackup-$worker_id [idle]");
            $busy=0;
        }

}

?>
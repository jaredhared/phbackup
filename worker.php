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


$ticker_step=5;
// Delay between DB connection attempts, seconds
$db_retry_step=30;
// Locked host without running backup process is considered stale after this time, minutes
$stale_minutes=5;

if (!preg_match('/phb-worker-(\d+)/', (string)getenv('SUPERVISOR_PROCESS_NAME'), $matches)) {
    die("SUPERVISOR_PROCESS_NAME is not set or invalid, worker should be run by Supervisor\n");
}
$worker_id = (int)$matches[1];
cli_set_process_title("phbackup-$worker_id [idle]");

log_msg("Started worker #$worker_id");


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


function log_msg($msg) {
    global $worker_id;
    echo date("Y-m-d H:i:s")." - [$worker_id] $msg\n";
}


// Connects to DB. If DB is not available, waits and retries instead of crashing,
// so a DB restart does not leave workers in FATAL state in Supervisor
function db_connect_retry() {
    global $db_host, $db_user, $db_pass, $db_name, $db_retry_step;

    $reported = false;
    while (true) {
        try {
            $db = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
            if ($reported) log_msg("DB connection restored");
            return $db;
        }
        catch (mysqli_sql_exception $e) {
            if (!$reported) log_msg("DB connection failed: ".$e->getMessage().", retrying every {$db_retry_step}s");
            $reported = true;
            sleep($db_retry_step);
        }
    }
}


// Loads host data and vars from DB. Returns array(host_data, host_vars), host_data is null if host is missing
function load_host($db, $id) {
    $res = db_query($db, "SELECT hosts.*, host_groups.path FROM hosts LEFT JOIN host_groups ON hosts.group_id=host_groups.id WHERE hosts.id=?", array($id));
    $host_data = $res->fetch_array();

    $host_vars = array();
    $res = db_query($db, "SELECT var, value FROM host_vars WHERE host=?", array($id));
    while ($row = $res->fetch_array()) {
        $host_vars[$row['var']] = $row['value'];
    }

    return array($host_data, $host_vars);
}


// Releases hosts which were locked by previous instance of this worker (crash, restart, kill)
function release_own_hosts($db) {
    global $worker_id;

    $res = db_query($db, "SELECT id, name FROM hosts WHERE worker=?", array($worker_id));
    while ($row = $res->fetch_array()) {
        log_msg("Host ".$row['name']." - was locked by previous run of this worker, unlocking");
        mark_backup_failed($db, $row['id']);
    }

    // Pre-script installations interrupted by crash will be restarted
    $db->query("UPDATE hosts SET pre_install=1 WHERE pre_install=3");
}


// Unlocks hosts which are locked by a worker, but no worker process is backing them up
function release_stale_hosts($db) {
    global $stale_minutes;

    $res = db_query($db, "SELECT id, name, worker FROM hosts WHERE worker>=0 AND backup_started < NOW() - INTERVAL ? MINUTE", array($stale_minutes));
    if ($res->num_rows == 0) return;

    $output = array();
    exec("ps -eo args", $output, $return_code);
    if ($return_code>0) {
        log_msg("Failed: ps -eo args");
        return;
    }

    while ($row = $res->fetch_array()) {
        // Looking for a worker which is backing up or cleaning this host
        $name_re = '/^phbackup-\d+ \[(backing|cleaning -) '.preg_quote($row['name'], '/').'\]/';
        $running = 0;
        foreach ($output as $line) {
            if (preg_match($name_re, $line)) $running++;
        }
        if ($running == 0) {
            log_msg("Host ".$row['name']." - Host is stale, unlocking");
            // worker=? protects from unlocking a host which was locked again in the meantime
            db_query($db, "UPDATE hosts set worker=-1, status=2, last_result=2, next_try=DATE_ADD(NOW(), INTERVAL 1 HOUR), backup_now=0 where id=? AND worker=?", array($row['id'], $row['worker']));
        }
    }
}


// Installs pre-backup script and cron file to one host.
// Host is claimed (pre_install=3) in a short transaction, SSH is done without holding DB locks.
function install_pre_script($db) {
    global $datestart;

    $db->begin_transaction();
    $res = $db->query("SELECT id FROM hosts WHERE pre_install=1 ORDER BY id LIMIT 1 FOR UPDATE");
    $row = $res->fetch_array();
    if (!$row) {
        $db->rollback();
        return;
    }
    $id = (int)$row['id'];
    $db->query("UPDATE hosts SET pre_install=3 WHERE id=$id");
    $db->commit();

    $datestart = date("Y-m-d_H:i:s");
    list($host_data, $host_vars) = load_host($db, $id);
    log_msg("Host ".$host_data['name']." - Starting cron script installation");

    $updateok=0;
    $host_error = check_host_data($host_data);
    if ($host_error == "" && (!isset($host_vars['pre_script']) || !isset($host_vars['pre_schedule']))) $host_error = "pre-backup script or schedule is not set";
    if ($host_error != "") {
        log_msg("Host ".$host_data['name']." - $host_error");
        $updateok = 1;
    }
    else {
        $ssh_opts = ssh_options($host_data['port'], "-p");
        $scp_opts = ssh_options($host_data['port'], "-P");
        $target = escapeshellarg($host_data['user']."@".$host_data['ip']);

        $bkpath = host_backup_path($host_data);
        if (!is_dir($bkpath)) mkdir($bkpath, 0750, true);
        file_put_contents("$bkpath/phbackup.sh", str_replace("\r", "", base64_decode($host_vars['pre_script'])));
        file_put_contents("$bkpath/phbackup", str_replace("\r", "", "# Updated at ".$datestart."\n".base64_decode($host_vars['pre_schedule'])."\n"));
        chmod("$bkpath/phbackup.sh", 0750);

        $cmds = array(
            "ssh $ssh_opts $target \"mkdir -p /opt > /dev/null\"",
            "scp $scp_opts ".escapeshellarg("$bkpath/phbackup.sh")." ".escapeshellarg($host_data['user']."@".$host_data['ip'].":/opt/")." > /dev/null",
            "ssh $ssh_opts $target \"rm -f /etc/cron.d/phbackup.cron\"",
            "scp $scp_opts ".escapeshellarg("$bkpath/phbackup")." ".escapeshellarg($host_data['user']."@".$host_data['ip'].":/etc/cron.d/")." > /dev/null",
        );
        foreach ($cmds as $cmd) {
            $output = array();
            exec($cmd, $output, $return_code);
            if ($return_code>0) {
                echo "Failed: $cmd\n";
                $updateok += $return_code;
                break;
            }
        }
    }

    if($updateok==0) {
        $db->query("UPDATE hosts set pre_install=0 where id=$id");
        log_msg("Host ".$host_data['name']." - updated pre-backup script");
    }
    else {
        $db->query("UPDATE hosts set pre_install=2 where id=$id");
        log_msg("Host ".$host_data['name']." - failed to update pre-backup script");
    }
}


// Checks if current hour is inside one of time slots like "0-2,4-7"
function in_time_slots($time_slots) {
    $curhour = (int)date("G");
    foreach (explode(",", (string)$time_slots) as $time) {
        $hours = explode("-", $time);
        if (count($hours) == 2 && $curhour >= (int)$hours[0] && $curhour < (int)$hours[1]) return true;
    }
    return false;
}


// Picks a host which needs backup, locks it and runs backup.
// Candidates are filtered by time slots first, so hosts outside their slots do not delay others.
// Host is locked by conditional UPDATE, no table-wide FOR UPDATE locks are needed.
function backup_next_host($db) {
    global $worker_id, $host_id, $datestart, $nextbackup;

    $sql="SELECT hosts.id, hosts.name, hosts.time_slots, hosts.backup_now
        FROM `hosts` 
        LEFT JOIN host_vars on hosts.id=host_vars.host 
        WHERE host_vars.var='backup_period' 
        AND hosts.enabled=1 
        AND hosts.worker=-1 
        AND ( hosts.next_try<NOW() OR hosts.backup_now=1 )";
    $res = $db->query($sql);

    $candidates = array();
    while ($row = $res->fetch_array()) {
        // "Backup now" ignores time slots
        if ($row['backup_now']==1 || in_time_slots($row['time_slots'])) $candidates[] = $row;
    }
    shuffle($candidates);

    $datestart = date("Y-m-d H:i:s");
    $nextbackup = date("Y-m-d H:i:00");

    foreach ($candidates as $row) {
        $locked = db_query($db, "UPDATE hosts set worker=?, backup_started=NOW(), status=1 where id=? AND worker=-1 AND enabled=1", array($worker_id, $row['id']));
        if ($locked != 1) continue; // Another worker was faster

        if ($row['backup_now']==1) log_msg("Host ".$row['name']." - found Backup Now flag");
        log_msg("Host ".$row['name']." - starting backup");

        // From now on the host is locked by us. If something throws, main loop will release it.
        $host_id = (int)$row['id'];
        list($host_data, $host_vars) = load_host($db, $host_id);
        run_backup($db, $host_data, $host_vars);
        $host_id = 0;
        return;
    }
}



// Workers do nothing until DB is upgraded, since DB structure may be outdated
function db_is_upgraded($db) {
    static $reported = false;
    if (is_upgraded($db) === true) {
        if ($reported) log_msg("DB is upgraded, resuming work");
        $reported = false;
        return true;
    }
    if (!$reported) log_msg("DB upgrade is needed, please run upgrade.php. Waiting...");
    $reported = true;
    return false;
}


$host_id = 0;
$failed_host_id = 0;
$own_hosts_released = false;

// Entering main cycle
while(true)
{
    try {
        $db = db_connect_retry();

        if (!db_is_upgraded($db)) {
            $db->close();
            sleep($db_retry_step);
            continue;
        }

        // Releasing hosts left locked by previous run of this worker
        if (!$own_hosts_released) {
            release_own_hosts($db);
            $own_hosts_released = true;
        }

        // Host which was being backed up when an error happened
        if ($failed_host_id > 0) {
            log_msg("Host id $failed_host_id - backup was interrupted by error, unlocking");
            mark_backup_failed($db, $failed_host_id);
            $failed_host_id = 0;
        }

        release_stale_hosts($db);
        install_pre_script($db);
        backup_next_host($db);

        $db->close();
    }
    catch (Throwable $e) {
        log_msg("Error: ".$e->getMessage()." at ".basename($e->getFile()).":".$e->getLine());
        if ($host_id > 0) $failed_host_id = $host_id;
        $host_id = 0;
        cli_set_process_title("phbackup-$worker_id [idle]");
        try { if (isset($db) && $db instanceof mysqli) $db->close(); } catch (Throwable $e2) {}
    }

    sleep($ticker_step);
}

?>

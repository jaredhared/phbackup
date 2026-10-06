<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz

// Settings
include("/etc/phbackup/opt.php");
require "/etc/phbackup/functions.php";

// Extra hours added to backup period before backup is considered overdue
isset($zabbix_age_slack) ? $age_slack = (int)$zabbix_age_slack : $age_slack = 2;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=db_connect();

if (PHP_SAPI != "cli") header("Content-Type: application/json; charset=utf-8");

$sql="select hosts.*, host_vars.value as period,
      TIMESTAMPDIFF(HOUR, hosts.last_backup, NOW()) as age,
      TIMESTAMPDIFF(HOUR, hosts.backup_started, NOW()) as running,
      TIMESTAMPDIFF(HOUR, hosts.last_backup, hosts.backup_started) as start_delay
      from hosts
      left JOIN host_vars on hosts.id=host_vars.host
      WHERE ( host_vars.var='backup_period' );";
$res = $db->query($sql);

$data = array();
$hosts = array();
while ($row = $res->fetch_array()) {
    $period = (int)$row['period'];
    $age = ($row['age'] === null) ? -1 : (int)$row['age'];

    // Backup is overdue if the last successful backup is older than period.
    // A running backup suppresses this only if it was started on time and is not running longer than period,
    // so a retry of a long failing host does not hide the problem.
    $overdue = 0;
    if ($row['enabled']==1 && $age > $period + $age_slack) {
        $on_time_run = $row['worker'] >= 0
            && $row['start_delay'] !== null && (int)$row['start_delay'] <= $period + $age_slack
            && $row['running'] !== null && (int)$row['running'] < $period;
        if (!$on_time_run) $overdue = 1;
    }

    // Disabled hosts are not discovered, so Zabbix does not alert for them
    if ($row['enabled']==1) $data[] = array("{#HOST}" => $row['name']);
    $hosts[$row['name']] = array(
        // Current state: -1 unknown, 0 ok, 1 backing up, 2 error
        "backup_status" => (int)$row['status'],
        // Result of the last finished backup: -1 none yet, 0 ok, 2 error. Not reset when a new backup starts
        "backup_last_result" => (int)$row['last_result'],
        "backup_period" => $period,
        // Hours since last successful backup, -1 if there was none
        "backup_age" => $age,
        "backup_overdue" => $overdue,
    );
}

echo json_encode(array("data" => $data) + $hosts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

?>

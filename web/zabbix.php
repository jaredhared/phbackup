<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz

// Settings
include("/etc/phbackup/opt.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);

if (PHP_SAPI != "cli") header("Content-Type: application/json; charset=utf-8");

$sql="select hosts.*, TIMESTAMPDIFF(HOUR, hosts.last_backup, NOW()) as age, host_vars.value as period from hosts
      left JOIN host_vars on hosts.id=host_vars.host
      WHERE ( host_vars.var='backup_period' );";
$res = $db->query($sql);

$data = array();
$hosts = array();
while ($row = $res->fetch_array()) {
    $data[] = array("{#HOST}" => $row['name']);
    $hosts[$row['name']] = array(
        "backup_status" => (int)$row['status'],
        "backup_period" => (int)$row['period'],
        "backup_age" => (int)$row['age'],
    );
}

echo json_encode(array("data" => $data) + $hosts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

?>

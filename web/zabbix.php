<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz

// Settings
include("/etc/phbackup/opt.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);


$sql="select hosts.*, TIMESTAMPDIFF(HOUR, hosts.last_backup, NOW()) as age, host_vars.value as period from hosts 
      left JOIN host_vars on hosts.id=host_vars.host
      WHERE ( host_vars.var='backup_period' );";
$res = $db->query($sql);
$num = $res->num_rows;
$i=0;
$json="{";
$data_str='"data": [';
$hosts_str="";

if ($num > 0) {

    while ($row = $res->fetch_array()) {
	$i++;
	$data_str .= '{"{#HOST}": "'.$row['name'].'"}';
	($i < $num) ? $data_str .= ",\n" : $data_str .= "\n],\n";



	$hosts_str .= '"'.$row['name'].'": { 
	"backup_status": '.$row['status'].',
	"backup_period": '.$row['period'].',
	"backup_age": '.($row['age']+0).'
	}';
	($i < $num) ? $hosts_str .= ",\n" : $hosts_str .= "\n";
    }
}

$json .= $data_str . $hosts_str . "}";
//echo nl2br($json);
echo $json;

?>

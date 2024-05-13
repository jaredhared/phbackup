#!/usr/bin/env php
<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz

// Functions
try {
    include("/etc/phbackup/opt.php");
    require '/etc/phbackup/functions.php';
}
catch (Error $e) {
    // debugging example:
    die('Caught error => ' . $e->getMessage());
}




function run_upgrade($db, $upgrade_versions) {

    $script_vars = get_script_vars($db);
    isset($script_vars['version']) ? $script_ver = $script_vars['version'] : $script_ver = 1;
    isset($script_vars['version_text']) ? $script_ver_text = $script_vars['version_text'] : $script_ver_text = "pre-1.5.0";
    $max_ver=$script_ver;
    $max_ver_text=$script_ver_text;

    ksort($upgrade_versions,SORT_NUMERIC);

    $ok=1;
    foreach ($upgrade_versions as $short => $version )
    {
        if ( $short > $script_ver && $ok>0 ) {

            $max_ver = $short;
            $max_ver_text = $version;
            $function = "upgrade_".$short."_".str_replace(".","_",$version);
            if(function_exists($function)) {
              $function($db);
            }
        }
    }

}





function upgrade_150_1_5_0($db) {
    $sql="select * from host_vars where host=10000;";
    $res = $db->query($sql);
    while ($row = $res->fetch_array()) {
        $script_vars[$row['var']] = $row['value'];
    }

    echo "Upgrading to 1.5.0... ";

    $ok=0;
    if (!isset($script_vars['version'])) {$sql="INSERT INTO host_vars (host,var,value) VALUES (10000, 'version', 150) "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }
    if (!isset($script_vars['version_text'])) {$sql="INSERT INTO host_vars (host,var,value) VALUES (10000, 'version_text', '1.5.0') "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }

    if ($ok==2) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_151_1_5_1($db) {

    echo "Upgrading to 1.5.1... ";

    $sql="select * from hosts where id<>10000;";
    $res = $db->query($sql);
    $ok=0;
    while ($row = $res->fetch_array()) {
        $sql="INSERT INTO host_vars (host,var,value) VALUES (".$row['id'].", 'backup_dir', '') "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=151 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.5.1' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }

    if ($ok==3) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_152_1_5_2($db) {
    echo "Upgrading to 1.5.2... ";

    $sql="select * from hosts where id<>10000;";
    $res = $db->query($sql);
    $ok=0;
    while ($row = $res->fetch_array()) {
        $sql="INSERT INTO host_vars (host,var,value) VALUES (".$row['id'].", 'backup_function', 'backup_server_via_ssh') "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=152 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.5.2' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }

    if ($ok==3) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_160_1_6_0($db) {

    echo "Upgrading to 1.6.0... ";

    $ok=0;
    $sql="DELETE FROM host_vars WHERE var='backup_dir'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    $sql="CREATE TABLE `host_groups` (`id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `path` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    $sql="ALTER TABLE `hosts` ADD `group_id` INT NOT NULL DEFAULT '1' AFTER `description`;"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    $sql="INSERT INTO `host_groups` (`id`, `name`, `path`) VALUES (NULL, 'Servers', '');"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    $sql="INSERT INTO `host_groups` (`id`, `name`, `path`) VALUES (NULL, 'Switches', '');"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=160 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.0' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }

    if ($ok==7) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }

};


function upgrade_161_1_6_1($db) {
    echo "Upgrading to 1.6.1... ";

    $sql="select * from hosts where id<>10000;";
    $res = $db->query($sql);
    $ok=0;
    while ($row = $res->fetch_array()) {
        if ($row['group_id']==1) $sql="INSERT INTO host_vars (host,var,value) VALUES (".$row['id'].", 'backup_function', 'backup_server_via_ssh') "; 
        if ($row['group_id']==2) $sql="INSERT INTO host_vars (host,var,value) VALUES (".$row['id'].", 'backup_function', 'backup_cisco_switch_via_telnet') "; 
        $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=161 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.1' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }

    if ($ok==3) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_162_1_6_2($db) {
    echo "Upgrading to 1.6.2... ";

    $sql="select * from hosts where group_id=1;";
    $res = $db->query($sql);
    $ok=0;
    while ($row = $res->fetch_array()) {
        if ($row['group_id']==1) $sql="update host_vars set value='IyEvYmluL2Jhc2gNCg0KbWFyaWFiYWNrdXA9YHdoaWNoIG1hcmlhYmFja3VwYA0KbXlzcWxkdW1wPWB3aGljaCBteXNxbGR1bXBgDQoNCk1BUklBQkFDS1VQPTENCk1ZU1FMRFVNUD0wDQpCQUNLVVBQQVRIPSIvdmFyL2RiLWJhY2t1cCINCg0Kcm0gLXJmICRCQUNLVVBQQVRIDQpta2RpciAkQkFDS1VQUEFUSA0KDQppZiBbICRNQVJJQUJBQ0tVUCAtZXEgMSBdOyB0aGVuDQogICAgZWNobyAtbiAibWFyaWFiYWNrdXAgc3RhcnRpbmcuLi4iDQogICAgJG1hcmlhYmFja3VwIC0tYmFja3VwIC0tdGFyZ2V0LWRpcj0kQkFDS1VQUEFUSC9tYXJpYWJhY2t1cCAtLXVzZXI9cm9vdA0KICAgIGVjaG8gLW4gIm1hcmlhYmFja3VwIHByZXBhcmUgc3RhcnRpbmcuLi4iDQogICAgJG1hcmlhYmFja3VwIC0tcHJlcGFyZSAtLXRhcmdldC1kaXI9JEJBQ0tVUFBBVEgvbWFyaWFiYWNrdXANCiAgICBlY2hvICJtYXJpYWJhY2t1cCBEb25lISINCmZpDQoNCmlmIFsgJE1ZU1FMRFVNUCAtZXEgMSBdOyB0aGVuDQogICAgd2hpbGUgcmVhZCBkYg0KICAgIGRvDQogICAgZWNobyAtbiAiRHVtcGluZyAkZGIuLi4iDQogICAgJG15c3FsZHVtcCAtLXRyaWdnZXJzIC0tcm91dGluZXMgLS1ldmVudHMgJGRiIHwgZ3ppcCA+ICRCQUNLVVBQQVRIL215c3FsZHVtcC8kZGIuc3FsLmd6DQogICAgZWNobyAiRG9uZSEiDQogICAgZG9uZSA8IDwobXlzcWwgLWUgIlNIT1cgREFUQUJBU0VTOyIgfCBzZWQgIjFkIiB8IGdyZXAgLXYgImluZm9ybWF0aW9uX3NjaGVtYVx8cGVyZm9ybWFuY2Vfc2NoZW1hXHxteXNxbCIpDQpmaQ0KDQpleGl0IDA=' where var='pre_script' and host=".$row['id']; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    $sql="update hosts set pre_install=1 where group_id=1;"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error);
    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=162 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.2' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }

    if ($ok==4) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};












mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);

$script_vars = get_script_vars($db);
isset($script_vars['version']) ? $script_ver = $script_vars['version'] : $script_ver = 1;
isset($script_vars['version_text']) ? $script_ver_text = $script_vars['version_text'] : $script_ver_text = "pre-1.5.0";
$upgrade_path = $script_ver_text;

$upgrade_versions = is_upgraded($db);

if (!is_array($upgrade_versions)) {
    echo "No update is needed. Congratulations!"; 
    $db->close();

    die();
}



foreach ($upgrade_versions as $short => $version ) { if ($short > $script_ver) $upgrade_path .= " => $version"; }
echo "An upgrade is needed: $upgrade_path\n Would you like to update script now? [Y/N]: ";

if (rtrim( fgets( STDIN ), "\n" ) == "Y") run_upgrade($db, $upgrade_versions);
else echo "Exiting without upgrade\n";


$db->close();






?>

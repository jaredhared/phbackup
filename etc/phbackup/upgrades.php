<?php

// PHBackup DB upgrade functions.
// Function name format: upgrade_<short version>_<major>_<minor>_<patch>, each returns true on success.
// Included from functions.php, so web interface and workers can detect that an upgrade is needed.


// What has to be updated by hand for each version, shown by upgrade.php before upgrading.
// Paths are relative to the git repository; where to copy them:
//   worker.php, upgrade.php          - stay in the repository (/root/scripts/phbackup), updated by git pull
//   etc/phbackup/*                   - /etc/phbackup/
//   web/*                            - your web directory
//   etc/supervisor/conf.d/*          - /etc/supervisor/conf.d/
//   zabbix/phbackup.conf             - /etc/zabbix/zabbix_agent2.d/
// "files" - files to copy, "steps" - other manual actions.
function upgrade_notes() {
    $all = array("worker.php", "upgrade.php", "etc/phbackup/functions.php", "web/index.php", "web/style.css");
    return array(
        150 => array('files' => $all),
        151 => array('files' => $all),
        152 => array('files' => $all),
        160 => array('files' => $all),
        161 => array('files' => $all),
        162 => array(
            'files' => array("worker.php", "upgrade.php", "etc/phbackup/functions.php"),
            'steps' => array(
                "Copy etc/phbackup/functions.custom.php to /etc/phbackup/ only if it does not exist there (it holds your own backup functions)",
                "Pre-backup scripts of all servers (group 1) are replaced with the default one and reinstalled",
            ),
        ),
        163 => array('files' => array("worker.php", "upgrade.php")),
        164 => array('files' => array("worker.php", "upgrade.php", "etc/phbackup/functions.php")),
        165 => array(
            'files' => array("worker.php", "upgrade.php", "etc/phbackup/functions.php", "etc/phbackup/upgrades.php",
                             "web/index.php", "web/zabbix.php", "web/style.css",
                             "etc/supervisor/conf.d/phbackup.conf", "zabbix/phbackup.conf"),
            'steps' => array(
                "etc/phbackup/upgrades.php is a new file: copy it BEFORE running upgrade.php, otherwise neither upgrade.php nor web interface start",
                "etc/phbackup/opt.php: do not overwrite, add the new option \$backup_min_keep = 3; (optional, 3 is the default)",
                "Supervisor config: keep your numprocs value, then run: supervisorctl update",
                "Zabbix: change the crontab line as described in README (write via temporary file) and re-import zabbix/PHBackup.yaml with \"Delete missing\" checked",
                "Default rsync options are replaced for hosts with old defaults, next backup of these hosts may take more space",
            ),
        ),
    );
}


function upgrade_150_1_5_0($db) {
    $sql="select * from host_vars where host=10000;";
    $res = $db->query($sql);
    while ($row = $res->fetch_array()) {
        $script_vars[$row['var']] = $row['value'];
    }

    echo "Upgrading to 1.5.0... ";

    $ok=0;
    if (!isset($script_vars['version'])) {$sql="INSERT INTO host_vars (host,var,value) VALUES (10000, 'version', 150) "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="INSERT INTO host_vars (host,var,value) VALUES (10000, 'version_text', '1.5.0') "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==2) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_151_1_5_1($db) {

    echo "Upgrading to 1.5.1... ";

    $sql="select * from hosts where id<>10000;";
    $res = $db->query($sql);
    $ok=0;
    while ($row = $res->fetch_array()) {
        $sql="INSERT INTO host_vars (host,var,value) VALUES (".$row['id'].", 'backup_dir', '') "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=151 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.5.1' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==3) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_152_1_5_2($db) {
    echo "Upgrading to 1.5.2... ";

    $sql="select * from hosts where id<>10000;";
    $res = $db->query($sql);
    $ok=0;
    while ($row = $res->fetch_array()) {
        $sql="INSERT INTO host_vars (host,var,value) VALUES (".$row['id'].", 'backup_function', 'backup_server_via_ssh') "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=152 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.5.2' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==3) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_160_1_6_0($db) {

    echo "Upgrading to 1.6.0... ";

    $ok=0;
    $sql="DELETE FROM host_vars WHERE var='backup_function'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    $sql="CREATE TABLE `host_groups` (`id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `path` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    $sql="ALTER TABLE `hosts` ADD `group_id` INT NOT NULL DEFAULT '1' AFTER `description`;"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    $sql="INSERT INTO `host_groups` (`id`, `name`, `path`) VALUES (NULL, 'Servers', '');"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    $sql="INSERT INTO `host_groups` (`id`, `name`, `path`) VALUES (NULL, 'Switches', '');"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=160 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.0' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

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
        $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=161 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.1' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==3) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_162_1_6_2($db) {
    echo "Upgrading to 1.6.2... ";

    $sql="select * from hosts where group_id=1;";
    $res = $db->query($sql);
    $ok=0;
    while ($row = $res->fetch_array()) {
        if ($row['group_id']==1) $sql="update host_vars set value='IyEvYmluL2Jhc2gNCg0KbWFyaWFiYWNrdXA9YHdoaWNoIG1hcmlhYmFja3VwYA0KbXlzcWxkdW1wPWB3aGljaCBteXNxbGR1bXBgDQoNCk1BUklBQkFDS1VQPTENCk1ZU1FMRFVNUD0wDQpCQUNLVVBQQVRIPSIvdmFyL2RiLWJhY2t1cCINCg0Kcm0gLXJmICRCQUNLVVBQQVRIDQpta2RpciAkQkFDS1VQUEFUSA0KDQppZiBbICRNQVJJQUJBQ0tVUCAtZXEgMSBdOyB0aGVuDQogICAgZWNobyAtbiAibWFyaWFiYWNrdXAgc3RhcnRpbmcuLi4iDQogICAgJG1hcmlhYmFja3VwIC0tYmFja3VwIC0tdGFyZ2V0LWRpcj0kQkFDS1VQUEFUSC9tYXJpYWJhY2t1cCAtLXVzZXI9cm9vdA0KICAgIGVjaG8gLW4gIm1hcmlhYmFja3VwIHByZXBhcmUgc3RhcnRpbmcuLi4iDQogICAgJG1hcmlhYmFja3VwIC0tcHJlcGFyZSAtLXRhcmdldC1kaXI9JEJBQ0tVUFBBVEgvbWFyaWFiYWNrdXANCiAgICBlY2hvICJtYXJpYWJhY2t1cCBEb25lISINCmZpDQoNCmlmIFsgJE1ZU1FMRFVNUCAtZXEgMSBdOyB0aGVuDQogICAgd2hpbGUgcmVhZCBkYg0KICAgIGRvDQogICAgZWNobyAtbiAiRHVtcGluZyAkZGIuLi4iDQogICAgJG15c3FsZHVtcCAtLXRyaWdnZXJzIC0tcm91dGluZXMgLS1ldmVudHMgJGRiIHwgZ3ppcCA+ICRCQUNLVVBQQVRIL215c3FsZHVtcC8kZGIuc3FsLmd6DQogICAgZWNobyAiRG9uZSEiDQogICAgZG9uZSA8IDwobXlzcWwgLWUgIlNIT1cgREFUQUJBU0VTOyIgfCBzZWQgIjFkIiB8IGdyZXAgLXYgImluZm9ybWF0aW9uX3NjaGVtYVx8cGVyZm9ybWFuY2Vfc2NoZW1hXHxteXNxbCIpDQpmaQ0KDQpleGl0IDA=' where var='pre_script' and host=".$row['id']; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    }

    $ok == $res->num_rows ? $ok=1 : $ok=0;

    $sql="update hosts set pre_install=1 where group_id=1;"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=162 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.2' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==4) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_163_1_6_3($db) {
    echo "Upgrading to 1.6.3... ";

    $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=163 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.3' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==2) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_164_1_6_4($db) {
    echo "Upgrading to 1.6.4... ";

    $ok=0;

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=164 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.4' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==2) { echo "Done!\n"; return true; }
    else { echo "Error!\n"; return false; }
};


function upgrade_165_1_6_5($db) {
    echo "Upgrading to 1.6.5... ";

    $ok=0;

    // Result of the last finished backup, is not reset when a new backup starts (used by Zabbix)
    // Safe to run again if previous upgrade attempt failed in the middle
    if ($db->query("SHOW COLUMNS FROM `hosts` LIKE 'last_result'")->num_rows == 0) {
        $sql="ALTER TABLE `hosts` ADD `last_result` INT NOT NULL DEFAULT -1 AFTER `status`"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
        $sql="UPDATE hosts SET last_result=status WHERE status IN (0,2)"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    }
    else $ok+=2;

    // Old default rsync options did not preserve permissions, owners, hard links, ACLs and xattrs.
    // Only untouched defaults are replaced, custom options are left as is.
    $sql="UPDATE host_vars SET value='".DEFAULT_RSYNC_OPTIONS."' WHERE var='rsync_options' AND value IN ('-vbrltz','-vbrlt')"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error);
    echo "\n  rsync options updated for ".$db->affected_rows." host(s). Next backup of these hosts may copy much of the data again instead of hard-linking it (owners and permissions are now compared), check free disk space!\n  ";

    if (!isset($script_vars['version'])) {$sql="UPDATE host_vars SET value=165 WHERE host=10000 AND var='version'"; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }
    if (!isset($script_vars['version_text'])) {$sql="UPDATE host_vars SET value='1.6.5' WHERE host=10000 AND var='version_text' "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $db->error); }

    if ($ok==5) { echo "Done!\n"; return true; }
    else { echo "Error!\n"; return false; }
};

?>

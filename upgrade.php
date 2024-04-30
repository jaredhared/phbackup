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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);

$upgrade_versions = is_upgraded($db);
if (!is_array($upgrade_versions)) {echo "No update is needed. Congratulations!"; die();};


run_upgrade($db, $upgrade_versions);



$db->close();

?>

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

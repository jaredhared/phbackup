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
              if ($function($db) === false) {
                echo "Upgrade to $version failed, stopping. Please fix the problem and run upgrade again.\n";
                $ok = 0;
              }
            }
        }
    }

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

if (strtoupper(trim( fgets( STDIN ) )) == "Y") run_upgrade($db, $upgrade_versions);
else echo "Exiting without upgrade\n";


$db->close();






?>

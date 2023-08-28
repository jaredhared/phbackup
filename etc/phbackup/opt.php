<?php

// Script version
$script_ver="1.1.0";

// Database host / default: localhost
$db_host = 'localhost';

// Database name
$db_name = 'phback';

// Database username
$db_user = 'phback_user';

// Database password
$db_pass = 'Cogb4FEmcKy4pnKP2jBOm';

// Backup path
$backup_path = '/var/www/phbackup';

// Paths to include by default
$default_include_paths="/etc
/home
/root
/var/www
/var/db-backup";

// Paths to exclude by default
$default_exclude_paths="/var/log
/var/www/logs";

// Default pre-backup schedule
$default_pre_schedule="15 0 * * * root /opt/phbackup.sh > /var/log/phbackup_pre.log 2>&1";

// Default pre-backup script
$default_pre_script="#!/bin/sh

# This is a phbackup pre-backup script

";



?>
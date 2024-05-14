<?php

// Database host / default: localhost
$db_host = 'localhost';

// Database name
$db_name = 'phbackup';

// Database username
$db_user = 'phbackupuser';

// Database password
$db_pass = 'somepassword';

// Path to Rsync
$cmd_rsync = '/usr/bin/rsync';

// Backup path to store everything
$backup_path = '/var/www/phbackup';

// Paths to include by default
$default_include_paths="/etc
/home
/root
/var/www
/var/spool/cron
/var/db-backup";

// Paths to exclude by default
$default_exclude_paths="/var/log
/var/www/logs";

// Default pre-backup schedule
$default_pre_schedule="15 0 * * * root /opt/phbackup.sh > /var/log/phbackup_pre.log 2>&1";

// Default pre-backup script
$default_pre_script = '
#!/bin/bash

mariabackup=`which mariabackup`
mysqldump=`which mysqldump`

MARIABACKUP=1
MYSQLDUMP=0
BACKUPPATH="/var/db-backup"

rm -rf $BACKUPPATH
mkdir $BACKUPPATH

if [ $MARIABACKUP -eq 1 ]; then
    echo -n "mariabackup starting..."
    $mariabackup --backup --target-dir=$BACKUPPATH/mariabackup --user=root
    echo -n "mariabackup prepare starting..."
    $mariabackup --prepare --target-dir=$BACKUPPATH/mariabackup
    echo "mariabackup Done!"
fi

if [ $MYSQLDUMP -eq 1 ]; then
    mkdir $BACKUPPATH/mysqldump
    while read db
    do
    echo -n "Dumping $db..."
    $mysqldump --triggers --routines --events $db | gzip > $BACKUPPATH/mysqldump/$db.sql.gz
    echo "Done!"
    done < <(mysql -e "SHOW DATABASES;" | sed "1d" | grep -v "information_schema\|performance_schema\|mysql")
fi

exit 0
'



?>

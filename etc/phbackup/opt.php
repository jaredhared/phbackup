<?php

// Script version
$script_ver="1.4.2";

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
$default_pre_script = '
#!/bin/bash

MARIABACKUP=1
MYSQLDUMP=0
BACKUPPATH="/var/db-backup"

rm -rf $BACKUPPATH
mkdir $BACKUPPATH

if [ $MARIABACKUP -eq 1 ]; then
    echo -n "mariabackup starting..."
    /usr/bin/mariabackup --backup --target-dir=$BACKUPPATH/mariabackup --user=root
    echo -n "mariabackup prepare starting..."
    /usr/bin/mariabackup --prepare --target-dir=$BACKUPPATH/mariabackup
    echo "mariabackup Done!"
fi

if [ $MYSQLDUMP -eq 1 ]; then
    while read db
    do
	echo -n "Dumping $db..."
	mysqldump --triggers --routines --events $db | gzip > $BACKUPPATH/mysqldump/$db.sql.gz
	echo "Done!"
    done < <(mysql -e "SHOW DATABASES;" | sed "1d" | grep -v "information_schema\|performance_schema\|mysql")
fi

exit 0
'



?>
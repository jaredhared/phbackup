# PHBackup changelog

## 1.6.5

* **Security:** fixed SQL injections, XSS and shell command injections in web interface and backup workers. A compromised host could get root access to the backup server via file names shown in the backup log
* **Security:** added CSRF protection to all web forms
* Host fields and rsync options are validated now. Hosts with invalid data in DB (e.g. spaces in name) will fail with an error in worker log until fixed
* Fixed failed backups being reported as successful when rsync could not start (e.g. invalid rsync options)
* Old backups are now removed by the date in their name, and the newest `$backup_min_keep` (default 3) backups are always kept, so a host which fails for a long time does not lose all its backups
* Default rsync options are now `-aHAXz --numeric-ids` (permissions, owners, hard links, ACLs and xattrs are preserved). Upgrade replaces old default options for existing hosts; next backup may take more space
* Fixed backup log for hosts in groups with a subdirectory
* Zabbix data is generated with json_encode, so it is always a valid JSON
* Telnet password is not written to disk anymore

## 1.6.4

* Fixed - Rsync options were not applied

## 1.6.3

* Fixed stale backups with no active workers

## 1.6.2

* Fixed a typo in default pre_script leading to mariabackup error
* Updated pre-scripts for all hosts in group id 1 (servers)
* Fixed SSH options for pre-script installation
* Added /etc/phbackup/functions.custom.php

## 1.6.1

* Fixed Rsync status handling
* Fixed upgrade bug

## 1.6.0

* Fixed workers staying in busy state
* Replaced host subdirectories with categories. TBD: category editor
* **Added switch backup functionality.** Currently only Cisco IOS/NX-OS backups are supported via Telnet, for switches with enabled aaa new-model. Tested on Catalyst 3560 and Nexus 3k/9k

## 1.5.2

* Reorganized functions in files
* Added backup function mechanism

## 1.5.1

* Added subdirectories for hosts to place them inside
* Updated upgrade mechanism

## 1.5.0

* Added upgrade scripts. Since now, automatic (or near automatic) updates will be possible
* Added changelog :)


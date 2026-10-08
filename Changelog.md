# PHBackup changelog

## Unreleased

* **Fixed skipped days with time slots**: if "start time + period" falls outside time slots (e.g. after "Backup now" at 07:18 with 2-6 slot, or after a late start), the next backup is planned to the opening of the last slot before it, instead of waiting for the next slot almost a whole period later. A backup is not planned earlier than half of the period after the previous one
* **Fixed successful backups of big hosts being marked as failed with "MySQL server has gone away"**: the DB connection opened before a long backup and cleaning was closed by MySQL (wait_timeout). Now the worker reconnects before saving the result
* Cleaning of old backups reports directories which could not be removed (and filesystem errors) instead of counting them as removed, and does not flood the log with thousands of rm errors
* Host list can be filtered by status: click a status in the summary under the table (click again to show all hosts)
* Fixed pre-backup script being installed by two workers at once when workers start together (a starting worker reset installations of other workers)
* Fixed a stale host being reported as unlocked by several workers at start; a stale lock is released only if it is still the same lock, so a host locked again for a new backup can not be unlocked by mistake

## 1.6.6

* **Fixed rsync failures "inflate returned -3 ... error in rsync protocol data stream (code 12)"** on random files: compression (`-z`) is removed from default rsync options, upgrade removes it from hosts with 1.6.5 default options. For slow links use `--zc=zstd` (rsync 3.2+ on both sides) or `--zc=zlibx`
* Fixed time zone mismatch: PHP used UTC (when date.timezone is not set in php.ini) while MySQL used system time, so time slots were checked in UTC and Zabbix backup age was shifted. Now system time zone (or `$timezone` from opt.php) is used by PHP and DB sessions
* Editing a host does not reset pending or failed pre-script installation anymore
* A host can not be renamed while it is being backed up
* "Backup now" for a disabled host shows a proper message
* IPv6 addresses work for rsync and scp
* Default keep period (30 days) is used if a host has no keep period set
* Zabbix process check counts only PHBackup workers

## 1.6.5

* **Security:** fixed SQL injections, XSS and shell command injections in web interface and backup workers. A compromised host could get root access to the backup server via file names shown in the backup log
* **Security:** added CSRF protection to all web forms
* Host fields and rsync options are validated now. Hosts with invalid data in DB (e.g. spaces in name) will fail with an error in worker log until fixed
* Fixed failed backups being reported as successful when rsync could not start (e.g. invalid rsync options)
* `--recursive` is always passed to rsync: with `--files-from`, `-a` does not imply recursion, so with options without `-r` only empty directories were backed up
* A backup without any files is considered failed
* rsync errors from stderr (e.g. reported by remote side) are added to the backup log
* Old backups are now removed by the date in their name, and the newest `$backup_min_keep` (default 3) backups are always kept, so a host which fails for a long time does not lose all its backups
* Default rsync options are now `-aHAXz --numeric-ids` (permissions, owners, hard links, ACLs and xattrs are preserved). Upgrade replaces old default options for existing hosts; next backup may take more space
* Fixed backup log for hosts in groups with a subdirectory
* Zabbix data is generated with json_encode, so it is always a valid JSON
* Telnet password is not written to disk anymore
* Workers do not crash when DB is unavailable, they wait and reconnect (previously a DB restart could leave workers in FATAL state)
* Any error during a backup is logged, the host is unlocked and the worker keeps running
* On start, a worker unlocks hosts left locked by its previous run
* Stale hosts are detected by lock time (5 minutes without a running backup process), including hosts which were never backed up
* Pre-backup script installation does not hold DB locks during SSH, stops on first failed step
* SSH: BatchMode, 30s connect timeout, dead connections are detected in ~2 minutes instead of ~28 days
* Supervisor config: stopasgroup/killasgroup, so rsync is stopped together with the worker. **Update /etc/supervisor/conf.d/phbackup.conf**
* Fixed "Pre-script install pending" status not shown in the host list
* **Zabbix:** fixed problems being hidden as soon as a new backup attempt starts. New `last_result` column keeps the result of the last finished backup, `zabbix.php` exports `backup_last_result` and `backup_overdue`. **Update zabbix/phbackup.conf and re-import the template**
* Zabbix: hosts never backed up have age -1 instead of 0, disabled hosts are not discovered
* "Backup now" does not start a second backup right after the first one and does not shift the schedule
* "Backup now" does not start a parallel backup of a host which is being backed up; "Unlock" restores the last result instead of "Ok"
* Hosts outside their time slots do not delay other hosts; workers lock hosts without table-wide locks
* Backup time slots over midnight (22-3) are supported, invalid slots are rejected instead of silently disabling backups
* Web interface and workers detect a pending DB upgrade (upgrade functions moved to /etc/phbackup/upgrades.php); workers wait until upgrade.php is run
* upgrade.php stops on the first failed step and shows which files have to be updated and which manual steps are needed for the upgrade path
* "Last try" shows the real time of the last attempt, password field is hidden
* **Host groups editor** in web interface: add, edit and delete groups. Groups with hosts and the last group can not be deleted. When group path is changed, new backups go to the new directory, existing ones stay where they are
* Refreshed web interface: compact layout, status badges, light/dark theme switch (follows system theme by default), works on narrow screens
* `111-Latest` links are relative now, so host backup directories can be moved

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


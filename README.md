# PHBackup - a Linux/Unix backup daemon

PHBackup is a simple and fast backup solution to make backups of Linux/Unix machines and some devices like switches. 
It runs at a backup server and allows you to configure and manage backups of your hosts.

## Screenshots

![Main interface window](screens/main.png?raw=true "Main window")
![Add host window](screens/add.png?raw=true "Add host window")

## Features

* PHBackup is a reverse backup system. This means that even if your host will be compromised, attacker will be unable to delete your backups, since there is no access from machine to backup server.
* There are minimal requirements to deploy PHBackup in your environment - machine should have only SSH and Rsync in order to be able to be backuped.
* There are also low requirements to run PHBackup at backup server: PHP, MySQL, webserver, Rsync and Supervisor.
* Backups are stored in regular directories so in case you will need some file, there is no need to download and unpack lots of gigabytes - you can just dive into folder for some date and copy needed files from there.
* There is a deduplication system implemented in PHBackup, so every folder for some day will contain whole files and directory structure, but all unchanged files will be saved only once at disk. All other copies will be just hard links to save disk space.
* There is a simple and handy web interface to manage your hosts, view status of their backups, run backups manually etc.
* After addifion of backup server SSH key to the target host, automatic pre-script deployment is supported, to prepare data like database dumps for backup.
* Backup logs can be viewed from web interface

## How it works

* PHBackup contains of two parts: backup daemon and web interface.
* Web interface is written in PHP/MySQL so it can be run with any compatible server (was tested with Nginx, PHP 8.2 and MariaDB 10.11.
* Daemon should be run by Supervisor, since it relies on Supervisor environmental vars.
* After host was added via web interface, first available worker tracks when it should be backed up, and assign backup task to itself.
* When a worker gets a task for him, he logs into target machine via SSH and launches an Rsync file transfer for configured paths.

## Installation

1. Make a **/root/scripts** folder
2. Clone this repository into /root/scripts: `cd /root/scripts && git clone https://github.com/jaredhared/phbackup.git`
3. Copy files from web folder into root of your web directory
4. Set up HTTP authorization for web part
5. Copy folder etc/phbackup folder into /etc
5. Copy file etc/supervisor/conf.d/phbackup.conf into /etc/supervisor/conf.d/
6. Adjust needed number of workers in /etc/supervisor/conf.d/phbackup.conf
7. Create database, user and password in MySQL
8. Import phback.sql into created database 
9. Edit /etc/phbackup/opt.php - add proper database credentials, edit paths etc
10. Start the daemon: `service supervisor restart`
11.  Done!


## Zabbix configuration

1. If you need to monitor your PHBackup installation via Zabbix 6+, you will need to install `jq` tool at backup server.
2. Also, you have to add a line to /etc/crontab: `*/5 * * * *	root	/bin/php /path/to/your/www/zabbix.php > /tmp/phbackup.zabbix`
3. After that, copy zabbix/phbackup.conf to /etc/zabbix/zabbix_agent2.d and import template from PHBackup.yaml to your Zabbix server

## Usage
1. Add a SSH key of backup server to the target machine and allow backup server to login as root with key-based authorization
2. Create a host in web interface and adjust its parameters
3. If it is enabled, backups will run automatically

## Usage - switches
### Cisco Catalyst
1. Enable aaa at the switch:
```
conf t
aaa new-model
aaa authentication login default
```
2. Create a host in PHbackup, with **backup_cisco_switch_via_telnet** as backup function

### Cisco Nexus
1. Enable telnet at the switch:
```
conf t
feature telnet
```
2. Create a host in PHbackup, with **backup_cisco_switch_via_telnet** as backup function

### Other switches/devices
You can add an appropriate function named backup_xxxxxxx to the /etc/phbackup/functions.php. After that it will be selectable as a backup function for hosts.

## Host groups
Since v. 1.6.0, host groups are supported. They are useful to place your hosts into subdirs inside backup directory, for example, servers by default are in the root of backup directory and switches are inside Switches folder.

## Pre-backup scripts
* PHbackup supports so-called pre-backup scripts, which allows to prepare data on the target host for backup: dump databases etc.
* Pre-backup script is a regular shell script, which will be run as scheduled by Cron daemon.
* You can customize both pre-backup script and cron line which will run it. If you will check "Install pre-backup script" checkbox, script will be deployed to the /opt/ directory on target host, and cron file will be placed in /etc/cron.d/.

## Script updating
To update script, you need to do few steps:
1. Update sources from Git
2. Place script files inside appropriate directories (daemon, web interface and /etc/ part)
3. Run upgrade.php to update database

## FAQ

> Why PHP?

I just know it better, it was the best way to write a first version in just one day. Also, it is handy when it comes to write web interfaces.

> Why there is no authorization?

Since backups are **very important** part of the infrastructure, you should place them on secured subdomain and restrict access by HTTP auth/firewall/etc.


> How to backup MySQL/Redis/Mongo/other things?

This can be done via pre-backup scripts. Estimate their time to run and adjust their schedule so they should finish before main backup will start.

> Is there any foolproof for input fields?

At the moment no, as I said, it was written in one day, so there are no syntax checks for input fields. Be careful and responsible during configuration - this is your backup system. :)



## Todo
1. Usage of preconfigured SSH keys instead of backup server one
2. Prepare script to run in a Docker container

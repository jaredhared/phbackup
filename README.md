# PHBackup - a Linux/Unix backup daemon

PHBackup is a simple and fast backup solution to make backups of Linux/Unix hosts. 
It runs at a backup server and allows you to configure and manage backups of your machines.

## Screenshots

![Main interface window](screens/main.png?raw=true "Main window")
![Add host window](screens/add.png?raw=true "Add host window")

## Features

* PHBackup is a reverse backup system. This means that even if your host will be compromised, attacker will be unable to delete your backups, since there is no access from machine to backup server.
* There are minimal requirements to deploy PHBackup in your environment - machine should have only SSH and Rsync in order to be able to be backuped.
* There are also low requirements to run PHBackup at backup server: PHP, MySQL, webserver, Rsync and Supervisor.
* Backups are stored in regular directories so in case you will need some file, there is no need to download and unpack lots of gigabytes - you can just dive into folder for some date and copy needed files from there.
* There is a deduplication system implemented in PHBackup, so every folder for some day will contain whole files and directory structure, but all unchanged files will be saved only once at disk. All other copies will be just hard links to save disk space.
* There is a simple and hande web interface to manage your hosts, view status of their backups, run backups manually etc.

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

## Usage
1. Create a host in web interface
2. If it is enabled, backups will run automatically

## FAQ

> Why PHP?

I just know it better, it was the best way to write this in just one day. Also, it is handy when it comes to write web interfaces.

> Why there is no authorization?

Since backups are **very important** part of the infrastructure, you should place them on secured subdomain and restrict access by HTTP auth/firewall/etc.


> How to backup MySQL/Redis/Mongo/other things?

PHBackup just copies files, so you should prepare your data before it will be copied. For example, you can dump your database into some dir and add this dir into include paths in host configuration.

> Is there any foolproof for input fields?

At the moment no, as I said, it was written in one day, so there are no syntax checks for input fields. Be careful and responsible during configuration - this is your backup system. :)



## Todo
1. Stats section in web
2. Monitoring stats for Zabbix
3. Usage of preconfigured SSH keys instead of backup server one
4. Prepare script to run in a Docker container

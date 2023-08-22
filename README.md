# PHBackup - a Linux/Unix backup daemon

PHBackup is a simple and fast backup solution to make backups of Linux/Unix hosts. 
It runs at a backup server and allows you to configure and manage backups of your machines.


## Features

* PHBackup is a reverse backup system. This means that even if your host will be compromised, attacker will be unable to delete your backups, since there is no access from machine to backup server.
* There are minimal requirements to deploy PHBackup in your environment - machine should have only SSH and Rsync in order to be able to be backuped.
* There are also low requirements to run PHBackup at backup server: PHP, MySQL, webserver, Rsync and Systemd.
* Backups are stored in regular directories so in case you will need some file, there is no need to download and unpack lots of gigabytes - you can just dive into folder for some date and copy needed files from there.
* There is a deduplication system implemented in PHBackup, so every folder for some day will contain whole files and directory structure, but all unchanged files will be saved only once at disk. All other copies will be just hard links to save disk space.
* There is a simple and hande web interface to manage your hosts, view status of their backups, run backups manually etc.

## How it works

* PHBackup contains of two parts: backup daemon and web interface.
* Web interface is written in PHP/MySQL so it can be run with any compatible server (was tested with Nginx, PHP 8.2 and MariaDB 10.11.
* Daemon should be run in **at least two copies** by Systemd, since the first copy becames a **scheduler** and other ones are **workers** which runs backups.
* After host was added via web interface, scheduler tracks when it should be backed up, and generates backup tasks for workers.
* When a worker gets a task for him from queue, he logs into target machine via SSH and launches an Rsync file transfer for configured paths.

## Installation

1. Make a **/root/scripts** folder
2. Clone this repository into /root/scripts
3. Copy files from web folder into root of your web directory
4. Set up HTTP authorization for web part
5. Copy folder phbackup from etc folder into /etc
6. Copy phbackup.service and phbackup@.service files into /etc/systemd/system
7. Reload Systemd: `systemctl daemon-reload`
8. Adjust needed number of workers in /root/scripts/start.sh
9. Create database, user and password in MySQL
10. Import phback.sql into created database 
11. Edit /etc/phbackup/opt.php - add proper database credentials, edit paths etc
12. Enable the daemon:`systemctl enable phbackup`
13. Start the daemon: `service phbackup start`
14.  Done!

## Usage
1. Create a host in web interface
2. If it is enabled, backups will run automatically

## FAQ

Q: Why PHP?
A: I just know it better, it was the best way to write this in just one day. Also, it is handy when it comes to write web interfaces


Q: Why there is no authorization?

A: Since backups are **very important** part of the infrastructure, you should place them on secured subdomain and restrict access by HTTP auth/firewall/etc.


Q: How to backup MySQL/Redis/Mongo/other things?

A: PHBackup just copies files, so you should prepare your data before it will be copied. For example, you can dump your database into some dir and add this dir into include paths in host configuration.


# PHBackup changelog

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


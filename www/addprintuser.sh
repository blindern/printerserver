#!/bin/bash

# legg til brukeren, deaktiver shell
useradd -MNG beboer -s /usr/sbin/nologin $1

# set unix-passord
echo "$1:$2" | chpasswd

# legg til og sett passord i samba
# kan dette forbedres? se http://jaka.kubje.org/infodump/2007-05-14-unix-samba-password-sync-on-debian-etch/
(echo $2; echo $2) | smbpasswd -as

# legg til i cups-database
echo $2 | ./lppasswd-script.sh -- -a $1

# legg til i pykota
pkusers -al noquota $1

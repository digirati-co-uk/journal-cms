#!/usr/bin/env bash
set -x
set -e
sudo service mysql stop
sudo  mysqld_safe --skip-grant-tables --pid-file=/var/lib/mysql/mysqld_safe.pid &
sleep 4
sudo mysql -e "use mysql; update user set authentication_string=PASSWORD('') where User='root'; update user set plugin='mysql_native_password';FLUSH PRIVILEGES;"
sudo kill -9 `sudo cat /var/lib/mysql/mysqld_safe.pid`
sleep 4

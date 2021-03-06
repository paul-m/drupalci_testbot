#!/bin/bash

export LANGUAGE=en_US.UTF-8
export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8

PGVERSION=$(/usr/bin/psql --version | awk '{print $3}' | head -n1 | cut  -c 1-3)
echo "PGSQL VERSION: ${PGVERSION}"
sudo chown -R postgres:postgres /var/lib/postgresql

if [ ! -z $(pg_lsclusters | grep -c ' main ') ];
    then
    echo "rebuilding PostgreSQL database cluster"
    # stop and drop the cluster
    pg_dropcluster ${PGVERSION} main --stop
    # create a fresh new cluster
    pg_createcluster ${PGVERSION} main --start

    # create a new user with CREATEDB permissions
    psql -c "CREATE USER drupaltestbot WITH PASSWORD 'drupaltestbotpw' CREATEDB;"
    # create a new default database for the user
    psql -c "CREATE DATABASE drupaltestbot OWNER drupaltestbot TEMPLATE DEFAULT ENCODING='utf8' LC_CTYPE='en_US.UTF-8' LC_COLLATE='en_US.UTF-8';"
    # stop the cluster
    pg_ctlcluster ${PGVERSION} main stop
    # allow md5-based password auth for IPv4 connections
    echo "host all all 0.0.0.0/0 md5" >> /etc/postgresql/${PGVERSION}/main/pg_hba.conf
    # copy conf after it was deleted by pg_dropcluster
    cp /opt/postgresql.conf /etc/postgresql/${PGVERSION}/main/postgresql.conf
    mkdir -p /var/lib/postgresql/${PGVERSION}/main.pg_stat_tmp
fi

/usr/lib/postgresql/${PGVERSION}/bin/postgres -D /var/lib/postgresql/${PGVERSION}/main -c config_file=/etc/postgresql/${PGVERSION}/main/postgresql.conf
echo "pgsql died at $(date)";


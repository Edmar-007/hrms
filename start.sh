#!/bin/bash

MYSQL_DATA="/home/runner/mysql_data"
MYSQL_SOCKET="/tmp/mysql.sock"
MYSQL_PID="/tmp/mysql.pid"

# Initialize MariaDB data directory if needed
if [ ! -d "$MYSQL_DATA" ] || [ ! -f "$MYSQL_DATA/ibdata1" ]; then
    echo "Initializing MariaDB data directory..."
    rm -rf "$MYSQL_DATA"
    mysql_install_db --datadir="$MYSQL_DATA" --auth-root-authentication-method=normal 2>&1 | grep -v "Couldn't set"
    echo "MariaDB initialized."
fi

# Remove stale socket and pid
rm -f "$MYSQL_SOCKET" "$MYSQL_PID"

# Start MariaDB
echo "Starting MariaDB..."
mariadbd \
    --datadir="$MYSQL_DATA" \
    --socket="$MYSQL_SOCKET" \
    --pid-file="$MYSQL_PID" \
    --port=33060 \
    --bind-address=127.0.0.1 \
    --skip-networking=0 \
    --skip-name-resolve \
    2>/tmp/mariadb.log &

MYSQL_BGPID=$!

# Wait for MariaDB to start
echo "Waiting for MariaDB to be ready..."
for i in $(seq 1 60); do
    if [ -S "$MYSQL_SOCKET" ] && mysqladmin --socket="$MYSQL_SOCKET" -u root status >/dev/null 2>&1; then
        echo "MariaDB ready after ${i}s."
        break
    fi
    if ! kill -0 $MYSQL_BGPID 2>/dev/null; then
        echo "MariaDB process died. Logs:"
        tail -20 /tmp/mariadb.log
        exit 1
    fi
    sleep 1
done

# Import schema if hrms_db doesn't exist
if ! mysql --socket="$MYSQL_SOCKET" -u root -e "USE hrms_db;" 2>/dev/null; then
    echo "Importing database schema..."
    mysql --socket="$MYSQL_SOCKET" -u root < hrms.sql
    echo "Schema imported."

    # Run migrations if they exist
    if [ -f "database/ensure_phase3.sql" ]; then
        mysql --socket="$MYSQL_SOCKET" -u root hrms_db < database/ensure_phase3.sql 2>/dev/null || true
        echo "Phase 3 migrations applied."
    fi
fi

echo "Starting PHP development server on port 5000..."
exec php -S 0.0.0.0:5000 -t . router.php

#!/bin/bash

set -e

# Create default bar assistant directories
mkdir -p "$APP_BASE_DIR"/storage/bar-assistant/uploads/{cocktails,ingredients,temp}
mkdir -p "$APP_BASE_DIR"/storage/bar-assistant/backups

# SQLite database location
db_file="$APP_BASE_DIR"/storage/bar-assistant/database.ba3.sqlite

if [ ! -f "$db_file" ]; then
    echo "[BAR-ASSISTANT] SQLite database not found, creating a new one..."
    touch "$db_file"
fi

# Enable WAL mode
echo "[BAR-ASSISTANT] Enabling database WAL mode..."
sqlite3 "$db_file" 'pragma journal_mode=wal;'

# Start running artisan commands
cd "$APP_BASE_DIR"

# Run DB setup
php artisan key:generate
php artisan migrate --force
php artisan storage:link
# Setup Meilisearch ENV variables
php artisan bar:setup-meilisearch
php artisan scout:sync-index-settings
php artisan config:cache
php artisan route:cache
php artisan event:cache
# Update meilisearch indexes
php artisan bar:refresh-search

php artisan about

echo '
                               
    __                         
   |@@@g_                      
   |@@ <@@g_              ~~,  
   |@@   @@@@a_           @@|  
   |@@   @@@@@@@_         @@|  
   |@@   @@@@@@@@@@@@@@@@@@@|  
   |@@   @@@@@@@@"        @@|  
   |@@   @@@@@P           @@|  
   |@@ _~@@P              @@|  
   |@@@@P                      
   '""'                        
                               
   ggggggggggggg               
   BBBBBBBBBBBBN               
                               
'

echo "[BAR-ASSISTANT] Bar Assistant API ready [port: 8080]"

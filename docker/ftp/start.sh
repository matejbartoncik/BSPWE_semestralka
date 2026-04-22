#!/bin/sh
set -e

PASSWD_FILE=/etc/pure-ftpd/passwd/pureftpd.passwd
PDB_FILE=/etc/pure-ftpd/passwd/pureftpd.pdb

# Generate initial pdb using this container's pure-pw (compatible version)
if [ -f "$PASSWD_FILE" ]; then
    pure-pw mkdb "$PDB_FILE" -f "$PASSWD_FILE"
fi

# Poll every 2s and regenerate pdb when passwd file changes
(
    LAST_MOD=""
    while true; do
        sleep 2
        if [ -f "$PASSWD_FILE" ]; then
            CURRENT_MOD=$(stat -c %Y "$PASSWD_FILE" 2>/dev/null || echo "")
            if [ "$CURRENT_MOD" != "$LAST_MOD" ]; then
                pure-pw mkdb "$PDB_FILE" -f "$PASSWD_FILE" 2>/dev/null || true
                LAST_MOD="$CURRENT_MOD"
            fi
        fi
    done
) &

exec /run.sh -l puredb:"$PDB_FILE" -E -j -R -P "$PUBLICHOST"

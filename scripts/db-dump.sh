#!/usr/bin/env bash
#
# db-dump.sh — export Chill's catalog data to S3 for local restores.
#
#   * Full schema is dumped; only DATA_TABLES have their rows exported.
#   * Refuses to run if an exported table has a foreign key to a table whose
#     rows are NOT exported (child without its parent). A parent without its
#     children is fine: those child tables are restored empty.
#   * Each run sends one JSON event to CloudWatch Logs with status, S3 key,
#     duration and the run's full output.
#
# Requires: pg_dump/psql/pg_restore (>= RDS major version), aws CLI, jq.
#
# Required env (libpq reads the PG* ones directly):
#   PGHOST PGPORT PGDATABASE PGUSER PGPASSWORD AWS_BUCKET LOG_GROUP
# Optional env:
#   SCHEMA (default: public)  S3_PREFIX (default: db-exports)
#
set -euo pipefail

: "${PGHOST:?}" "${PGDATABASE:?}" "${PGUSER:?}" "${PGPASSWORD:?}" "${AWS_BUCKET:?}" "${LOG_GROUP:?}"
SCHEMA="${SCHEMA:-public}"
S3_PREFIX="${S3_PREFIX:-db-exports}"

# Tables whose rows are exported. `migrations` lets `php artisan migrate` work locally.
DATA_TABLES=(
  migrations
  languages
  titles
  genres
  genre_titles
  production_companies
  title_production_company
  keywords
  keyword_title
  people
  title_person
  title_similarities
  title_collections
)
TABLES_CSV="$(IFS=,; echo "${DATA_TABLES[*]}")"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
KEY="${S3_PREFIX}/chill-catalog-${STAMP}.dump"

log() { printf '[%s] %s\n' "$(date -u +%FT%TZ)" "$*"; }
die() { log "ERROR: $*"; exit 1; }

# psql with :'schema' and :'tables' available; -X ignores ~/.psqlrc.
run_sql() {
  psql -X -A -t -q -v ON_ERROR_STOP=1 -v schema="$SCHEMA" -v tables="$TABLES_CSV"
}

export_dump() {
  # 1. Every listed table exists (catches typos before anything else runs).
  local missing
  missing="$(run_sql <<'SQL'
SELECT t FROM unnest(string_to_array(:'tables', ',')) AS t
WHERE to_regclass(format('%I.%I', :'schema', t)) IS NULL;
SQL
)"
  [[ -z "$missing" ]] || die "tables not found in '${SCHEMA}': ${missing//$'\n'/, }"

  # 2. No exported child references a parent whose rows are not exported.
  #    Only declared FOREIGN KEYs are visible; Laravel morph relations are not.
  local orphans
  orphans="$(run_sql <<'SQL'
SELECT format('%s -> %s (%s)', cc.relname, pc.relname, con.conname)
FROM pg_constraint con
JOIN pg_class cc     ON cc.oid = con.conrelid
JOIN pg_namespace cn ON cn.oid = cc.relnamespace
JOIN pg_class pc     ON pc.oid = con.confrelid
JOIN pg_namespace pn ON pn.oid = pc.relnamespace
WHERE con.contype = 'f'
  AND con.conparentid = 0
  AND cn.nspname = :'schema'
  AND cc.relname = ANY (string_to_array(:'tables', ','))
  AND NOT (pn.nspname = :'schema' AND pc.relname = ANY (string_to_array(:'tables', ',')))
ORDER BY 1;
SQL
)"
  [[ -z "$orphans" ]] || die "exported tables reference parents that are not exported: ${orphans//$'\n'/; }"

  # 3. Exclude the rows of every other table. Names are double-quoted so
  #    pg_dump matches them exactly. Captured into a variable first, because a
  #    failure inside <(...) would not trip set -e.
  local exclude_list
  exclude_list="$(run_sql <<'SQL'
SELECT format('--exclude-table-data="%s"."%s"',
              replace(n.nspname, '"', '""'), replace(c.relname, '"', '""'))
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE c.relkind IN ('r', 'p')
  AND n.nspname NOT IN ('pg_catalog', 'information_schema')
  AND n.nspname NOT LIKE 'pg\_toast%'
  AND NOT (n.nspname = :'schema' AND c.relname = ANY (string_to_array(:'tables', ',')))
ORDER BY 1;
SQL
)"
  local -a exclude_args=()
  [[ -n "$exclude_list" ]] && mapfile -t exclude_args <<< "$exclude_list"
  log "exporting rows of ${#DATA_TABLES[@]} tables, schema only for ${#exclude_args[@]} others"

  # 4. Dump locally, check the archive is readable, then upload.
  #    pg_dump itself refuses servers newer than its own major version.
  local dump_file="$WORKDIR/export.dump"
  pg_dump --format=custom --no-owner --no-acl --lock-wait-timeout=60s \
    "${exclude_args[@]}" --file="$dump_file"
  pg_restore --list "$dump_file" > /dev/null
  log "dump OK ($(du -h "$dump_file" | cut -f1))"

  aws s3 cp "$dump_file" "s3://${AWS_BUCKET}/${KEY}" --only-show-errors
  log "uploaded s3://${AWS_BUCKET}/${KEY}"
}

# Send one structured event per run. A logging failure is reported on stderr
# but never changes the export's exit code.
ship_log() {
  local status="$1" duration="$2" output_file="$3"
  local stream="${STAMP}-${status}"
  local events="$WORKDIR/events.json"

  jq -n --rawfile output "$output_file" \
        --arg status "$status" --arg key "$KEY" --arg bucket "$AWS_BUCKET" \
        --argjson duration "$duration" --argjson ts "$(( $(date +%s) * 1000 ))" \
     '[{timestamp: $ts,
        message: ({status: $status, bucket: $bucket, key: $key,
                   duration_s: $duration, output: ($output | .[-50000:])} | tojson)}]' \
     > "$events" \
  && aws logs create-log-stream --log-group-name "$LOG_GROUP" --log-stream-name "$stream" \
  && aws logs put-log-events --log-group-name "$LOG_GROUP" --log-stream-name "$stream" \
       --log-events "file://$events" > /dev/null \
  || echo "WARN: could not send log to CloudWatch group ${LOG_GROUP}" >&2
}

# Run the export in a subshell with its own `set -e`, and test its status
# through PIPESTATUS, never through `|| ...`: inside an || list bash ignores
# set -e for the whole function, so a failed pg_dump would not stop it.
OUTPUT_FILE="$WORKDIR/output.log"
started=$(date +%s)
set +e
( set -e; export_dump ) 2>&1 | tee "$OUTPUT_FILE"
rc=${PIPESTATUS[0]}
set -e

status=success
(( rc == 0 )) || { status=failure; KEY=""; }
ship_log "$status" "$(( $(date +%s) - started ))" "$OUTPUT_FILE" || true
exit "$rc"
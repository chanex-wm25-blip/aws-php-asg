#!/bin/bash
# Runs on an app instance via SSM Run Command (see .github/workflows/db-init.yml).
# Idempotent: skips the import if event_ticketing_db.users already exists, so
# accidentally re-running the workflow later is a safe no-op instead of
# crashing on duplicate-key errors from schema.sql's seed INSERTs.
set -euo pipefail

AWS_REGION="${AWS_REGION:-us-east-1}"
SECRET_ID="assignment-db-credentials"
SCHEMA_S3_URI="$1"
BUCKET_NAME="${2:-}"

SECRET_JSON=$(aws secretsmanager get-secret-value \
  --secret-id "$SECRET_ID" --region "$AWS_REGION" --query SecretString --output text)

DB_HOST=$(echo "$SECRET_JSON" | jq -r .host)
DB_USER=$(echo "$SECRET_JSON" | jq -r .username)
DB_PASS=$(echo "$SECRET_JSON" | jq -r .password)

ALREADY_SEEDED=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='event_ticketing_db' AND table_name='users'")

if [ "$ALREADY_SEEDED" -gt 0 ]; then
  echo "event_ticketing_db.users already exists - database already seeded, skipping import."
  if [ -n "$BUCKET_NAME" ]; then
    # Fix image paths even if already seeded, in case we're rerunning to fix them
    S3_PREFIX="https://${BUCKET_NAME}.s3.${AWS_REGION}.amazonaws.com/uploads/"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "event_ticketing_db" -e \
      "UPDATE events SET image_url = REPLACE(image_url, '/uploads/', '${S3_PREFIX}') WHERE image_url LIKE '/uploads/%';"
    echo "Updated sample image URLs to S3."
  fi
  exit 0
fi

aws s3 cp "$SCHEMA_S3_URI" /tmp/schema.sql --region "$AWS_REGION"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" < /tmp/schema.sql

if [ -n "$BUCKET_NAME" ]; then
  # Rewrite local image paths to S3 URLs for the sample events
  S3_PREFIX="https://${BUCKET_NAME}.s3.${AWS_REGION}.amazonaws.com/uploads/"
  mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "event_ticketing_db" -e \
    "UPDATE events SET image_url = REPLACE(image_url, '/uploads/', '${S3_PREFIX}') WHERE image_url LIKE '/uploads/%';"
fi

echo "Database seeded successfully."

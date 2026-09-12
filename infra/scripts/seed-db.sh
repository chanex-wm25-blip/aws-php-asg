#!/bin/bash
# Runs on an app instance via SSM Run Command (see .github/workflows/db-init.yml).
# Idempotent: skips the import if shuttle_bus_db.users already exists, so
# accidentally re-running the workflow later is a safe no-op instead of
# crashing on duplicate-key errors from schema.sql's seed INSERTs.
set -euo pipefail

AWS_REGION="${AWS_REGION:-us-east-1}"
SECRET_ID="${SECRET_NAME:-shuttle-bus-ticketing-db-credentials}"
SCHEMA_S3_URI="$1"
BUCKET_NAME="${2:-}"

SECRET_JSON=$(aws secretsmanager get-secret-value \
  --secret-id "$SECRET_ID" --region "$AWS_REGION" --query SecretString --output text)

DB_HOST=$(echo "$SECRET_JSON" | jq -r .host)
DB_USER=$(echo "$SECRET_JSON" | jq -r .username)
DB_PASS=$(echo "$SECRET_JSON" | jq -r .password)

MYSQL_ARGS=(--connect-timeout=10 -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS")

ALREADY_SEEDED=$(mysql "${MYSQL_ARGS[@]}" -N -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='shuttle_bus_db' AND table_name='users'")

if [ "$ALREADY_SEEDED" -gt 0 ]; then
  echo "shuttle_bus_db.users already exists - database already seeded, skipping import."
  # Keep existing deployments compatible with the current ticket workflow.
  HAS_SEAT_NUMBERS=$(mysql "${MYSQL_ARGS[@]}" -N -e \
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='shuttle_bus_db' AND table_name='tickets' AND column_name='seat_numbers'")
  if [ "$HAS_SEAT_NUMBERS" -eq 0 ]; then
    mysql "${MYSQL_ARGS[@]}" -D "shuttle_bus_db" -e \
      "ALTER TABLE tickets ADD COLUMN seat_numbers VARCHAR(100) NULL;"
  fi
  HAS_STATUS=$(mysql "${MYSQL_ARGS[@]}" -N -e \
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='shuttle_bus_db' AND table_name='tickets' AND column_name='status'")
  if [ "$HAS_STATUS" -eq 0 ]; then
    mysql "${MYSQL_ARGS[@]}" -D "shuttle_bus_db" -e \
      "ALTER TABLE tickets ADD COLUMN status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending';"
  fi
  HAS_CHAT_MESSAGES=$(mysql "${MYSQL_ARGS[@]}" -N -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='shuttle_bus_db' AND table_name='chat_messages'")
  if [ "$HAS_CHAT_MESSAGES" -eq 0 ]; then
    mysql "${MYSQL_ARGS[@]}" -D "shuttle_bus_db" -e \
      "CREATE TABLE chat_messages (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, sender ENUM('user', 'admin') NOT NULL, message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id), INDEX idx_chat_messages_user_id (user_id));"
  fi
  if [ -n "$BUCKET_NAME" ]; then
    # Fix image paths even if already seeded, in case we're rerunning to fix them
    S3_PREFIX="https://${BUCKET_NAME}.s3.${AWS_REGION}.amazonaws.com/uploads/"
    mysql "${MYSQL_ARGS[@]}" -D "shuttle_bus_db" -e \
      "UPDATE routes SET image_url = REPLACE(image_url, '/uploads/', '${S3_PREFIX}') WHERE image_url LIKE '/uploads/%';"
    echo "Updated sample image URLs to S3."
  fi
  exit 0
fi

aws s3 cp "$SCHEMA_S3_URI" /tmp/schema.sql --region "$AWS_REGION"
mysql "${MYSQL_ARGS[@]}" < /tmp/schema.sql

if [ -n "$BUCKET_NAME" ]; then
  # Rewrite local image paths to S3 URLs for the sample routes
  S3_PREFIX="https://${BUCKET_NAME}.s3.${AWS_REGION}.amazonaws.com/uploads/"
  mysql "${MYSQL_ARGS[@]}" -D "shuttle_bus_db" -e \
    "UPDATE routes SET image_url = REPLACE(image_url, '/uploads/', '${S3_PREFIX}') WHERE image_url LIKE '/uploads/%';"
fi

echo "Database seeded successfully."

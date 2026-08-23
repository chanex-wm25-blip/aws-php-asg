# assignment-db-credentials: single source of truth for the app's DB connection
# info. EC2 instances read this at boot instead of having credentials baked into
# an AMI or passed in plaintext user-data.
#
# No extra IAM policy is needed — the Academy LabRole already has broad
# permissions including secretsmanager:GetSecretValue. Attempting to attach an
# inline policy (iam:PutRolePolicy) would fail because the student user's own
# permission boundary blocks that action.
resource "aws_secretsmanager_secret" "db" {
  name                    = var.secret_name
  description             = "RDS connection details for the event-ticketing app (${var.name_prefix} sandbox)"
  recovery_window_in_days = 0 # skip 30-day soft-delete; allows immediate re-creation

  tags = {
    Name = var.secret_name
  }
}

resource "aws_secretsmanager_secret_version" "db" {
  secret_id = aws_secretsmanager_secret.db.id
  secret_string = jsonencode({
    host     = var.db_host
    port     = var.db_port
    dbname   = var.db_name
    username = var.db_username
    password = var.db_password
  })
}


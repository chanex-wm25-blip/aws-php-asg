# Override defaults from variables.tf here if needed for a given run.
# Left mostly empty on purpose - variables.tf already defaults everything to
# the assignment-{resource} sandbox configuration.
aws_region        = "us-east-1"
name_prefix       = "shuttle-bus-ticketing"
s3_bucket_name    = "shuttle-bus-ticketing-app-609329194143"
db_name           = "shuttle_bus_db"
secret_name       = "shuttle-bus-ticketing-db-credentials"
health_check_path = "/healthz.php"
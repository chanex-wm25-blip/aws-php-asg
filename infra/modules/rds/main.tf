# assignment-rds: single-AZ sandbox MySQL instance. The DB subnet group still
# needs subnets in >= 2 AZs (an AWS hard requirement) even though the instance
# itself is single-AZ per the assignment's own assumptions.
resource "aws_db_subnet_group" "this" {
  name       = "${var.name_prefix}-db-subnet-group"
  subnet_ids = var.private_subnet_ids

  tags = {
    Name = "${var.name_prefix}-db-subnet-group"
  }
}

resource "aws_db_instance" "this" {
  identifier     = "${var.name_prefix}-rds"
  engine         = "mysql"
  engine_version = var.engine_version
  instance_class = var.instance_class

  allocated_storage = var.allocated_storage
  storage_type      = "gp3"

  db_name  = var.db_name
  username = var.db_username
  password = var.db_password

  db_subnet_group_name   = aws_db_subnet_group.this.name
  vpc_security_group_ids = [var.rds_sg_id]

  multi_az            = false
  publicly_accessible = false

  # Sandbox environment: prioritize cheap/disposable over durability.
  skip_final_snapshot     = true
  backup_retention_period = 1
  deletion_protection     = false
  apply_immediately       = true

  tags = {
    Name = "${var.name_prefix}-rds"
  }
}
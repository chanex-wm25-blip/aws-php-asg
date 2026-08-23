# assignment-sg-alb: the only security group allowed to receive traffic from the
# public internet.
resource "aws_security_group" "alb" {
  name        = "${var.name_prefix}-sg-alb"
  description = "Allow inbound HTTP from the internet to the ALB"
  vpc_id      = var.vpc_id

  ingress {
    description = "HTTP from internet"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.name_prefix}-sg-alb"
  }
}

# assignment-sg-ec2: app instances only ever accept HTTP from the ALB. SSH is
# scoped to the VPC CIDR only (instances have no public IP, so this only matters
# for same-VPC tooling); SSM Session Manager is the primary shell access path.
resource "aws_security_group" "ec2" {
  name        = "${var.name_prefix}-sg-ec2"
  description = "Allow HTTP only from the ALB, SSH only from within the VPC"
  vpc_id      = var.vpc_id

  ingress {
    description     = "HTTP from ALB only"
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  ingress {
    description = "SSH from within the VPC only"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.name_prefix}-sg-ec2"
  }
}

# assignment-sg-rds: only the app instances may reach the database.
resource "aws_security_group" "rds" {
  name        = "${var.name_prefix}-sg-rds"
  description = "Allow MySQL/Aurora only from application instances"
  vpc_id      = var.vpc_id

  ingress {
    description     = "MySQL/Aurora from EC2 app instances only"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.ec2.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.name_prefix}-sg-rds"
  }
}

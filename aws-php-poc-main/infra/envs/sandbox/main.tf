# Generated once by Terraform, never typed/committed anywhere. Stored in
# Secrets Manager below and read by EC2 instances at boot via their IAM role.
resource "random_password" "db" {
  length           = 20
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

# Used to make the S3 bucket name globally unique (S3 bucket names are global
# across every AWS account) while keeping the assignment- prefix convention.
data "aws_caller_identity" "current" {}

module "vpc" {
  source = "../../modules/vpc"

  name_prefix          = var.name_prefix
  vpc_cidr             = var.vpc_cidr
  azs                  = var.azs
  public_subnet_cidrs  = var.public_subnet_cidrs
  private_subnet_cidrs = var.private_subnet_cidrs
}

module "security_groups" {
  source = "../../modules/security-groups"

  name_prefix = var.name_prefix
  vpc_id      = module.vpc.vpc_id
  vpc_cidr    = var.vpc_cidr
}

module "s3" {
  source = "../../modules/s3"

  name_prefix = var.name_prefix
  # Suffix the account ID so the globally-unique bucket name doesn't collide
  # with another account's, e.g. assignment-s3-uploads-123456789012.
  bucket_name = "${var.s3_bucket_name}-${data.aws_caller_identity.current.account_id}"
}

module "rds" {
  source = "../../modules/rds"

  name_prefix        = var.name_prefix
  private_subnet_ids = module.vpc.private_subnet_ids
  rds_sg_id          = module.security_groups.rds_sg_id
  db_name            = var.db_name
  db_username        = var.db_username
  db_password        = random_password.db.result
}

module "secrets" {
  source = "../../modules/secrets"

  name_prefix = var.name_prefix
  secret_name = var.secret_name
  db_host     = module.rds.db_address
  db_name     = var.db_name
  db_username = var.db_username
  db_password = random_password.db.result
}

module "alb" {
  source = "../../modules/alb"

  name_prefix       = var.name_prefix
  vpc_id            = module.vpc.vpc_id
  public_subnet_ids = module.vpc.public_subnet_ids
  alb_sg_id         = module.security_groups.alb_sg_id
  health_check_path = var.health_check_path
}

module "asg" {
  source = "../../modules/asg"

  name_prefix           = var.name_prefix
  vpc_id                = module.vpc.vpc_id
  private_subnet_ids    = module.vpc.private_subnet_ids
  ec2_sg_id             = module.security_groups.ec2_sg_id
  target_group_arn      = module.alb.target_group_arn
  instance_type         = var.instance_type
  instance_profile_name = var.instance_profile_name
  secret_arn            = module.secrets.secret_arn
  aws_region            = var.aws_region
  min_size              = var.asg_min_size
  max_size              = var.asg_max_size
  desired_capacity      = var.asg_desired_capacity
  artifact_bucket       = module.s3.bucket_id
  artifact_key          = var.artifact_key
}

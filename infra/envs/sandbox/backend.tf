# State bucket + lock table are bootstrapped manually once (see infra/README.md
# section 4) before this backend can be used - Terraform can't create the
# backend it's about to store its own state in.
#
# IMPORTANT: S3 bucket names are GLOBALLY unique. "shuttlebus-tfstate" is a
# sensible base name, but to avoid collisions you should suffix it with your AWS
# account ID (for example: shuttlebus-tfstate-<your-account-id>). A backend
# block cannot use variables/interpolation, so replace the bucket name below
# with your own unique one and create it with that same name in the bootstrap
# step. The DynamoDB lock table name is only account-scoped
terraform {
  backend "s3" {
    bucket         = "shuttlebus-tfstate-609329194143"
    key            = "sandbox/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "shuttlebusticketing-tf-lock"
    encrypt        = true
  }
}

# State bucket + lock table are bootstrapped manually once (see infra/README.md
# section 4) before this backend can be used - Terraform can't create the
# backend it's about to store its own state in.
#
# IMPORTANT: S3 bucket names are GLOBALLY unique. "assignment-tfstate" is almost
# certainly already taken by someone else, which would make bootstrap fail. A
# backend block cannot use variables/interpolation, so replace the bucket name
# below with your own unique one (e.g. assignment-tfstate-<your-account-id>) and
# create it with that same name in the bootstrap step. The DynamoDB lock table
# name is only account-scoped, so "assignment-tf-lock" is fine as-is.
terraform {
  backend "s3" {
    bucket         = "assignment-tfstate-561758157329" # <-- change to a globally-unique name
    key            = "sandbox/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "assignment-tf-lock"
    encrypt        = true
  }
}

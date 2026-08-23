variable "name_prefix" {
  description = "Prefix applied to all resource names in this module."
  type        = string
  default     = "assignment"
}

variable "vpc_id" {
  description = "VPC ID (unused directly but kept for clarity/future rules)."
  type        = string
}

variable "private_subnet_ids" {
  description = "Private subnet IDs the ASG launches instances into."
  type        = list(string)
}

variable "ec2_sg_id" {
  description = "Security group ID to attach to app instances."
  type        = string
}

variable "target_group_arn" {
  description = "ALB target group ARN instances register into."
  type        = string
}

variable "instance_type" {
  description = "EC2 instance type. Bump this (e.g. to t3.small) if t3.micro is insufficient under load - no other changes needed."
  type        = string
  default     = "t3.micro"
}

variable "instance_profile_name" {
  description = "Existing IAM instance profile name (AWS Academy: LabInstanceProfile). Cannot create a new one in a Learner Lab account."
  type        = string
  default     = "LabInstanceProfile"
}

variable "secret_arn" {
  description = "Secrets Manager secret ARN holding DB connection details, read by user-data at boot."
  type        = string
}

variable "artifact_bucket" {
  description = "S3 bucket that deploy.yml uploads application release artifacts to."
  type        = string
}

variable "artifact_key" {
  description = "S3 object key of the latest application release artifact, pulled by user-data on every boot."
  type        = string
  default     = "artifacts/assignment-app.zip"
}

variable "aws_region" {
  description = "AWS region, passed into user-data for the Secrets Manager CLI call."
  type        = string
  default     = "us-east-1"
}

variable "min_size" {
  type    = number
  default = 2
}

variable "max_size" {
  type    = number
  default = 4
}

variable "desired_capacity" {
  type    = number
  default = 2
}

variable "cpu_target_value" {
  description = "Target average CPU utilization (%) for the scaling policy."
  type        = number
  default     = 60
}

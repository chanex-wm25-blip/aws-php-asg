variable "name_prefix" {
  description = "Prefix applied to all resource names in this module."
  type        = string
  default     = "assignment"
}

variable "vpc_id" {
  description = "VPC ID for the target group."
  type        = string
}

variable "public_subnet_ids" {
  description = "Public subnet IDs (must span >= 2 AZs) for the ALB."
  type        = list(string)
}

variable "alb_sg_id" {
  description = "Security group ID to attach to the ALB."
  type        = string
}

variable "health_check_path" {
  description = "HTTP path used for target group health checks."
  type        = string
  default     = "/healthz.php"
}

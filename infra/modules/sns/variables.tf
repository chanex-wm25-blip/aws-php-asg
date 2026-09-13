variable "name_prefix" {
  description = "Prefix used for SNS topic and CloudWatch alarm names."
  type        = string
}

variable "alert_email" {
  description = "Email address that receives SNS alerts."
  type        = string
}

variable "asg_name" {
  description = "Name of the Auto Scaling Group monitored by CloudWatch."
  type        = string
}

variable "alb_arn_suffix" {
  description = "ARN suffix of the Application Load Balancer."
  type        = string
}

variable "target_group_arn_suffix" {
  description = "ARN suffix of the ALB target group."
  type        = string
}
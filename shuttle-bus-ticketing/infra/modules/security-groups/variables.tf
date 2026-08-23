variable "name_prefix" {
  description = "Prefix applied to all resource names in this module."
  type        = string
  default     = "assignment"
}

variable "vpc_id" {
  description = "VPC ID these security groups belong to."
  type        = string
}

variable "vpc_cidr" {
  description = "VPC CIDR block, used to scope SSH access to inside the VPC only."
  type        = string
}

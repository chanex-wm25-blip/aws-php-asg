variable "name_prefix" {
  description = "Prefix applied to all resource names in this module."
  type        = string
  default     = "assignment"
}

variable "vpc_cidr" {
  description = "CIDR block for the VPC."
  type        = string
  default     = "10.0.0.0/16"
}

variable "azs" {
  description = "Availability zones to spread subnets across (must have 2 entries)."
  type        = list(string)
}

variable "public_subnet_cidrs" {
  description = "CIDR blocks for the public subnets, one per AZ."
  type        = list(string)
}

variable "private_subnet_cidrs" {
  description = "CIDR blocks for the private subnets, one per AZ."
  type        = list(string)
}

variable "name_prefix" {
  description = "Prefix applied to all resource names in this module."
  type        = string
  default     = "assignment"
}

variable "private_subnet_ids" {
  description = "Private subnet IDs (must span >= 2 AZs) for the DB subnet group."
  type        = list(string)
}

variable "rds_sg_id" {
  description = "Security group ID allowed to reach the database."
  type        = string
}

variable "db_name" {
  description = "Initial database name."
  type        = string
  default     = "shuttle_bus_db"
}

variable "db_username" {
  description = "Master username."
  type        = string
  default     = "admin"
}

variable "db_password" {
  description = "Master password."
  type        = string
  sensitive   = true
}

variable "instance_class" {
  description = "RDS instance class."
  type        = string
  default     = "db.t3.micro"
}

variable "allocated_storage" {
  description = "Allocated storage in GB."
  type        = number
  default     = 20
}

variable "engine_version" {
  description = "MySQL engine version."
  type        = string
  default     = "8.0"
}

variable "name_prefix" {
  description = "Prefix applied to all resource names in this module."
  type        = string
  default     = "assignment"
}

variable "secret_name" {
  description = "Name of the Secrets Manager secret holding DB connection details."
  type        = string
  default     = "assignment-db-credentials"
}


variable "db_host" {
  description = "RDS endpoint address."
  type        = string
}

variable "db_port" {
  description = "RDS port."
  type        = number
  default     = 3306
}

variable "db_name" {
  description = "Database name."
  type        = string
}

variable "db_username" {
  description = "Database master username."
  type        = string
}

variable "db_password" {
  description = "Database master password."
  type        = string
  sensitive   = true
}

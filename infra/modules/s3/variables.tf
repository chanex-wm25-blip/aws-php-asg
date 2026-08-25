variable "name_prefix" {
  description = "Prefix applied to all resource names in this module."
  type        = string
  default     = "assignment"
}

variable "bucket_name" {
  description = "Globally-unique S3 bucket name for uploads."
  type        = string
  default     = "shuttlebusticketing"
}

variable "public_read_prefix" {
  description = "Object key prefix (glob) that is publicly readable, e.g. uploads/*."
  type        = string
  default     = "uploads/*"
}

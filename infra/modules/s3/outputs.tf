output "bucket_id" {
  value = var.bucket_name
}

output "bucket_arn" {
  value = "arn:aws:s3:::${var.bucket_name}"
}

output "bucket_regional_domain_name" {
  value = "${var.bucket_name}.s3.amazonaws.com"
}

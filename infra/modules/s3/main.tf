# In AWS Academy / Voclabs, Service Control Policy (SCP) explicitly blocks
# s3:GetBucketObjectLockConfiguration, causing Terraform's aws_s3_bucket resource
# read to fail with 403 AccessDenied. Since the bucket (var.bucket_name) is already
# created in the account, we configure policies, public access block, and CORS
# directly on the bucket name without managing the bucket container itself.

resource "aws_s3_bucket_public_access_block" "uploads" {
  bucket = var.bucket_name

  block_public_acls       = true
  ignore_public_acls      = true
  block_public_policy     = false
  restrict_public_buckets = false
}

resource "aws_s3_bucket_policy" "public_read" {
  bucket = var.bucket_name
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid       = "PublicReadRouteImages"
        Effect    = "Allow"
        Principal = "*"
        Action    = "s3:GetObject"
        Resource  = "arn:aws:s3:::${var.bucket_name}/${var.public_read_prefix}"
      }
    ]
  })

  depends_on = [aws_s3_bucket_public_access_block.uploads]
}

resource "aws_s3_bucket_cors_configuration" "uploads" {
  bucket = var.bucket_name

  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET"]
    allowed_origins = ["*"]
    max_age_seconds = 3000
  }
}

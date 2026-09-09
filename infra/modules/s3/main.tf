# assignment-s3-uploads: holds event photo uploads. Bucket ACLs stay blocked;
# only unauthenticated GetObject under uploads/* is allowed via bucket policy so
# images render in the browser, without allowing public listing or writes.
resource "aws_s3_bucket" "uploads" {
  bucket = var.bucket_name

  # Sandbox environment: by the time you `terraform destroy`, this bucket will
  # contain uploaded event images, deploy.yml release artifacts, and
  # db-init.yml's schema.sql/seed-db.sh. AWS refuses to delete a non-empty
  # bucket, so without force_destroy the destroy would fail on this resource.
  force_destroy = true

  tags = {
    Name = "${var.name_prefix}-s3-uploads"
  }
}

resource "aws_s3_bucket_public_access_block" "uploads" {
  bucket = aws_s3_bucket.uploads.id

  block_public_acls       = true
  ignore_public_acls      = true
  block_public_policy     = false
  restrict_public_buckets = false
}

resource "aws_s3_bucket_policy" "public_read" {
  bucket = aws_s3_bucket.uploads.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid       = "PublicReadEventImages"
        Effect    = "Allow"
        Principal = "*"
        Action    = "s3:GetObject"
        Resource  = "${aws_s3_bucket.uploads.arn}/${var.public_read_prefix}"
      }
    ]
  })

  depends_on = [aws_s3_bucket_public_access_block.uploads]
}

resource "aws_s3_bucket_cors_configuration" "uploads" {
  bucket = aws_s3_bucket.uploads.id

  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET"]
    allowed_origins = ["*"]
    max_age_seconds = 3000
  }
}
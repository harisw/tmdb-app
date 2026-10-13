resource "aws_s3_bucket_lifecycle_configuration" "exports" {
  bucket = var.export_bucket

  rule {
    id     = "expire-db-exports"
    status = "Enabled"

    filter {
      prefix = "${var.export_prefix}/"
    }

    expiration {
      days = var.export_retention_days
    }
  }
}

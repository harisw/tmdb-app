variable "export_bucket" {
  description = "S3 bucket the dumps are uploaded to (AWS_BUCKET on prod)"
  type        = string
}

variable "export_prefix" {
  description = "Key prefix of the dumps (S3_PREFIX in scripts/db-dump.sh)"
  type        = string
  default     = "db-exports"
}

variable "export_retention_days" {
  description = "Days a dump is kept in S3 before it expires"
  type        = number
  default     = 30
}

variable "ec2_role_name" {
  description = "IAM role attached to the prod EC2 instance profile"
  type        = string
  default     = "ec2_role"
}

variable "alert_email" {
  description = "Email subscribed to the db export alarm"
  type        = string
}

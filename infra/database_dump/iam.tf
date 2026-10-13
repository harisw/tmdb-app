resource "aws_iam_role_policy" "db_export" {
  name = "tmdb-db-export"
  role = var.ec2_role_name

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect   = "Allow"
        Action   = ["s3:PutObject"]
        Resource = "arn:aws:s3:::${var.export_bucket}/${var.export_prefix}/*"
      },
      {
        Effect   = "Allow"
        Action   = ["logs:CreateLogStream", "logs:PutLogEvents"]
        Resource = "${aws_cloudwatch_log_group.db_export.arn}:*"
      }
    ]
  })
}

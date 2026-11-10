resource "aws_cloudwatch_log_group" "db_export" {
  name              = "/tmdb/db-export"
  retention_in_days = 14
}

resource "aws_cloudwatch_log_metric_filter" "success" {
  name           = "db-export-success"
  log_group_name = aws_cloudwatch_log_group.db_export.name
  pattern        = "{ $.status = \"success\" }"
  metric_transformation {
    name      = "DbExportSuccess"
    namespace = "Tmdb"
    value     = "1"
  }
}

# Dead man's switch: no success in 4 days → alarm
resource "aws_cloudwatch_metric_alarm" "export_missing" {
  alarm_name          = "db-export-missing"
  namespace           = "Tmdb"
  metric_name         = "DbExportSuccess"
  statistic           = "Sum"
  period              = 86400
  evaluation_periods  = 4
  comparison_operator = "LessThanThreshold"
  threshold           = 1
  treat_missing_data  = "breaching"
  alarm_actions       = [aws_sns_topic.alerts.arn]
}

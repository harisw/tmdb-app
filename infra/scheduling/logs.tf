resource "aws_cloudwatch_log_group" "app" {
  name              = "/tmdb/app"
  retention_in_days = 14
}

resource "aws_cloudwatch_log_group" "horizon" {
  name              = "/tmdb/horizon"
  retention_in_days = 14
}

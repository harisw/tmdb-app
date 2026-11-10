data "aws_caller_identity" "current" {}

locals {
  account_id = data.aws_caller_identity.current.account_id
  log_groups = ["/tmdb/app", "/tmdb/horizon"]
}

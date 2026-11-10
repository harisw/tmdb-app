resource "aws_iam_openid_connect_provider" "github" {
  url            = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]
}

resource "aws_iam_role" "triage_logs_reader" {
  name = "chill-triage-logs-reader"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Federated = aws_iam_openid_connect_provider.github.arn }
      Action    = "sts:AssumeRoleWithWebIdentity"
      Condition = {
        StringEquals = {
          "token.actions.githubusercontent.com:aud" = "sts.amazonaws.com"
          "token.actions.githubusercontent.com:sub" = "repo:harisw/tmdb-app:ref:refs/heads/master"
        }
      }
    }]
  })
}

resource "aws_iam_role_policy" "triage_logs_reader" {
  name = "chill-triage-logs-reader-policy"
  role = aws_iam_role.triage_logs_reader.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect   = "Allow"
        Action   = ["logs:StartQuery"]
        Resource = [for group in local.log_groups : "arn:aws:logs:ap-northeast-2:${local.account_id}:log-group:${group}:*"]
      },
      {
        Effect   = "Allow"
        Action   = ["logs:GetQueryResults", "logs:StopQuery"]
        Resource = "*"
      },
    ]
  })
}

#Lambda execution
resource "aws_iam_role" "lambda_scheduler" {
    name    = "chill-scheduler-lambda-role"

    assume_role_policy  = jsonencode({
        Version = "2012-10-17"
        Statement   = [{
            Effect      = "Allow"
            Principal   = { Service = "lambda.amazonaws.com"}
            Action      = "sts:AssumeRole"
        }]
    })
}

resource "aws_iam_role_policy" "lambda_scheduler" {
    name    = "chill-scheduler-lambda-policy"
    role    = aws_iam_role.lambda_scheduler.id

    policy  = jsonencode({
        Version = "2012-10-17"
        Statement   = [
            {
                Effect      = "Allow"
                Action      = ["ec2:StartInstances", "ec2:StopInstances"]
                Resource    = "arn:aws:ec2:ap-northeast-2:*:instance/${var.ec2_instance_id}"
            },
            {
                Effect      = "Allow"
                Action      = ["rds:StartDBInstance", "rds:StopDBInstance"]
                Resource    = data.aws_db_instance.chill.db_instance_arn
            },
            {
                Effect      = "Allow"
                Action      = ["logs:CreateLogGroup", "logs:CreateLogStream", "logs:PutLogEvents"]
                Resource    = "arn:aws:logs:*:*:*"
            }
        ]
    })
}

#EventBridge
resource "aws_iam_role" "eventbridge_scheduler" {
    name    = "chill-scheduler-eventbridge-role"
    assume_role_policy  = jsonencode ({
        Version = "2012-10-17"
        Statement   = [{
            Effect      = "Allow"
            Principal   = { Service = "scheduler.amazonaws.com" }
            Action      = "sts:AssumeRole"
        }]
    })
}

resource "aws_iam_role_policy" "eventbridge_scheduler" {
    name    = "chill-scheduler-eventbridge-policy"
    role    = aws_iam_role.eventbridge_scheduler.id

    policy  = jsonencode({
        Version = "2012-10-17"
        Statement   = [{
            Effect      = "Allow"
            Action      = "lambda:InvokeFunction"
            Resource    = [
                aws_lambda_function.start.arn,
                aws_lambda_function.stop.arn
            ]
        }]
    })
}
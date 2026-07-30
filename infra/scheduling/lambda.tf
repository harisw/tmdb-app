data "archive_file" "start" {
    type        = "zip"
    source_file = "${path.module}/functions/start.py"
    output_path = "${path.module}/functions/start.zip"
}

data "archive_file" "stop" {
    type        = "zip"
    source_file = "${path.module}/functions/stop.py"
    output_path = "${path.module}/functions/stop.zip"
}

resource "aws_lambda_function" "start" {
    function_name       = "chill-start-resources"
    role                = aws_iam_role.lambda_scheduler.arn
    handler             = "start.lambda_handler"
    runtime             = "python3.12"
    filename            = data.archive_file.start.output_path
    source_code_hash    = data.archive_file.start.output_base64sha256

    environment {
        variables = {
            EC2_INSTANCE_ID     = var.ec2_instance_id
            RDS_INSTANCE_ID     = var.rds_instance_identifier
        }
    }
}

resource "aws_lambda_function" "stop" {
    function_name       = "chill-stop-resources"
    role                = aws_iam_role.lambda_scheduler.arn
    handler             = "stop.lambda_handler"
    runtime             = "python3.12"
    filename            = data.archive_file.stop.output_path
    source_code_hash    = data.archive_file.stop.output_base64sha256

    environment {
        variables = {
            EC2_INSTANCE_ID     = var.ec2_instance_id
            RDS_INSTANCE_ID     = var.rds_instance_identifier
        }
    }
}

resource "aws_cloudwatch_log_group" "start" {
  name              = "/aws/lambda/${aws_lambda_function.start.function_name}"
  retention_in_days = 7
}

resource "aws_cloudwatch_log_group" "stop" {
  name              = "/aws/lambda/${aws_lambda_function.stop.function_name}"
  retention_in_days = 7
}
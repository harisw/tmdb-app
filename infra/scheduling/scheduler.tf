resource "aws_scheduler_schedule" "start_weekday" {
    name    = "chill-start-resources-weekday"

    schedule_expression             = var.start_cron_weekday
    schedule_expression_timezone    = "Asia/Seoul"

    flexible_time_window {
        mode    = "OFF"
    }

    target {
        arn         = aws_lambda_function.start.arn
        role_arn    = aws_iam_role.eventbridge_scheduler.arn
    }
}

resource "aws_scheduler_schedule" "stop_weekday" {
    name    = "chill-stop-resources-weekday"

    schedule_expression             = var.stop_cron_weekday
    schedule_expression_timezone    = "Asia/Seoul"

    flexible_time_window {
        mode    = "OFF"
    }

    target {
        arn         = aws_lambda_function.stop.arn
        role_arn    = aws_iam_role.eventbridge_scheduler.arn
    }
}

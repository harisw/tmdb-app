output "start_schedule_weekday_arn" {
    value   = aws_scheduler_schedule.start_weekday.arn
}

output "stop_schedule_weekday_arn" {
    value   = aws_scheduler_schedule.stop_weekday.arn
}

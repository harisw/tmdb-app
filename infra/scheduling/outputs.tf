output "start_schedule_weekday_arn" {
    value   = aws_scheduler_schedule.start_weekday.arn
}

output "start_schedule_weekend_arn" {
    value   = aws_scheduler_schedule.start_weekend.arn
}

output "stop_schedule_weekday_arn" {
    value   = aws_scheduler_schedule.stop_weekday.arn
}

output "stop_schedule_weekend_arn" {
    value   = aws_scheduler_schedule.stop_weekend.arn
}
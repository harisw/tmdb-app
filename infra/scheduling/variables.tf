variable "ec2_instance_id" {
  description = "Chill EC2 instance ID"
  type        = string
}

variable "rds_instance_identifier" {
  description = "Chill RDS instance identifier"
  type        = string
}


variable "start_cron_weekday" {
  description = "Cron to start resources (KST, per schedule_expression_timezone)"
  type        = string
  default     = "cron(0 17 ? * MON-FRI *)"
}

variable "stop_cron_weekday" {
  description = "Cron to stop resources (KST, per schedule_expression_timezone)"
  type        = string
  default     = "cron(0 3 ? * MON-FRI *)"
}

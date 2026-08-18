variable "ec2_instance_id" {
    description = "Chill EC2 instance ID"
    type    = string
}

variable "rds_instance_identifier" {
    description = "Chill RDS instance identifier"
    type    = string
}

variable "start_cron_weekday" {
    description = "Cron to start resources (KST, per schedule_expression_timezone)"
    type    = string
    default = "cron(30 11 ? * MON-FRI *)"
}

variable "start_cron_weekend" {
    description = "Cron to start resources (KST, per schedule_expression_timezone)"
    type    = string
    default = "cron(0 10 ? * SAT *)"
}

variable "stop_cron_weekday" {
    description = "Cron to stop resources (KST, per schedule_expression_timezone)"
    type    = string
    default = "cron(0 0 ? * MON-FRI *)"
}

variable "stop_cron_weekend" {
    description = "Cron to stop resources (KST, per schedule_expression_timezone)"
    type    = string
    default = "cron(0 13 ? * SAT *)"
}
variable "ec2_instance_id" {
    description = "Chill EC2 instance ID"
    type    = string
}

variable "rds_instance_identifier" {
    description = "Chill RDS instance identifier"
    type    = string
}

variable "start_cron" {
    description = "Cron to start resources (UTC)"
    type    = string
    default = "cron(0 22 * * ? *)" #8 am KST
}

variable "stop_cron" {
    description = "Cron to stop resources(UTC)"
    type    = string
    default = "cron(0 16 * * ? *)" #1 am KST
}
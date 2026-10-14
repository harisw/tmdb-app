variable "cloudflare_account_id" {
  description = "Cloudflare account ID"
  type        = string
}

variable "cloudflare_api_token" {
  description = "Cloudflare API Token"
  type        = string
  sensitive   = true
}

variable "zone_name" {
  description = "Domain managed in Cloudflare, e.g. example.com"
  type        = string
}

variable "route_patterns" {
  description = "Routes the maintenance worker runs on"
  type        = list(string)
}

variable "site_name" {
  description = "Name shown on the maintenance page"
  type        = string
}

variable "message" {
  description = "Optional custom message; empty uses the page default"
  type        = string
  default     = ""
}

variable "schedule" {
  description = "Mirrors the EventBridge crons. Days: 0 = Sunday … 6 = Saturday"
  type = object({
    utc_offset_minutes = number
    start              = object({ days = list(number), time = string })
    stop               = object({ days = list(number), time = string })
  })
  default = {
    utc_offset_minutes = 540
    start              = { days = [1, 2, 3, 4, 5], time = "17:00" }
    stop               = { days = [1, 2, 3, 4, 5], time = "03:00" }
  }
}

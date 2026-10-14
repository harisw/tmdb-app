resource "cloudflare_workers_script" "maintenance" {
  account_id         = var.cloudflare_account_id
  script_name        = "maintenance-page"
  main_module        = "worker.js"
  compatibility_date = "2026-10-01"
  content            = replace(file("${path.module}/worker.js"), "__PAGE_HTML__", jsonencode(file("${path.module}/page.html")))

  bindings = concat([
    { name = "SITE_NAME", type = "plain_text", text = var.site_name },
    {
      name = "SCHEDULE"
      type = "json"
      json = jsonencode({
        utcOffsetMinutes = var.schedule.utc_offset_minutes
        start            = var.schedule.start
        stop             = var.schedule.stop
      })
    }
  ], var.message == "" ? [] : [{ name = "MESSAGE", type = "plain_text", text = var.message }])
}

resource "cloudflare_workers_route" "maintenance" {
  for_each = toset(var.route_patterns)

  zone_id = data.cloudflare_zone.site.id
  pattern = each.value
  script  = cloudflare_workers_script.maintenance.script_name
}

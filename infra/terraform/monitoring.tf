# OWASP A09 · Alertas. Sin esto, un worker o un scheduler caído no se nota:
# la app responde y simplemente no sale ningún recordatorio.

resource "digitalocean_monitor_alert" "cpu" {
  alerts {
    email = [var.email_alertas]
  }
  window      = "10m"
  type        = "v1/insights/droplet/cpu"
  compare     = "GreaterThan"
  value       = 85
  enabled     = true
  entities    = [digitalocean_droplet.app.id]
  description = "${local.nombre}: CPU sobre 85 % durante 10 minutos"
}

resource "digitalocean_monitor_alert" "memoria" {
  alerts {
    email = [var.email_alertas]
  }
  window      = "10m"
  type        = "v1/insights/droplet/memory_utilization_percent"
  compare     = "GreaterThan"
  value       = 90
  enabled     = true
  entities    = [digitalocean_droplet.app.id]
  description = "${local.nombre}: memoria sobre 90 % (MySQL es el primero en morir)"
}

resource "digitalocean_monitor_alert" "disco" {
  alerts {
    email = [var.email_alertas]
  }
  window      = "5m"
  type        = "v1/insights/droplet/disk_utilization_percent"
  compare     = "GreaterThan"
  value       = 80
  enabled     = true
  entities    = [digitalocean_droplet.app.id]
  description = "${local.nombre}: disco sobre 80 %"
}

# Chequeo externo del endpoint de salud de Laravel. Solo cuando hay dominio:
# sin él Caddy no tiene certificado y /up no responde por HTTPS.
resource "digitalocean_uptime_check" "app" {
  count   = local.fqdn == "" ? 0 : 1
  name    = "${local.nombre}-up"
  target  = "https://${local.fqdn}/up"
  type    = "https"
  regions = ["us_east", "us_west"]
  enabled = true
}

resource "digitalocean_uptime_alert" "caida" {
  count      = local.fqdn == "" ? 0 : 1
  name       = "${local.nombre}: la app no responde"
  check_id   = digitalocean_uptime_check.app[0].id
  type       = "down"
  period     = "2m"
  comparison = "less_than"
  threshold  = 1
  notifications {
    email = [var.email_alertas]
  }
}

resource "digitalocean_uptime_alert" "certificado" {
  count      = local.fqdn == "" ? 0 : 1
  name       = "${local.nombre}: el certificado vence pronto"
  check_id   = digitalocean_uptime_check.app[0].id
  type       = "ssl_expiry"
  period     = "2m"
  comparison = "less_than"
  threshold  = 14
  notifications {
    email = [var.email_alertas]
  }
}

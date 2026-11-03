output "ip_publica" {
  description = "IP reservada. Es la que va en el registro A del subdominio y en DO_HOST."
  value       = digitalocean_reserved_ip.app.ip_address
}

output "url" {
  description = "URL de la app (vacía mientras no haya dominio)."
  value       = local.fqdn == "" ? "" : "https://${local.fqdn}"
}

output "webhook_meta" {
  description = "URL a registrar en el portal de Meta."
  value       = local.fqdn == "" ? "" : "https://${local.fqdn}/api/v1/webhooks/whatsapp"
}

output "firewall_id" {
  description = "Va en la variable DO_FIREWALL_ID del entorno `produccion` de GitHub: el CD abre ahí la IP del runner."
  value       = digitalocean_firewall.app.id
}

output "bucket_backups" {
  description = "Bucket de Spaces para los dumps (vacío si backups_en_spaces = false)."
  value       = try(digitalocean_spaces_bucket.backups[0].name, "")
}

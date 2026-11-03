locals {
  nombre   = "${var.proyecto}-${var.entorno}"
  fqdn     = var.dominio == "" ? "" : "${var.subdominio}.${var.dominio}"
  etiqueta = [var.proyecto, var.entorno]
}

resource "digitalocean_project" "this" {
  name        = local.nombre
  description = "AgendaLlena: agendamiento por WhatsApp para PyMEs."
  purpose     = "Web Application"
  environment = "Production"
  resources = compact([
    digitalocean_droplet.app.urn,
    digitalocean_volume.datos.urn,
    digitalocean_reserved_ip.app.urn,
    try(digitalocean_spaces_bucket.backups[0].urn, ""),
  ])
}

resource "digitalocean_tag" "proyecto" {
  for_each = toset(local.etiqueta)
  name     = each.value
}

# Red privada propia: MySQL y Redis no escuchan fuera de Docker, pero cualquier
# servicio que se sume después (una base administrada, otro droplet) habla por acá.
resource "digitalocean_vpc" "this" {
  name     = "${local.nombre}-vpc"
  region   = var.region
  ip_range = "10.20.0.0/24"
}

resource "digitalocean_ssh_key" "admin" {
  name       = "${local.nombre}-admin"
  public_key = var.clave_ssh_publica
}

# Datos fuera del droplet: recrear el servidor (otra imagen, otro tamaño) no
# toca la base. `prevent_destroy` obliga a un paso manual para borrarla.
resource "digitalocean_volume" "datos" {
  name                    = "${local.nombre}-datos"
  region                  = var.region
  size                    = var.tamanio_volumen_gb
  initial_filesystem_type = "ext4"
  description             = "MySQL, storage de Laravel, certificados de Caddy y dumps."
  tags                    = [for t in digitalocean_tag.proyecto : t.id]

  lifecycle {
    prevent_destroy = true
  }
}

resource "digitalocean_droplet" "app" {
  name       = local.nombre
  region     = var.region
  size       = var.tamanio_droplet
  image      = var.imagen
  vpc_uuid   = digitalocean_vpc.this.id
  ssh_keys   = [digitalocean_ssh_key.admin.id]
  monitoring = true # agente de métricas: alimenta las alertas de monitoring.tf
  backups    = var.backups_del_droplet
  ipv6       = true
  tags       = [for t in digitalocean_tag.proyecto : t.id]

  # Adjunto desde la creación: cloud-init lo encuentra montable en el primer arranque.
  volume_ids = [digitalocean_volume.datos.id]

  user_data = templatefile("${path.module}/cloud-init.yaml.tftpl", {
    clave_admin    = var.clave_ssh_publica
    clave_deploy   = var.clave_ssh_deploy
    nombre_volumen = digitalocean_volume.datos.name
  })

  lifecycle {
    # Cambiar el cloud-init no debe recrear un servidor en producción sin aviso:
    # se aplica a mano o con un `-replace` explícito.
    ignore_changes = [user_data, ssh_keys]
  }
}

# IP fija: el DNS y el webhook de Meta no cambian si se recrea el droplet.
resource "digitalocean_reserved_ip" "app" {
  region = var.region
}

resource "digitalocean_reserved_ip_assignment" "app" {
  ip_address = digitalocean_reserved_ip.app.ip_address
  droplet_id = digitalocean_droplet.app.id
}

# Firewall de red, delante del droplet (Docker no puede saltearlo como a ufw).
resource "digitalocean_firewall" "app" {
  name        = "${local.nombre}-fw"
  droplet_ids = [digitalocean_droplet.app.id]

  # SSH solo desde administración. El CD abre la IP del runner durante el
  # deploy y la cierra al terminar (ver .github/workflows/deploy.yml).
  inbound_rule {
    protocol         = "tcp"
    port_range       = "22"
    source_addresses = var.ips_de_administracion
  }

  # Riesgo aceptado (Trivy AVD-DIG-0001): es una app web pública y Meta llama al
  # webhook desde IPs que no publica de forma estable. 80 solo sirve el desafío
  # ACME y la redirección a HTTPS que hace Caddy.
  #trivy:ignore:AVD-DIG-0001
  inbound_rule {
    protocol         = "tcp"
    port_range       = "80"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  #trivy:ignore:AVD-DIG-0001
  inbound_rule {
    protocol         = "tcp"
    port_range       = "443"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  # HTTP/3 (QUIC), que Caddy sirve por defecto.
  #trivy:ignore:AVD-DIG-0001
  inbound_rule {
    protocol         = "udp"
    port_range       = "443"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  # Salida limitada a los puertos que la app usa (Trivy AVD-DIG-0003 por destino
  # abierto, riesgo aceptado: las APIs de Meta y Google no tienen IPs fijas).
  # Cerrar el resto corta una shell reversa o una exfiltración por un puerto raro.
  dynamic "outbound_rule" {
    for_each = {
      https = { protocol = "tcp", puertos = "443" } # Meta, Google, GHCR, Let's Encrypt
      http  = { protocol = "tcp", puertos = "80" }  # repositorios de Ubuntu, ACME
      dns   = { protocol = "udp", puertos = "53" }
      dnst  = { protocol = "tcp", puertos = "53" }
      ntp   = { protocol = "udp", puertos = "123" }
      smtp  = { protocol = "tcp", puertos = "587" } # correo transaccional, si se configura
    }
    content {
      protocol              = outbound_rule.value.protocol
      port_range            = outbound_rule.value.puertos
      destination_addresses = ["0.0.0.0/0", "::/0"] #trivy:ignore:AVD-DIG-0003
    }
  }
}

resource "digitalocean_record" "app" {
  count  = var.gestionar_dns && var.dominio != "" ? 1 : 0
  domain = var.dominio
  type   = "A"
  name   = var.subdominio
  value  = digitalocean_reserved_ip.app.ip_address
  ttl    = 300
}

# Copia de los dumps fuera del servidor: si se pierde el droplet y el volumen,
# la base sigue existiendo.
resource "digitalocean_spaces_bucket" "backups" {
  count  = var.backups_en_spaces ? 1 : 0
  name   = "${local.nombre}-backups"
  region = var.region
  acl    = "private"

  versioning {
    enabled = true
  }

  lifecycle_rule {
    id      = "retencion"
    enabled = true
    expiration {
      days = 30
    }
    noncurrent_version_expiration {
      days = 7
    }
  }
}

variable "proyecto" {
  description = "Prefijo de nombres y etiqueta común de todos los recursos."
  type        = string
  default     = "agendallena"
}

variable "entorno" {
  description = "Entorno que se aprovisiona."
  type        = string
  default     = "produccion"
}

variable "region" {
  description = "Región de DigitalOcean. No hay región en Latinoamérica: nyc3 es la de menor latencia hacia Argentina y Paraguay."
  type        = string
  default     = "nyc3"
}

variable "tamanio_droplet" {
  description = "Plan del droplet. 2 GB es el mínimo cómodo para MySQL 8.4 + PHP + Redis."
  type        = string
  default     = "s-1vcpu-2gb"
}

variable "imagen" {
  description = "Imagen base del droplet."
  type        = string
  default     = "ubuntu-24-04-x64"
}

variable "clave_ssh_publica" {
  description = "Clave pública SSH del administrador (contenido, no ruta)."
  type        = string
}

variable "clave_ssh_deploy" {
  description = "Clave pública SSH dedicada al CD de GitHub Actions. Distinta de la del administrador."
  type        = string
}

variable "ips_de_administracion" {
  description = "CIDRs desde los que se permite SSH (tu IP /32). Nunca 0.0.0.0/0."
  type        = list(string)

  validation {
    condition = length(var.ips_de_administracion) > 0 && alltrue([
      for c in var.ips_de_administracion : !contains(["0.0.0.0/0", "::/0"], c)
    ])
    error_message = "SSH no puede quedar abierto a todo internet: indicá al menos una IP de administración."
  }
}

variable "tamanio_volumen_gb" {
  description = "Tamaño del volumen de datos (MySQL, storage, certificados, dumps)."
  type        = number
  default     = 10
}

variable "backups_del_droplet" {
  description = "Backups semanales del droplet por DigitalOcean (cubren el sistema, no el volumen)."
  type        = bool
  default     = true
}

variable "dominio" {
  description = "Dominio raíz, por ejemplo midominio.com. Vacío mientras no exista."
  type        = string
  default     = ""
}

variable "subdominio" {
  description = "Subdominio de la app, por ejemplo agendallena."
  type        = string
  default     = "agendallena"
}

variable "gestionar_dns" {
  description = "true solo si el dominio usa los nameservers de DigitalOcean. Si no, se crea el registro A a mano con el output ip_publica."
  type        = bool
  default     = false
}

variable "email_alertas" {
  description = "Correo que recibe las alertas de monitoreo y de caída."
  type        = string
}

variable "backups_en_spaces" {
  description = "Crea un bucket de Spaces para los dumps diarios de MySQL fuera del servidor."
  type        = bool
  default     = false
}

variable "spaces_access_id" {
  description = "Access key de Spaces (solo si backups_en_spaces = true)."
  type        = string
  default     = null
  sensitive   = true
}

variable "spaces_secret_key" {
  description = "Secret key de Spaces (solo si backups_en_spaces = true)."
  type        = string
  default     = null
  sensitive   = true
}

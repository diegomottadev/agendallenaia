terraform {
  required_version = ">= 1.6"

  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.40"
    }
  }

  # Estado remoto en DigitalOcean Spaces (API compatible con S3). La
  # configuración vive en `backend.hcl` (ver `backend.hcl.example`) y las
  # credenciales en AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY: nada de esto se
  # versiona. El estado contiene IPs e ids de recursos, no debe quedar en git.
  backend "s3" {}
}

# El token se lee de DIGITALOCEAN_TOKEN. Las claves de Spaces solo hacen falta
# si se crea el bucket de backups (`backups_en_spaces = true`).
provider "digitalocean" {
  spaces_access_id  = var.spaces_access_id
  spaces_secret_key = var.spaces_secret_key
}

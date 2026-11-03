-- Base y usuario propios de AgendaLlena dentro del mysql8 existente.
-- Correr una vez:  docker exec -i mysql8 mysql -uroot -p < docker/compartido/crear-base.sql
-- Antes, reemplazar CAMBIAR_POR_UNA_CLAVE_LARGA (y usar la misma en DB_PASSWORD).
-- El usuario solo ve su base: no puede leer las de atiende ni pedidos (OWASP A01).
CREATE DATABASE IF NOT EXISTS agendallena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'agendallena'@'%' IDENTIFIED BY 'CAMBIAR_POR_UNA_CLAVE_LARGA';
GRANT ALL PRIVILEGES ON agendallena.* TO 'agendallena'@'%';
FLUSH PRIVILEGES;

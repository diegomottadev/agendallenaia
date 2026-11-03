import { expect, test } from '@playwright/test';
import { ClienteDeWhatsApp } from './support/whatsapp';
import { Panel } from './support/panel';

/**
 * Los caminos de error que más le cuestan al negocio, de punta a punta.
 *
 * Ninguno usa `page.route` para inventar la falla: la provoca el simulador del
 * lado de "Meta" o la provoca otro cliente real. Así la falla recorre el mismo
 * código que en producción (cola, reintento, re-consulta de disponibilidad).
 */
const NEGOCIO = 'Peluquería Sur';

test.afterEach(async ({ page }) => {
  // La falla programada vive en la caché del servidor: no se deja ninguna para el test siguiente.
  if (page.url().includes('/dev/whatsapp')) {
    await new ClienteDeWhatsApp(page).proximoEnvioDeMeta('sale bien');
  }
});

test('si otro cliente toma el horario antes, se avisa y se ofrecen otros', async ({ page }) => {
  const cliente = new ClienteDeWhatsApp(page);
  await cliente.abrir(NEGOCIO);

  const ana = { telefono: ClienteDeWhatsApp.telefonoNuevo(), nombre: 'Ana Colisión' };
  const beto = { telefono: String(Number(ana.telefono) + 1), nombre: 'Beto Colisión' };

  // Ana llega hasta la lista de horarios y no elige todavía.
  await cliente.como(ana.telefono, ana.nombre);
  await cliente.escribir('Hola');
  await cliente.tocar('Reservar un turno');
  await cliente.escribir(ana.nombre);
  await expect(cliente.horarios().first()).toBeVisible();
  const horario = ClienteDeWhatsApp.tituloDe((await cliente.horarios().first().textContent())!);

  // Beto reserva ese mismo horario.
  await cliente.como(beto.telefono, beto.nombre);
  await expect(cliente.estado).toHaveText('sin conversación'); // ORACULO estado: es otro cliente
  await cliente.escribir('Hola');
  await cliente.tocar('Reservar un turno');
  await cliente.escribir(beto.nombre);
  await cliente.tocar(horario);
  await expect(cliente.estado).toHaveText('BOOKED'); // ORACULO estado

  // Ana toca el horario que ya no está.
  await cliente.como(ana.telefono, ana.nombre);
  await expect(cliente.estado).toHaveText('SELECTING_SLOT');
  await cliente.tocar(horario);

  await expect(cliente.chat).toContainText('Ese horario ya no está disponible'); // ORACULO UI: el aviso correcto, no el genérico de error
  await expect(cliente.chat).not.toContainText('Tu turno quedó reservado'); // ORACULO UI: no hubo doble reserva
  await expect(cliente.estado).toHaveText('SELECTING_SLOT'); // ORACULO estado: sigue eligiendo, no vuelve a empezar
  // ORACULO UI: la lista nueva ya no ofrece el horario perdido.
  await expect(cliente.horarios().last()).toBeVisible();
  const ultimaLista = cliente.chat.getByRole('button', { name: horario });
  await expect(ultimaLista).toHaveCount(1); // solo la de la lista vieja de Ana
});

test('si Meta limita el envío, la cola reintenta y el cliente igual recibe la respuesta', async ({ page }) => {
  const cliente = new ClienteDeWhatsApp(page);
  await cliente.abrir(NEGOCIO);
  await cliente.como(ClienteDeWhatsApp.telefonoNuevo(), 'Carla Reintento');

  await cliente.proximoEnvioDeMeta('rate limit');
  await cliente.escribir('Hola');

  // El primer envío falla con 130429; el job se relanza con backoff de 5 s.
  await expect(cliente.chat).toContainText('Gracias por escribirnos', { timeout: 30_000 }); // ORACULO UI
  await expect(cliente.chat.getByText('Gracias por escribirnos')).toHaveCount(1); // ORACULO estado: el reintento no duplica el saludo
});

test('con credenciales inválidas el panel no deja entrar', async ({ page }) => {
  const panel = new Panel(page);
  // Un correo distinto por corrida: el login limita a 5 intentos por minuto.
  await panel.entrar({ email: `nadie-${Date.now()}@peluqueria-sur.test`, password: 'incorrecta' });

  await expect(page).toHaveURL(/\/login$/); // ORACULO UI
  await expect(page.getByText('Las credenciales no coinciden.')).toBeVisible(); // ORACULO UI

  await page.goto('/panel/conversaciones');
  await expect(page).toHaveURL(/\/login$/); // ORACULO estado: no quedó ninguna sesión
});

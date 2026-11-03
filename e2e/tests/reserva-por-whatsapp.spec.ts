import { expect, test, type Page } from '@playwright/test';
import { ClienteDeWhatsApp } from './support/whatsapp';
import { DUENIA, Panel } from './support/panel';

/**
 * El flujo principal de punta a punta, como lo vive cada actor:
 *
 * 1. Un cliente escribe por WhatsApp (el simulador de /dev/whatsapp, que manda
 *    el webhook firmado al endpoint real), reserva y recibe la confirmación.
 * 2. La dueña entra al panel y ve esa conversación con su turno, una sola vez.
 *
 * Entre los dos pasos no hay atajos: el mensaje pasa por la firma HMAC, la cola,
 * el worker, la máquina de estados, `freeBusy` y la creación del evento. Lo único
 * simulado es Meta y Google.
 *
 * Oráculos, marcados en cada afirmación:
 * - ORACULO UI: lo que el actor ve en pantalla.
 * - ORACULO red: cada acción del cliente llega al webhook y se acepta (en `ClienteDeWhatsApp`).
 * - ORACULO estado: el `current_state` de la conversación y la fila única en el panel.
 *
 * `CAPTURAS=1` guarda una imagen por paso en `capturas/` (las del readme).
 */
const NEGOCIO = 'Peluquería Sur';

async function captura(page: Page, nombre: string) {
  if (process.env.CAPTURAS) {
    await page.screenshot({ path: `capturas/${nombre}.png`, fullPage: true });
  }
}

test('un cliente reserva por WhatsApp y el turno aparece en el panel', async ({ page }) => {
  const cliente = new ClienteDeWhatsApp(page);
  const telefono = ClienteDeWhatsApp.telefonoNuevo();
  const nombre = 'Lucía ' + telefono.slice(-4);

  // --- 1 · El cliente, por WhatsApp -----------------------------------------
  await cliente.abrir(NEGOCIO);
  await cliente.como(telefono, nombre);

  await cliente.escribir('Hola');
  await expect(cliente.chat).toContainText('Gracias por escribirnos'); // ORACULO UI
  await captura(page, '01-bienvenida');

  await cliente.tocar('Reservar un turno');
  await expect(cliente.chat).toContainText('¿A nombre de quién'); // ORACULO UI
  await expect(cliente.estado).toHaveText('GATHERING_PARAMS'); // ORACULO estado

  await cliente.escribir(nombre);
  await expect(cliente.chat).toContainText('horarios disponibles'); // ORACULO UI
  await expect(cliente.estado).toHaveText('SELECTING_SLOT'); // ORACULO estado
  await captura(page, '02-horarios');

  // Cualquier horario sirve: el calendario simulado está libre.
  const primero = cliente.horarios().first();
  const horario = ClienteDeWhatsApp.tituloDe((await primero.textContent())!);
  await cliente.tocar(horario);

  await expect(cliente.chat).toContainText('Tu turno quedó reservado'); // ORACULO UI
  await expect(cliente.chat).toContainText(horario.replace(' hs', '')); // ORACULO UI: el horario elegido, no otro
  await expect(cliente.estado).toHaveText('BOOKED'); // ORACULO estado
  await expect(cliente.error).toBeEmpty(); // ORACULO UI: ningún envío falló en el camino
  await captura(page, '03-reservado');

  // --- 2 · La dueña, en el panel --------------------------------------------
  const panel = new Panel(page);
  await panel.entrar(DUENIA);
  await expect(page).toHaveURL(/\/panel/); // ORACULO UI
  await captura(page, '04-panel');

  await panel.conversaciones();
  const filas = panel.filasDe(telefono);
  await expect(filas).toHaveCount(1); // ORACULO estado: una conversación por teléfono, sin duplicados
  await expect(filas).toContainText(nombre); // ORACULO estado: el nombre llegó a la conversación
  await expect(filas).toContainText('Turno agendado'); // ORACULO estado
  await expect(filas.getByRole('link', { name: 'Ver en Google Calendar' })).toBeVisible(); // ORACULO estado: el evento existe
  await captura(page, '05-conversaciones');

  await panel.abrirConversacion(telefono);
  await expect(page.getByText('Tu turno quedó reservado').first()).toBeVisible(); // ORACULO UI: el historial completo
  await captura(page, '06-detalle-conversacion');
});

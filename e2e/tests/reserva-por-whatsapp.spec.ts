import { expect, test, type Page } from '@playwright/test';

/**
 * El flujo principal de punta a punta, como lo vive cada actor:
 *
 * 1. Un cliente escribe por WhatsApp (el simulador de /dev/whatsapp, que manda
 *    el webhook firmado al endpoint real), reserva y recibe la confirmación.
 * 2. La dueña entra al panel y ve esa conversación con su turno.
 *
 * Entre los dos pasos no hay atajos: el mensaje pasa por la firma HMAC, la cola,
 * el worker, la máquina de estados, `freeBusy` y la creación del evento. Lo único
 * simulado es Meta y Google.
 *
 * `CAPTURAS=1` guarda una imagen por paso en `capturas/` (las del readme).
 */
const NEGOCIO = 'Peluquería Sur';
const DUENIA = { email: 'duenia@peluqueria-sur.test', password: 'password' };

async function captura(page: Page, nombre: string) {
  if (process.env.CAPTURAS) {
    await page.screenshot({ path: `capturas/${nombre}.png`, fullPage: true });
  }
}

/** La última burbuja del chat: es la respuesta más reciente del bot. */
const chat = (page: Page) => page.locator('#chat');

test('un cliente reserva por WhatsApp y el turno aparece en el panel', async ({ page }) => {
  // Un teléfono nuevo por corrida: cada ejecución es un cliente que escribe por primera vez.
  const telefono = '54911' + Date.now().toString().slice(-8);
  const cliente = 'Lucía ' + telefono.slice(-4);

  // --- 1 · El cliente, por WhatsApp -----------------------------------------
  await page.goto('/dev/whatsapp');
  const numero = await page.locator('#numero option', { hasText: NEGOCIO }).getAttribute('value');
  await page.locator('#numero').selectOption(numero!);
  await page.locator('#from').fill(telefono);
  await page.locator('#from').dispatchEvent('change');
  await page.locator('#nombre').fill(cliente);

  await page.locator('#texto').fill('Hola');
  await page.locator('#texto').press('Enter');
  await expect(chat(page)).toContainText('Gracias por escribirnos');
  await captura(page, '01-bienvenida');

  await chat(page).getByRole('button', { name: 'Reservar un turno' }).last().click();
  await expect(chat(page)).toContainText('¿A nombre de quién');

  await page.locator('#texto').fill(cliente);
  await page.locator('#texto').press('Enter');
  await expect(chat(page)).toContainText('horarios disponibles');
  await captura(page, '02-horarios');

  // El primer horario de la lista: cualquiera sirve, todos están libres en el calendario.
  const horario = chat(page).getByRole('button', { name: /\d{2}:\d{2} hs/ }).first();
  const textoHorario = (await horario.textContent())!.split(' · ')[0].trim();
  await horario.click();

  await expect(chat(page)).toContainText('Tu turno quedó reservado');
  await expect(chat(page)).toContainText(textoHorario.replace(' hs', ''));
  await expect(page.locator('#estado')).toHaveText('BOOKED');
  await captura(page, '03-reservado');

  // --- 2 · La dueña, en el panel --------------------------------------------
  await page.goto('/login');
  await page.locator('#email').fill(DUENIA.email);
  await page.locator('#password').fill(DUENIA.password);
  await page.getByRole('button', { name: 'Ingresar' }).click();
  await expect(page).toHaveURL(/\/panel/);
  await captura(page, '04-panel');

  await page.goto('/panel/conversaciones');
  // Se busca por teléfono, que es único por corrida, y además tiene que mostrar
  // el nombre que dio el cliente: antes la fila decía "Sin nombre".
  const fila = page.locator('tr', { hasText: telefono });
  await expect(fila).toBeVisible();
  await expect(fila).toContainText(cliente);
  await expect(fila).toContainText('Turno agendado');
  await expect(fila).toContainText('Ver en Google Calendar');
  await captura(page, '05-conversaciones');

  await fila.getByRole('link').first().click();
  await expect(page.locator('body')).toContainText('Tu turno quedó reservado');
  await captura(page, '06-detalle-conversacion');
});

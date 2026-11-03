import { expect, type Locator, type Page } from '@playwright/test';

/**
 * El cliente final, por WhatsApp: el simulador de /dev/whatsapp.
 *
 * Cada acción del cliente pasa por `POST /dev/whatsapp/enviar`, que arma el
 * webhook de Meta, lo firma con HMAC y lo manda al endpoint real. Por eso cada
 * acción verifica también su **oráculo de red**: si el webhook no respondió 200,
 * lo que siga en pantalla no prueba nada.
 *
 * Selectores: solo rol y etiqueta accesible, nunca ids ni clases.
 */
export class ClienteDeWhatsApp {
  constructor(private readonly page: Page) {}

  /** Un teléfono distinto por corrida: la base de desarrollo no se limpia. */
  static telefonoNuevo(): string {
    return '54911' + Date.now().toString().slice(-8);
  }

  get chat(): Locator {
    return this.page.getByRole('log', { name: 'Chat' });
  }

  /** ORACULO estado: el `current_state` de la conversación, tal como lo muestra el simulador. */
  get estado(): Locator {
    return this.page.getByRole('status', { name: 'Estado de la conversación' });
  }

  get error(): Locator {
    return this.page.getByRole('alert');
  }

  async abrir(negocio: string): Promise<void> {
    await this.page.goto('/dev/whatsapp');
    const selector = this.page.getByLabel('Negocio (phone_number_id)');
    const valor = await selector.locator('option', { hasText: negocio }).getAttribute('value');
    expect(valor, `No hay un negocio "${negocio}" en el simulador: corré dev:whatsapp:preparar`).toBeTruthy();
    await selector.selectOption(valor!);
  }

  /** Pasa a escribir como otro cliente. El chat se recarga con su conversación. */
  async como(telefono: string, nombre: string): Promise<void> {
    const campo = this.page.getByLabel('Teléfono del cliente');
    await campo.fill(telefono);
    await campo.dispatchEvent('change');
    await this.page.getByLabel('Nombre', { exact: true }).fill(nombre);
  }

  /** Programa una falla para el próximo envío del bot hacia Meta. */
  async proximoEnvioDeMeta(modo: 'sale bien' | 'rate limit' | 'rechazo'): Promise<void> {
    const valor = { 'sale bien': '', 'rate limit': 'rate_limit', rechazo: 'rechazo' }[modo];
    const programada = this.page.waitForResponse(
      (r) => r.url().endsWith('/dev/whatsapp/falla') && r.request().method() === 'POST',
    );
    await this.page.getByLabel('El próximo envío del bot a Meta…').selectOption(valor);
    expect((await programada).status(), 'ORACULO red: la falla no quedó programada').toBe(200);
  }

  async escribir(texto: string): Promise<void> {
    await this.conOraculoDeRed({ tipo: 'texto', texto }, async () => {
      const mensaje = this.page.getByRole('textbox', { name: 'Mensaje' });
      await mensaje.fill(texto);
      await mensaje.press('Enter');
    });
  }

  /**
   * Toca un botón o una fila de lista que mandó el bot.
   * Siempre la **última** coincidencia: es la del mensaje más reciente.
   */
  async tocar(nombre: string | RegExp): Promise<void> {
    const opcion = this.chat.getByRole('button', { name: nombre }).last();
    await this.conOraculoDeRed({}, () => opcion.click());
  }

  /** Las filas de horario de la última lista, como "09:00 hs". */
  horarios(): Locator {
    return this.chat.getByRole('button', { name: /\d{2}:\d{2} hs/ });
  }

  /** El título de una fila de horario, sin la descripción que el simulador agrega tras " · ". */
  static tituloDe(textoDeLaFila: string): string {
    return textoDeLaFila.split(' · ')[0].trim();
  }

  /**
   * ORACULO red: la acción del cliente salió como `POST /dev/whatsapp/enviar`,
   * con el cuerpo esperado, y el webhook real la aceptó (200 adentro y afuera).
   */
  private async conOraculoDeRed(cuerpo: Record<string, unknown>, accion: () => Promise<void>): Promise<void> {
    const respuesta = this.page.waitForResponse(
      (r) => r.url().endsWith('/dev/whatsapp/enviar') && r.request().method() === 'POST',
    );
    await accion();
    const r = await respuesta;
    const enviado = r.request().postDataJSON();
    expect(enviado).toMatchObject(cuerpo);
    expect(r.status(), 'ORACULO red: el simulador no pudo entregar el webhook').toBe(200);
    expect(await r.json(), 'ORACULO red: el webhook firmado no fue aceptado').toEqual({ webhook: 200 });
  }
}

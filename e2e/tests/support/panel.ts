import { expect, type Locator, type Page } from '@playwright/test';

/** La dueña del negocio demo (RolesDemoSeeder). */
export const DUENIA = { email: 'duenia@peluqueria-sur.test', password: 'password' };

/** El equipo de la PyME, en el panel. Selectores por etiqueta y rol. */
export class Panel {
  constructor(private readonly page: Page) {}

  async entrar(credenciales: { email: string; password: string }): Promise<void> {
    await this.page.goto('/login');
    await this.page.getByLabel('Correo').fill(credenciales.email);
    await this.page.getByLabel('Contraseña').fill(credenciales.password);
    await this.page.getByRole('button', { name: 'Ingresar' }).click();
  }

  async conversaciones(): Promise<void> {
    await this.page.goto('/panel/conversaciones');
  }

  /** Las filas del listado de un teléfono. Tiene que haber una sola. */
  filasDe(telefono: string): Locator {
    return this.page.getByRole('row').filter({ hasText: telefono });
  }

  async abrirConversacion(telefono: string): Promise<void> {
    const fila = this.filasDe(telefono);
    await expect(fila).toHaveCount(1);
    await fila.getByRole('link').first().click();
  }
}

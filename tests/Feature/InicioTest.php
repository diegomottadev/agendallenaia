<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La raíz del dominio no muestra la bienvenida de Laravel: lleva al login, o
 * al panel si ya hay sesión.
 */
class InicioTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    public function test_sin_sesion_la_raiz_lleva_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_con_sesion_la_raiz_lleva_al_panel(): void
    {
        $tenant = Tenant::create([
            'name' => 'PyME inicio', 'slug' => 'inicio',
            'status' => 'active', 'timezone' => 'America/Argentina/Buenos_Aires',
        ]);
        $usuario = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Vanina',
            'email' => 'owner@inicio.test',
            'password' => 'secreto123',
            'role' => Role::Owner,
        ]);

        $this->actingAs($usuario)->get('/')->assertRedirect('/panel');
    }
}

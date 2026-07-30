<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SectionHeading;
use Filament\Pages\Page;

/**
 * El escritorio de quien opera RentaFácil, no el de un rentador.
 *
 * Estaba vacío, y el único widget que cargaba era el de equipos del inquilino
 * activo: los números de una lavandería cualquiera, no los del negocio.
 *
 * La pregunta que contesta es dónde se están quedando las cuentas. Todo excluye
 * las demos: una demo trae 17 equipos y 200 pagos de mentiras, y mezclarlas hacía
 * ver las cifras cuatro veces mejor de lo que están.
 */
class AdminDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-s-presentation-chart-line';

    protected static string $view = 'filament.pages.admin-dashboard';

    protected static ?string $navigationGroup = 'Administrador';

    protected static ?string $slug = 'escritorio';

    protected static ?string $title = 'Cómo va el negocio';

    protected static ?string $navigationLabel = 'Cómo va el negocio';

    protected static ?int $navigationSort = -1;

    public function getSubheading(): ?string
    {
        return 'Las cuentas reales de RentaFácil, sin contar demos. Dónde se están quedando y a quién hay que marcarle.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SectionHeading::make([
                'titulo' => 'El embudo',
                'descripcion' => 'De registrarse a cobrar. Cada paso que se pierde es una cuenta que no vuelve.',
            ]),
            \App\Filament\Widgets\EmbudoDeCuentas::class,

            SectionHeading::make([
                'titulo' => 'A quién hay que marcarle',
                'descripcion' => 'Gente que ya dijo que sí y se quedó a medio camino.',
            ]),
            \App\Filament\Widgets\CuentasAtoradas::class,

            SectionHeading::make([
                'titulo' => 'Quiénes sí lo usaron',
                'descripcion' => 'Cuánto alcanzó a cargar cada uno, con plan vigente o vencido. Quien ya capturó sus equipos sabe para qué sirve.',
            ]),
            \App\Filament\Widgets\CuentasQueLoUsaron::class,

            SectionHeading::make([
                'titulo' => 'Si el sistema está trabajando',
                'descripcion' => 'Lo que corre solo, y si de verdad está corriendo. Una tarea caída no se ve en ninguna otra pantalla.',
            ]),
            \App\Filament\Widgets\SaludDelSistema::class,

            SectionHeading::make([
                'titulo' => 'Seguimiento',
                'descripcion' => 'Lo que se vence, lo que se atoró y lo que falta contactar.',
            ]),
            \App\Filament\Widgets\SeguimientoDelNegocio::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}

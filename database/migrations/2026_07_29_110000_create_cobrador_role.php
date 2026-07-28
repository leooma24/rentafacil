<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El rol de cobrador, y los permisos de pagos e incidencias que faltaban.
 *
 * Hasta ahora sólo existían dos roles: propietario (43 usuarios) y super_admin
 * (2). El dueño no podía mandar a nadie a cobrar sin darle acceso completo,
 * incluidos precios y la posibilidad de borrar. Con 100 lavadoras eso deja de
 * ser un detalle: nadie cobra 100 domicilios solo.
 *
 * OJO con el orden: Payment e Incident no tenían política, y sin política
 * Filament deja pasar todo. Al agregarlas, el propietario se habría quedado
 * fuera de su propia pantalla de pagos, porque nunca tuvo esos permisos. Por
 * eso esta migración se los da antes que nada.
 */
return new class extends Migration
{
    /** Lo que el propietario ya podía hacer de facto, ahora escrito. */
    private const ACCIONES_COMPLETAS = [
        'view_any', 'view', 'create', 'update', 'delete', 'delete_any',
        'force_delete', 'force_delete_any', 'restore', 'restore_any',
        'replicate', 'reorder',
    ];

    /** El cobrador mira clientes, rentas y lavadoras, pero no los toca. */
    private const SOLO_LECTURA = ['view_any', 'view'];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $propietario = Role::findOrCreate('propietario', 'web');
        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $cobrador = Role::findOrCreate('cobrador', 'web');

        // 1. Lo primero: que el propietario no se quede fuera de pagos e
        //    incidencias al aparecer sus políticas.
        foreach (['payment', 'incident'] as $modelo) {
            foreach (self::ACCIONES_COMPLETAS as $accion) {
                $permiso = Permission::findOrCreate("{$accion}_{$modelo}", 'web');
                $propietario->givePermissionTo($permiso);
                $superAdmin->givePermissionTo($permiso);
            }
        }

        // 2. El cobrador: ve a quién le toca cobrar, pero no edita el catálogo.
        foreach (['customer', 'rental', 'washing::machine'] as $modelo) {
            foreach (self::SOLO_LECTURA as $accion) {
                $cobrador->givePermissionTo(Permission::findOrCreate("{$accion}_{$modelo}", 'web'));
            }
        }

        // 3. Su trabajo: registrar cobros y levantar reportes. Nunca borrarlos.
        foreach (['view_any_payment', 'view_payment', 'create_payment'] as $permiso) {
            $cobrador->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        foreach ([
            'view_any_incident', 'view_incident', 'create_incident', 'update_incident',
        ] as $permiso) {
            $cobrador->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Se quita el rol y los permisos del cobrador, pero NO los del propietario:
     * revocárselos lo dejaría sin su pantalla de pagos, que es peor que dejar
     * de más.
     */
    public function down(): void
    {
        Role::where('name', 'cobrador')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

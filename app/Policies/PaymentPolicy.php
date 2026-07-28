<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Quién puede hacer qué con los cobros.
 *
 * No existía, y sin política Filament deja pasar todo: cualquiera con acceso al
 * panel podía borrar un cobro. Eso no se notaba mientras el único usuario de
 * cada empresa era el dueño, pero con un cobrador dentro deja de ser aceptable.
 *
 * La migración que crea el rol de cobrador también le da al propietario todos
 * estos permisos: sin eso, poner esta política lo dejaría fuera de su propia
 * pantalla de pagos.
 */
class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_payment');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('view_payment');
    }

    public function create(User $user): bool
    {
        return $user->can('create_payment');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can('update_payment');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can('delete_payment');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_payment');
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return $user->can('force_delete_payment');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_payment');
    }

    public function restore(User $user, Payment $payment): bool
    {
        return $user->can('restore_payment');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_payment');
    }

    public function replicate(User $user, Payment $payment): bool
    {
        return $user->can('replicate_payment');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_payment');
    }
}

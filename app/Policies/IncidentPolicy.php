<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Quién puede hacer qué con los reportes de falla.
 *
 * Igual que la de pagos: no existía, y sin política Filament deja pasar todo.
 * El cobrador levanta y atiende reportes, pero no los borra.
 */
class IncidentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_incident');
    }

    public function view(User $user, Incident $incident): bool
    {
        return $user->can('view_incident');
    }

    public function create(User $user): bool
    {
        return $user->can('create_incident');
    }

    public function update(User $user, Incident $incident): bool
    {
        return $user->can('update_incident');
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->can('delete_incident');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_incident');
    }

    public function forceDelete(User $user, Incident $incident): bool
    {
        return $user->can('force_delete_incident');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_incident');
    }

    public function restore(User $user, Incident $incident): bool
    {
        return $user->can('restore_incident');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_incident');
    }

    public function replicate(User $user, Incident $incident): bool
    {
        return $user->can('replicate_incident');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_incident');
    }
}

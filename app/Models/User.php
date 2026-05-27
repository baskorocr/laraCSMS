<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['company_id', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isGlobalAdmin(): bool
    {
        return DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', self::class)
            ->where('model_has_roles.model_id', $this->id)
            ->where('roles.name', 'admin')
            ->where('roles.company_id', 0)
            ->exists();
    }

    public function permissionTeamId(): int
    {
        return $this->isGlobalAdmin() ? 0 : (int) ($this->company_id ?? 0);
    }

    public function canAccessRoute(string $routeName): bool
    {
        foreach ($this->routePermissionCandidates($routeName) as $permissionName) {
            if ($this->can($permissionName)) {
                return true;
            }
        }

        return match ($routeName) {
            'access-control.roles.index' => $this->can('manage_roles'),
            'access-control.permissions.index' => $this->can('manage_permissions'),
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    private function routePermissionCandidates(string $routeName): array
    {
        $candidates = [$routeName];
        $segments = explode('.', $routeName);
        $actionSuffixes = ['create', 'store', 'edit', 'update', 'destroy', 'show', 'sync-routes', 'sync-permissions', 'assign-role'];

        $last = end($segments);
        if (in_array($last, $actionSuffixes, true) && count($segments) > 1) {
            array_pop($segments);
            $prefix = implode('.', $segments);
            $candidates[] = $prefix;
            $candidates[] = $prefix.'.index';
        }

        return array_values(array_unique($candidates));
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_WAREHOUSE = 'warehouse';

    public const ROLE_HR = 'hr';

    public const ROLE_ACCOUNTANT = 'accountant';

    public const ROLES = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_WAREHOUSE => 'MRP Staff',
        self::ROLE_HR => 'HR Staff',
        self::ROLE_ACCOUNTANT => 'Accounting Staff',
    ];

    public const MODULE_MRP = 'mrp';

    public const MODULE_HR = 'hr';

    public const MODULE_ACCOUNTING = 'accounting';

    public const MODULE_SALES = 'sales';

    /**
     * The single source of truth for RBAC: routes and the sidebar both read this map.
     *
     * @var array<string, list<string>>
     */
    public const MODULE_ROLES = [
        self::MODULE_MRP => [self::ROLE_ADMIN, self::ROLE_WAREHOUSE],
        self::MODULE_HR => [self::ROLE_ADMIN, self::ROLE_HR],
        self::MODULE_ACCOUNTING => [self::ROLE_ADMIN, self::ROLE_ACCOUNTANT],
        self::MODULE_SALES => [self::ROLE_ADMIN],
    ];

    /**
     * @var array<string, string>
     */
    private const MODULE_HOME_ROUTES = [
        self::MODULE_MRP => 'staff.mrp.index',
        self::MODULE_HR => 'staff.hr.index',
        self::MODULE_ACCOUNTING => 'staff.accounting.index',
        self::MODULE_SALES => 'staff.sales.index',
    ];

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function canAccessModule(string $module): bool
    {
        return $this->hasRole(...self::MODULE_ROLES[$module]);
    }

    /**
     * Where this account lands after signing in: the first module its role can open.
     */
    public function homeRouteName(): string
    {
        foreach (self::MODULE_HOME_ROUTES as $module => $routeName) {
            if ($this->canAccessModule($module)) {
                return $routeName;
            }
        }

        abort(403);
    }

    public function loginRouteName(): string
    {
        return $this->isAdmin() ? 'admin.login' : 'staff.login';
    }

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
}

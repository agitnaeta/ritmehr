<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Spatie's Permission, extended only to satisfy Backpack — see App\Models\Role.
 */
class Permission extends SpatiePermission
{
    use CrudTrait;
}

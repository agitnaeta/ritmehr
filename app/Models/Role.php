<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie's Role, extended only to satisfy Backpack.
 *
 * Backpack refuses to build a CRUD panel for a model without CrudTrait, and
 * the package model does not (and should not) carry it.
 */
class Role extends SpatieRole
{
    use CrudTrait;
}

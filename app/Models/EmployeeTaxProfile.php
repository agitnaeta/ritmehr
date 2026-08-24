<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTaxProfile extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'employee_tax_profiles';

    protected $fillable = [
        'user_id', 'npwp', 'tax_status', 'tax_method',
        'bpjs_kesehatan', 'bpjs_ketenagakerjaan',
        'bpjs_tk_jht', 'bpjs_tk_jp', 'bpjs_tk_jkk', 'bpjs_tk_jkm',
    ];

    protected $casts = [
        'bpjs_kesehatan'       => 'boolean',
        'bpjs_ketenagakerjaan' => 'boolean',
        'bpjs_tk_jht'          => 'boolean',
        'bpjs_tk_jp'           => 'boolean',
        'bpjs_tk_jkk'          => 'boolean',
        'bpjs_tk_jkm'          => 'boolean',
    ];

    public const TAX_STATUSES = [
        'TK/0', 'TK/1', 'TK/2', 'TK/3',
        'K/0', 'K/1', 'K/2', 'K/3',
        'K/I/0', 'K/I/1', 'K/I/2', 'K/I/3',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Employees without an NPWP historically paid a 20% surcharge. Kept as an
     * explicit accessor so the rule is visible where it is applied.
     */
    public function hasNpwp(): bool
    {
        return filled($this->npwp);
    }
}

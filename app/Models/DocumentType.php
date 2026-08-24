<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'document_types';

    protected $fillable = [
        'name', 'code', 'has_expiry', 'is_required',
        'max_file_size_mb', 'allowed_extensions',
    ];

    protected $casts = [
        'has_expiry'       => 'boolean',
        'is_required'      => 'boolean',
        'max_file_size_mb' => 'integer',
    ];

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * @return string[] lowercase, no dots
     */
    public function extensions(): array
    {
        return collect(explode(',', (string) $this->allowed_extensions))
            ->map(fn ($e) => strtolower(trim($e, " \t.")))
            ->filter()
            ->values()
            ->all();
    }

    public function maxFileSizeKb(): int
    {
        return max(1, (int) $this->max_file_size_mb) * 1024;
    }
}

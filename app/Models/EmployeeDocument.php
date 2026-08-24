<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'employee_documents';

    protected $fillable = [
        'user_id', 'document_type_id', 'file_path', 'file_name', 'file_size',
        'document_number', 'issued_date', 'expiry_date', 'notes', 'uploaded_by',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'expiry_date' => 'date',
        'file_size'   => 'integer',
    ];

    // -- Relationships --------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // -- Scopes ---------------------------------------------

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
                     ->whereDate('expiry_date', '<', now()->toDateString());
    }

    /**
     * Documents expiring within the next $days (and not already expired).
     */
    public function scopeExpiringWithin($query, int $days)
    {
        return $query->whereNotNull('expiry_date')
                     ->whereDate('expiry_date', '>=', now()->toDateString())
                     ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    // -- Helpers --------------------------------------------

    public function isExpired(): bool
    {
        return $this->expiry_date !== null
            && Carbon::parse($this->expiry_date)->startOfDay()->isBefore(now()->startOfDay());
    }

    public function daysUntilExpiry(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse($this->expiry_date)->startOfDay(),
            false
        );
    }

    public function humanFileSize(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1024 / 1024, 2) . ' MB';
    }
}

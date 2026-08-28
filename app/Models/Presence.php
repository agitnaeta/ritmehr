<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presence extends Model
{
    use CrudTrait;
    use HasFactory;
    use \App\Traits\Auditable;
    protected $fillable = [
        'in',
        'out',
        'user_id',
        'is_overtime',
        'is_late',
        'late_minute',
        'lat',
        'lng',
        'outside',
        'extra_time',
        'branch_id',
        'selfie_path',
        'source',
        'accuracy',
        'approval_status',
        'approval_note',
        'approved_by',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function branch(){
        return $this->belongsTo(Branch::class);
    }

    public function approver(){
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Authorized stream URL for the selfie proof, or null when there is none. */
    public function selfieUrl(): ?string
    {
        if (! $this->selfie_path) {
            return null;
        }

        return route('portal.attendance.selfie', $this->id);
    }
}

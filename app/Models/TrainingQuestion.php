<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M11 — a multiple-choice quiz question for a training. One shared set per
 * training (not per material). correct_option is one of a|b|c|d.
 */
class TrainingQuestion extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'training_questions';

    protected $fillable = [
        'training_id', 'position', 'question',
        'option_a', 'option_b', 'option_c', 'option_d', 'correct_option',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    /** @return array<string, string> option key => text (skips empty c/d) */
    public function options(): array
    {
        return array_filter([
            'a' => $this->option_a,
            'b' => $this->option_b,
            'c' => $this->option_c,
            'd' => $this->option_d,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function isCorrect(?string $answer): bool
    {
        return $answer !== null && strtolower($answer) === strtolower((string) $this->correct_option);
    }
}

<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::log('create', $model, null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function ($model) {
            $dirty = $model->auditableValues($model->getDirty());
            if (empty($dirty)) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $dirty);
            AuditLog::log('update', $model, $model->auditableValues($old), $dirty);
        });

        static::deleted(function ($model) {
            AuditLog::log('delete', $model, $model->auditableValues($model->getAttributes()), null);
        });
    }

    /**
     * Buang kolom rahasia sebelum masuk jejak audit.
     *
     * Jejak audit adalah tabel kedua yang menyimpan salinan data, dan dibaca
     * oleh lebih banyak orang daripada tabel aslinya. Menyalin hash password ke
     * sana memperluas permukaan serangan tanpa manfaat apa pun — satu dump
     * `audit_logs` saja sudah cukup untuk memanen hash yang bisa di-crack.
     *
     * Kolom `$hidden` model dipakai sebagai dasar (untuk User itu `password`
     * dan `remember_token`); tambahkan `$auditExclude` bila sebuah model perlu
     * menyembunyikan kolom lain dari audit tanpa menyembunyikannya dari JSON.
     */
    public function auditableValues(array $values): array
    {
        $rahasia = array_merge(
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return array_diff_key($values, array_flip($rahasia));
    }
}

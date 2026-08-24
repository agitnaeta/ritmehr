<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use CrudTrait, HasFactory;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'type',
        'channel_database',
        'channel_email',
        'channel_whatsapp',
    ];

    protected $casts = [
        'channel_database' => 'boolean',
        'channel_email'    => 'boolean',
        'channel_whatsapp' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Channels this user wants for $type. Absent preference means the default:
     * database on, everything else off.
     */
    public static function channelsFor(int $userId, string $type): array
    {
        $pref = static::where('user_id', $userId)->where('type', $type)->first();

        if (! $pref) {
            return [Notification::CHANNEL_DATABASE];
        }

        return array_values(array_filter([
            $pref->channel_database ? Notification::CHANNEL_DATABASE : null,
            $pref->channel_email    ? Notification::CHANNEL_EMAIL : null,
            $pref->channel_whatsapp ? Notification::CHANNEL_WHATSAPP : null,
        ]));
    }
}

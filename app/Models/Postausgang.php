<?php

namespace App\Models;

use App\Enums\MailEreignis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Meldung, die auf die nächste Sammelmail wartet (NID-35).
 *
 * Nur created_at, kein updated_at: eine Zeile wird geschrieben, gelesen und
 * gelöscht, aber nie geändert. Siehe Support\Sammelmail.
 */
#[Fillable([
    'user_id', 'ereignis', 'herkunft', 'notification_id', 'customer_id',
    'titel', 'text', 'url', 'farbe',
])]
class Postausgang extends Model
{
    protected $table = 'mail_postausgang';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'ereignis' => MailEreignis::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

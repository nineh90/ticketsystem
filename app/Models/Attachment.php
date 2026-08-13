<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['ticket_id', 'user_id', 'pfad', 'dateiname', 'mime', 'groesse'])]
class Attachment extends Model
{
    use HasFactory;

    /** Die Platte, auf der Anhänge liegen — bewusst nicht "public". */
    public const PLATTE = 'local';

    protected static function booted(): void
    {
        // Datei mitlöschen. Ohne das sammeln sich verwaiste Dateien an, die
        // niemand mehr zuordnen kann — und sie enthalten unter Umständen
        // genau die Daten, die eigentlich weg sollten.
        static::deleted(function (Attachment $anhang) {
            Storage::disk(self::PLATTE)->delete($anhang->pfad);
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function hochgeladenVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function istBild(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /** Adresse der geschützten Ausliefer-Route. */
    public function url(): string
    {
        return route('anhang.zeigen', $this);
    }

    /** Größe als "1,4 MB" statt "1468006". */
    public function groesseLesbar(): string
    {
        $bytes = $this->groesse;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', '.').' MB';
    }
}

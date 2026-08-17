<?php

namespace App\Models;

use App\Observers\NachrichtObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine einzelne Nachricht in einer Unterhaltung.
 *
 * Reiner Text, kein HTML. Der Unterschied zu den Ticketkommentaren ist
 * Absicht: dort schreibt man einen Befund, hier ein paar Sätze. Ein
 * Formatierungsbalken über einem Chatfenster lädt dazu ein, Überschriften in
 * Nachrichten zu setzen, und die Darstellung müsste denselben Text in zwei
 * Panels bereinigen — der eine Ort, an dem eine vergessene Bereinigung zu
 * fremdem Markup im Kundenbereich führt.
 */
#[Fillable(['unterhaltung_id', 'user_id', 'text'])]
#[ObservedBy(NachrichtObserver::class)]
class Nachricht extends Model
{
    use HasFactory;

    protected $table = 'nachrichten';

    public function unterhaltung(): BelongsTo
    {
        return $this->belongsTo(Unterhaltung::class);
    }

    public function absender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Für die Glocke: der Anfang der Nachricht, einzeilig. */
    public function auszug(int $zeichen = 120): string
    {
        return str($this->text)->squish()->limit($zeichen)->toString();
    }
}

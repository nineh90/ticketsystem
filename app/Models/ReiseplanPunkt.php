<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Etappe innerhalb einer Reiseplan-Vorlage.
 *
 * Bewusst ein eigenes Modell und keine JSON-Spalte an der Vorlage: die
 * Etappen werden sortiert und einzeln bearbeitet, und eine json-Spalte hat
 * dieser Anwendung schon einmal jedes "select distinct" zerlegt (siehe die
 * Migration für users.mail_ereignisse).
 */
#[Fillable(['reiseplan_vorlage_id', 'titel', 'beschreibung', 'sortierung'])]
class ReiseplanPunkt extends Model
{
    protected $table = 'reiseplan_punkte';

    protected function casts(): array
    {
        return ['sortierung' => 'integer'];
    }

    public function vorlage(): BelongsTo
    {
        return $this->belongsTo(ReiseplanVorlage::class, 'reiseplan_vorlage_id');
    }
}

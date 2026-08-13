<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'user_id', 'body', 'ist_intern'])]
class Comment extends Model
{
    use HasFactory;

    /**
     * Intern, bis ausdrücklich anders gesagt. Sobald Kunden ihre Tickets
     * sehen können, entscheidet dieses Flag darüber, was sie zu lesen
     * bekommen — ein vergessener Wert darf dann keine interne Notiz
     * freigeben.
     */
    protected $attributes = [
        'ist_intern' => true,
    ];

    protected function casts(): array
    {
        return [
            'ist_intern' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeFuerKunden(Builder $query): Builder
    {
        return $query->where('ist_intern', false);
    }
}

<?php

namespace App\Models;

use App\Observers\CommentObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'user_id', 'body', 'ist_intern', 'frage_an', 'beantwortet_at'])]
#[ObservedBy(CommentObserver::class)]
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
            'beantwortet_at' => 'datetime',
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

    /** An wen sich der Kommentar als Frage richtet — siehe NID-36. */
    public function gefragt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frage_an');
    }

    public function istOffeneFrage(): bool
    {
        return $this->frage_an !== null && $this->beantwortet_at === null;
    }

    public function scopeOffeneFragen(Builder $query): Builder
    {
        return $query->whereNotNull('frage_an')->whereNull('beantwortet_at');
    }

    public function scopeFuerKunden(Builder $query): Builder
    {
        return $query->where('ist_intern', false);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

/**
 * Was im Ticketsystem los ist — für das Dashboard in der Redaktion der
 * Website.
 *
 * Nur Zahlen und die letzten zehn Zeilen, nur lesend. Die Form ist in
 * docs/n8n.md festgeschrieben; die Website wird genau dagegen gebaut, also
 * hier nichts umbenennen und Neues nur dazustellen.
 *
 * Bewusst ohne Nutzerfilter: hinter dem Aufruf steht kein angemeldeter
 * Mensch, sondern die Website mit ihrem Token, und die Redaktion dort ist
 * Nils selbst.
 */
class UebersichtController extends Controller
{
    /** Mehr Zeilen liest auf einem Dashboard niemand. */
    private const NEUESTE = 10;

    public function __invoke(): JsonResponse
    {
        $neueste = Ticket::query()
            ->with(['customer:id,name,kuerzel', 'status:id,name'])
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::NEUESTE)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'kennung' => $ticket->kennung(),
                'titel' => $ticket->titel,
                'status' => $ticket->status->name,
                'kunde' => $ticket->customer->name,
                'url' => $ticket->oeffentlicheAdresse(),
                'geaendert_am' => $ticket->updated_at->toIso8601String(),
            ]);

        return response()->json([
            'offen' => Ticket::query()->offen()->count(),
            // Dieselbe Regel wie die stündliche Erinnerung des Planers, nur
            // ohne deren Wartezeit: hier zählt jedes Ticket ab der ersten
            // Minute, gemahnt wird erst nach einem Tag.
            'wartet_auf_uns' => Ticket::query()->wartetAufUns()->count(),
            'anfragen_offen' => Ticket::query()
                ->offen()
                ->whereHas('project', fn ($q) => $q->where('slug', config('ticketsystem.anfragen_projekt')))
                ->count(),
            'neueste' => $neueste,
        ]);
    }
}

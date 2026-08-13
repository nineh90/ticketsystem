<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Models\TicketStatus;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/**
 * Der Verlauf eines Tickets.
 *
 * Reine Anzeige — der Verlauf wird von spatie/laravel-activitylog
 * geschrieben und ist nicht bearbeitbar. Ein änderbares Protokoll wäre
 * keines.
 */
class AktivitaetRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Verlauf';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Wann')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Wer')
                    // Leer heißt: nicht von einem Menschen ausgelöst, also
                    // über die Schnittstelle (n8n) oder einen Hintergrundlauf.
                    ->placeholder('System / Schnittstelle'),

                TextColumn::make('event')
                    ->label('Was')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'created' => 'angelegt',
                        'updated' => 'geändert',
                        'deleted' => 'gelöscht',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('aenderungen')
                    ->label('Änderungen')
                    ->state(fn (Activity $record) => static::beschreiben($record))
                    // Mehrere geänderte Felder gehören untereinander. Ein "\n"
                    // im String reicht nicht — HTML bricht daran nicht um, und
                    // die Zeilen liefen ineinander.
                    ->listWithLineBreaks()
                    ->bulleted(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            // Keine Aktionen: der Verlauf wird gelesen, nicht bearbeitet.
            ->emptyStateHeading('Noch nichts passiert');
    }

    /**
     * Die rohen Feldwerte in etwas Lesbares übersetzen.
     *
     * Ohne das stünde hier "ticket_status_id: 2 → 5" — technisch korrekt und
     * im Alltag wertlos.
     */
    /** @return array<int, string> eine Zeile je geändertem Feld */
    private static function beschreiben(Activity $record): array
    {
        // In activitylog v5 stehen die Feldänderungen in attribute_changes,
        // nicht mehr in properties — properties bleibt hier leer. Nachgesehen,
        // nicht angenommen: mit dem alten Zugriff zeigte der Verlauf für jede
        // Änderung nur einen Strich an.
        $aenderungen = $record->attribute_changes ?? [];

        $alt = $aenderungen['old'] ?? [];
        $neu = $aenderungen['attributes'] ?? [];

        if ($record->event === 'created') {
            return ['Ticket angelegt'];
        }

        $zeilen = [];

        foreach ($neu as $feld => $wert) {
            $vorher = $alt[$feld] ?? null;

            if ($vorher === $wert) {
                continue;
            }

            $zeilen[] = static::feldname($feld).': '
                .static::wert($feld, $vorher).' → '.static::wert($feld, $wert);
        }

        return $zeilen ?: ['—'];
    }

    private static function feldname(string $feld): string
    {
        return match ($feld) {
            'titel' => 'Titel',
            'ticket_status_id' => 'Status',
            'prioritaet' => 'Priorität',
            'assigned_to' => 'Zuständig',
            'faellig_am' => 'Fällig',
            default => $feld,
        };
    }

    private static function wert(string $feld, mixed $wert): string
    {
        if ($wert === null || $wert === '') {
            return '—';
        }

        return match ($feld) {
            'ticket_status_id' => TicketStatus::find($wert)?->name ?? (string) $wert,
            'assigned_to' => User::find($wert)?->name ?? (string) $wert,
            'faellig_am' => \Illuminate\Support\Carbon::parse($wert)->format('d.m.Y'),
            default => (string) $wert,
        };
    }
}

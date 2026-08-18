<?php

namespace App\Filament\Kunde\Resources\Dokumente;

use App\Filament\Kunde\Resources\Dokumente\Pages\ListDokumente;
use App\Filament\Kunde\Resources\Dokumente\Pages\ViewDokument;
use App\Filament\Kunde\Resources\Dokumente\Tables\DokumenteTable;
use App\Models\Dokument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Angebote, Rechnungen und Verträge im Kundenbereich — nur ansehen.
 *
 * Wie bei den Projekten gibt es weder Formular noch Anlegen- oder
 * Bearbeiten-Seite. Was es nicht gibt, kann auch über eine nachgebaute
 * Anfrage nicht aufgerufen werden. Die einzige Ausnahme ist die Antwort auf
 * ein Angebot, und die ist ein einzelner Knopf mit eigener Prüfung
 * (DokumentPolicy::beantworten).
 *
 * **Der Menüpunkt erscheint erst, wenn wirklich etwas darin steht.** Das ist
 * kein Detail, sondern der Grund, warum der Bereich so gebaut ist: der
 * Kundenbereich hat eine Handvoll Punkte, und einer, der ein Jahr lang leer
 * ist, sieht nicht nach "kommt noch" aus, sondern nach unfertig. Schlimmer
 * noch — man gewöhnt sich an, ihn zu übergehen, und übersieht ihn dann auch,
 * wenn das erste Angebot darin liegt. Dieselbe Überlegung wie bei der Karte
 * "Wer arbeitet gerade", die verschwindet, wenn keine Uhr läuft.
 */
class DokumentResource extends Resource
{
    protected static ?string $model = Dokument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Dokumente';

    protected static ?string $modelLabel = 'Dokument';

    protected static ?string $pluralModelLabel = 'Dokumente';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'dokumente';

    protected static ?string $recordTitleAttribute = 'titel';

    /**
     * sichtbarFuer kennt die Rolle "kunde" und liefert dann ausschließlich
     * die freigegebenen Dokumente seines Kunden.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->sichtbarFuer(auth()->user());
    }

    /**
     * Zugang nur, wenn es überhaupt etwas zu sehen gibt.
     *
     * Steuert beides zugleich: den Menüpunkt und den Direktaufruf. Ein Kunde
     * ohne freigegebene Dokumente bekommt hier eine 403 statt einer leeren
     * Liste — und das ist die ehrlichere Antwort, denn für ihn gibt es diesen
     * Bereich in dem Moment tatsächlich nicht.
     *
     * Die Abfrage ist ein EXISTS über den Index (customer_id,
     * kunden_sichtbar) und läuft einmal je Seitenaufbau.
     */
    public static function canAccess(): bool
    {
        $nutzer = auth()->user();

        if ($nutzer === null || ! $nutzer->istKunde()) {
            return false;
        }

        return Dokument::query()->sichtbarFuer($nutzer)->exists();
    }

    public static function table(Table $table): Table
    {
        return DokumenteTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDokumente::route('/'),
            'view' => ViewDokument::route('/{record}'),
        ];
    }
}

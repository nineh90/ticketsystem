<?php

namespace App\Filament\Resources\Treffen;

use App\Filament\Formulare\Treffenformular;
use App\Filament\Resources\Treffen\Pages\ListTreffen;
use App\Filament\Resources\Treffen\Tables\TreffenTable;
use App\Models\Treffen;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Die Messe im Menü — alle Treffen, mit und ohne Kunden.
 *
 * Der Reiter an der Kundenakte bleibt: wer dort steht, will ein Treffen mit
 * genau diesem Kunden ansetzen, und der Umweg über eine Liste aller Termine
 * wäre einer zu viel. Diese Seite beantwortet die andere Frage — "was steht
 * überhaupt an" — und ist der einzige Ort, an dem ein Termin **ohne** Kunden
 * entstehen kann: Wochenplanung, Retro, ein Gespräch zu zweit.
 *
 * Ohne sie hatte genau diese Sorte Termin kein Zuhause und landete wieder in
 * einem fremden Kalender — und dann steht die Hälfte der Woche woanders.
 */
class TreffenResource extends Resource
{
    protected static ?string $model = Treffen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?string $navigationLabel = 'Messe';

    /**
     * Ausdrücklich gesetzt: Filament leitet den Pfad aus Namensraum und
     * Modellnamen ab und käme auf "treffen/treffens" — die Mehrzahl von
     * "Treffen" ist "Treffen", das weiß nur kein Pluralisierer. Nebenbei
     * verhindert es eine Nachbarschaft zu /treffen/{id}/kalender.
     */
    protected static ?string $slug = 'messe';

    protected static ?string $modelLabel = 'Treffen';

    protected static ?string $pluralModelLabel = 'Treffen';

    /** Hinter Funk, vor den Passagieren: ein Termin ist Verabredung, keine Akte. */
    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'titel';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(Treffenformular::felder(mitKundenwahl: true));
    }

    public static function table(Table $table): Table
    {
        return TreffenTable::configure($table);
    }

    /**
     * Wie überall: die Sichtbarkeit steht am Modell und nicht hier.
     *
     * Für einen Mitarbeiter heißt das — die Treffen seiner Kunden, dazu die
     * internen, bei denen er selbst in der Crew steht.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->sichtbarFuer(auth()->user());
    }

    /** Kundenzugänge haben mit dieser Seite nichts zu tun. */
    public static function canAccess(): bool
    {
        return auth()->user()?->istKunde() === false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreffen::route('/'),
        ];
    }
}

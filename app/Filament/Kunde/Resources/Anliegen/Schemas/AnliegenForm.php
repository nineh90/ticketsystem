<?php

namespace App\Filament\Kunde\Resources\Anliegen\Schemas;

use App\Filament\Formulare\Anhangfeld;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Das Formular, mit dem ein Kunde etwas meldet.
 *
 * Vier Felder, mehr nicht. Jedes weitere ist eine Hürde vor einer Meldung,
 * die wir haben wollen — wer beim Melden eines Fehlers erst Priorität,
 * Fälligkeit und Kategorie einordnen soll, ruft stattdessen an oder schreibt
 * eine Mail, und dann steht es wieder nirgends.
 *
 * Was hier fehlt, ist Absicht: Art, Priorität, Zuständigkeit, Termin und
 * Status setzen wir. Die Art (Fehler, Änderungswunsch, Frage) stand bis
 * NID-33 als erste Frage im Formular — eine Entscheidung vor dem eigentlichen
 * Anliegen, die der Kunde oft nicht treffen kann und die wir beim Lesen
 * ohnehin selbst treffen. Ob ein Wunsch Gewährleistung ist oder neue Arbeit,
 * ist unsere Einordnung, nicht seine.
 *
 * Auch sonst gilt: Ein Kunde, der seine eigene Meldung auf "dringend" stellen
 * kann, stellt jede darauf, und die Angabe wird wertlos.
 */
class AnliegenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ihr Anliegen')
                    ->schema(self::alleFelder()),
            ]);
    }

    /**
     * Die Felder ohne Rahmen — für das Formular auf der Übersicht
     * (Widgets\EtwasMelden), das selbst schon in einer Karte steht. Eine
     * zweite Fassung der Felder gäbe es nur, damit sie sich irgendwann um
     * ein Pflichtfeld unterscheiden.
     *
     * @return array<int, Component>
     */
    public static function alleFelder(): array
    {
        return [
            Select::make('project_id')
                ->label('Projekt')
                ->options(fn () => Project::query()
                    ->sichtbarFuer(auth()->user())
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->required()
                // Bei genau einem Projekt gibt es nichts zu
                // wählen — dann steht die Auswahl vorbelegt da,
                // statt eine Entscheidung zu verlangen, die keine
                // ist.
                ->default(fn () => Project::query()
                    ->sichtbarFuer(auth()->user())
                    ->count() === 1
                        ? Project::query()->sichtbarFuer(auth()->user())->value('id')
                        : null)
                ->native(false)
                ->searchable()
                ->helperText('Zu welchem Projekt gehört es?'),

            TextInput::make('titel')
                ->label('Kurz gesagt')
                ->required()
                ->maxLength(255)
                ->placeholder('Neuer Text für die Startseite')
                ->helperText('Ein Satz genügt.'),

            Textarea::make('beschreibung')
                ->label('Ausführlich')
                ->rows(8)
                ->columnSpanFull()
                // Für alles, was kommt: einen Fehler, einen Wunsch, eine
                // Frage, einen fertigen Text für die Website. Die Absätze
                // bleiben stehen, wie sie getippt wurden (NID-31).
                ->placeholder(
                    "Was sollen wir tun, oder was ist Ihnen aufgefallen?\n\n".
                    'Texte für die Website können Sie direkt hier hineinschreiben — Absätze bleiben erhalten.',
                ),

            // Gleich hier und nicht erst hinterher am angelegten
            // Anliegen. Beides geht, aber wer einen Fehler meldet,
            // hat den Screenshot in genau diesem Moment auf dem
            // Bildschirm — eine Seite später ist er vergessen, und
            // dann kostet die Rückfrage "können Sie ein Bild
            // schicken?" beide Seiten einen halben Tag.
            // Dasselbe Feld wie beim internen Anlegen, siehe
            // Anhangfeld: gleiche Typen, gleiche Höchstgröße,
            // gleiche Ablage. Zwei Fassungen, die sich um ein
            // erlaubtes Format unterscheiden, fallen erst dem
            // Kunden auf.
            Anhangfeld::machen()
                ->label('Bilder und Dateien')
                ->helperText('Screenshots, Fotos oder PDF, je bis 16 MB. Ein Screenshot spart oft drei Absätze Beschreibung.'),
        ];
    }
}

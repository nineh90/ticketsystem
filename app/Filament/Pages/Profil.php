<?php

namespace App\Filament\Pages;

use App\Enums\MailEreignis;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Das eigene Profil auf der Brücke.
 *
 * Filaments Vorlage kann Name, Adresse und Passwort. Hier kommt das dazu,
 * was bis eben nur ein Administrator einstellen konnte: **welche Mails ich
 * bekomme.**
 *
 * Der Grund für den Umzug ist keine Bequemlichkeit. Die Auswahl stand unter
 * *Maschinenraum → Crew*, also an der Stelle, an der einer für einen anderen
 * entscheidet, was der zu lesen bekommt. Wer seine Mails nicht selbst gewählt
 * hat, schaltet sie beim ersten Ärger ganz ab — und danach erreicht ihn auch
 * das, was ihn wirklich angeht, nicht mehr.
 *
 * Im Formular unter Crew bleiben die Felder trotzdem stehen: einen frisch
 * angelegten Zugang muss man einstellen können, bevor sich jemand das erste
 * Mal anmeldet.
 *
 * Zur Auswahl stehen nur die Ereignisse, die nach innen gehen
 * (MailEreignis::vorgabeIntern). Was wir selbst nach außen schicken, ist
 * keine Nachricht an uns.
 */
class Profil extends EditProfile
{
    public function getTitle(): string
    {
        return 'Mein Zugang';
    }

    public function getHeading(): string
    {
        return 'Mein Zugang';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Zugang')
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                ]),

            Section::make('Benachrichtigungen')
                ->description('An der Glocke steht ohnehin alles. Hier entscheidest du, was zusätzlich als Mail hinausgeht.')
                ->schema([
                    Toggle::make('mail_benachrichtigungen')
                        ->label('E-Mail bei Meldungen')
                        // live, weil die Auswahl darunter daran hängt.
                        ->live()
                        ->helperText(fn () => 'Geht an '.$this->getUser()->email.'. Jederzeit hier wieder abschaltbar.'),

                    CheckboxList::make('mail_ereignisse')
                        ->label('Und zwar worüber')
                        ->options(collect(MailEreignis::cases())
                            ->filter(fn (MailEreignis $e) => $e->nachInnen())
                            ->mapWithKeys(fn (MailEreignis $e) => [$e->value => $e->getLabel()])
                            ->all())
                        ->descriptions(collect(MailEreignis::cases())
                            ->filter(fn (MailEreignis $e) => $e->nachInnen())
                            ->mapWithKeys(fn (MailEreignis $e) => [$e->value => $e->getDescription()])
                            ->all())
                        ->columns(2)
                        ->bulkToggleable()
                        ->default(MailEreignis::vorgabeIntern())
                        ->visible(fn (Get $get) => (bool) $get('mail_benachrichtigungen'))
                        // Der Hinweis auf die leere Liste steht hier, weil
                        // genau das der Fall ist, den man nicht sieht: nichts
                        // angehakt heißt nichts, nicht alles.
                        ->helperText('Nichts angehakt heißt: keine Mail.'),
                ]),

            Section::make('Passwort ändern')
                ->description('Leer lassen, wenn dein Passwort so bleiben soll.')
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                ]),
        ]);
    }

    /**
     * Wer hier gespeichert hat, hat die Frage beantwortet.
     *
     * Auch dann, wenn er den Schalter gar nicht angefasst hat — er hatte ihn
     * vor sich. Damit verschwindet der Hinweis auf der Wache, und zwar
     * genauso bei "nein" wie bei "ja": ein Hinweis, der nach der Entscheidung
     * stehen bleibt, ist eine Aufforderung.
     *
     * forceFill und nicht über das Formular: die Spalte steht bewusst nicht
     * in der Fillable-Liste des Models — sie ist ein Merker über den Nutzer,
     * keine Angabe von ihm. Ohne das würde der Wert beim Speichern
     * stillschweigend verworfen. Dasselbe Muster wie im Kundenbereich.
     */
    protected function afterSave(): void
    {
        $nutzer = $this->getUser();

        if ($nutzer->benachrichtigungen_gefragt_at !== null) {
            return;
        }

        $nutzer->forceFill(['benachrichtigungen_gefragt_at' => now()])->save();
    }
}

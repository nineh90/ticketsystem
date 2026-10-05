<?php

namespace App\Filament\Kunde\Widgets;

use App\Filament\Concerns\LegtAnliegenAn;
use App\Filament\Concerns\NimmtDateienEntgegen;
use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Filament\Kunde\Resources\Anliegen\Schemas\AnliegenForm;
use App\Models\Project;
use App\Models\Ticket;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

/**
 * "Etwas melden" direkt auf der Übersicht (NID-23).
 *
 * Vorher stand hier ein Knopf, der auf das Formular führte. Das ist ein Klick
 * und ein Seitenwechsel vor dem Einzigen, weswegen die meisten Kunden
 * überhaupt herkommen. Jetzt steht das Formular da, wo sie landen.
 *
 * Es sind dieselben Felder und dieselbe Prüfung wie auf der Seite "Neues
 * Anliegen" (AnliegenForm, LegtAnliegenAn) — die Seite bleibt bestehen, weil
 * die Kontaktseite und die Projektseite mit einer Vorauswahl dorthin führen.
 */
class EtwasMelden extends Widget implements HasSchemas
{
    use InteractsWithSchemas;
    use LegtAnliegenAn;
    use NimmtDateienEntgegen;

    protected string $view = 'filament.kunde.widgets.etwas-melden';

    /** Unter den Zahlen, über den Projekten. */
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * Ohne freigegebenes Projekt gibt es nichts, wozu man etwas melden
     * könnte — dann lieber keine Karte als ein Formular, das beim Absenden
     * erklärt, dass es nicht geht.
     */
    public static function canView(): bool
    {
        $nutzer = auth()->user();

        return $nutzer !== null
            && $nutzer->istKunde()
            && Project::query()->sichtbarFuer($nutzer)->exists();
    }

    public function mount(): void
    {
        $this->zwischenlagerAufraeumen();

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(AnliegenForm::alleFelder())
            ->statePath('data');
    }

    public function senden(): void
    {
        $daten = $this->anliegenDaten(
            $this->dateienAusFormular($this->form->getState()),
        );

        $anliegen = Ticket::create($daten);

        $this->dateienAnhaengen($anliegen);

        Notification::make()
            ->title('Ihr Anliegen ist bei uns eingegangen.')
            ->body($anliegen->kennung().' — '.$anliegen->titel)
            ->success()
            ->send();

        // Direkt in das angelegte Anliegen, wie von der Seite aus: dort
        // steht die Nummer, und der Kunde sucht nicht erst in einer Liste,
        // ob es angekommen ist.
        $this->redirect(AnliegenResource::getUrl('view', ['record' => $anliegen]));
    }
}

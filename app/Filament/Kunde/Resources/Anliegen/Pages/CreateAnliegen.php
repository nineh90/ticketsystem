<?php

namespace App\Filament\Kunde\Resources\Anliegen\Pages;

use App\Enums\TicketArt;
use App\Filament\Concerns\LegtAnliegenAn;
use App\Filament\Concerns\NimmtDateienEntgegen;
use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateAnliegen extends CreateRecord
{
    use LegtAnliegenAn;
    use NimmtDateienEntgegen;

    protected static string $resource = AnliegenResource::class;

    public function getTitle(): string
    {
        return 'Neues Anliegen';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Absenden');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        // "Erstellen und noch eines" ergibt hier keinen Sinn: wer zwei Dinge
        // zu melden hat, meldet sie einzeln — und zwei Meldungen in einem
        // Rutsch landen erfahrungsgemäß in einem Ticket.
        return parent::getCreateAnotherFormAction()->hidden();
    }

    /**
     * Vorgaben aus der Adresse übernehmen.
     *
     * Zwei Wege führen mit einer Vorauswahl hierher: „Etwas melden" auf einer
     * Projektseite (dann ist das Projekt gemeint, das man gerade ansieht) und
     * „Nur eine Frage stellen" auf der Kontaktseite. Ohne diese Methode
     * blieben die Anhängsel in der Adresse wirkungslos, und der Kunde müsste
     * noch einmal auswählen, was er gerade angeklickt hat.
     *
     * Beide Werte werden geprüft, nicht übernommen: aus der Adresse darf
     * weder ein fremdes Projekt noch eine erfundene Art werden. Ein falscher
     * Wert wird stillschweigend ignoriert — eine Fehlermeldung über einen
     * Parameter, den der Kunde nie gesehen hat, hülfe ihm nicht.
     */
    protected function fillForm(): void
    {
        $this->zwischenlagerAufraeumen();

        parent::fillForm();

        $vorgaben = [];

        $art = TicketArt::tryFrom((string) request()->query('art'));

        if ($art !== null && in_array($art, TicketArt::fuerKunden(), strict: true)) {
            $vorgaben['art'] = $art->value;
        }

        $projekt = Project::query()
            ->sichtbarFuer(auth()->user())
            ->whereKey(request()->query('project_id'))
            ->value('id');

        if ($projekt !== null) {
            $vorgaben['project_id'] = $projekt;
        }

        if ($vorgaben !== []) {
            $this->form->fill([...$this->form->getRawState(), ...$vorgaben]);
        }
    }

    /**
     * Nach dem Absenden direkt in das angelegte Anliegen.
     *
     * Nicht zurück in die Liste: dort suchte der Kunde erst, ob es
     * angekommen ist. Auf der Detailseite steht die Nummer, und darunter
     * hängen Antworten und Dateien — dort kann er den Screenshot gleich
     * anhängen, auf den das Formular ihn hingewiesen hat.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Ihr Anliegen ist bei uns eingegangen.';
    }

    /**
     * Alles, was nicht im Formular steht, wird hier gesetzt — siehe
     * LegtAnliegenAn, das dieselbe Prüfung auch für die Karte auf der
     * Übersicht stellt.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // "dateien" ist keine Spalte; die Dateien werden erst in afterCreate()
        // zugeordnet, wenn das Anliegen eine Nummer hat.
        return $this->anliegenDaten($this->dateienAusFormular($data));
    }

    /** Die mitgeschickten Dateien an das eben angelegte Anliegen hängen. */
    protected function afterCreate(): void
    {
        $this->dateienAnhaengen($this->getRecord());
    }
}

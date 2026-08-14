<?php

namespace App\Filament\Kunde\Resources\Anliegen\Pages;

use App\Enums\Quelle;
use App\Enums\TicketArt;
use App\Filament\Kunde\Resources\Anliegen\AnliegenResource;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\TicketStatus;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateAnliegen extends CreateRecord
{
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
     * Alles, was nicht im Formular steht, wird hier gesetzt.
     *
     * Das ist der eigentliche Schutz dieser Seite: der Kunde füllt vier
     * Felder aus, und die übrigen — Kunde, Herkunft, Stadium, Urheber —
     * ergeben sich, statt aus dem Browser zu kommen. Käme etwa project_id
     * ungeprüft aus dem Formular, ließe sich mit einer geänderten Anfrage ein
     * Anliegen in einem fremden Projekt anlegen.
     */
    /**
     * Die hochgeladenen Dateien, zwischengeparkt.
     *
     * Sie gehören nicht ins Ticket-Model — "dateien" ist keine Spalte —, und
     * zuordnen lassen sie sich erst, wenn das Anliegen eine ID hat. Deshalb
     * werden sie vor dem Anlegen aus den Formulardaten genommen und danach
     * in afterCreate() verarbeitet.
     *
     * @var list<string>
     */
    private array $dateien = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $nutzer = auth()->user();

        $this->dateien = array_values((array) ($data['dateien'] ?? []));
        unset($data['dateien']);

        // Gehört das Projekt wirklich diesem Kunden? Die Auswahlliste zeigt
        // nur passende, aber eine Auswahlliste ist keine Prüfung.
        $projekt = Project::query()
            ->sichtbarFuer($nutzer)
            ->whereKey($data['project_id'] ?? null)
            ->first();

        if ($projekt === null) {
            throw ValidationException::withMessages([
                'data.project_id' => 'Bitte wählen Sie eines Ihrer Projekte aus.',
            ]);
        }

        $stadium = TicketStatus::standard();

        if ($stadium === null) {
            throw ValidationException::withMessages([
                'data.titel' => 'Das System nimmt gerade keine Anliegen an. Bitte melden Sie sich direkt bei uns.',
            ]);
        }

        return [
            ...$data,
            'project_id' => $projekt->getKey(),
            'customer_id' => $projekt->customer_id,
            // Das erste Stadium der Reihenfolge — bei uns "Backlog". Neue
            // Anliegen kommen bewusst dort an und nicht in "Offen": erst
            // sehen wir sie an, dann werden sie eingeplant.
            'ticket_status_id' => $stadium->getKey(),
            'quelle' => Quelle::Kunde,
            'created_by' => $nutzer->getKey(),
            // Priorität bleibt auf dem Standardwert des Models. Wer sie
            // festlegt, sind wir.
        ];
    }

    /**
     * Die mitgeschickten Dateien an das eben angelegte Anliegen hängen.
     *
     * Sie liegen bis hierher im Zwischenlager anhaenge/eingang, weil beim
     * Hochladen noch keine Ticketnummer existierte. Jetzt wandern sie in den
     * Ordner des Anliegens — derselbe Aufbau wie bei intern hochgeladenen
     * Anhängen, damit es später nur eine Art von Ablage gibt.
     *
     * Fehler beim Verschieben werden übersprungen und nicht hochgeworfen: das
     * Anliegen ist zu diesem Zeitpunkt bereits angelegt und benachrichtigt,
     * und ein Abbruch hier hinterließe dem Kunden eine Fehlermeldung für
     * etwas, das in Wahrheit angekommen ist.
     */
    protected function afterCreate(): void
    {
        if ($this->dateien === []) {
            return;
        }

        $ticket = $this->getRecord();
        $platte = Storage::disk(Attachment::PLATTE);

        foreach ($this->dateien as $eingang) {
            $basis = basename($eingang);
            $ziel = 'anhaenge/'.$ticket->getKey().'/'.$basis;

            if (! $platte->exists($eingang)) {
                continue;
            }

            if ($eingang !== $ziel && ! $platte->move($eingang, $ziel)) {
                continue;
            }

            // Alles hinter "__" ist der ursprüngliche Dateiname; davor steht
            // der Zufallsvorsatz, der zwei "screenshot.png" auseinanderhält.
            $anzeigename = str_contains($basis, '__')
                ? Str::after($basis, '__')
                : $basis;

            $ticket->attachments()->create([
                'user_id' => auth()->id(),
                'pfad' => $ziel,
                'dateiname' => $anzeigename,
                'mime' => $platte->mimeType($ziel) ?: null,
                'groesse' => $platte->size($ziel),
            ]);
        }

        $this->dateien = [];
    }

    /**
     * Liegengebliebene Dateien im Zwischenlager wegräumen.
     *
     * Filament legt einen Upload sofort ab, auch wenn das Formular danach nie
     * abgeschickt wird — wer einen Screenshot anhängt und es sich anders
     * überlegt, hinterlässt eine Datei, die zu nichts mehr gehört. Das ist
     * derselbe Fall, für den Attachment::booted() beim Löschen die Datei
     * mitnimmt: verwaiste Dateien lassen sich niemandem mehr zuordnen, und
     * sie enthalten unter Umständen genau das, was weg sollte.
     *
     * Der Aufräumer hängt am Aufruf dieser Seite und nicht am Zeitplan: einen
     * Scheduler gibt es in diesem Projekt nicht (siehe deploy/entrypoint.sh),
     * ein schedule:run wäre also ein zusätzlicher Dauerprozess für eine
     * Handvoll Dateien. Wer ein Anliegen meldet, räumt beim Betreten das auf,
     * was jemand anders vor mehr als einem Tag stehengelassen hat.
     */
    private function zwischenlagerAufraeumen(): void
    {
        $platte = Storage::disk(Attachment::PLATTE);
        $grenze = now()->subDay()->getTimestamp();

        foreach ($platte->files('anhaenge/eingang') as $datei) {
            if ($platte->lastModified($datei) < $grenze) {
                $platte->delete($datei);
            }
        }
    }
}

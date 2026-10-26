<?php

namespace App\Mail;

use App\Support\Termin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Montags früh: deine Woche — siehe Wache::wochenplan.
 *
 * Dieselbe Vorlage wie die Sammelmail, nur nach Tagen statt nach Kunden
 * gruppiert. Zwei Vorlagen für "eine Liste mit Knöpfen" wären zwei Stellen,
 * an denen das nächste Postfach-Problem einzeln behoben werden muss.
 */
class Wochenmeldung extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, array<int, array{titel: string, text: ?string, url: ?string, farbe: ?string, kunde: ?string}>> */
    public array $gruppen = [];

    /** @var array<int, array<string, mixed>> */
    public array $posten = [];

    public int $treffen = 0;

    /**
     * @param  Collection<int, Termin>  $termine  in dieser Woche
     * @param  Collection<int, Termin>  $ueberfaellig  eigene Tickets, deren Termin vorbei ist
     */
    public function __construct(
        public string $vorname,
        Collection $termine,
        Collection $ueberfaellig,
    ) {
        if ($ueberfaellig->isNotEmpty()) {
            $this->gruppen['Überfällig'] = $ueberfaellig
                ->map(fn (Termin $t) => self::zeile($t, mitDatum: true, farbe: 'danger'))
                ->all();
        }

        foreach ($termine->groupBy(fn (Termin $t) => $t->zeitpunkt->format('Y-m-d')) as $tag => $amTag) {
            $this->gruppen[Carbon::parse($tag)->locale('de')->isoFormat('dddd, D. MMMM')] = $amTag
                ->map(fn (Termin $t) => self::zeile($t))
                ->all();
        }

        $this->posten = array_merge(...array_values($this->gruppen ?: [[]]));
        $this->treffen = $termine->where('art', Termin::TREFFEN)->count();
    }

    /** @return array{titel: string, text: ?string, url: ?string, farbe: ?string, kunde: ?string} */
    private static function zeile(Termin $termin, bool $mitDatum = false, ?string $farbe = null): array
    {
        // Unter "Überfällig" sagt die Überschrift schon, was es ist; dort
        // zählt nur, wie lange schon.
        $titel = $mitDatum
            ? 'seit '.$termin->zeitpunkt->format('d.m.').' · '.$termin->titel
            : collect([
                $termin->ganztaegig ? null : $termin->zeitpunkt->format('H:i').' Uhr',
                $termin->bezeichnung().': '.$termin->titel,
            ])->filter()->implode(' · ');

        return [
            'titel' => $titel,
            'text' => collect([$termin->kunde, $termin->zusatz])->filter()->implode(' · ') ?: null,
            'url' => $termin->url,
            'farbe' => $farbe ?? match ($termin->art) {
                Termin::TREFFEN => null,
                Termin::MEILENSTEIN => 'info',
                Termin::FRIST => 'warning',
                default => 'success',
            },
            'kunde' => $termin->kunde,
        ];
    }

    public function betreff(): string
    {
        $anzahl = count($this->posten);

        return 'Deine Woche: '.$anzahl.' '.($anzahl === 1 ? 'Termin' : 'Termine')
            .($this->treffen > 0 ? ', davon '.$this->treffen.' Treffen' : '');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->betreff());
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sammelmeldung',
            text: 'mail.sammelmeldung-text',
            with: [
                'betreff' => $this->betreff(),
                'fuerKunden' => false,
                'einleitung' => 'Moin '.$this->vorname.', das steht diese Woche für dich an.',
                'fussnote' => 'Kommt montags um 7 Uhr, weil an deinem Zugang „Meine Woche“ angehakt ist. Abschalten unter Mein Zugang.',
            ],
        );
    }
}

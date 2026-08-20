<?php

namespace App\Support;

use App\Enums\MailEreignis;
use App\Enums\Rolle;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Was der Planer von sich aus meldet — die Meldungen zur Wache.
 *
 * Alle diese Zahlen gab es vorher schon: was heute fällig ist, steht in der
 * Wochenvorschau, was liegen geblieben ist auf der Brücke, die laufende Uhr
 * in ihrer eigenen Karte. Sie hatten nur eines gemeinsam — man sah sie nur,
 * wenn man ohnehin hinsah. Genau das ist bei allem, was liegen bleibt, der
 * unwahrscheinlichste Fall.
 *
 * Deshalb hier: einmal am Tag, einmal in der Woche, einmal pro Ticket, das zu
 * lange wartet. Jede Meldung geht an genau den, der etwas tun kann, und
 * niemals an alle — eine Rundmail an drei Leute, von der zwei nichts zu tun
 * haben, bringt allen dreien bei, sie zu übergehen.
 *
 * Jede Methode gibt zurück, an wie viele sie gemeldet hat. Der Befehl
 * schreibt das ins Protokoll; ohne diese Zahl sähe ein Planer, der nichts
 * findet, genauso aus wie einer, der nicht läuft.
 */
class Wache
{
    /** Ab wann ein Anliegen ohne unsere Antwort auffällt. */
    public const ANTWORT_SPAETESTENS_STUNDEN = 24;

    /** Ab wann ein offenes Angebot als unbeantwortet gilt (siehe Kasse). */
    public const ANGEBOT_NACHFASSEN_TAGEN = 7;

    /**
     * Morgens: was heute an mir hängt.
     *
     * Nur die eigenen Tickets, und nur wenn welche da sind. Eine Mail, die
     * an manchen Tagen "nichts zu tun" sagt, ist eine, die man nach zwei
     * Wochen ungelesen wegwischt — und dann auch an dem Tag, an dem etwas
     * darin steht.
     */
    public static function morgenmeldung(): int
    {
        $gemeldet = 0;

        foreach (self::crew() as $nutzer) {
            $meine = Ticket::query()
                ->sichtbarFuer($nutzer)
                ->offen()
                ->where('assigned_to', $nutzer->getKey());

            $heute = (clone $meine)->whereDate('faellig_am', today())->count();
            $ueberfaellig = (clone $meine)->whereDate('faellig_am', '<', today())->count();

            if ($heute === 0 && $ueberfaellig === 0) {
                continue;
            }

            Benachrichtigung::an(
                collect([$nutzer]),
                Notification::make()
                    ->title($ueberfaellig > 0
                        ? $ueberfaellig.' überfällig, '.$heute.' heute fällig'
                        : $heute.' heute fällig')
                    ->body('Aus deinen offenen Tickets.')
                    ->icon('heroicon-o-sun')
                    ->color($ueberfaellig > 0 ? 'danger' : 'info')
                    ->actions([
                        Benachrichtigung::knopf('Ansehen', TicketResource::listeUrl('meine')),
                    ]),
                null,
                null,
                MailEreignis::Tagesmeldung,
            );

            $gemeldet++;
        }

        return $gemeldet;
    }

    /**
     * Abends: deine Uhr läuft noch.
     *
     * Bewusst nur eine Erinnerung und kein automatisches Stoppen. Eine
     * Buchung ist eine Aussage darüber, wie lange jemand gearbeitet hat —
     * die schreibt das System nicht selbst um. Es fragt nach, und der Rest
     * ist eine Entscheidung.
     */
    public static function laufendeUhren(): int
    {
        $laufend = TimeEntry::query()
            ->laufend()
            ->with(['user', 'ticket'])
            ->get()
            ->filter(fn (TimeEntry $zeit) => $zeit->user?->aktiv);

        foreach ($laufend->groupBy('user_id') as $zeiten) {
            $nutzer = $zeiten->first()->user;

            $laengste = $zeiten->sortByDesc(fn (TimeEntry $zeit) => $zeit->bisherigeMinuten())->first();

            Benachrichtigung::an(
                collect([$nutzer]),
                Notification::make()
                    ->title('Deine Uhr läuft noch')
                    ->body(trim(($laengste->ticket?->kennung() ?? '').' — seit '
                        .$laengste->gestartet_am->format('H:i').' Uhr, inzwischen '
                        .Dauer::alsStunden($laengste->bisherigeMinuten()).'.', ' —'))
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->actions([
                        Benachrichtigung::knopf(
                            'Ansehen',
                            $laengste->ticket
                                ? Benachrichtigung::urlIntern($laengste->ticket)
                                : TicketResource::listeUrl('meine'),
                        ),
                    ]),
                $laengste->ticket ? Herkunft::ticket($laengste->ticket) : null,
                null,
                MailEreignis::Tagesmeldung,
            );
        }

        return $laufend->groupBy('user_id')->count();
    }

    /**
     * Wöchentlich: was ohne Bewegung liegt.
     *
     * Zwei Empfängerkreise, weil es zwei Sorten Liegengebliebenes gibt: was
     * jemandem gehört, geht an ihn — was niemandem gehört, an die
     * Administratoren. Ein unzugeteiltes Ticket ist keine vergessene Aufgabe,
     * sondern eine ungetroffene Entscheidung, und Zuteilen ist ihre.
     */
    public static function liegengebliebenes(): int
    {
        $gemeldet = 0;

        foreach (self::crew() as $nutzer) {
            $ruhend = Ticket::query()
                ->sichtbarFuer($nutzer)
                ->ruhend()
                ->where('assigned_to', $nutzer->getKey())
                ->count();

            if ($ruhend === 0) {
                continue;
            }

            Benachrichtigung::an(
                collect([$nutzer]),
                Notification::make()
                    ->title($ruhend.' Ticket'.($ruhend === 1 ? '' : 's').' ohne Bewegung')
                    ->body('Seit mindestens '.Ticket::RUHEND_AB_TAGEN.' Tagen offen, ohne Änderung und ohne Kommentar.')
                    ->icon('heroicon-o-moon')
                    ->color('warning')
                    ->actions([
                        Benachrichtigung::knopf('Ansehen', TicketResource::listeUrl('meine', 'ruhend')),
                    ]),
                null,
                null,
                MailEreignis::Liegengeblieben,
            );

            $gemeldet++;
        }

        $unzugeteilt = Ticket::query()->offen()->whereNull('assigned_to')->count();

        if ($unzugeteilt > 0) {
            $admins = self::admins();

            if ($admins->isNotEmpty()) {
                Benachrichtigung::an(
                    $admins,
                    Notification::make()
                        ->title($unzugeteilt.' Ticket'.($unzugeteilt === 1 ? '' : 's').' ohne Zuständige')
                        ->body('Offen und niemandem zugeteilt.')
                        ->icon('heroicon-o-user-minus')
                        ->color('warning')
                        ->actions([
                            Benachrichtigung::knopf('Zuteilen', TicketResource::listeUrl('unzugewiesen')),
                        ]),
                    null,
                    null,
                    MailEreignis::Liegengeblieben,
                );

                $gemeldet += $admins->count();
            }
        }

        return $gemeldet;
    }

    /**
     * Stündlich: ein Kunde wartet zu lange auf unsere erste Antwort.
     *
     * Das ist die einzige Meldung hier, die etwas misst, das wir versprochen
     * haben. Alles andere ist unser eigener Haushalt; hier steht draußen
     * jemand, der nichts hört.
     *
     * "Antwort" heißt: ein Kommentar von uns, den der Kunde auch sieht. Eine
     * interne Notiz zählt nicht — der Kunde hat davon nichts, und genau die
     * Verwechslung wäre die teuerste: wir hätten das Gefühl, geantwortet zu
     * haben.
     */
    public static function kundeWartet(): int
    {
        $grenze = now()->subHours(self::ANTWORT_SPAETESTENS_STUNDEN);

        $wartende = Ticket::query()
            ->offen()
            ->vomKunden()
            ->whereNull('nachgehakt_at')
            ->where('created_at', '<', $grenze)
            ->whereDoesntHave('comments', fn (Builder $q) => $q
                ->where('ist_intern', false)
                ->whereHas('autor', fn (Builder $a) => $a->where('rolle', '!=', Rolle::Kunde->value)))
            ->with(['customer', 'project'])
            ->get();

        foreach ($wartende as $ticket) {
            // Stempel zuerst, wie bei den Terminerinnerungen: von den beiden
            // möglichen Fehlern ist die stündlich wiederkehrende Meldung der
            // schlimmere.
            $ticket->forceFill(['nachgehakt_at' => now()])->saveQuietly();

            Benachrichtigung::nachInnen(
                $ticket,
                Notification::make()
                    ->title('Wartet seit '.$ticket->created_at->diffForHumans(short: true))
                    ->body($ticket->kennung().' · '.$ticket->customer?->name.' — '.$ticket->titel)
                    ->icon('heroicon-o-hand-raised')
                    ->color('danger')
                    ->actions([
                        Benachrichtigung::knopf('Antworten', Benachrichtigung::urlIntern($ticket)),
                    ]),
                MailEreignis::Liegengeblieben,
            );
        }

        return $wartende->count();
    }

    /**
     * Alle, die an Bord arbeiten — Administratoren eingeschlossen.
     *
     * @return Collection<int, User>
     */
    private static function crew(): Collection
    {
        return User::query()
            ->where('aktiv', true)
            ->where('rolle', '!=', Rolle::Kunde->value)
            ->get();
    }

    /** @return Collection<int, User> */
    private static function admins(): Collection
    {
        return User::query()
            ->where('aktiv', true)
            ->where('rolle', Rolle::Admin->value)
            ->get();
    }
}

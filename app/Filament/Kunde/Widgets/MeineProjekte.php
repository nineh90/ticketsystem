<?php

namespace App\Filament\Kunde\Widgets;

use App\Models\Project;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Die Projekte des Kunden auf seiner Übersicht — als Karten.
 *
 * Vorher war das eine Tabelle. Sie hat funktioniert und trotzdem das Falsche
 * getan: eine Tabelle ist zum Vergleichen vieler gleichartiger Zeilen da. Ein
 * Kunde hat zwei Projekte, und er vergleicht sie nicht, sondern sieht nach,
 * wie weit seines ist. Dafür braucht jede Zeile Platz — für den Stand, den
 * Fortschritt und den Knopf, um den es eigentlich geht: die Seite ansehen.
 *
 * Die Zahlen kommen aus einer einzigen Abfrage mit withCount statt aus einem
 * count() je Karte. Bei zwei Projekten ist das gleichgültig, bei zwölf nicht,
 * und ausgerechnet die Übersicht ist die Seite, die jeder zuerst öffnet.
 */
class MeineProjekte extends Widget
{
    protected string $view = 'filament.kunde.widgets.meine-projekte';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** @return Collection<int, Project> */
    public function getProjekte(): Collection
    {
        return Project::query()
            ->sichtbarFuer(auth()->user())
            ->withCount([
                'tickets as offene_anliegen' => fn ($q) => $q->offen(),
                'tickets as am_zug' => fn ($q) => $q->wartetAufKunde(),
                'meilensteine as meilensteine_gesamt' => fn ($q) => $q->kundenSichtbar(),
                'meilensteine as meilensteine_erledigt' => fn ($q) => $q->kundenSichtbar()->erledigt(),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Der Fortschritt aus den mitgeladenen Zählern.
     *
     * Bewusst nicht Project::fortschritt(): die Methode fragt zweimal nach,
     * und hier stehen die Zahlen schon da. Beide rechnen dasselbe — was
     * doppelt gepflegt werden müsste, ist die Regel "ohne Meilensteine gibt
     * es keinen Balken", und die steht deshalb in beiden ausdrücklich.
     */
    public function fortschritt(Project $projekt): ?int
    {
        if (($projekt->meilensteine_gesamt ?? 0) === 0) {
            return null;
        }

        return (int) round($projekt->meilensteine_erledigt / $projekt->meilensteine_gesamt * 100);
    }
}

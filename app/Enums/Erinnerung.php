<?php

namespace App\Enums;

use App\Models\Treffen;

/**
 * Wann vor einem Treffen daran erinnert wird.
 *
 * Zwei Stufen, und beide haben einen anderen Zweck: einen Tag vorher, damit
 * man den Termin in seinen Tag einplanen kann — eine Stunde vorher, damit man
 * ihn nicht verpasst. Die erste ist eine Ansage, die zweite ein Weckruf.
 *
 * Als Aufzählung und nicht als zwei Zahlen im Befehl: an jeder Stufe hängen
 * drei Dinge zusammen (Vorlauf, die Spalte, in der ihr Versand vermerkt wird,
 * und der Anlass, der in der Meldung steht). Stünden die verstreut, hätte die
 * nächste Stufe — "eine Woche vorher" für Quartalsgespräche etwa — drei
 * Fundstellen statt einer.
 */
enum Erinnerung: string
{
    /** 24 Stunden vorher. */
    case Tag = 'tag';

    /** Eine Stunde vorher. */
    case Stunde = 'stunde';

    /** Der Vorlauf in Minuten. */
    public function vorlaufMinuten(): int
    {
        return match ($this) {
            self::Tag => 24 * 60,
            self::Stunde => 60,
        };
    }

    /**
     * Wo vermerkt wird, dass diese Stufe erledigt ist.
     *
     * Ein Zeitstempel je Stufe am Treffen und keine eigene Tabelle: es sind
     * zwei feste Stufen, und die Frage "ist das schon raus?" beantwortet die
     * Zeile, um die es geht, am besten selbst.
     */
    public function spalte(): string
    {
        return match ($this) {
            self::Tag => 'erinnert_24h_at',
            self::Stunde => 'erinnert_1h_at',
        };
    }

    /**
     * Was in der Meldung vorne steht.
     *
     * Die Tagesstufe fragt das Treffen, statt "Morgen" festzuschreiben:
     * normalerweise trifft es zu — die Meldung geht auf die Minute genau 24
     * Stunden vorher raus —, aber wenn der Planer eine Weile stand, holt er
     * sie später nach, und dann stünde "Morgen" über einem Termin von heute
     * Nachmittag. Ein Wort, das nachweislich falsch sein kann, ist an der
     * Stelle das schlechtere.
     */
    public function anlass(Treffen $treffen): string
    {
        return match ($this) {
            self::Tag => $treffen->beginnt_am->isTomorrow() ? 'Morgen' : 'Heute',
            self::Stunde => 'Gleich',
        };
    }

    /**
     * Lohnt sich diese Stufe für dieses Treffen überhaupt noch?
     *
     * Zwei Fälle, in denen die Antwort nein ist, und beide sind derselbe
     * Gedanke: eine Meldung, die nichts Neues sagt, macht die nächste
     * unglaubwürdiger.
     *
     *  - **Das Treffen ist erst innerhalb des Vorlaufs entstanden.** Wer um
     *    halb zwei einen Termin für zwei Uhr ansetzt, weiß von ihm. Die Crew
     *    hat ihre Einladung im selben Moment bekommen.
     *  - **Die kürzere Stufe steht schon an.** Ein "Morgen", das um Viertel
     *    vor zwei rausgeht, weil der Planer stand, käme eine Minute vor dem
     *    "Gleich" an.
     *
     * In beiden Fällen wird die Stufe trotzdem abgehakt (der Befehl stempelt
     * unabhängig davon), damit sie nicht bei jedem Lauf erneut geprüft wird.
     */
    public function lohntSich(Treffen $treffen): bool
    {
        $beginn = $treffen->beginnt_am;

        if ($treffen->created_at?->greaterThan($beginn->copy()->subMinutes($this->vorlaufMinuten()))) {
            return false;
        }

        if ($this === self::Tag && now()->greaterThanOrEqualTo($beginn->copy()->subMinutes(self::Stunde->vorlaufMinuten()))) {
            return false;
        }

        return true;
    }
}

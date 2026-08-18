<?php

namespace Database\Factories;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dokument>
 */
class DokumentFactory extends Factory
{
    public function definition(): array
    {
        $datum = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'art' => DokumentArt::Rechnung,
            'titel' => fake()->sentence(3),
            'nummer' => 'R-2026-'.fake()->unique()->numberBetween(100, 999),
            'datum' => $datum,
            'faellig_am' => (clone $datum)->modify('+14 days'),
            'betrag' => fake()->randomFloat(2, 100, 5000),
            'stand' => DokumentStand::Offen,
            // Ein Pfad, hinter dem keine Datei liegt. Für alles außer der
            // Ausliefer-Route reicht das; die Tests, die eine echte Datei
            // brauchen, legen sie mit Storage::fake selbst an.
            'pfad' => 'dokumente/test/'.fake()->uuid().'__rechnung.pdf',
            'dateiname' => 'rechnung.pdf',
            'mime' => 'application/pdf',
            'groesse' => 120_000,
            'kunden_sichtbar' => false,
        ];
    }

    /** Ein offenes Angebot, wie der Kunde es zu sehen bekommt. */
    public function angebot(): static
    {
        return $this->state(fn () => [
            'art' => DokumentArt::Angebot,
            'nummer' => 'A-2026-'.fake()->unique()->numberBetween(100, 999),
            'stand' => DokumentStand::Offen,
            'dateiname' => 'angebot.pdf',
        ]);
    }

    /** Freigegeben — der Kunde sieht es. */
    public function freigegeben(): static
    {
        return $this->state(fn () => ['kunden_sichtbar' => true]);
    }

    /**
     * Vom Kunden beantwortet.
     *
     * Setzt die beiden Spalten direkt: sie stehen nicht in der
     * Fillable-Liste, weil eine Antwort nur über
     * Dokument::vomKundenBeantworten entstehen soll — im Test ist der
     * Umweg über die Oberfläche aber nicht der Punkt.
     */
    public function beantwortet(User $wer, DokumentStand $stand = DokumentStand::Angenommen): static
    {
        return $this->angebot()->freigegeben()->state(fn () => [
            'stand' => $stand,
            'beantwortet_at' => now(),
            'beantwortet_von' => $wer->getKey(),
        ]);
    }

    /** Offen und die Frist ist durch. */
    public function ueberfaellig(): static
    {
        return $this->state(fn () => [
            'stand' => DokumentStand::Offen,
            'faellig_am' => now()->subDays(10),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Treffen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treffen>
 */
class TreffenFactory extends Factory
{
    protected $model = Treffen::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'titel' => 'Quartalsgespräch',
            'beginnt_am' => now()->addWeek()->setTime(14, 0),
            'dauer_minuten' => 30,
            'url' => 'https://meet.google.com/abc-defg-hij',
            // Wie am Modell: aus. Ein Test, der die Kundensicht prüft, sagt
            // dann ausdrücklich eingeladen() — und liest sich damit wie das,
            // was er prüft.
            'kunden_sichtbar' => false,
        ];
    }

    /** Freigegeben — der Kunde sieht es und ist benachrichtigt worden. */
    public function eingeladen(): static
    {
        return $this->state(['kunden_sichtbar' => true]);
    }

    public function abgesagt(): static
    {
        return $this->state(['abgesagt_at' => now()]);
    }

    /** Läuft gerade — begann vor fünf Minuten. */
    public function laufend(): static
    {
        return $this->state([
            'beginnt_am' => now()->subMinutes(5),
            'dauer_minuten' => 30,
        ]);
    }

    public function vergangen(): static
    {
        return $this->state(['beginnt_am' => now()->subWeek()]);
    }
}

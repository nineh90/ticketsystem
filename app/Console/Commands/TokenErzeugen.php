<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TokenErzeugen extends Command
{
    protected $signature = 'ticket:token
        {--website : Token für die Website statt für n8n}';

    protected $description = 'Erzeugt einen Token für die Schnittstelle (n8n oder Website)';

    public function handle(): int
    {
        $token = Str::random(48);

        // Zwei Aufrufer, zwei Variablen: an der Variable hängt, welche
        // Quelle die Tickets tragen, und jede lässt sich einzeln neu belegen.
        [$wer, $variable] = $this->option('website')
            ? ['die Website', 'TICKET_API_TOKEN_WEBSITE']
            : ['n8n', 'TICKET_API_TOKEN'];

        $this->newLine();
        $this->line("  Neuer Token für {$wer}:");
        $this->newLine();
        $this->line('  <fg=cyan>'.$token.'</>');
        $this->newLine();
        $this->line('  In die .env eintragen und danach die Konfiguration neu einlesen:');
        $this->line("    {$variable}={$token}");
        $this->line('    php artisan config:cache');
        $this->newLine();
        $this->comment('  Wird hier nur einmal angezeigt — es gibt keine Kopie.');
        $this->newLine();

        return self::SUCCESS;
    }
}

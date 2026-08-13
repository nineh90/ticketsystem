<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TokenErzeugen extends Command
{
    protected $signature = 'ticket:token';

    protected $description = 'Erzeugt einen Token für die n8n-Schnittstelle';

    public function handle(): int
    {
        $token = Str::random(48);

        $this->newLine();
        $this->line('  Neuer Token für die Schnittstelle:');
        $this->newLine();
        $this->line('  <fg=cyan>'.$token.'</>');
        $this->newLine();
        $this->line('  In die .env eintragen und danach die Konfiguration neu einlesen:');
        $this->line('    TICKET_API_TOKEN='.$token);
        $this->line('    php artisan config:cache');
        $this->newLine();
        $this->comment('  Wird hier nur einmal angezeigt — es gibt keine Kopie.');
        $this->newLine();

        return self::SUCCESS;
    }
}

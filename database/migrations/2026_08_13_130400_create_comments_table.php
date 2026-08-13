<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');

            // Der Schalter für den späteren Kundenbereich. Default true, also
            // intern — ein versehentlich nicht gesetztes Flag darf nie dazu
            // führen, dass eine interne Notiz beim Kunden auftaucht. Die
            // Richtung des Defaults ist hier die ganze Sicherheitsmaßnahme.
            $table->boolean('ist_intern')->default(true);

            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};

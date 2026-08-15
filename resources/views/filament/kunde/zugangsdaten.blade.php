{{--
    Die Zugangsdaten, die ein Kunde sehen darf.

    Zwei Entscheidungen bestimmen das Aussehen:

    1. Das Passwort steht nicht offen da. Es ist einen Klick entfernt — und
       der Klick ist der Unterschied zwischen "Zugangsdaten liegen offen auf
       dem Bildschirm" und "ich habe sie gerade nachgesehen". Wer im Zug
       sitzt oder den Bildschirm teilt, hat das Passwort nicht versehentlich
       vorgezeigt.

    2. Es gibt einen Kopierknopf. Ohne ihn tippt man ein Passwort ab, das
       genau dafür nicht gemacht ist, und schreibt es sich beim dritten
       Versuch in eine Notiz-App — womit es dann dort steht.

    Die Einträge kommen fertig gefiltert herein (Zugangsdaten::sichtbarFuer).
    Diese Ansicht filtert bewusst nicht selbst: sie wird an zwei Stellen
    eingebunden, und eine Sichtbarkeitsregel, die in einer Blade-Datei steht,
    ist eine, die man beim dritten Einbinden vergisst.
--}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Zugangsdaten> $eintraege */
@endphp

@if ($eintraege->isNotEmpty())
    <div class="space-y-3">
        @foreach ($eintraege as $eintrag)
            <div
                x-data="{
                    offen: false,
                    kopiert: null,
                    async kopieren(wert, feld) {
                        try {
                            await navigator.clipboard.writeText(wert);
                            this.kopiert = feld;
                            setTimeout(() => (this.kopiert = null), 1500);
                        } catch (e) {
                            // Ohne Zwischenablage-Recht (etwa über eine
                            // unverschlüsselte Verbindung) bleibt der Weg
                            // über das Aufdecken und Markieren.
                            this.offen = true;
                        }
                    },
                }"
                class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $eintrag->bezeichnung }}
                        </p>

                        @if (filled($eintrag->url))
                            <a
                                href="{{ $eintrag->url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-0.5 inline-flex items-center gap-1 text-xs text-primary-500 hover:underline"
                            >
                                <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5" />
                                {{ str($eintrag->url)->after('://')->limit(45) }}
                            </a>
                        @endif
                    </div>

                    @if ($eintrag->hatAnmeldedaten())
                        <button
                            type="button"
                            x-on:click="offen = ! offen"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-950/10 hover:bg-white dark:text-gray-300 dark:ring-white/20 dark:hover:bg-white/10"
                        >
                            <span x-show="! offen">Anzeigen</span>
                            <span x-show="offen" x-cloak>Verbergen</span>
                        </button>
                    @endif
                </div>

                @if ($eintrag->hatAnmeldedaten())
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        @if (filled($eintrag->benutzername))
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Benutzername</dt>
                                <dd class="mt-0.5 flex items-center gap-2">
                                    <code class="truncate rounded bg-white px-2 py-1 font-mono text-xs text-gray-950 ring-1 ring-gray-950/5 dark:bg-gray-950/50 dark:text-white dark:ring-white/10">{{ $eintrag->benutzername }}</code>

                                    <button
                                        type="button"
                                        x-on:click="kopieren(@js($eintrag->benutzername), 'benutzer')"
                                        class="shrink-0 text-gray-400 hover:text-primary-500"
                                        title="Kopieren"
                                    >
                                        <x-filament::icon
                                            icon="heroicon-m-clipboard-document"
                                            class="h-4 w-4"
                                            x-show="kopiert !== 'benutzer'"
                                        />
                                        <x-filament::icon
                                            icon="heroicon-m-check"
                                            class="h-4 w-4 text-success-500"
                                            x-show="kopiert === 'benutzer'"
                                            x-cloak
                                        />
                                    </button>
                                </dd>
                            </div>
                        @endif

                        @if (filled($eintrag->passwort))
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Passwort</dt>
                                <dd class="mt-0.5 flex items-center gap-2">
                                    <code class="truncate rounded bg-white px-2 py-1 font-mono text-xs text-gray-950 ring-1 ring-gray-950/5 dark:bg-gray-950/50 dark:text-white dark:ring-white/10">
                                        <span x-show="! offen">••••••••••</span>
                                        <span x-show="offen" x-cloak>{{ $eintrag->passwort }}</span>
                                    </code>

                                    <button
                                        type="button"
                                        x-on:click="kopieren(@js($eintrag->passwort), 'passwort')"
                                        class="shrink-0 text-gray-400 hover:text-primary-500"
                                        title="Kopieren"
                                    >
                                        <x-filament::icon
                                            icon="heroicon-m-clipboard-document"
                                            class="h-4 w-4"
                                            x-show="kopiert !== 'passwort'"
                                        />
                                        <x-filament::icon
                                            icon="heroicon-m-check"
                                            class="h-4 w-4 text-success-500"
                                            x-show="kopiert === 'passwort'"
                                            x-cloak
                                        />
                                    </button>
                                </dd>
                            </div>
                        @endif
                    </dl>
                @endif

                @if (filled($eintrag->hinweis))
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ $eintrag->hinweis }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>
@endif

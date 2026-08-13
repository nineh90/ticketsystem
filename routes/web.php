<?php

/*
 * Die Wurzel gehört dem Filament-Panel (siehe AdminPanelProvider: ->path('')).
 * Deshalb steht hier bewusst keine "/"-Route — sie würde sich mit dem
 * Dashboard bzw. der Weiterleitung auf /login ins Gehege kommen.
 *
 * Öffentliche Routen, die einmal ohne Login erreichbar sein sollen, kommen
 * hierher. Die Schnittstelle für n8n liegt getrennt in routes/api.php.
 */

<?php declare(strict_types=1);

// German texts of the user-visible software strings (D-1488 began with the choosers; all screens since D-1500).
return [
    'project-id-version' => 'Taxmod GLS',
    'language'           => 'de_DE',
    'plural-forms'       => 'nplurals=2; plural=(n != 1);',
    'messages'           => [
        'Client key'                                                                     => 'Client-Key',
        'Client key and secret of your app in the GLS developer portal (Apps → My Apps). The secret is stored encrypted.' => 'Client-Key und Secret deiner App im GLS Developer Portal (Apps → My Apps). Das Secret wird verschlüsselt gespeichert.',
        'Client key or secret is missing — enter and save both first, or define them in wp-config.php.' => 'Client-Key oder Secret fehlt — erst beides eintragen und speichern oder in wp-config.php setzen.',
        'Client secret'                                                                  => 'Client-Secret',
        'Connection failed (%1$s): %2$s'                                                 => 'Verbindung fehlgeschlagen (%1$s): %2$s',
        'Connection works (%1$s): %2$s.'                                                 => 'Verbindung steht (%1$s): %2$s.',
        'Copy client key and secret of the app (Apps → My Apps). Never post them in a chat.' => 'Client-Key und Secret der App kopieren (Apps → My Apps). Nie in einen Chat schreiben.',
        'Enter both here, save and click «Test connection». If the test says the web server cannot reach GLS, your host blocks outgoing calls.' => 'Beides hier eintragen, speichern und «Verbindung testen» klicken. Meldet der Test, dass der Webserver GLS nicht erreicht, blockt der Hoster ausgehende Aufrufe.',
        'For the API: sign in at the GLS developer portal, create an app and enable «Track And Trace V1» (it uses the «Authentication API v2»).' => 'Für die API: im GLS Developer Portal anmelden, eine App anlegen und «Track And Trace V1» aktivieren (nutzt die «Authentication API v2»).',
        'GLS calls today: %1$d of %2$d. Other modules ask through the filter taxmod_gls_tracking, only when a page asks for fresh data.' => 'GLS-Abfragen heute: %1$d von %2$d. Andere Module fragen über den Filter taxmod_gls_tracking, nur wenn eine Seite frische Daten will.',
        'Gateway'                                                                        => 'Gateway',
        'Last test %1$s (%2$s): %3$s'                                                    => 'Letzter Test %1$s (%2$s): %3$s',
        'Let GLS send its parcel notices to the mailbox of Taxmod Mail (or forward them there). Each notice goes to the order with the same parcel number; it never creates an order.' => 'GLS die Paketbenachrichtigungen an das Postfach von Taxmod Mail schicken lassen (oder dorthin weiterleiten). Jede Benachrichtigung geht an die Bestellung mit derselben Paketnummer; sie legt nie eine Bestellung an.',
        'Production (real parcels)'                                                      => 'Produktion (echte Pakete)',
        'Sandbox (test data)'                                                            => 'Sandbox (Testdaten)',
        'Set in wp-config.php (TAXGLS_CLIENT_KEY, TAXGLS_CLIENT_SECRET), gateway: %s. This takes precedence over the fields.' => 'In wp-config.php gesetzt (TAXGLS_CLIENT_KEY, TAXGLS_CLIENT_SECRET), Gateway: %s. Das geht den Feldern vor.',
        'The stored secret no longer opens — the salts in wp-config.php changed. Enter it again.' => 'Das gespeicherte Secret lässt sich nicht mehr öffnen — die Salts in wp-config.php haben sich geändert. Bitte neu eingeben.',
        'This Taxonomy Modeller has no vault yet (0.1.31 or later), so the access can only be defined in wp-config.php:' => 'Dieser Taxonomy Modeller hat noch keinen Tresor (ab 0.1.31), der Zugang geht daher nur über wp-config.php:',
        'needs the Taxonomy Modeller 0.1.89 or later and Taxmod Mail 0.6.0 or later.'    => 'braucht den Taxonomy Modeller 0.1.89 und Taxmod Mail 0.6.0 oder neuer.',
        'needs the Taxonomy Modeller 0.2.237 or later and Taxmod Mail 0.6.0 or later.'   => 'braucht den Taxonomy Modeller ab 0.2.237 und Taxmod Mail ab 0.6.0.',
        'run "composer install" in the plugin folder — the autoloader is missing.'       => 'im Plugin-Ordner «composer install» ausführen — der Autoloader fehlt.',
        'stored — leave empty to keep it'                                                => 'gespeichert — leer lassen, um es zu behalten',
    ],
];

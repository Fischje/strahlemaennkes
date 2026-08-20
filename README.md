# Strahlemännkes – PHP/SQLite PWA

Mobile Web-App für den Schützenzug **„Strahlemännkes“** der St. Maria Männerbruderschaft Bettrath.

## Technischer Ansatz
- PHP 8.2+
- SQLite über `pdo_sqlite`
- Bootstrap 5
- Progressive Web App (PWA): installierbar auf iPhone/Android, App-Icon, Standalone-Modus, Offline-Fallback
- optional echter Web-Push per VAPID und `minishlink/web-push`

Die Datenbank liegt standardmäßig in `data/strahlemaennkes.sqlite`. Es ist kein MySQL-Server nötig.

## Installation
1. Dateien in den Webroot kopieren.
2. PHP-Erweiterung `pdo_sqlite` aktivieren.
3. Schreibrechte für `data/` sicherstellen.
4. `/setup.php` aufrufen und den ersten Admin anlegen.
5. Über `/login.php` anmelden.
6. Für eine PWA im Produktivbetrieb HTTPS verwenden.

Die SQLite-Datei und das Schema werden beim ersten Aufruf automatisch angelegt. Neue SQL-Migrationen im Ordner `migrations/` werden bei späteren Updates automatisch genau einmal ausgeführt.

## PWA / Handy-App
Die App enthält:
- `manifest.webmanifest`
- Service Worker
- PWA-Icons und Apple-Touch-Icon
- mobilen Bottom-Navigation-Bar für eingeloggte Nutzer
- Offline-Fallback (keine veralteten eingeloggten HTML-Seiten werden gecacht)
- Installationsbutton/-hinweis
- Safe-Area-Unterstützung für moderne iPhones

**Android:** Im Browser „App installieren“ / „Zum Startbildschirm hinzufügen“ wählen.

**iPhone/iPad:** In Safari `Teilen` → `Zum Home-Bildschirm`.

## Getränkefunktion
### Mitglied
- ein Getränk aus der freigegebenen Liste auswählen
- Auswahl bleibt aktiv, bis sie geändert wird
- „Derzeit nichts“ setzen
- neues Getränk vorschlagen und sofort selbst verwenden
- Push-Benachrichtigungen aktivieren

### Spieß / Admin
- Getränkevorschläge freigeben
- Getränke aktivieren/deaktivieren und neue anlegen
- alle Status für einen neuen Abend zurücksetzen
- alle oder nur Konten ohne Auswahl erinnern
- eigene **Thekenansicht** unter `/theke.php`

## Thekenansicht
Die Ansicht ist für den schnellen Einsatz am Tresen optimiert:
- große Anzahl je Getränk
- nur die wichtigsten Informationen im ersten Blick
- aufklappbare Namen der Besteller
- automatische Aktualisierung alle 15 Sekunden
- manueller Refresh
- optional „Display wach halten“ über die Screen Wake Lock API, wenn der Browser das unterstützt

## Web-Push (optional)
Composer-Abhängigkeiten installieren:

```bash
composer install
```

Danach als Umgebungsvariablen setzen:
- `VAPID_SUBJECT`
- `VAPID_PUBLIC_KEY`
- `VAPID_PRIVATE_KEY`

Ohne VAPID funktioniert die App vollständig weiter; Erinnerungen werden dann intern protokolliert, aber nicht als System-Push zugestellt.

## Backup und Updates
Für ein einfaches Backup die SQLite-Datei sichern:

`data/strahlemaennkes.sqlite`

Empfehlung beim Update:
1. Datenbankdatei sichern.
2. neue App-Dateien hochladen.
3. Seite öffnen.
4. Migrationen laufen automatisch.

Optional kann die Datenbank per `SQLITE_PATH` außerhalb des Webroots gespeichert werden.

## Rollen
- **Mitglied:** eigenes Getränk, Vorschläge, Push
- **Spieß:** zusätzlich Getränkezentrale, Reset, Erinnerung und Thekenansicht
- **Admin:** zusätzlich Benutzerverwaltung

## Später vorgesehen
Strafen, Kalender, Zugereignisse und Stammtischplanung können als neue Module und Migrationen ergänzt werden.

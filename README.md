# Strahlemännkes Web-App

Interne, mobile Web-App (PWA) für den Schützenzug **„Strahlemännkes“**: Getränkewahl und Thekenansicht, Runden, Kasse, Stammtisch mit Terminabstimmung, Tagesordnung und Protokoll, Antreten, Chronik und Mitgliederverwaltung.

## Technik

- PHP 8.2+ mit `pdo_pgsql`, `gd`, `fileinfo`, `mbstring`
- PostgreSQL 15+
- Bootstrap 5.3 und Bootstrap Icons (per CDN), eigenes Design in `assets/css/app.css` + `assets/css/theme-d.css` (helles Design „D“, wird nach `app.css` geladen)
- Web-Push über `minishlink/web-push` (Composer)
- Progressive Web App: Manifest, Service Worker, Offline-Seite

## Einrichtung auf dem Server

1. Code nach `/srv/strahlemaennkes` (der Server holt `main` per fast-forward).
2. `composer install --no-dev --optimize-autoloader` im App-Verzeichnis.
3. `config.php` liegt nur auf dem Server (nicht im Repo, siehe `.gitignore`) und liest die Zugangsdaten aus Umgebungsvariablen der php-fpm-Pool-Datei:
   - `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, optional `PGSSLMODE`
   - `APP_URL` bzw. `base_url` in `config.php` (z. B. `https://xn--strahlemnnkes-ifb.de`, Umlaut-Domains in Punycode) – **wichtig**: daraus werden die Links in Passwort-Reset-Mails gebaut. Ist kein Wert gesetzt, nimmt die App den Host aus der Anfrage, und der ist von außen beeinflussbar.
   - `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` für Push
   - optional `APP_LEGACY_HOSTS` bzw. `legacy_hosts` in `config.php` (kommagetrennt, Umlaut-Domains in Punycode): alte Adressen nach einem Domain-Umzug. Dort ist dann nur noch die Umzugsseite `umzug.php` erreichbar. Ablauf siehe `docs/domain-umzug.md`.
4. Ordner für geschützte Dateien (außerhalb des Webroots):
   ```bash
   sudo install -d -o www-data -g www-data -m 0750 \
     /srv/strahlemaennkes-private/meeting-images \
     /srv/strahlemaennkes-private/protocol-documents
   ```
5. Datenbank-Migrationen aus `migrations/` laufen beim ersten Seitenaufruf nach einem Update automatisch genau einmal.
6. Leere Datenbank: `/setup.php` legt den ersten Administrator an (nur solange es noch keinen Benutzer gibt).

### Cron (Erinnerungen und Aufräumen)

`bin/send-meeting-reminders.php` läuft jede Minute als root-Cron über ein Hilfsskript, das die `env[...]`-Zeilen aus der php-fpm-Pool-Datei übernimmt und PHP als `www-data` startet (siehe `docs/deployment-stammtisch.md`). Das Skript verschickt die Stammtisch-Erinnerungen und räumt stündlich abgelaufene Anmelde-Tokens und alte Fehlversuche auf. Es lässt sich nur auf der Kommandozeile starten.

## Rollen und Ämter

- **Mitglied:** eigenes Getränk, Runden und Kasse lesen, Stammtisch abstimmen und zu-/absagen, Themen vorschlagen, Protokolle lesen.
- **Spieß** (auch eine eingesetzte Spieß-Vertretung): Getränke und Theke, Runden verwalten, Sektbar, Terminfindung starten, Protokoll führen.
- **Administrator:** Benutzerverwaltung, Freischaltungen (Registrierung, Sektbar, Hintergrundbild), Statistik und Kirmes-Planung.
- Zusatzämter unabhängig von der Rolle: **Zugführer** (Antreten, Chronik, Stammtisch, für andere zu-/absagen), **Kassierer** (Kasse), **Schriftführer** (Protokoll, Protokolle wieder öffnen).

## Sicherheit

- Passwörter: mindestens 8 Zeichen, mindestens ein Buchstabe und eine Zahl; gespeichert mit `password_hash`.
- Login: nach 8 Fehlversuchen für denselben Benutzer 15 Minuten Pause.
- Die Sitzung wird bei jedem Aufruf mit der Datenbank abgeglichen: Deaktivierte Mitglieder sind sofort abgemeldet, geänderte Rollen gelten sofort.
- „Eingeloggt bleiben“: zufälliges, gehasht gespeichertes Token, 180 Tage gleitend; Passwortwechsel, Reset und Deaktivierung entfernen es.
- Alle Formulare mit CSRF-Token, SQL ausschließlich mit Platzhaltern, Protokoll-HTML wird serverseitig bereinigt.
- Protokollbilder und PDFs liegen außerhalb des Webroots und werden nur an eingeloggte Mitglieder ausgeliefert. Das Hintergrundbild liegt in der Datenbank.

## Versionierung

Version, Datum und Cache-Schlüssel stehen in `includes/version.php`; bei jedem Update zusätzlich den Cache-Namen in `service-worker.js` und `CHANGELOG.md` anpassen. Ein Klick auf die Version in der Fußzeile zeigt den Changelog.

Regel: Bei jedem Update wird die **dritte Stelle** erhöht. Die zweite oder erste Stelle nur auf ausdrückliche Anweisung.

## Backup

Vor Updates ein PostgreSQL-Backup erstellen, z. B. `pg_dump -Fc strahlemaennkes > backup.dump`, und `/srv/strahlemaennkes-private` mitsichern.

# Strahlemännkes Web-App

## Datenbank ab Beta 0.6.0: PostgreSQL

Die Anwendung verwendet ausschließlich PostgreSQL. Benötigt werden PHP 8.2+ mit `pdo_pgsql` und PostgreSQL 15+ (ältere unterstützte PostgreSQL-Versionen funktionieren voraussichtlich ebenfalls).

Umgebungsvariablen: `PGHOST`, `PGPORT` (Standard 5432), `PGDATABASE` (Standard `strahlemaennkes`), `PGUSER` (Standard `strahlemaennkes`), `PGPASSWORD` und optional `PGSSLMODE` (Standard `prefer`).

Beim ersten Start wird die saubere PostgreSQL-Baseline aus `migrations/001_postgresql_baseline.sql` automatisch angelegt. Die historischen SQLite-Migrationen gehören bewusst nicht mehr zum Laufzeitschema.

### Bestehende SQLite-Daten einmalig übernehmen

1. PostgreSQL-Datenbank und Benutzer anlegen und die PG*-Variablen setzen.
2. Die App einmal aufrufen, damit das PostgreSQL-Schema angelegt wird.
3. Vor dem Import PostgreSQL sichern.
4. Import ausführen: `php bin/import-sqlite-to-postgres.php /pfad/zur/strahlemaennkes.sqlite --confirm-import`
5. Der Import leert die App-Tabellen in PostgreSQL und übernimmt die vorhandenen Nutzdaten mit ihren IDs. Alte Runden-Spalten werden bewusst verworfen. Die SQLite-Datei bleibt unverändert und dient als Rückfallkopie.


Mobile Web-App für den Schützenzug **„Strahlemännkes“**.

## Technischer Ansatz
- PHP 8.2+
- PostgreSQL über `pdo_pgsql`
- Bootstrap 5
- Progressive Web App (PWA): installierbar auf iPhone/Android, App-Icon, Standalone-Modus, Offline-Fallback
- optional echter Web-Push per VAPID und `minishlink/web-push`

Die Laufzeitdaten liegen in PostgreSQL; die frühere SQLite-Datei wird nur noch vom einmaligen Importwerkzeug gelesen.

## Installation
1. Dateien in den Webroot kopieren.
2. PostgreSQL bereitstellen und PHP-Erweiterung `pdo_pgsql` aktivieren.
3. PostgreSQL-Datenbank/-Benutzer anlegen und die `PG*`-Umgebungsvariablen setzen.
4. `/setup.php` aufrufen; das Schema wird automatisch angelegt und der erste Admin kann erstellt werden.
5. Über `/login.php` anmelden.
6. Für eine PWA im Produktivbetrieb HTTPS verwenden.

Neue PostgreSQL-Migrationen im Ordner `migrations/` werden bei späteren Updates automatisch genau einmal ausgeführt. Für die Übernahme einer bestehenden Installation siehe `docs/postgresql-migration.md`.

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
Vor Updates ein PostgreSQL-Backup erstellen, zum Beispiel mit `pg_dump`, und die App-Dateien sichern.

Empfehlung beim Update:
1. PostgreSQL-Backup erstellen.
2. neue App-Dateien hochladen.
3. Seite öffnen.
4. Migrationen laufen automatisch.

Die alte SQLite-Datei nach dem Umstieg zunächst als unveränderte Rückfallkopie aufbewahren.

## Rollen
- **Mitglied:** eigenes Getränk, Vorschläge, Push
- **Spieß:** zusätzlich Getränkezentrale, Reset, Erinnerung und Thekenansicht
- **Admin:** zusätzlich Benutzerverwaltung

## Später vorgesehen
Strafen, Kalender, Zugereignisse und Stammtischplanung können als neue Module und Migrationen ergänzt werden.


## Designstand

- Öffentliche Startseite: nur Titel, Login und Link zur Chronik.
- Standardoberfläche: dunkles, augenschonendes Design.
- Thekenansicht für Spieß/Admin: bewusst hell für hohe Lesbarkeit bei Veranstaltungen.
- Kalender und Stammtisch sind als geschützte Platzhalterseiten vorbereitet.

## Versionierung

Aktueller Stand: **Beta 0.7.1 vom 22.08.2026**.

Die Versionsnummer wird zentral in `includes/version.php` gepflegt und zusätzlich in `VERSION` sowie im Footer angezeigt. `CHANGELOG.md` enthält die Release-Änderungen für Git/GitHub.

Vereinbarte Regel für kommende Versionen:
- Änderungen bis einschließlich 500 Codezeilen: letzte Stelle erhöhen (`0.7.1` → `0.7.2`).
- Änderungen über 500 Codezeilen: mittlere Stelle erhöhen und Patchstelle zurücksetzen (`0.7.x` → `0.8.0`).
- Die erste Stelle wird nur auf ausdrückliche Anweisung erhöht.

## Passwortregeln und Passwort-Reset

Passwörter benötigen mindestens **9 Zeichen**, mindestens **einen Buchstaben** und mindestens **eine Zahl**.

Für „Passwort vergessen“ muss beim Benutzer eine E-Mail-Adresse hinterlegt sein. Der Benutzer erhält keinen Klartext des alten Passworts, sondern einen einmal verwendbaren Reset-Link, der 60 Minuten gültig ist.

Der Versand nutzt die PHP-Funktion `mail()`. Auf Debian muss deshalb ein lokaler Mail-Transport (z. B. Postfix oder msmtp/sendmail-kompatibel) eingerichtet sein. Als Absender kann die Umgebungsvariable gesetzt werden:

```bash
MAIL_FROM=noreply@deine-domain.de
```

Die Benutzerverwaltung erlaubt das Nachtragen bzw. Ändern der E-Mail-Adresse für bestehende Konten.

## Eingeloggt bleiben

Beim Login kann „Auf diesem Gerät eingeloggt bleiben“ aktiviert werden. Dabei wird ein zufälliges, gehasht gespeichertes Login-Token für 30 Tage verwendet. Logout, Deaktivierung oder Passwortänderung entfernen die zugehörigen Dauer-Logins.

## Cache-Busting bei Releases

CSS, JavaScript, Manifest und Service Worker verwenden den zentralen Versionsschlüssel. PHP-Seiten werden mit `no-cache/no-store` ausgeliefert, Assets werden versionsabhängig geladen und der Service Worker arbeitet network-first. Nach einem neuen Release übernimmt ein neuer Service Worker sofort die Kontrolle und lädt die Seite einmal neu. Dadurch sollen Designänderungen nicht mehr an alten PWA-Caches hängen bleiben.


## Rechte ab Beta 0.9.0

- Alle eingeloggten Mitglieder können die Mitgliederübersicht und den Kassenbereich lesen.
- Nur Administratoren können Benutzer anlegen, bearbeiten, deaktivieren und Rollen/Kassenrechte vergeben.
- Das Kassenrecht ist von der Benutzerrolle getrennt. Ein Mitglied oder Spieß kann also Kassierer sein, ohne Administrator zu werden.
- Administratoren dürfen die Kasse immer bearbeiten.
- Kassierer/Admin können Kassenstände mit Stichtag und Notiz erfassen sowie den monatlichen Mitgliedsbeitrag ändern.


## Rechte ab Beta 0.1

Zusatzämter werden unabhängig von der Hauptrolle vergeben:

- **Spieß / Moderator:** Getränke und Strafen verwalten, Neuigkeiten schreiben, Chronik bearbeiten.
- **Zugführer:** Zusatzrecht; darf Neuigkeiten schreiben und die Chronik bearbeiten.
- **Kassierer:** Zusatzrecht; darf Kasse verwalten und Neuigkeiten schreiben.
- **Administrator:** Benutzeradministration sowie übergeordnete Verwaltungsrechte; darf Neuigkeiten schreiben und die Chronik bearbeiten.
- **Mitglied:** darf Neuigkeiten, Mitglieder, Kasse und die öffentliche Chronik lesen, aber keine Neuigkeiten oder Chronik-Einträge bearbeiten.

Die öffentliche Chronik liest direkt aus `chronicle_years`. Nur als veröffentlicht markierte Jahrgänge sind öffentlich sichtbar.


## Runde-holen-Ablauf ab Beta 0.2.0

Eine geschuldete Runde ist zunächst offen. Spieß oder Admin aktiviert sie mit **„Runde holen schicken“**. Das Mitglied erhält nach Möglichkeit eine Push-Nachricht und sieht in der App **„Aktuelle Runde holen“**.

Die darauf folgende Bestellansicht ist keine gespeicherte Momentaufnahme: Sie liest die Getränkewünsche fortlaufend aus SQLite und aktualisiert sich alle fünf Sekunden. Mitglieder können ihre Getränkewahl also bis kurz vor der Bestellung noch ändern.

Wenn die Runde gebracht wurde, markiert der Spieß sie mit **„Gegeben“**. Der Hol-Auftrag verschwindet daraufhin beim Mitglied. Bei mehreren geschuldeten Runden wird nur eine Runde abgezogen; die übrigen bleiben offen.


## Bild-Uploads für Neuigkeiten (Beta 0.2.2)

Der PHP-FPM-Benutzer benötigt Schreibrechte auf `uploads/news`. Bei Debian/PHP-FPM ist das normalerweise `www-data`:

```bash
sudo chown -R www-data:www-data /pfad/zur/strahlemaennkes-app/uploads/news
sudo chmod 750 /pfad/zur/strahlemaennkes-app/uploads/news
```

Die App akzeptiert dort nur JPG, PNG, WebP und GIF bis 8 MB. Die PHP-Einstellungen `upload_max_filesize` und `post_max_size` müssen mindestens 8 MB erlauben.

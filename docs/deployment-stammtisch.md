# Deployment: Stammtisch und Protokolle

Diese Anleitung aktualisiert direkt die produktive Installation unter `/srv/strahlemaennkes`.

## Vor dem Update

```bash
set -euo pipefail
stamp=$(date +%F-%H%M%S)
sudo -u postgres pg_dump -Fc strahlemaennkes > /srv/strahlemaennkes-backups/strahlemaennkes-pre-stammtisch-$stamp.dump
sudo tar -C /srv -czf /srv/strahlemaennkes-backups/strahlemaennkes-files-pre-stammtisch-$stamp.tar.gz strahlemaennkes
```

Bewahre beide Dateien auf, bis die neue Version geprüft ist.

## Einspielen

Für das sichere serverseitige Verkleinern von Protokollbildern muss die bereits zur PHP-FPM-Version passende GD-Erweiterung aktiv sein (bei PHP 8.4 z. B. `php8.4-gd`). Prüfen mit `php -m | grep -i '^gd$'`.

1. Das ZIP in ein frisches temporäres Verzeichnis entpacken und den enthaltenen Ordner `strahlemaennkes/` prüfen.
2. Die App-Dateien nach `/srv/strahlemaennkes/` kopieren. Dabei ausdrücklich `--delete` verwenden, damit die entfernten Dateien `news.php` und `news-image-upload.php` nicht erreichbar bleiben; `config.php`, `vendor/`, `uploads/` und `data/` müssen erhalten bleiben.

```bash
sudo rsync -a --delete \
  --exclude config.php --exclude vendor --exclude uploads --exclude data \
  /pfad/zum/entpackten/strahlemaennkes/ /srv/strahlemaennkes/
sudo install -d -o www-data -g www-data -m 0750 \
  /srv/strahlemaennkes-private/meeting-images \
  /srv/strahlemaennkes-private/protocol-documents
```

3. Die Migrationen laufen automatisch beim ersten App-Aufruf. Alternativ kann eine Seite nach dem Reload geöffnet werden. Danach prüfen:

```bash
sudo -u postgres psql -d strahlemaennkes -c "SELECT version, applied_at FROM schema_migrations ORDER BY version;"
```

Erwartet wird `002_stammtisch_protocols.sql`. Diese Migration entfernt `news_posts` und legt alle Stammtisch-/Protokolltabellen an.

4. Eine stündliche Erinnerung braucht diesen Cron-Eintrag (jede Minute ausführen; der Prozess verschickt nur im 55–65-Minuten-Fenster genau einmal):

```cron
* * * * * www-data /usr/bin/php /srv/strahlemaennkes/bin/send-meeting-reminders.php >> /var/log/strahlemaennkes-meeting-reminders.log 2>&1
```

5. Im Browser prüfen: Startseite, Stammtisch, Protokolle, Upload eines kleinen Testbildes und Zugriff auf ein Test-PDF. Erst anschließend den Testeintrag wieder löschen bzw. den Test-Stammtisch abschließen.

## Rückfall

Dateien und Datenbank gehören zusammen. App zunächst in Wartung nehmen bzw. Caddy kurz stoppen, dann das gesicherte Dateipaket zurückspielen und die Datenbank wiederherstellen:

```bash
sudo systemctl stop caddy
sudo rm -rf /srv/strahlemaennkes
sudo tar -C /srv -xzf /srv/strahlemaennkes-backups/strahlemaennkes-files-pre-stammtisch-DATUM.tar.gz
sudo -u postgres dropdb strahlemaennkes
sudo -u postgres createdb -O strahlemaennkes strahlemaennkes
sudo -u postgres pg_restore -d strahlemaennkes /srv/strahlemaennkes-backups/strahlemaennkes-pre-stammtisch-DATUM.dump
sudo systemctl start caddy
```

`DATUM` durch denselben Zeitstempel aus dem Backup-Schritt ersetzen. Die während des neuen Stands hochgeladenen Protokollbilder und PDFs liegen geschützt außerhalb des Webroots in `/srv/strahlemaennkes-private/` und sind nicht in `pg_dump`; falls sie nach einem Rückfall erhalten bleiben sollen, diesen Ordner vorher separat sichern.

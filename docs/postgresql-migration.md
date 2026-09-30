# Migration SQLite → PostgreSQL

## Ziel

Beta 0.6.0 verwendet PostgreSQL als einzige Laufzeitdatenbank. Die bisherige SQLite-Datei wird beim Import ausschließlich gelesen und nicht verändert.

## Vor dem Umschalten

1. Web-App kurz in Wartung nehmen, damit während des Imports keine neuen Daten in SQLite entstehen.
2. ZIP/Dateisicherung der App und insbesondere `data/strahlemaennkes.sqlite` erstellen.
3. PostgreSQL-Datenbank und Benutzer anlegen.
4. PHP-Erweiterung `pdo_pgsql` installieren/aktivieren.
5. `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, optional `PGSSLMODE` für PHP setzen.

Beispiel Debian/PostgreSQL (Namen/Passwort anpassen):

```text
sudo apt install postgresql php-pgsql
sudo -u postgres createuser --pwprompt strahlemaennkes
sudo -u postgres createdb --owner=strahlemaennkes strahlemaennkes
```

## Schema anlegen

Nach gesetzter PostgreSQL-Konfiguration die App einmal aufrufen. `includes/migrate.php` legt `schema_migrations` und `001_postgresql_baseline.sql` an. Alternativ kann derselbe Bootstrap über PHP ausgelöst werden.

## Daten übernehmen

Der Import ersetzt die Inhalte der App-Tabellen im PostgreSQL-Ziel. Deshalb verlangt er bewusst einen Bestätigungsschalter:

```text
php bin/import-sqlite-to-postgres.php /absoluter/pfad/strahlemaennkes.sqlite --confirm-import
```

Übernommen werden Benutzer, Rollen, Auth-Tokens, Getränke, Favoriten, Getränkewahlen, Runden, Kasse, Neuigkeiten, Chronik, Spieß-Vertretung, Antreten, Sektbar, Gäste, Feature-Einstellungen und Push-Abos. IDs bleiben erhalten. PostgreSQL-Sequenzen werden anschließend auf die höchsten importierten IDs gesetzt.

Bei `fines` werden nur die heute fachlich benötigten Felder übernommen. Die historischen SQLite-Spalten `amount`, `rounds_count`, `rounds_given` und `last_dispatched_at` werden absichtlich nicht in PostgreSQL angelegt. Die aktuelle SQLite-Datenbank hat durch Migration 010 bereits jede Runde in einen eigenen Datensatz aufgeteilt.

## Nachkontrolle

- Login mit Admin und normalem Mitglied
- Mitgliederliste und Rollen
- Getränkewahl/Favoriten/Theke/Gastbestellung
- offene, aktive, gegebene und gestrichene Runden + Statistik
- Neuigkeiten und Bildinhalt
- Kasse und Chronik
- Antreten und Sektbar
- Push-Abos/Push-Test
- Passwort-Reset und „eingeloggt bleiben“

Die SQLite-Datei erst löschen, wenn die PostgreSQL-Version mehrere Tage stabil läuft und ein PostgreSQL-Backup getestet wurde.

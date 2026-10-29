# Domain-Umzug: sm.fischje.de → strahlemännkes.de

Ab Version 0.7.26 kann die App selbst umziehen. Server, Datenbank, Reverse Proxy und Code bleiben gleich,
nur die Adresse ändert sich.

**Wichtig:** Umlaut-Domains stehen in DNS, Proxy und Konfiguration immer in Punycode:

| lesbar              | technisch (Punycode)        |
|---------------------|-----------------------------|
| `strahlemännkes.de` | `xn--strahlemnnkes-ifb.de`  |

## Was die App beim Umzug macht

Eingeschaltet wird der Umzug über zwei Werte in der php-fpm-Pool-Datei (oder `config.php`):

```
env[APP_URL] = https://xn--strahlemnnkes-ifb.de
env[APP_LEGACY_HOSTS] = sm.fischje.de
```

Dann gilt:

- **Alte Adresse:** Jede Seite führt auf `umzug.php` („Die App ist umgezogen“). Wer dort noch angemeldet ist,
  bekommt einen Knopf, der ihn per Einmal-Link (30 Minuten, einmalig) **angemeldet** auf die neue Adresse bringt.
  In der alten, installierten App öffnet der Knopf den echten Browser, weil man nur dort neu installieren kann.
  Schnittstellen (z. B. Push-Anmeldung) antworten auf der alten Adresse mit `410`.
- **Neue Adresse:** `umzug.php` erklärt in drei Schritten: App installieren, Push einschalten, altes Symbol löschen.
- **Push:** Bestehende Push-Abos gehören zur alten App und funktionieren weiter. Tippt man auf eine Mitteilung,
  landet man auf der Umzugsseite – das ist gewollt und erinnert an den Umzug. Sobald ein Mitglied Push in der
  **neuen** App einschaltet, werden seine alten Abos gelöscht (sonst käme jede Mitteilung doppelt).
- **Admin:** Unter Funktionen → Freischaltungen zeigt eine Karte, wer schon umgezogen ist, und verschickt eine
  „Umzugs-Erinnerung“ nur an Geräte, die noch auf der alten Adresse sind.
- Reset-Mails und WhatsApp-Links nutzen automatisch `APP_URL`.

Ohne `APP_LEGACY_HOSTS` verhält sich die App wie bisher.

## Ablauf

### 1. Vorbereitung (ohne Auswirkung für die Mitglieder)

1. **DNS:** Domain in Cloudflare aufnehmen (wie fischje.de), Nameserver beim Registrar auf Cloudflare stellen.
   Einträge wie bei `sm.fischje.de` (gleiches Ziel, Proxy „orange“). SSL-Modus wie bei fischje.de.
2. **Reverse Proxy:** `xn--strahlemnnkes-ifb.de` als **zusätzlichen** Hostnamen auf dasselbe Ziel legen
   (z. B. `server_name sm.fischje.de xn--strahlemnnkes-ifb.de;`). Zertifikat für die neue Domain besorgen bzw.
   ins vorhandene aufnehmen. Die alte Adresse **nicht** umleiten – sie muss weiter bei PHP ankommen.
   Der Proxy muss den echten `Host`-Header durchreichen (tut er heute schon, sonst ginge die App nicht).
3. **Test:** `https://strahlemännkes.de` aufrufen. Die App muss normal laufen (Login ist dort getrennt, das ist richtig).
4. Empfehlung: auch `strahlemaennkes.de` (ohne Umlaut) sichern und per 301 auf die neue Adresse leiten.
   Umlaut-Adressen tippen sich auf manchen Tastaturen schlecht und werden nicht überall als Link erkannt.

### 2. Umschalten (5 Minuten)

1. In der php-fpm-Pool-Datei `APP_URL` und `APP_LEGACY_HOSTS` wie oben setzen.
   Prüfen, ob `config.php` `base_url` aus `APP_URL` liest – steht dort die alte Adresse fest drin, dort ändern.
2. `sudo systemctl reload php*-fpm` (der Cron-Wrapper liest dieselbe Pool-Datei, nichts weiter zu tun).
3. Prüfen:
   - `https://sm.fischje.de/dashboard.php` → Umzugsseite.
   - Knopf „Weiter zur neuen Adresse“ → angemeldet auf `strahlemännkes.de`.
   - „Passwort vergessen“ schickt einen Link mit der neuen Adresse.
   - Admin-Karte „Umzug“ unter Funktionen → Freischaltungen erscheint.

**Zurück:** `APP_LEGACY_HOSTS` leeren, `APP_URL` wieder auf `https://sm.fischje.de`, php-fpm neu laden.

### 3. Mitglieder mitnehmen (2–6 Wochen)

1. Ankündigung in die WhatsApp-Gruppe (Vorschlag unten).
2. In der Admin-Karte „Umzugs-Erinnerung“ schicken. Nach ein paar Tagen wiederholen.
3. Wer bei „Ohne Push“ steht, bekommt den Link persönlich.

### 4. Abschluss

Wenn (fast) alle umgezogen sind: im Reverse Proxy `sm.fischje.de` per **301** auf `https://xn--strahlemnnkes-ifb.de$request_uri`
umleiten. Danach funktionieren Umzugsseite und automatische Anmeldung von der alten Adresse aus nicht mehr,
Nachzügler melden sich einfach mit Passwort an. Die Weiterleitung mindestens ein Jahr stehen lassen.

## Gut zu wissen

- Eine installierte App (PWA) gehört fest zu ihrer Adresse. Sie lässt sich technisch nicht „umbiegen“ –
  deshalb muss jedes Mitglied einmal neu installieren und Push neu erlauben.
- iPhone: Die installierte App hat einen eigenen Speicher, getrennt von Safari. Es kann sein, dass man sich
  in der neu installierten App noch einmal mit Passwort anmelden muss.
- Die neue App heißt genauso wie die alte. Das alte Symbol erkennt man daran, dass es nur noch die Umzugsseite zeigt.

## WhatsApp-Vorschlag

> 📦 **Unsere Zug-App zieht um!** Neue Adresse: **strahlemännkes.de**
> Öffnet einfach einmal die alte App (oder sm.fischje.de) und tippt auf „Weiter zur neuen Adresse“ –
> ihr seid dann automatisch angemeldet. Danach: neue App installieren, Mitteilungen erlauben, altes Symbol löschen.
> Dauert eine Minute. Fragen gerne an mich. 🍻

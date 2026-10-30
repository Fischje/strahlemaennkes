# Domain-Umzug: sm.fischje.de → www.strahlemaennkes.de

Ab Version 0.7.26 kann die App selbst umziehen. Server, Datenbank, Reverse Proxy und Code bleiben gleich,
nur die Adresse ändert sich.

Die neue Adresse ist **`www.strahlemaennkes.de`** – bewusst ohne Umlaut (lässt sich überall tippen, wird in
WhatsApp und Mails zuverlässig als Link erkannt, keine Punycode-Schreibweise). Die Adresse ohne „www“
(`strahlemaennkes.de`) leitet nur per 301 auf die www-Adresse weiter.

Die Kette ist **Cloudflare → NPMplus (HTTPS) → Caddy (Port 80) → php-fpm**.

## Was die App beim Umzug macht

Eingeschaltet wird der Umzug über zwei Werte in der php-fpm-Pool-Datei (oder `config.php`):

```
env[APP_URL] = https://www.strahlemaennkes.de
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

1. **DNS (Cloudflare):** Zone `strahlemaennkes.de` anlegen, Nameserver beim Registrar auf Cloudflare stellen.
   Zwei Einträge, beide mit Proxy „orange“: `strahlemaennkes.de` (A, Ziel wie bei `sm.fischje.de`) und
   `www` (CNAME auf `strahlemaennkes.de`). Unter *Rules → Redirect Rules* und *Page Rules* darf **keine**
   Regel „www → ohne www“ stehen (Cloudflare bietet die als Vorlage an).
2. **NPMplus:** neuer Proxy Host mit **beiden** Namen `www.strahlemaennkes.de` und `strahlemaennkes.de`,
   Ziel wie beim Proxy Host von `sm.fischje.de` (Caddy, Port 80, http). Let's-Encrypt-Zertifikat für beide Namen,
   „Force SSL“ an. Den Proxy Host von `sm.fischje.de` nicht ändern.
3. **Caddy** (`/etc/caddy/Caddyfile`): Im bestehenden Block die www-Adresse ergänzen und für die Adresse ohne www
   einen eigenen Weiterleitungs-Block anlegen:
   ```
   sm.fischje.de:80, www.strahlemaennkes.de:80 {
       # … alles wie bisher …
   }

   strahlemaennkes.de:80 {
       redir https://www.strahlemaennkes.de{uri} permanent
   }
   ```
   Dann `caddy validate --config /etc/caddy/Caddyfile && systemctl reload caddy`.
   Die alte Adresse `sm.fischje.de` **nicht** umleiten – sie muss weiter bei PHP ankommen (Umzugsseite).
4. **Test** (auf dem Caddy-Server, geht auch vor dem DNS):
   `curl -sI -H "Host: www.strahlemaennkes.de" http://localhost/login.php` → `200`,
   `curl -sI -H "Host: strahlemaennkes.de" http://localhost/login.php` → `301` mit `Location: https://www.strahlemaennkes.de/login.php`.
   Danach im Browser `https://www.strahlemaennkes.de` öffnen (Login ist dort getrennt, das ist richtig).
5. Optional: Wer mag, sichert zusätzlich `strahlemännkes.de` und leitet sie per 301 auf `www.strahlemaennkes.de` um.
   Im Proxy und bei Cloudflare heißt sie dann `xn--strahlemnnkes-ifb.de` (Punycode-Schreibweise der Umlaut-Domain).

**Landet man trotzdem auf der Adresse ohne www**, kommt die Weiterleitung von außerhalb der App. Mit
`curl -sI https://www.strahlemaennkes.de` (Antwort-Header `location:`) prüfen und der Reihe nach nachsehen:
Cloudflare-Regeln, NPMplus-Proxy-Host (Reiter „Advanced“), Caddyfile, Weiterleitung beim Registrar.
Browser merken sich 301-Weiterleitungen – zum Testen ein privates Fenster nehmen.

### 2. Umschalten (5 Minuten)

1. In der php-fpm-Pool-Datei `APP_URL` und `APP_LEGACY_HOSTS` wie oben setzen.
   Prüfen, ob `config.php` `base_url` aus `APP_URL` liest – steht dort die alte Adresse fest drin, dort ändern.
2. `sudo systemctl reload php*-fpm` (der Cron-Wrapper liest dieselbe Pool-Datei, nichts weiter zu tun).
3. Prüfen:
   - `https://sm.fischje.de/dashboard.php` → Umzugsseite.
   - Knopf „Weiter zur neuen Adresse“ → angemeldet auf `www.strahlemaennkes.de`.
   - „Passwort vergessen“ schickt einen Link mit der neuen Adresse.
   - Admin-Karte „Umzug“ unter Funktionen → Freischaltungen erscheint.

**Zurück:** `APP_LEGACY_HOSTS` entfernen, `APP_URL` wieder auf `https://sm.fischje.de`, php-fpm neu laden.

### 3. Mitglieder mitnehmen (2–6 Wochen)

1. Ankündigung in die WhatsApp-Gruppe (Vorschlag unten).
2. In der Admin-Karte „Umzugs-Erinnerung“ schicken. Nach ein paar Tagen wiederholen.
3. Wer bei „Ohne Push“ steht, bekommt den Link persönlich.

### 4. Abschluss

Wenn (fast) alle umgezogen sind: im Reverse Proxy `sm.fischje.de` per **301** umleiten, z. B. in Caddy
`sm.fischje.de:80 { redir https://www.strahlemaennkes.de{uri} permanent }`. Danach funktionieren Umzugsseite und automatische Anmeldung von der alten Adresse aus nicht mehr,
Nachzügler melden sich einfach mit Passwort an. Die Weiterleitung mindestens ein Jahr stehen lassen.

## Gut zu wissen

- Eine installierte App (PWA) gehört fest zu ihrer Adresse. Sie lässt sich technisch nicht „umbiegen“ –
  deshalb muss jedes Mitglied einmal neu installieren und Push neu erlauben.
- iPhone: Die installierte App hat einen eigenen Speicher, getrennt von Safari. Es kann sein, dass man sich
  in der neu installierten App noch einmal mit Passwort anmelden muss.
- Die neue App heißt genauso wie die alte. Das alte Symbol erkennt man daran, dass es nur noch die Umzugsseite zeigt.

## WhatsApp-Vorschlag

> 📦 **Unsere Zug-App zieht um!** Neue Adresse: **www.strahlemaennkes.de**
> Öffnet einfach einmal die alte App (oder sm.fischje.de) und tippt auf „Weiter zur neuen Adresse“ –
> ihr seid dann automatisch angemeldet. Danach: neue App installieren, Mitteilungen erlauben, altes Symbol löschen.
> Dauert eine Minute. Fragen gerne an mich. 🍻

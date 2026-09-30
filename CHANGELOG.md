# Changelog

## Beta 0.6.0 – 28.09.2026

- Datenbank vollständig von SQLite auf PostgreSQL umgestellt.
- Historische SQLite-Migrationskette durch eine saubere PostgreSQL-Baseline des aktuellen Funktionsumfangs ersetzt.
- Runden-Datenmodell bereinigt: eine Tabellenzeile entspricht genau einer Runde; veraltete Felder `amount`, `rounds_count`, `rounds_given` und `last_dispatched_at` entfernt.
- SQLite-spezifische SQL-Ausdrücke (`COLLATE NOCASE`, `strftime`, `datetime`, `INSERT OR IGNORE`) aus dem Anwendungscode entfernt bzw. ersetzt.
- ID-Ermittlung bei neuen Datensätzen auf PostgreSQL `RETURNING id` umgestellt.
- Einmaliges CLI-Importwerkzeug für die vorhandene SQLite-Datenbank ergänzt; die Quelldatei wird dabei nicht verändert.
- PostgreSQL-Konfiguration erfolgt über PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD/PGSSLMODE.

## Beta 0.5.8 – 28.09.2026

- „Getränke verwalten“ auf Smartphone/PWA ohne horizontales Scrollen umgebaut.
- Die Getränketabelle wird mobil zu einzelnen Karten mit Status, Quelle, Sortierung und Aktionen.
- Aktionsknöpfe werden touchfreundlich zweispaltig, auf sehr schmalen Displays einspaltig angeordnet.
- Formulare, Getränkenamen und Übersichtsbereiche gegen horizontales Überlaufen abgesichert.
- Desktop bleibt bei der bisherigen Tabellenansicht.

## Beta 0.5.7 – 27.09.2026

- Navigation konsolidiert: normale Seiten bleiben in der Hauptnavigation, Verwaltungswerkzeuge liegen gesammelt unter „Funktionen“.
- Neue Funktionen-Zentrale mit rollenabhängigen Einträgen für Neuigkeit erstellen, Getränke verwalten, Sektbar, Spieß-Vertretung, Antreten festlegen, Chronik verwalten und Admin-Freischaltungen.
- Einzelne Verwaltungslinks aus Burger-Menü und Desktop-Sidebar entfernt.
- Thekenansicht und normale Antreten-Ansicht sind in der Hauptnavigation für alle Mitglieder direkt erreichbar.
- „Neuigkeit erstellen“ aus Funktionen öffnet direkt den vorhandenen Neuigkeiten-Editor.

## Beta 0.5.6 – 27.09.2026

- Hotfix 2: Im Smartphone-Burgermenü erscheint bei aktivem Sektbarmodus für Admin/aktiven Spieß zusätzlich die direkte Aktion „Sektbarmodus ausschalten“.

- Hotfix: JavaScript-Fehler in der Thekenansicht behoben, durch den Live-Stand und Getränkeliste nicht mehr geladen wurden.
- Bereits eingetragene Gästebestellungen mit Löschfunktion in den dunklen Live-Bereich der Thekenansicht verschoben.
- Aktiver Spieß sieht dort zusätzlich namentlich, welche Mitglieder aktuell noch kein Getränk gewählt haben.
- Gäste-Eingabemaske bleibt weiter unten ausschließlich zum Hinzufügen neuer Bestellungen.

## Beta 0.5.5 – 27.09.2026

- Weitergabe-Aktionen der Thekenansicht auf Mobilgeräten nach oben gezogen; Gästeverwaltung folgt darunter.
- Kopieren, WhatsApp und Drucken enthalten Mitglieder als Initialen (z. B. MP), Gäste mit neutralem G-Kürzel.
- Push an das betroffene Mitglied, wenn der Spieß eine Runde als „Gegeben“ markiert.
- Push an alle aktiven Mitglieder beim Aktivieren des Sektbarmodus.
- Einzel-Push-Tags werden korrekt an den Service Worker weitergereicht.

## Beta 0.5.4 – 27.09.2026

- Sektbar aus der prominenten mobilen Bottom-Navigation entfernt.
- Sektbar stattdessen in das PWA-Burgermenü aufgenommen. Administratoren sehen den Eintrag dort immer; nach Freischaltung sehen ihn auch alle übrigen Mitglieder.
- Veraltete CSS-Regeln für den zusätzlichen Sektbar-Bottom-Button entfernt.

## Beta 0.5.3 – 27.09.2026

- Datenschutz bei der Thekenliste verbessert: „Liste kopieren“ und WhatsApp geben nur Getränk und Anzahl weiter, niemals Mitglieder- oder Gastnamen.
- Neuer Button „Drucken“ in der Thekenansicht. Die separate Druckansicht enthält ebenfalls ausschließlich Getränk und Anzahl und öffnet den System-Druckdialog, auch aus unterstützten PWA-Umgebungen.
- Namen bleiben ausschließlich in der internen sichtbaren Thekenansicht erhalten.

## Beta 0.5.2 – 27.09.2026

- Statistik „Schnellste gegebene Runde“ korrigiert: Startzeit ist jetzt exakt das im Rundeneintrag gespeicherte Feld „Wann?“ (`occurred_at`), Ende ist der Klick auf „Gegeben“ (`settled_at`). Erstellungszeit und Losschicken spielen keine Rolle.
- Sektbar in der PWA-Navigation für Administratoren immer sichtbar, auch wenn die Funktion noch nicht freigeschaltet ist.
- Zusätzlicher mobiler Admin-Schnellzugriff auf die Sektbar in der Kopfzeile ergänzt, damit die Funktion auch bei enger Bottom-Navigation sicher erreichbar bleibt.

## Beta 0.5.1 – 27.09.2026

- „Schnellste gegebene Runde“ neu definiert und korrigiert: Dauer von der tatsächlichen Eintragung der Runde (`created_at`) bis zum Klick auf „Gegeben“ (`settled_at`). Losschicken spielt für diese Statistik keine Rolle mehr.
- Persönliche Getränkefavoriten ergänzt. Mitglieder können Getränke mit einem Stern markieren; Favoriten stehen in der eigenen Auswahl automatisch oben.
- Sektbar ist bei Admin-Freigabe für alle Mitglieder als eigene Seite sichtbar; nur aktiver Spieß/Admin kann den Modus schalten.
- Sektbar-Menüpunkt auch in der PWA-Navigation ergänzt.
- Sektbar-Aktivierung optisch auf einen roten, hochklappbaren Sicherheitsdeckel mit Metall-Kippschalter nach Art eines filmischen Kill-Switches umgestellt.

## Beta 0.5.0 – 26.09.2026

- Statistik „Schnellste gegebene Runde“ korrigiert: Es zählen nur erfolgreich nach aktivem Losschicken gegebene Runden; abgebrochene/alte Dispatch-Zeiten, Direkt-Gegeben, offene und gestrichene Runden werden ausgeschlossen.
- Zeitdifferenz wird sekundengenau über SQLite-Unix-Zeit berechnet; erneutes Losschicken nach Zurückstellen verwendet den letzten tatsächlichen Start.
- Sektbar-Funktion erhält eine globale Admin-Freigabe. Spieß/Vertreter sehen und nutzen Sektbar erst nach Freischaltung.
- Sperrt der Admin die Funktion während eines aktiven Sektbarmodus, wird dieser sicher beendet und nur automatisch aktivierte Getränke werden zurückgesetzt.
- Thekenansicht und Live-Bestellliste für alle angemeldeten Mitglieder freigegeben; Getränkeverwaltung bleibt Spieß-Funktion.
- Fremd-/Gastgetränke für den aktiven Spieß ergänzt; erscheinen live in der Thekenliste und können einzeln entfernt werden.
- Aktiver Spieß erhält Push bei eigener Getränkänderung eines Mitglieds; bei aktiver Vertretung geht die Nachricht an den Vertreter.
- Sektbar-Getränkeflag in der Getränkeverwaltung ergänzt.
- Sektbar-Modus mit animierter Schutzklappe und separatem roten Startknopf ergänzt.
- Beim Start werden nur markierte, zuvor inaktive Getränke aktiviert; beim Ausschalten werden ausschließlich diese automatisch aktivierten Getränke wieder deaktiviert.
- Regenbogenfarbenes Sektbar-Banner ersetzt während des aktiven Modus den Antreten-Hinweis; reduzierte Bewegung wird berücksichtigt.
- Mobile/PWA-Navigation stellt die Thekenansicht allen Mitgliedern bereit und Sektbar dem aktiven Spieß.
- Neue Migration `016_sektbar_guests.sql`.

## Beta 0.4.10 – 26.09.2026

- Antreten-Hinweis unter die rote Kopfzeile verschoben und auf Datum, Uhrzeit und Ort reduziert; Klick öffnet die vollständige Antreten-Seite.
- Antreten-Seite für alle Mitglieder lesbar; Bearbeiten und Löschen bleiben Zugführer/Admin vorbehalten.
- Antreten um ein separates Pflichtfeld „Ort / Treffpunkt“ ergänzt; der Ort wird in Verwaltung und globaler Kopfzeile angezeigt.
- Neue Funktion „Antreten“ für Zugführer und Administrator.
- Genau ein Termin mit Datum, Uhrzeit und Anzugordnung; ein neuer ersetzt den bisherigen.
- Deutsche Wochentagskürzel Mo, Di, Mi, Do, Fr, Sa, So.
- Aktiver Termin erscheint für angemeldete Mitglieder auf allen Seiten als schmale Kopfzeile.
- Automatisches Ausblenden eine Stunde nach Antretezeit; manuelles Löschen jederzeit möglich.
- Europe/Berlin/UTC-Umrechnung berücksichtigt Sommer- und Winterzeit.
- Neue Migration `014_assembly_status.sql`.

## Beta 0.4.9 – 21.09.2026

- Zeitzonenfehler der Live-Uhrzeit behoben: Die App verwendet zentral `Europe/Berlin` und berücksichtigt damit automatisch Sommer- und Winterzeit.
- Live-APIs liefern Zeitstempel jetzt als ISO-8601 inklusive Zeitzonen-Offset statt als serverabhängige reine Uhrzeit.
- Thekenansicht und Runde-holen-Ansicht formatieren den Live-Zeitstempel zusätzlich auf dem Endgerät; dadurch bleibt die Anzeige auch bei abweichender Server-Zeitzone korrekt.
- Bestehende SQLite-Zeitstempel bleiben weiterhin in UTC gespeichert; nur die Darstellung wird in lokale deutsche Zeit umgerechnet.

## Beta 0.4.8 – 30.08.2026

- Globalen Admin-Schalter für die Selbstregistrierung neuer Mitglieder ergänzt.
- Selbstregistrierung ist nach dem Update standardmäßig deaktiviert.
- Bei aktivierter Registrierung erscheint auf der Login-Seite „Jetzt registrieren“.
- Registrierte Benutzer werden ausschließlich als aktive normale Mitglieder angelegt; Sonderrollen und Ämter können nicht selbst gewählt werden.
- Selbstregistrierte Benutzer wählen direkt ihr persönliches Passwort nach der bestehenden Kennwortrichtlinie und müssen es nicht beim ersten Login erneut ändern.
- Die Registrierungsseite prüft den Schalter auch unmittelbar vor dem Speichern erneut, sodass ein zwischenzeitliches Schließen sofort greift.
- Neue Migration `013_app_settings.sql` für zentrale App-Einstellungen.

## Beta 0.4.7 – 30.08.2026

- Temporäre „Spieß-Vertretung“ ergänzt.
- Spieß oder Administrator können genau ein aktives Mitglied als Vertreter einsetzen, wechseln oder die Vertretung beenden.
- Vertreter erhält ausschließlich die operativen Spieß-Rechte für Thekenansicht, Getränkeverwaltung und Rundenverwaltung.
- Chronikbearbeitung, Benutzerverwaltung, Neuigkeitenrechte und sonstige Sonderämter werden nicht durch die Vertretung übertragen.
- Die bestehende Rolle des Vertreters bleibt unverändert; die Vertretung ist ein zusätzliches temporäres Recht.
- Aktive Vertretung bleibt über Sitzungen und Tage hinweg bestehen, bis Spieß/Admin sie ausdrücklich beendet oder wechselt.
- Vertretungshistorie mit Beginn, Ende und einsetzendem Benutzer wird gespeichert.
- Dashboard und Navigation kennzeichnen den aktiven Spieß-Vertreter.
- Mobile/PWA-Navigation berücksichtigt die temporären operativen Rechte ebenfalls.
- Neue Migration `012_spiess_delegation.sql`.

## Beta 0.4.6 – 25.08.2026

- Ursache für unerwartete Abmeldungen bei „eingeloggt bleiben“ behoben: Ein neuer Dauer-Login löscht nicht länger die Tokens anderer Geräte/Browser desselben Benutzers.
- Dauer-Login von 30 auf 180 Tage verlängert.
- Dauer-Login arbeitet jetzt gleitend: Bei erfolgreicher Wiederherstellung wird die Laufzeit des jeweiligen Geräts erneut auf 180 Tage gesetzt.
- Ablaufzeiten werden in UTC gespeichert, passend zu SQLite `CURRENT_TIMESTAMP`.
- HTTPS-Erkennung für Cookies um `X-Forwarded-Proto` und Port 443 erweitert, wichtig hinter Caddy/Reverse-Proxy-Konfigurationen.
- Login-Seite erklärt die Dauer des persistenten Logins.
- Mobile/PWA-Hauptnavigation des Spieß/Admin um „Getränke verwalten“ ergänzt.
- Mobile/PWA-Hauptnavigation für Spieß, Zugführer und Admin um „Chronik bearbeiten“ ergänzt.
- Verwaltungslinks zusätzlich im mobilen Benutzermenü oben rechts hinterlegt.

## Beta 0.4.5 – 23.08.2026

- Vom Administrator neu angelegte Benutzer müssen weiterhin beim ersten Login ein persönliches Passwort festlegen.
- Setzt oder ändert der Administrator später das Passwort eines bestehenden Benutzers, wird ebenfalls zwingend ein Passwortwechsel beim nächsten Login ausgelöst.
- Admin-vergebene Start-/Resetpasswörter unterliegen bewusst keiner Kennwortrichtlinie und dürfen frei gewählt werden.
- Die normale Kennwortrichtlinie gilt erst für das persönliche Passwort des Benutzers: mindestens 8 Zeichen, mindestens ein Buchstabe und mindestens eine Zahl sowie die dokumentierten erlaubten Sonderzeichen.
- Neue Benutzerfunktion „Passwort ändern“ ergänzt; dabei muss das aktuelle Passwort bestätigt werden.
- Klick auf den eigenen Namen oben rechts öffnet jetzt ein Kontomenü mit „Passwort ändern“ und „Abmelden“.
- Kontomenü ist sowohl auf Desktop als auch mobil/PWA verfügbar.
- Beim eigenen Passwortwechsel werden bestehende dauerhafte Login-Tokens aus Sicherheitsgründen entfernt.

## Beta 0.4.4 – 22.08.2026

- Rollenübersicht von exklusiv auf additiv umgestellt: Spieß, Administrator und Zugführer sehen ihre zusätzlichen operativen Kacheln und darunter weiterhin sämtliche normalen Mitgliederbereiche.
- Auch Sonderrollen sehen damit die letzten drei Neuigkeiten, den eigenen Getränkewunsch, die eigenen offenen Runden, offene Runden insgesamt und die letzten drei Runden.
- Neuigkeiten-Push technisch an den bereits funktionierenden Runden-Push angeglichen: gleiche VAPID-Abos, hohe Dringlichkeit und kein separates Web-Push-Topic.
- Service Worker verwendet nicht länger für jede Benachrichtigung fest den Tag „drink-reminder“.
- Getränkeerinnerungen, „Runde holen“ und Neuigkeiten besitzen jetzt getrennte Notification-Tags und ersetzen sich dadurch nicht gegenseitig.
- Push-Rückmeldung nach einer Neuigkeit zeigt jetzt explizit erfolgreiche und insgesamt eingereihte Geräte an.

## Beta 0.4.3 – 22.08.2026

- Beim Erstellen einer Neuigkeit kann optional „Als Push-Benachrichtigung an alle senden“ aktiviert werden.
- Neuigkeiten werden unabhängig vom Push-Ergebnis immer zuerst gespeichert.
- Push öffnet beim Antippen direkt den veröffentlichten Beitrag im internen Neuigkeiten-Bereich.
- Versandrückmeldung zeigt erfolgreiche Geräte, fehlende Zustellungen oder fehlende Push-Abonnements an.
- Neue besondere Mitgliederfunktion „Schriftführer“ ergänzt und in der Admin-Mitgliederverwaltung vergebbar gemacht.
- Schriftführer wird in Mitglieder-/Benutzeranzeigen als Zusatzamt dargestellt.
- Platzhalter „Kalender“, „Stammtisch“ und neu „Protokolle“ sind für alle eingeloggten Mitglieder in der Navigation sichtbar.
- Neue Platzhalterseite „Protokolle“ ergänzt; spätere Verwaltung ist für Schriftführer und Administrator vorgesehen.
- Schriftführer bleibt bei der rollenabhängigen Übersicht in der normalen/persönlichen Mitgliederansicht, solange kein eigenes Protokollmodul existiert.

## Beta 0.4.2 – 22.08.2026

- Übersicht rollenabhängig neu strukturiert.
- Spieß, Administrator und Zugführer sehen als operative Kurzansicht „Getränke gewählt“ und „Offene Runden“.
- Normale Mitglieder sowie Zusatzberechtigte ohne Zugführer-/Spieß-/Admin-Funktion sehen maximal drei Neuigkeiten, ihren aktuellen Getränkewunsch, ihre eigenen offenen Runden, die offenen Runden insgesamt und die letzten drei Runden.
- Persönlicher „Aktuelle Runde holen“-Hinweis bleibt für jeden Benutzer unabhängig von der Dashboard-Rolle verfügbar.
- Push-/Erinnerungsstatus von „Mein Getränk“ auf das Ende der Übersichtsseite verschoben.
- Push-Bereich zeigt jetzt allgemein den Status für Getränkeerinnerungen und „Runde holen“-Hinweise und ermöglicht Aktivieren/Deaktivieren direkt auf der Übersicht.

## Beta 0.4.1 – 22.08.2026

- Runden-Seite um die persönliche Kachel „Deine offenen Runden derzeit“ ergänzt.
- Jedes eingeloggte Mitglied sieht dort sofort die Anzahl der eigenen aktuell offenen Einzelrunden.
- Die persönliche Zahl ist unabhängig von der darunter weiterhin vollständig sichtbaren zugweiten Rundenliste.
- Auch Spieß und Administrator sehen in dieser Kachel ausschließlich ihre eigenen offenen Runden.

## Beta 0.4.0 – 22.08.2026

- Runden-Datenmodell korrigiert: Eine geschuldete Runde entspricht ab jetzt immer genau einem eigenen Datenbankeintrag.
- Gibt der Spieß beim Anlegen z. B. „4 Runden“ an, werden vier getrennte offene Rundeneinträge erzeugt.
- Jede dieser Runden kann dadurch einzeln „Runde holen geschickt“, als „Gegeben“ markiert, zurückgestellt, gestrichen oder gelöscht werden.
- Bestehende Mehrfach-Rundeneinträge werden durch Migration `010_split_round_entries.sql` automatisch in einzelne Einträge aufgeteilt.
- Bereits teilweise abgearbeitete Mehrfach-Einträge werden soweit möglich in erledigte und offene Einzelrunden zerlegt.
- Ein aktuell laufender Hol-Auftrag bleibt bei der automatischen Aufteilung auf genau einer Einzelrunde erhalten.
- Rundenansicht zeigt folgerichtig pro Zeile immer genau `1×` Runde.
- Hinweis beim Erfassen ergänzt, dass mehrere angegebene Runden als einzelne Einträge angelegt werden.

## Beta 0.3.3 – 22.08.2026

- Pull-to-Refresh für mobile Browser und installierte PWA ergänzt.
- Beim Herunterziehen am oberen Seitenrand erscheint ein sichtbarer Aktualisierungsindikator.
- Ab der Auslöseschwelle zeigt die App „Loslassen zum Aktualisieren“ und lädt die aktuelle Seite neu.
- In Eingabefeldern, Auswahlfeldern, Modalen und dem Neuigkeiten-WYSIWYG wird Pull-to-Refresh bewusst nicht ausgelöst.
- Auf Desktop-Geräten mit Maus bleibt der mobile Indikator ausgeblendet.

## Beta 0.3.2 – 22.08.2026

- Vom Administrator neu angelegte Benutzer müssen ihr Startpasswort beim ersten Login zwingend ändern.
- Erst nach erfolgreichem Passwortwechsel wird der normale App-Zugang freigegeben.
- „Eingeloggt bleiben“ wird bei einem Startpasswort erst nach dem erzwungenen Passwortwechsel aktiviert.
- Admin-Mitgliederliste zeigt „Passwortwechsel ausstehend“ bei noch nicht abgeschlossenen Erstlogins.
- Passwortregel systemweit auf mindestens 8 Zeichen, mindestens einen Buchstaben und mindestens eine Zahl geändert.
- Erlaubte Sonderzeichen werden unter den Passwortfeldern explizit angezeigt.
- Zulässige Sonderzeichen: ! # $ % & ( ) * + , - . / : ; < = > ? @ [ ] ^ _ { | } ~
- Passwort-Reset hebt einen eventuell noch offenen Erstlogin-Passwortzwang auf.

## Beta 0.3.1 – 22.08.2026

- Smilie-mit-Zylinder-Favicon und PWA-Appsymbole auf hellen, warmweißen Hintergrund umgestellt.
- PWA-Start-Hintergrund passend hell gesetzt; die App-Oberfläche selbst bleibt dunkel.
- Spieß/Admin können neue Getränkevorschläge jetzt ausdrücklich freigeben oder ablehnen.
- Ablehnen entfernt einen noch nicht freigegebenen Vorschlag.
- Getränkearten können zusätzlich endgültig gelöscht werden; Deaktivieren bleibt als reversible Alternative erhalten.
- Beim endgültigen Löschen werden aktuelle Wünsche auf die gelöschte Getränkeart automatisch auf „Derzeit nichts“ gesetzt.

## Beta 0.3.0 – 22.08.2026

- Neues Smilie-mit-Zylinder-Motiv als Favicon und PWA-Appsymbol übernommen.
- Icons auf dunklem anthrazit-schwarzem Hintergrund umgesetzt.
- Favicon-Größen 16–256 px, Apple-Touch-Icon sowie 192/512-PWA-Icons neu erzeugt.
- Maskable-PWA-Icon mit größerer Sicherheitszone erstellt, damit Zylinder und Smilie auf Android-Launchern nicht abgeschnitten werden.
- Cache-Version angehoben, damit Browser und PWA die neuen Symbole bevorzugt neu laden.

## Beta 0.2.9 – 22.08.2026

- Rundenbuch für alle eingeloggten Mitglieder vollständig einsehbar gemacht.
- Jedes Mitglied sieht jetzt die Runden aller Mitglieder inklusive Name, Anzahl, Grund/Notiz, Datum und Status.
- Normale Mitglieder erhalten weiterhin keinerlei Schreib-, Abhak-, Lösch-, Streichen- oder Losschick-Rechte.
- Bezeichnung in der Navigation vereinheitlicht auf „Runden“ statt „Meine Runden“.
- Dashboard zeigt jetzt ebenfalls die zugweiten offenen und letzten Runden.
- Der persönliche Button „Aktuelle Runde holen“ bleibt weiterhin ausschließlich beim tatsächlich losgeschickten Mitglied sichtbar.

## Beta 0.2.8 – 22.08.2026

- Runden-Seite für normale Mitglieder in allen relevanten Navigationen sichtbar gemacht.
- Desktop-Kopfnavigation zeigt für Mitglieder „Meine Runden“ und für Spieß/Admin „Runden“.
- „Runden“ als festen Hauptpunkt in die mobile PWA-Bottom-Navigation aufgenommen.
- Mitglieder sehen auf der Runden-Seite weiterhin ausschließlich ihre eigenen Einträge; Bearbeitungsaktionen bleiben Spieß/Admin vorbehalten.
- Mitgliederansicht um einen kurzen Hinweis zu offenen, laufenden und bereits gegebenen Runden ergänzt.

## Beta 0.2.7 – 22.08.2026

- Spieß und Administrator können Getränkewünsche stellvertretend für aktive Mitglieder erfassen.
- In der Getränkezentrale neue Auswahl „Getränk für Mitglied setzen“ ergänzt.
- Auswahl unterstützt alle aktiven/freigegebenen Getränke sowie „Derzeit nichts“.
- Mitglieder werden alphabetisch nach Nachname angezeigt und ihr aktueller Getränkestatus ist direkt in der Auswahl sichtbar.
- Stellvertretend gesetzte Wünsche verwenden dieselbe Live-Datenbasis wie die normale Mitgliedsauswahl und erscheinen sofort in Theken- und Holansicht.

## Beta 0.2.6 – 22.08.2026

- Neuigkeiten auf der Übersichtsseite direkt unter Begrüßung/Aktionsbereich und vor allen Statistik- und Getränkekacheln platziert.
- Menüpunkt „Neuigkeiten“ in der linken Navigation direkt auf Position 2 unter „Übersicht“ verschoben.
- Desktop-Kopfnavigation ebenfalls auf „Übersicht → Neuigkeiten → …“ angepasst.
- Mobile Bottom-Navigation zeigt „News“ direkt nach „Start“.

## Beta 0.2.5 – 22.08.2026

- Dashboard-Begrüßung von „Mahlzeit“ auf „Grüß Dich, [Vorname]!“ geändert.
- Kaffeetassen-Symbol bei „Runde abrufen“ durch ein Getränk-/Bierglas-Symbol ersetzt.

## Beta 0.2.4 – 22.08.2026

- Offizielles Strahlemännkes-Wappen in die Anwendung übernommen.
- Header-Branding von der bisherigen „SM“-Platzhaltermarke auf das echte Logo umgestellt.
- Originalwappen zusätzlich auf Startseite und Login eingebunden.
- Favicon, Apple-Touch-Icon sowie 192/512-PWA-Icons aus einer kompakten Smilie-mit-Zylinder-Variante des Logos erstellt.
- Maskable-PWA-Icon mit zusätzlicher Sicherheitszone ergänzt.
- Farbwelt nur behutsam an das Wappen angenähert: Schwarz/Anthrazit stärker gewichtet und das Smilie-Gelb als dezenter Akzent ergänzt; Rot bleibt Aktionsfarbe.
- PWA-Hintergrund/Theme auf das dunkle Logo-Schwarz abgestimmt.
- Service-Worker-Cache um die neuen Branding-Assets erweitert.

## Beta 0.2.3 – 22.08.2026

- Spieß und Administrator können Rundeneinträge zusätzlich zum Streichen endgültig löschen.
- Löschen ist als irreversible Test-/Bereinigungsfunktion deutlich gekennzeichnet und verlangt eine Bestätigung.
- Bilder im Neuigkeiten-WYSIWYG können nach dem Einfügen angeklickt und über einen Griff an der rechten unteren Ecke zwischen 20 % und 100 % Breite gezogen werden.
- Gewählte Bildbreite wird im Beitrag gespeichert und serverseitig auf sichere Prozentwerte begrenzt.
- Bilder bleiben auf Smartphones responsiv und können niemals breiter als der verfügbare Beitragsbereich werden.

## Beta 0.2.2 – 22.08.2026

- Neuigkeiten-WYSIWYG um direkten Bild-Upload ergänzt.
- Bilder können auf Smartphone/Tablet aus der Fotoauswahl gewählt und direkt an der Cursorposition in den Beitrag eingefügt werden.
- Unterstützte Bildformate: JPG, PNG, WebP und GIF bis 8 MB.
- Hochgeladene Dateien werden serverseitig per MIME-Typ und Bildprüfung validiert und mit zufälligem Dateinamen gespeichert.
- News-HTML-Bereinigung erlaubt nur Bilder aus dem app-eigenen `/uploads/news/`-Verzeichnis.
- Runden-Seite um vier Live-Statistiken ergänzt: meiste offene Runden, meiste Runden seit Aufzeichnung, schnellste losgeschickte/gegebene Runde und älteste offene Runde.
- Zeitpunkt des letzten „Runde holen schicken“ wird für die Geschwindigkeitsstatistik historisch erhalten.
- Direkte, ohne vorheriges Losschicken gegebene Runden fließen nicht in die Geschwindigkeitsstatistik ein.

## Beta 0.2.1 – 22.08.2026

- Spieß/Admin können eine offene Runde direkt als „Gegeben“ markieren, ohne das Mitglied vorher mit „Runde holen schicken“ zu aktivieren.
- Bei mehreren offenen Runden wird auch bei direktem „Gegeben“ jeweils genau eine Runde abgezogen.
- Mitgliederübersicht strikt alphabetisch nach Nachname und anschließend Vorname sortiert; Aktivstatus beeinflusst die Sortierung nicht mehr.
- Neuigkeiten-Editor zu einem mobilen WYSIWYG-Editor ausgebaut.
- Unterstützte Formatierungen: Fett, Kursiv, Unterstrichen, Durchgestrichen, Absätze, H2/H3, Aufzählungen, nummerierte Listen, Zitate, Textausrichtung, Links, Formatierung entfernen sowie Rückgängig/Wiederholen.
- Rich-Text-Formatierungen werden in SQLite gespeichert und beim Anzeigen beibehalten.
- Server-seitige HTML-Bereinigung auf erlaubte sichere Formatierungen und Linktypen ergänzt.
- Bestehende alte Nur-Text-Neuigkeiten bleiben lesbar und können im neuen Editor weiterbearbeitet werden.

## Beta 0.2.0 – 22.08.2026

- Rundenablauf neu als aktiver Hol-Auftrag umgesetzt: Offen → Runde holen geschickt → Gegeben.
- Spieß/Admin können eine konkrete offene Runde mit „Runde holen schicken“ aktivieren.
- Beim Aktivieren wird automatisch eine Push-Nachricht an das betroffene Mitglied gesendet.
- Pro Mitglied kann nur eine Runde gleichzeitig zum Holen aktiv sein.
- Bei mehreren geschuldeten Runden wird immer nur eine Runde aktiviert; nach „Gegeben“ bleiben weitere Runden offen.
- Spieß/Admin können einen laufenden Hol-Auftrag mit „Zurückstellen“ wieder in den offenen Zustand versetzen.
- Mitglieder sehen einen live aktualisierten Button „Aktuelle Runde holen“ auf Dashboard und Getränkeseite.
- Der Button erscheint und verschwindet automatisch, ohne dass die Seite manuell neu geladen werden muss.
- Neue helle Holansicht für Mitglieder mit aktueller Live-Getränkebestellung.
- Die Holansicht aktualisiert den Getränkestand alle fünf Sekunden direkt aus SQLite.
- Getränkewünsche können während eines laufenden Hol-Auftrags weiter geändert werden und erscheinen automatisch in der Live-Liste.
- Nach „Gegeben“ oder „Zurückstellen“ beendet sich die Holansicht automatisch.
- Kopieren und WhatsApp-Weitergabe bleiben in der Spieß-Thekenansicht erhalten.
- Die bisherige zusätzliche manuelle Push-Weitergabe aus der Thekenansicht wurde entfernt; Push ist jetzt eindeutig an „Runde holen schicken“ gekoppelt.

## Beta 0.1.2 – 22.08.2026

- Bisheriges Strafenmodul vollständig auf geschuldete Getränkerunden umgestellt.
- Keine Eurobeträge mehr: erfasst werden Mitglied, Anzahl Runden, Datum, Grund und optionale Erläuterung.
- Spieß/Admin können offene Runden als „gegeben“ markieren, streichen oder wieder öffnen.
- Erledigte Runden werden im Rundenbuch sichtbar durchgestrichen und bleiben als Historie erhalten.
- Mitglieder sehen ausschließlich ihre eigenen Runden; Spieß/Admin sehen und verwalten alle.
- Dashboard und Navigation von „Strafen“ auf „Runden“ umgestellt.
- Thekenansicht um kopierbare formatierte Bestellliste ergänzt.
- Direkte WhatsApp-Freigabe der aktuellen Getränkebestellung ergänzt.
- Aktuelle Getränkebestellung kann gezielt per Web Push an ein ausgewähltes Mitglied gesendet werden.
- Einzel-Push entfernt abgelaufene Geräteabonnements automatisch.

## Beta 0.1.1 – 22.08.2026

- Chronik um das Amt „Zugspieß“ ergänzt.
- Zugspieß kann in der Chronik-Administration pro Jahr erfasst und bearbeitet werden.
- Zugspieß wird in der öffentlichen Chronik angezeigt.

## Beta 0.1 – 22.08.2026

- Versionszählung für die weitere Testphase bewusst auf Beta 0.1 zurückgesetzt.
- Internen Bereich „Neuigkeiten“ als Blog für alle Mitglieder ergänzt.
- Schreibrechte für Neuigkeiten: Spieß, Zugführer, Kassierer und Administrator.
- Normale Mitglieder besitzen im Neuigkeiten-Bereich ausschließlich Leserechte.
- Zugführer als eigenständiges Zusatzamt/Recht in der Mitgliederverwaltung ergänzt.
- Chronik vollständig auf SQLite-Daten umgestellt.
- Separate Seite „Chronik verwalten“ für Spieß, Zugführer und Administrator ergänzt.
- Chronik-Jahrgänge können angelegt, bearbeitet, veröffentlicht/ausgeblendet und gelöscht werden.
- Admin und Kassierer technisch voneinander entkoppelt; der bestehende erste Admin bleibt als Kassierer markiert.
- Neuigkeiten auf dem Dashboard und in der mobilen Navigation hervorgehoben.

## Frühere Teststände vor dem Versions-Reset

## Beta 0.9.3 – 22.08.2026

- Sichtbaren Push-/Erinnerungsstatus in „Mein Getränk“ ergänzt.
- Unterscheidung zwischen aktiviert, nicht aktiviert, blockiert und nicht unterstützt.
- Button wechselt automatisch zwischen „Erinnerungen aktivieren“ und „Erinnerungen deaktivieren“.
- Deaktivieren kündigt das Browser-Push-Abo und entfernt es gleichzeitig aus SQLite.
- Neuen Endpunkt `push-unsubscribe.php` ergänzt.
- Service-Worker-/Asset-Cache auf Beta 0.9.3 aktualisiert.

## Beta 0.9.2 – 22.08.2026

- Fehler beim Speichern von Web-Push-Abonnements behoben.
- SQL in `push-subscribe.php` verwendet jetzt echte Zeilenumbrüche statt versehentlich übergebener `\n`-Zeichen.
- Push-Abo-Endpunkt liefert strukturierte JSON-Fehler zurück.
- Browser zeigt bei künftigen Push-Fehlern die konkrete Serverursache statt nur einer allgemeinen Meldung.
- Service-Worker-/Asset-Cache auf Beta 0.9.2 aktualisiert.

## Beta 0.9.1 – 22.08.2026

- Echten serverseitigen Web-Push-Versand für Getränkeerinnerungen ergänzt.
- Push kann wahlweise an alle aktiven Mitglieder oder nur Mitglieder ohne Getränkewahl gesendet werden.
- Abgelaufene Push-Abonnements (HTTP 404/410) werden automatisch aus SQLite entfernt.
- Aussagekräftige Versandrückmeldung für den Spieß ergänzt.
- VAPID-Konfiguration pro App direkt in `config.php` vorbereitet.
- Globalen „App installieren“-Button ergänzt; er wird nur angezeigt, wenn die Seite nicht als installierte PWA läuft.
- Installationshinweise für iPhone sowie Fallback-Hinweis für Browser ohne nativen Installationsdialog ergänzt.
- Service-Worker-Cache auf Beta 0.9.1 aktualisiert.

## Beta 0.9.0 – 22.08.2026

- Mitgliederübersicht für alle eingeloggten Mitglieder freigeschaltet.
- Login-Namen, E-Mail-Adressen und Bearbeitungsfunktionen bleiben ausschließlich Administratoren vorbehalten.
- Separates Kassenrecht eingeführt, unabhängig von der eigentlichen Benutzerrolle.
- Administratoren besitzen immer Kassenbearbeitungsrechte; weitere Mitglieder können vom Admin zusätzlich als Kassierer markiert werden.
- Neuer interner Bereich „Kasse“ für alle Mitglieder.
- Kassenstand mit Stichtag, Notiz und Verlauf ergänzt.
- Monatlichen Mitgliedsbeitrag als zentrale Kasseneinstellung ergänzt.
- Bearbeitung von Kassenstand und Monatsbeitrag auf Admin/Kassierer beschränkt.
- Kassenstatus auf dem Dashboard ergänzt.
- Navigation für Mitglieder und Kasse auf Desktop und Mobil erweitert.

## Beta 0.8.1 – 22.08.2026

- Öffentliche Chronik vollständig auf dunkles Design umgestellt; helle Hero-Flächen entfernt.
- Startseite gegen helle Flächenreste abgesichert.
- Google Fonts eingebunden: Permanent Marker für große Überschriften, Roboto für Fließtext und Inter für UI/Navigation.
- Kontrast der öffentlichen Überschriften und Beschreibungstexte erhöht.
- Thekenansicht bleibt bewusst hell und verwendet weiterhin eine nüchterne UI-Typografie.
- Service-Worker-Cache auf die Release-Version gekoppelt und frisches Laden der Design-Assets bei Updates erzwungen.

## Beta 0.8.0 – 22.08.2026

- Dashboard vollständig von Demo-Daten auf Live-Daten aus SQLite umgestellt.
- Gemeinsame Rollen-/Seitennavigation eingeführt; tote `#`-Links entfernt.
- Rollenanzeige und Begrüßung verwenden jetzt den tatsächlich angemeldeten Benutzer.
- Getränkewahl der Mitglieder vollständig mit SQLite verdrahtet.
- Getränkezentrale funktional gemacht: anlegen, bearbeiten, freigeben, aktivieren/deaktivieren, erinnern und Status zurücksetzen.
- Helle Thekenansicht mit echten Rückwegen zu Übersicht und Verwaltung ergänzt.
- Grundmodul für Strafen ergänzt: erfassen sowie offen/bezahlt/storniert verwalten.
- Mitgliederverwaltung überarbeitet: bearbeiten, E-Mail, Rollen, Aktivstatus und Passwortregel ab 9 Zeichen.
- Kalender und Stammtisch bleiben echte, klar gekennzeichnete Platzhalter und sind über reale URLs erreichbar.
- Mobile Navigation an die Rollen und realen Seiten angepasst.

## Beta 0.7.3 – 22.08.2026

- Regression der öffentlichen Startseite behoben.
- Startseite wieder auf Überschrift, internen Login und Chronik-Link reduziert.
- `home-page`-Klasse wieder korrekt gesetzt, damit Navbar ausgeblendet und das dunkle Startseitendesign zuverlässig angewendet wird.

## Beta 0.7.2 – 22.08.2026

- Mail-Absender pro Installation direkt in `config.php` konfigurierbar gemacht.
- Standard-Absender für diese Installation auf `fischje@fischje.de` gesetzt.

## Beta 0.7.1 – 22.08.2026

- Zentrale Versionsanzeige im Footer und `VERSION`-Datei für Git/GitHub ergänzt.
- Getränkebearbeitung für Spieß/Admin ergänzt.
- Passwortregel vereinheitlicht: mindestens 9 Zeichen, mindestens ein Buchstabe und eine Zahl.
- Passwort-Reset per E-Mail-Link ergänzt.
- Option „Auf diesem Gerät eingeloggt bleiben“ ergänzt.
- Kontraste, Buttons und Rundungen im Dark-Theme überarbeitet.
- PWA-/Asset-Cache-Busting verstärkt, damit Designänderungen nach Releases sofort geladen werden.

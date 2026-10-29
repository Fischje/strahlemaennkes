<?php
$appBootstrapConfig = require __DIR__ . '/../config.php';
date_default_timezone_set($appBootstrapConfig['timezone'] ?? 'Europe/Berlin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => request_is_https(),
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/push.php';

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function protocol_sanitize_html(string $html): string {
    $html = trim($html);
    if ($html === '') return '';

    // Skripte, Styles und Kommentare vorab vollständig entfernen.
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
    $html = preg_replace('/<!--.*?-->/s', '', $html) ?? '';

    $allowed = '<p><br><strong><b><em><i><u><s><h2><h3><ul><ol><li><a><blockquote><div><img>';
    $html = strip_tags($html, $allowed);

    $html = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function(array $m): string {
        $tag = strtolower($m[1]);
        $attrs = $m[2] ?? '';

        if ($tag === 'a') {
            $href = '';
            if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $hm)) {
                $href = html_entity_decode(trim($hm[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/\bhref\s*=\s*([^\s"\'>]+)/i', $attrs, $hm)) {
                $href = html_entity_decode(trim($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if ($href !== '' && preg_match('#^(https?://|mailto:|tel:|/)#i', $href)) {
                return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">';
            }
            return '<a>';
        }

        if ($tag === 'img') {
            $src = '';
            $alt = '';
            if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $sm)) {
                $src = html_entity_decode(trim($sm[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $attrs, $am)) {
                $alt = trim($am[2]);
            }
            // Bilder werden ausschließlich über den geschützten Protokoll-Endpunkt ausgeliefert.
            if (preg_match('#^/meeting-image\.php\?id=[1-9][0-9]*$#', $src)) {
                $width = 100;
                if (preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/i', $attrs, $wm)
                    && preg_match('/(?:^|;)\s*width\s*:\s*(\d{1,3})%/i', $wm[2], $wp)) {
                    $width = max(20, min(100, (int)$wp[1]));
                }
                return '<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($alt, ENT_QUOTES, 'UTF-8').'" loading="lazy" style="width:'.$width.'%">';
            }
            return '';
        }

        if (in_array($tag, ['p','div'], true)) {
            if (preg_match('/text-align\s*:\s*(left|right|center|justify)/i', $attrs, $am)) {
                return '<'.$tag.' style="text-align:'.strtolower($am[1]).'">';
            }
        }

        return '<'.$tag.'>';
    }, $html) ?? '';

    return trim($html);
}

function protocol_body_html(string $body): string {
    $body = trim($body);
    if ($body === '') return '';
    if (!preg_match('/<[^>]+>/', $body)) {
        return nl2br(e($body));
    }
    return protocol_sanitize_html($body);
}
function protocol_body_text_length(string $body): int {
    $plain = html_entity_decode(strip_tags(protocol_sanitize_html($body)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_strlen(trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain));
}
function redirect(string $path): never { header('Location: ' . $path); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type','message'); }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Ungültige oder abgelaufene Anfrage.'); } }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function role_label(string $role): string { return ['member'=>'Mitglied','spiess'=>'Spieß / Moderator','admin'=>'Administrator'][$role] ?? $role; }
function is_treasurer(): bool { $u=current_user(); return (bool)($u && !empty($u['is_treasurer'])); }
function can_edit_cash(): bool { return has_role('admin') || is_treasurer(); }
function is_leader(): bool { $u=current_user(); return (bool)($u && !empty($u['is_leader'])); }
function is_secretary(): bool { $u=current_user(); return (bool)($u && !empty($u['is_secretary'])); }
function can_manage_protocols(): bool { return has_role('admin','spiess') || is_secretary() || is_treasurer() || is_leader(); }
function can_create_meetings(PDO $pdo, ?array $user=null): bool { return can_act_as_spiess($pdo,$user) || is_treasurer() || is_leader(); }
function can_manage_meetings(PDO $pdo, ?array $user=null): bool { return can_create_meetings($pdo,$user) || is_secretary(); }
function can_run_protocol(PDO $pdo, ?array $user=null): bool { return is_leader() || is_secretary() || can_act_as_spiess($pdo,$user); }
/** Wer die Anwesenheitsstatistik sehen darf (aktuell nur Admin; später ggf. alle Mitglieder). */
function can_view_stats(PDO $pdo, ?array $user=null): bool { return has_role('admin'); }
/** Kirmes-Planung: vorerst nur Admin (Ansicht und Bearbeitung getrennt, damit später die Ansicht geöffnet werden kann). */
function can_view_kirmes(PDO $pdo, ?array $user=null): bool { return has_role('admin'); }
function can_edit_kirmes(PDO $pdo, ?array $user=null): bool { return has_role('admin'); }
function can_reopen_protocols(): bool { return has_role('admin') || is_secretary(); }
function can_edit_chronicle(): bool { return has_role('spiess','admin') || is_leader(); }
function can_manage_assembly(): bool { return has_role('admin') || is_leader(); }
function sektbar_is_enabled(PDO $pdo): bool {
    $st=$pdo->prepare("SELECT is_enabled FROM feature_settings WHERE feature_key='sektbar'");
    $st->execute();
    return (int)$st->fetchColumn()===1;
}
function can_use_sektbar(PDO $pdo, ?array $user=null): bool {
    $user=$user ?: current_user();
    return $user && sektbar_is_enabled($pdo) && can_act_as_spiess($pdo,$user);
}
function sektbar_is_active(PDO $pdo): bool {
    return (int)$pdo->query("SELECT is_active FROM sektbar_state WHERE id=1")->fetchColumn()===1;
}
function active_spiess_push_user_ids(PDO $pdo): array {
    $d=active_spiess_delegation($pdo);
    if($d) return [(int)$d['representative_user_id']];
    return array_map('intval',$pdo->query("SELECT id FROM users WHERE is_active=1 AND role='spiess'")->fetchAll(PDO::FETCH_COLUMN));
}
function notify_active_spiess_drink_change(PDO $pdo, array $member, string $drinkName): void {
    foreach(active_spiess_push_user_ids($pdo) as $uid){
        if($uid===(int)$member['id']) continue;
        send_push_to_user($pdo,$uid,'Getränk geändert',$member['first_name'].' '.$member['last_name'].': '.$drinkName,'/theke.php','drink-change','getraenke');
    }
}
function german_weekday_short(DateTimeInterface $d): string {
 return [1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr',6=>'Sa',7=>'So'][(int)$d->format('N')];
}
function active_assembly(PDO $pdo): ?array {
 $r=$pdo->query("SELECT * FROM assembly_status WHERE id=1 LIMIT 1")->fetch();
 if(!$r) return null;
 try {
  $utc=(new DateTimeImmutable($r['starts_at_utc'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
  if(new DateTimeImmutable('now',new DateTimeZone('UTC')) >= $utc->modify('+1 hour')) return null;
  $local=$utc->setTimezone(new DateTimeZone('Europe/Berlin'));
  $r['weekday_short']=german_weekday_short($local);
  $r['display_date']=$local->format('d.m.Y');
  $r['display_time']=$local->format('H:i');
  return $r;
 } catch(Throwable $e) { return null; }
}
function require_treasurer(): array { $u=require_login(); if(!can_edit_cash()){ http_response_code(403); exit('Keine Berechtigung für die Kassenverwaltung.'); } return $u; }
function require_meeting_manager(PDO $pdo): array { $u=require_login(); if(!can_manage_meetings($pdo,$u)){ http_response_code(403); exit('Keine Berechtigung für Stammtische.'); } return $u; }
function require_chronicle_editor(): array { $u=require_login(); if(!can_edit_chronicle()){ http_response_code(403); exit('Keine Berechtigung zur Bearbeitung der Chronik.'); } return $u; }
function has_role(string ...$roles): bool { $u=current_user(); return $u && in_array($u['role'],$roles,true); }
function active_spiess_delegation(PDO $pdo): ?array {
    static $loaded=false;
    static $delegation=null;
    if($loaded) return $delegation;
    $loaded=true;

    $st=$pdo->query("
        SELECT d.*,
               r.first_name representative_first,
               r.last_name representative_last,
               a.first_name assigned_first,
               a.last_name assigned_last
        FROM spiess_delegations d
        JOIN users r ON r.id=d.representative_user_id AND r.is_active=1
        JOIN users a ON a.id=d.assigned_by
        WHERE d.active=1 AND d.ended_at IS NULL
        ORDER BY d.id DESC
        LIMIT 1
    ");
    $delegation=$st->fetch() ?: null;
    return $delegation;
}

function is_spiess_delegate(PDO $pdo, ?array $user=null): bool {
    $user=$user ?: current_user();
    if(!$user) return false;
    $d=active_spiess_delegation($pdo);
    return $d && (int)$d['representative_user_id']===(int)$user['id'];
}

function can_act_as_spiess(PDO $pdo, ?array $user=null): bool {
    $user=$user ?: current_user();
    if(!$user) return false;
    if(in_array($user['role'],['spiess','admin'],true)) return true;
    return is_spiess_delegate($pdo,$user);
}

function require_spiess_operator(PDO $pdo): array {
    $u=require_login();
    if(!can_act_as_spiess($pdo,$u)){
        http_response_code(403);
        exit('Keine Berechtigung für die operativen Spieß-Funktionen.');
    }
    return $u;
}

function can_manage_spiess_delegation(): bool {
    return has_role('spiess','admin');
}

/** Zusatzfunktionen, für die das Mitglied berechtigt ist: [Link, Icon, Titel, Beschreibung]. Genutzt von functions.php und dem Menü „Funktionen“. */
function function_menu_items(PDO $pdo, array $u): array {
    $items=[];
    if(can_create_meetings($pdo,$u)) $items[]=['/stammtisch.php','bi-calendar2-plus','Terminfindung starten','Neuen Stammtisch mit Terminoptionen anlegen.'];
    if(can_act_as_spiess($pdo,$u)) $items[]=['/drinks.php','bi-sliders','Getränke verwalten','Getränke bearbeiten, freigeben und für die Sektbar kennzeichnen.'];
    if(has_role('admin') || sektbar_is_enabled($pdo)) {
        $sektText=sektbar_is_active($pdo) ? 'Sektbarmodus ist aktuell aktiv.' : (sektbar_is_enabled($pdo) ? 'Sektbar öffnen und Status ansehen.' : 'Sektbar ist noch nicht freigeschaltet.');
        $items[]=['/sektbar.php','bi-stars','Sektbar',$sektText];
    }
    if(can_manage_spiess_delegation()) $items[]=['/spiess-vertretung.php','bi-person-badge','Spieß-Vertretung','Vertretung einsetzen oder wieder beenden.'];
    if(can_manage_assembly()) $items[]=['/antreten.php','bi-clock','Antreten festlegen','Nächsten Termin, Treffpunkt und Kleidung festlegen.'];
    if(can_edit_chronicle()) $items[]=['/chronik-admin.php','bi-pencil-square','Chronik verwalten','Einträge der Zug-Chronik bearbeiten und veröffentlichen.'];
    if(can_view_kirmes($pdo,$u)) $items[]=['/kirmes.php','bi-calendar-event','Kirmes-Planung','Tage mit Antreten, Kleidung und Mitbringsel.'];
    if(can_view_stats($pdo,$u)) $items[]=['/statistik.php','bi-bar-chart','Anwesenheitsstatistik','Wer war wie oft beim Stammtisch?'];
    if(has_role('admin')) $items[]=['/admin-features.php','bi-toggles','Freischaltungen','Registrierung und optionale Funktionen zentral freischalten.'];
    return $items;
}

function app_setting(PDO $pdo, string $key, ?string $default=null): ?string {
    $st=$pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
    $st->execute([$key]);
    $value=$st->fetchColumn();
    return $value===false ? $default : (string)$value;
}

function app_setting_bool(PDO $pdo, string $key, bool $default=false): bool {
    $value=app_setting($pdo,$key,$default ? '1' : '0');
    return in_array(strtolower((string)$value),['1','true','yes','on'],true);
}

function set_app_setting(PDO $pdo, string $key, string $value, ?int $updatedBy=null): void {
    $st=$pdo->prepare("
        INSERT INTO app_settings(setting_key,setting_value,updated_at,updated_by)
        VALUES(?,?,CURRENT_TIMESTAMP,?)
        ON CONFLICT(setting_key) DO UPDATE SET
            setting_value=excluded.setting_value,
            updated_at=CURRENT_TIMESTAMP,
            updated_by=excluded.updated_by
    ");
    $st->execute([$key,$value,$updatedBy]);
}

function public_registration_enabled(PDO $pdo): bool {
    return app_setting_bool($pdo,'registration_enabled',false);
}
function require_login(): array {
    $u=current_user();
    if(!$u){
        flash('warning','Bitte zuerst anmelden.');
        redirect('/login.php');
    }
    $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
    if(!empty($u['must_change_password']) && $path!=='/first-login-password.php'){
        redirect('/first-login-password.php');
    }
    // Merker für die Kopfzeile: Hinweisleisten (Stammtisch, Antreten, Sektbar) nur auf Seiten des internen Bereichs.
    $GLOBALS['smInternalPage']=true;
    return $u;
}
function require_role(string ...$roles): array { $u=require_login(); if(!in_array($u['role'],$roles,true)){ http_response_code(403); exit('Keine Berechtigung.'); } return $u; }

function active_round_for_user(PDO $pdo, int $userId): ?array {
    $st=$pdo->prepare("
        SELECT f.*,c.first_name creator_first,c.last_name creator_last,
               d.first_name dispatcher_first,d.last_name dispatcher_last
        FROM fines f
        JOIN users c ON c.id=f.created_by
        LEFT JOIN users d ON d.id=f.dispatched_by
        WHERE f.user_id=? AND f.status='open' AND f.dispatched_at IS NOT NULL
        ORDER BY f.dispatched_at DESC,f.id DESC
        LIMIT 1
    ");
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

/** Zeitstempel des Hintergrundbilds (null, wenn keines hochgeladen ist). Wird für den Cache-Parameter der Bild-URL genutzt. */
function site_background_stamp(PDO $pdo): ?int {
    static $loaded=false, $stamp=null;
    if(!$loaded){
        $loaded=true;
        try{
            $v=$pdo->query('SELECT EXTRACT(EPOCH FROM updated_at)::bigint FROM site_background WHERE id=1')->fetchColumn();
            $stamp=$v===false ? null : (int)$v;
        }catch(Throwable $e){ $stamp=null; }
    }
    return $stamp;
}
function app_version(): array {
    static $version = null;
    if ($version === null) $version = require __DIR__ . '/version.php';
    return $version;
}

function app_changelog(int $limit=6): array {
    $file = dirname(__DIR__) . '/CHANGELOG.md';
    $lines = is_readable($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
    $out = []; $cur = null;
    foreach ($lines as $line) {
        if (preg_match('/^##\s+(.+)$/u', $line, $m)) {
            if ($cur !== null) $out[] = $cur;
            if (count($out) >= $limit) { $cur = null; break; }
            $cur = ['title' => trim($m[1]), 'items' => []];
        } elseif ($cur !== null && preg_match('/^-\s+(.+)$/u', $line, $m)) {
            $cur['items'][] = str_replace('`', '', trim($m[1]));
        }
    }
    if ($cur !== null && count($out) < $limit) $out[] = $cur;
    return $out;
}

function password_allowed_specials(): string {
    return '! # $ % & ( ) * + , - . / : ; < = > ? @ [ ] ^ _ { | } ~';
}

function password_is_valid(string $password): bool {
    if(mb_strlen($password,'UTF-8') < 8) return false;
    if(preg_match('/\p{L}/u',$password)!==1) return false;
    if(preg_match('/[0-9]/',$password)!==1) return false;

    // Buchstaben (inkl. Umlaute), Ziffern und bewusst begrenzte ASCII-Sonderzeichen.
    return preg_match('/[^\p{L}0-9!#$%&()*+,\-.\/:;<=>?@\[\]\^_{}|~]/u',$password)!==1;
}

function password_rule_text(): string {
    return 'Mindestens 8 Zeichen, mindestens ein Buchstabe und mindestens eine Zahl.';
}

function password_rule_help(): string {
    return password_rule_text().' Erlaubte Sonderzeichen: '.password_allowed_specials();
}

/** Die Felder des Benutzers, die in der Sitzung gehalten werden. */
function session_user_from_row(array $user): array {
    return [
        'id'=>(int)$user['id'],
        'username'=>$user['username'],
        'first_name'=>$user['first_name'],
        'last_name'=>$user['last_name'],
        'role'=>$user['role'],
        'is_treasurer'=>(int)($user['is_treasurer'] ?? 0),
        'is_leader'=>(int)($user['is_leader'] ?? 0),
        'is_secretary'=>(int)($user['is_secretary'] ?? 0),
        'must_change_password'=>(int)($user['must_change_password'] ?? 0),
    ];
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = session_user_from_row($user);
}

/**
 * Gleicht die Sitzung bei jeder Anfrage mit der Datenbank ab. Ohne diesen Abgleich behielt ein
 * deaktiviertes Mitglied oder ein Admin, dem die Rolle entzogen wurde, seine Rechte bis zum Abmelden.
 */
function refresh_session_user(PDO $pdo): void {
    $u = current_user();
    if (!$u) return;
    $st = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([(int)$u['id']]);
    $row = $st->fetch();
    if (!$row || (int)$row['is_active'] !== 1) {
        clear_remember_login($pdo);
        $_SESSION = [];
        session_regenerate_id(true);
        flash('warning', 'Dein Konto ist nicht mehr aktiv. Bitte wende dich an den Administrator.');
        return;
    }
    $_SESSION['user'] = session_user_from_row($row);
}

/* ---------- Schutz gegen Passwort-Raten: höchstens LOGIN_MAX_FAILURES Fehlversuche je Login in LOGIN_WINDOW_MINUTES ---------- */
const LOGIN_MAX_FAILURES = 8;
const LOGIN_WINDOW_MINUTES = 15;
function login_key(string $login): string { return mb_strtolower(trim($login), 'UTF-8'); }
function login_is_throttled(PDO $pdo, string $login): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE login_key=? AND attempted_at > CURRENT_TIMESTAMP - make_interval(mins => ?)");
    $st->execute([login_key($login), LOGIN_WINDOW_MINUTES]);
    return (int)$st->fetchColumn() >= LOGIN_MAX_FAILURES;
}
function login_record_failure(PDO $pdo, string $login): void {
    $pdo->prepare('INSERT INTO login_attempts(login_key) VALUES(?)')->execute([mb_substr(login_key($login), 0, 254)]);
}
function login_clear_failures(PDO $pdo, string $login): void {
    $pdo->prepare('DELETE FROM login_attempts WHERE login_key=?')->execute([login_key($login)]);
}

/** Fehlermeldung für die Oberfläche: eigene Hinweise ja, Datenbank- und Systemfehler nur allgemein (Details ins Log). */
function user_error_message(Throwable $e): string {
    if ($e instanceof RuntimeException && !($e instanceof PDOException)) return $e->getMessage();
    error_log('Strahlemännkes: ' . get_class($e) . ': ' . $e->getMessage());
    return 'Das hat nicht geklappt. Bitte erneut versuchen oder den Administrator informieren.';
}

/** Regelmäßige Aufräumarbeiten (stündlich aus dem Cron-Skript): abgelaufene Tokens und alte Fehlversuche löschen. */
function app_maintenance(PDO $pdo): void {
    $pdo->exec('DELETE FROM remember_tokens WHERE expires_at <= CURRENT_TIMESTAMP');
    $pdo->exec('DELETE FROM password_reset_tokens WHERE used_at IS NOT NULL OR expires_at <= CURRENT_TIMESTAMP');
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < CURRENT_TIMESTAMP - INTERVAL '1 day'");
    $pdo->exec("DELETE FROM domain_handoff_tokens WHERE expires_at < CURRENT_TIMESTAMP - INTERVAL '1 day'");
    $pdo->exec('DELETE FROM calendar_feed_tokens t USING users u WHERE u.id=t.user_id AND u.is_active=0');
}

/** Gemeinsame Daten für die Live-Bestellung (Theke und Runde holen). */
function live_drink_status(PDO $pdo): array {
    $items=[];
    $memberRows=$pdo->query("SELECT d.id,d.name,d.emoji,u.first_name || ' ' || substr(u.last_name,1,1) || '.' person, upper(substr(trim(u.first_name),1,1) || substr(trim(u.last_name),1,1)) export_code FROM users u JOIN user_drink_preferences p ON p.user_id=u.id JOIN drinks d ON d.id=p.drink_id WHERE u.is_active=1")->fetchAll();
    $guestRows=$pdo->query("SELECT d.id,d.name,d.emoji,g.guest_name || ' (Gast)' person, 'G' || g.id export_code FROM guest_drink_orders g JOIN drinks d ON d.id=g.drink_id")->fetchAll();
    foreach(array_merge($memberRows,$guestRows) as $r){
        $id=(int)$r['id'];
        if(!isset($items[$id])) $items[$id]=['id'=>$id,'name'=>$r['name'],'emoji'=>$r['emoji'],'qty'=>0,'members'=>[],'export_codes'=>[]];
        $items[$id]['qty']++;
        $items[$id]['members'][]=$r['person'];
        $items[$id]['export_codes'][]=$r['export_code'];
    }
    $rows=array_values($items);
    usort($rows,fn($a,$b)=>$b['qty']<=>$a['qty'] ?: strcasecmp($a['name'],$b['name']));
    $total=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
    $noneMembers=$pdo->query("SELECT u.first_name || ' ' || substr(u.last_name,1,1) || '.' AS name FROM users u LEFT JOIN user_drink_preferences p ON p.user_id=u.id WHERE u.is_active=1 AND p.drink_id IS NULL ORDER BY u.last_name,u.first_name")->fetchAll(PDO::FETCH_COLUMN);
    $memberChosen=count($memberRows);
    $guestCount=count($guestRows);
    return ['updated_at'=>date(DATE_ATOM),'total'=>$total,'chosen'=>$memberChosen+$guestCount,'member_chosen'=>$memberChosen,'guest_count'=>$guestCount,'none'=>max(0,$total-$memberChosen),'none_members'=>$noneMembers,'drinks'=>$rows];
}

/** Für JSON-Endpunkte: Anfrage muss von der eigenen Seite kommen (zusätzlich zu SameSite-Cookies). */
function require_same_origin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $ok = $site === '' || in_array($site, ['same-origin', 'none'], true);
    if ($ok && $origin !== '' && $host !== '') {
        $ok = strcasecmp((string)parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : ''), $host) === 0;
    }
    if (!$ok) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'error' => 'Anfrage nicht erlaubt.'], JSON_UNESCAPED_UNICODE));
    }
}

function request_is_https(): bool {
    if(!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS'])!=='off') return true;
    if(strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))==='https') return true;
    if((string)($_SERVER['SERVER_PORT'] ?? '')==='443') return true;
    return false;
}

function remember_cookie_options(int $expires): array {
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function create_remember_login(PDO $pdo, int $userId): void {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    // 180 Tage statt 30 Tage; bei erfolgreicher Wiederherstellung wird
    // die Laufzeit erneut verlängert (gleitender Dauer-Login).
    $expiresTs = time() + 60 * 60 * 24 * 180;
    $expiresAt = gmdate('Y-m-d H:i:s', $expiresTs);

    // Wichtig: andere Geräte/Browser desselben Benutzers NICHT abmelden.
    // Früher wurden hier alle Tokens dieses Benutzers gelöscht.
    $pdo->prepare('DELETE FROM remember_tokens WHERE expires_at <= CURRENT_TIMESTAMP')->execute();
    $pdo->prepare('INSERT INTO remember_tokens(user_id,token_hash,expires_at) VALUES(?,?,?)')
        ->execute([$userId,$hash,$expiresAt]);

    setcookie('sm_remember', $token, remember_cookie_options($expiresTs));
    $_COOKIE['sm_remember']=$token;
}

function clear_remember_login(PDO $pdo): void {
    $token = $_COOKIE['sm_remember'] ?? '';
    if ($token !== '') {
        $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash=?')->execute([hash('sha256',$token)]);
    }
    setcookie('sm_remember', '', remember_cookie_options(time()-3600));
    unset($_COOKIE['sm_remember']);
}

function restore_remember_login(PDO $pdo): void {
    if (current_user() || empty($_COOKIE['sm_remember'])) return;

    $token=(string)$_COOKIE['sm_remember'];
    $hash=hash('sha256',$token);

    $st=$pdo->prepare('
        SELECT u.*
        FROM remember_tokens r
        JOIN users u ON u.id=r.user_id
        WHERE r.token_hash=?
          AND r.expires_at > CURRENT_TIMESTAMP
          AND u.is_active=1
        LIMIT 1
    ');
    $st->execute([$hash]);
    $user=$st->fetch();

    if(!$user){
        clear_remember_login($pdo);
        return;
    }

    login_user($user);

    // Gleitende Laufzeit: ein genutztes Gerät bleibt weitere 180 Tage angemeldet.
    $expiresTs=time() + 60 * 60 * 24 * 180;
    $expiresAt=gmdate('Y-m-d H:i:s',$expiresTs);
    $pdo->prepare('UPDATE remember_tokens SET expires_at=? WHERE token_hash=?')
        ->execute([$expiresAt,$hash]);
    setcookie('sm_remember',$token,remember_cookie_options($expiresTs));
}

function absolute_url(string $path): string {
    $base = app_base_url();
    if ($base !== '') return rtrim($base,'/') . '/' . ltrim($path,'/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path,'/');
}

/* ---------- Domain-Umzug ----------
 * Die offizielle Adresse steht in base_url (config.php) bzw. APP_URL. Alte Adressen stehen in legacy_hosts
 * bzw. APP_LEGACY_HOSTS (kommagetrennt). Ruft jemand die App über eine alte Adresse auf, sieht er nur noch
 * die Umzugsseite (umzug.php) und kann sich von dort angemeldet auf die neue Adresse bringen lassen.
 * Ohne legacy_hosts ist alles wie bisher. */

function app_base_url(): string {
    static $base = null;
    if ($base === null) {
        $config = require __DIR__ . '/../config.php';
        $base = trim((string)($config['base_url'] ?? ''));
        if ($base === '') $base = trim((string)(getenv('APP_URL') ?: ''));
        $base = rtrim($base, '/');
    }
    return $base;
}

/** Hostname in Kleinbuchstaben, ohne Port. Umlaut-Domains bitte immer in Punycode (xn--…) angeben. */
function normalize_host(string $host): string {
    $host = strtolower(trim($host));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    return rtrim($host, '.');
}

function app_canonical_host(): string {
    $base = app_base_url();
    return $base === '' ? '' : normalize_host((string)parse_url($base, PHP_URL_HOST));
}

function app_legacy_hosts(): array {
    static $hosts = null;
    if ($hosts === null) {
        $config = require __DIR__ . '/../config.php';
        $raw = $config['legacy_hosts'] ?? (getenv('APP_LEGACY_HOSTS') ?: '');
        $list = is_array($raw) ? $raw : explode(',', (string)$raw);
        $canonical = app_canonical_host();
        $hosts = array_values(array_unique(array_filter(array_map(fn($h) => normalize_host((string)$h), $list),
            fn($h) => $h !== '' && $h !== $canonical)));
    }
    return $hosts;
}

/** Umzug ist eingeschaltet: neue Adresse gesetzt und mindestens eine alte Adresse eingetragen. */
function domain_move_active(): bool {
    return app_canonical_host() !== '' && app_legacy_hosts() !== [];
}

function request_host(): string {
    return normalize_host((string)($_SERVER['HTTP_HOST'] ?? ''));
}

/** Kommt die aktuelle Anfrage über eine alte Adresse? */
function request_is_legacy_host(): bool {
    return PHP_SAPI !== 'cli' && domain_move_active() && in_array(request_host(), app_legacy_hosts(), true);
}

/** Lesbare Form der neuen Adresse (Punycode wird, wenn möglich, in Umlaute zurückverwandelt). */
function app_display_host(): string {
    $labels = explode('.', app_canonical_host());
    foreach ($labels as &$label) {
        if (str_starts_with($label, 'xn--')) $label = punycode_decode_label(substr($label, 4)) ?? 'xn--' . $label;
    }
    return implode('.', $labels);
}

/** Punycode nach RFC 3492 (ein Namensteil ohne „xn--“), damit keine php-intl-Erweiterung nötig ist. */
function punycode_decode_label(string $input): ?string {
    $n = 128; $i = 0; $bias = 72; $output = [];
    $pos = strrpos($input, '-');
    if ($pos !== false) { $output = str_split(substr($input, 0, $pos)); $input = substr($input, $pos + 1); }
    $len = strlen($input); $in = 0;
    while ($in < $len) {
        $oldi = $i; $w = 1;
        for ($k = 36; ; $k += 36) {
            if ($in >= $len) return null;
            $c = ord($input[$in++]);
            $digit = $c - 48 < 10 ? $c - 22 : ($c - 65 < 26 ? $c - 65 : ($c - 97 < 26 ? $c - 97 : 36));
            if ($digit >= 36) return null;
            $i += $digit * $w;
            $t = $k <= $bias ? 1 : ($k >= $bias + 26 ? 26 : $k - $bias);
            if ($digit < $t) break;
            $w *= 36 - $t;
        }
        $count = count($output) + 1;
        $delta = $oldi === 0 ? intdiv($i - $oldi, 700) : intdiv($i - $oldi, 2);
        $delta += intdiv($delta, $count);
        for ($k = 0; $delta > 455; $k += 36) $delta = intdiv($delta, 35);
        $bias = $k + intdiv(36 * $delta, $delta + 38);
        $n += intdiv($i, $count);
        $i %= $count;
        array_splice($output, $i, 0, [mb_chr($n, 'UTF-8')]);
        $i++;
    }
    return $output ? implode('', $output) : null;
}

/** Einmal-Link für den angemeldeten Wechsel auf die neue Adresse (30 Minuten, einmalig). */
function create_domain_handoff(PDO $pdo, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO domain_handoff_tokens(token_hash,user_id,expires_at) VALUES(?,?,CURRENT_TIMESTAMP + INTERVAL '30 minutes')")
        ->execute([hash('sha256', $token), $userId]);
    return app_base_url() . '/umzug.php?t=' . $token;
}

/** Löst einen Einmal-Link ein und liefert das (aktive) Mitglied oder null. */
function redeem_domain_handoff(PDO $pdo, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $st = $pdo->prepare('UPDATE domain_handoff_tokens SET used_at=CURRENT_TIMESTAMP
        WHERE token_hash=? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP RETURNING user_id');
    $st->execute([hash('sha256', $token)]);
    $userId = $st->fetchColumn();
    if ($userId === false) return null;
    $st = $pdo->prepare('SELECT * FROM users WHERE id=? AND is_active=1');
    $st->execute([(int)$userId]);
    return $st->fetch() ?: null;
}

/** SQL-Bedingung für Push-Abos, die zu einer alten Adresse gehören (NULL = vor dem Umzug angelegt). */
function legacy_push_condition(array &$params): string {
    $hosts = app_legacy_hosts();
    if (!$hosts) return ' AND FALSE';
    array_push($params, ...$hosts);
    return ' AND (s.origin_host IS NULL OR s.origin_host IN (' . implode(',', array_fill(0, count($hosts), '?')) . '))';
}

/** Auf alten Adressen ist nur die Umzugsseite erreichbar; alles andere führt dorthin. */
function enforce_domain_move(): void {
    if (!request_is_legacy_host()) return;
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['/umzug.php', '/background.php'], true)) return;
    header('Cache-Control: no-store');
    if ($script === '/calendar-feed.php') {
        // Kalender-Abos von der alten Adresse dauerhaft auf die neue umleiten, damit sie weiterlaufen.
        header('Location: ' . absolute_url('/calendar-feed.php?' . (string)($_SERVER['QUERY_STRING'] ?? '')), true, 301);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        redirect('/umzug.php');
    }
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['ok' => false, 'error' => 'Die App ist umgezogen: ' . app_base_url()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function meeting_local_to_utc(string $date, string $time): ?string {
    try {
        $local = new DateTimeImmutable(trim($date).' '.trim($time), new DateTimeZone('Europe/Berlin'));
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $e) { return null; }
}
function meeting_display(?string $utc, string $format='l, d.m.Y · H:i'): string {
    if (!$utc) return '';
    try {
        $dt = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'));
        $days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        $months = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        $map = [
            'l' => $days[(int)$dt->format('w')],
            'D' => substr($days[(int)$dt->format('w')], 0, 2),
            'F' => $months[(int)$dt->format('n') - 1],
            'M' => mb_substr($months[(int)$dt->format('n') - 1], 0, 3),
        ];
        $out = '';
        for ($i = 0, $n = strlen($format); $i < $n; $i++) {
            $c = $format[$i];
            if ($c === '\\' && $i + 1 < $n) { $out .= $format[++$i]; continue; }
            $out .= isset($map[$c]) ? $map[$c] : $dt->format($c);
        }
        return $out;
    }
    catch (Throwable $e) { return ''; }
}
/** Zeitpunkt aus der Datenbank (UTC, z. B. CURRENT_TIMESTAMP) in deutscher Ortszeit anzeigen. */
function local_time(?string $utc, string $format='d.m.Y H:i'): string { return meeting_display($utc, $format); }
function meeting_local_input(?string $utc): string {
    if (!$utc) return '';
    try { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d\\TH:i'); }
    catch (Throwable $e) { return ''; }
}
function active_meeting(PDO $pdo): ?array {
    $row=$pdo->query("SELECT * FROM meetings WHERE status IN ('poll','scheduled','running') ORDER BY id DESC LIMIT 1")->fetch();
    return $row ?: null;
}
const MEETING_TASK_SELECT="SELECT t.id,t.title,t.due_date,t.status,t.agenda_item_id,t.meeting_id,t.done_at,t.done_meeting_id,TRIM(a.first_name||' '||a.last_name) AS assignee,m.title AS meeting_title,m.starts_at_utc AS meeting_starts FROM meeting_tasks t JOIN meetings m ON m.id=t.meeting_id LEFT JOIN users a ON a.id=t.assignee_user_id";
/** Aufgaben, die in diesem Stammtisch angelegt wurden, gruppiert nach Tagesordnungspunkt. */
function meeting_tasks_by_item(PDO $pdo, int $meetingId): array {
    $q=$pdo->prepare(MEETING_TASK_SELECT." WHERE t.meeting_id=? ORDER BY t.id");$q->execute([$meetingId]);$out=[];
    foreach($q->fetchAll() as $t)$out[(int)$t['agenda_item_id']][]=$t;
    return $out;
}
/** Noch offene Aufgaben aus früheren Stammtischen (erscheinen im laufenden Protokoll zum Abhaken). */
function meeting_open_tasks_before(PDO $pdo, int $meetingId): array {
    $q=$pdo->prepare(MEETING_TASK_SELECT." WHERE t.status='open' AND t.meeting_id<>? ORDER BY m.starts_at_utc,t.id");$q->execute([$meetingId]);
    return $q->fetchAll();
}
/** In diesem Stammtisch erledigte Aufgaben aus früheren Protokollen. */
function meeting_tasks_done_in(PDO $pdo, int $meetingId): array {
    $q=$pdo->prepare(MEETING_TASK_SELECT." WHERE t.done_meeting_id=? AND t.meeting_id<>? ORDER BY t.done_at,t.id");$q->execute([$meetingId,$meetingId]);
    return $q->fetchAll();
}
function meeting_audit(PDO $pdo, int $meetingId, int $userId, string $event, ?string $details=null): void {
    $pdo->prepare('INSERT INTO meeting_audit(meeting_id,user_id,event,details) VALUES(?,?,?,?)')->execute([$meetingId,$userId,$event,$details]);
}

function send_password_reset_mail(string $email, string $name, string $resetUrl): bool {
    $config = require __DIR__ . '/../config.php';
    $from = $config['mail']['from'] ?? 'fischje@fischje.de';
    $fromName = $config['mail']['from_name'] ?? 'Schützenzug Strahlemännkes';
    $subject = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader('Strahlemännkes – Passwort zurücksetzen', 'UTF-8') : 'Strahlemännkes - Passwort zurücksetzen';
    $body = "Hallo {$name},\n\nüber diesen Link kannst du dein Passwort neu setzen:\n{$resetUrl}\n\nDer Link ist 60 Minuten gültig. Falls du das nicht angefordert hast, kannst du diese E-Mail ignorieren.\n\nSchützenzug Strahlemännkes";
    $headers = [
        'From: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    $sent = @mail($email, $subject, $body, implode("\r\n", $headers));
    if (!$sent) error_log('Strahlemännkes: Passwort-Reset-Mail konnte nicht an ' . $email . ' versendet werden.');
    return $sent;
}

restore_remember_login($pdo);
refresh_session_user($pdo);
enforce_domain_move();

<?php
$appBootstrapConfig = require __DIR__ . '/../config.php';
date_default_timezone_set($appBootstrapConfig['timezone'] ?? 'Europe/Berlin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
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
    require_once __DIR__.'/push.php';
    foreach(active_spiess_push_user_ids($pdo) as $uid){
        if($uid===(int)$member['id']) continue;
        send_push_to_user($pdo,$uid,'Getränk geändert',$member['first_name'].' '.$member['last_name'].': '.$drinkName,'/theke.php','drink-change');
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

function app_version(): array {
    static $version = null;
    if ($version === null) $version = require __DIR__ . '/version.php';
    return $version;
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

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
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
    $config = require __DIR__ . '/../config.php';
    $base = $config['base_url'] ?? '';
    if ($base !== '') return rtrim($base,'/') . '/' . ltrim($path,'/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path,'/');
}

function meeting_local_to_utc(string $date, string $time): ?string {
    try {
        $local = new DateTimeImmutable(trim($date).' '.trim($time), new DateTimeZone('Europe/Berlin'));
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $e) { return null; }
}
function meeting_display(?string $utc, string $format='l, d.m.Y · H:i'): string {
    if (!$utc) return '';
    try { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'))->format($format); }
    catch (Throwable $e) { return ''; }
}
function meeting_local_input(?string $utc): string {
    if (!$utc) return '';
    try { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d\\TH:i'); }
    catch (Throwable $e) { return ''; }
}
function active_meeting(PDO $pdo): ?array {
    $row=$pdo->query("SELECT * FROM meetings WHERE status IN ('poll','scheduled') ORDER BY id DESC LIMIT 1")->fetch();
    return $row ?: null;
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

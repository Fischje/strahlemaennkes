<?php
/**
 * Minimaler PDF-Schreiber ohne Fremdbibliothek: DIN A4, Standardschriften (Helvetica),
 * Zeilenumbruch, Seitenumbruch, JPEG-Bilder. Reicht für Protokolle (Text, Listen, Bilder).
 */
final class SimplePdf
{
    private const W = 595.28;
    private const H = 841.89;
    private const M = 56.0;

    private const REG = [32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,
        65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,
        83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,
        97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,
        113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584];
    private const BOLD = [32=>278,33=>333,34=>474,35=>556,36=>556,37=>889,38=>722,39=>238,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>333,59=>333,60=>584,61=>584,62=>584,63=>611,64=>975,
        65=>722,66=>722,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>556,75=>722,76=>611,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,
        83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>333,92=>278,93=>333,94=>584,95=>556,96=>333,
        97=>556,98=>611,99=>556,100=>611,101=>556,102=>333,103=>611,104=>611,105=>278,106=>278,107=>556,108=>278,109=>889,110=>611,111=>611,112=>611,
        113=>611,114=>389,115=>556,116=>333,117=>611,118=>556,119=>778,120=>556,121=>556,122=>500,123=>389,124=>280,125=>389,126=>584];

    /** @var array<int,string> Inhalt je Seite */
    private array $pages = [];
    private array $images = []; // Bildobjekte: [data, w, h, cs]
    private float $y = 0;
    private int $cur = -1;
    private string $footer = '';

    public function __construct(string $footer = '')
    {
        $this->footer = $footer;
        $this->newPage();
    }

    private function newPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
        $this->y = self::H - self::M;
    }

    private static function enc(string $s): string
    {
        $s = str_replace(["\u{2013}", "\u{2014}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2022}", "\u{2026}", "\u{00A0}"], ['-', '-', "'", "'", '"', '"', '-', '...', ' '], $s);
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $out;
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    private function width(string $s, float $size, bool $bold): float
    {
        $t = $bold ? self::BOLD : self::REG;
        $w = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($s[$i]);
            if ($c >= 192 && $c <= 255) $c = self::baseChar($c);
            $w += $t[$c] ?? 556;
        }
        return $w * $size / 1000;
    }

    private static function baseChar(int $c): int
    {
        // Latin-1-Umlaute und Akzente haben die Breite ihres Grundbuchstabens.
        $map = [192=>65,193=>65,194=>65,195=>65,196=>65,197=>65,198=>65,199=>67,200=>69,201=>69,202=>69,203=>69,204=>73,205=>73,206=>73,207=>73,
            209=>78,210=>79,211=>79,212=>79,213=>79,214=>79,217=>85,218=>85,219=>85,220=>85,221=>89,
            224=>97,225=>97,226=>97,227=>97,228=>97,229=>97,230=>97,231=>99,232=>101,233=>101,234=>101,235=>101,236=>105,237=>105,238=>105,239=>105,
            241=>110,242=>111,243=>111,244=>111,245=>111,246=>111,249=>117,250=>117,251=>117,252=>117,253=>121,255=>121,223=>115];
        return $map[$c] ?? 77;
    }

    private function ensure(float $need): void
    {
        if ($this->y - $need < self::M + 14) $this->newPage();
    }

    /** Textabsatz mit Umbruch. $indent in pt, $prefix wird in die erste Zeile gesetzt. */
    public function text(string $text, float $size = 10.5, bool $bold = false, float $indent = 0, string $prefix = '', float $after = 4): void
    {
        $text = self::enc(trim(preg_replace('/[ \t]+/', ' ', $text)));
        $prefix = self::enc($prefix);
        $lead = $size * 1.35;
        $maxW = self::W - 2 * self::M - $indent;
        $lines = [];
        foreach (preg_split('/\n/', $text) as $para) {
            $words = preg_split('/ /', $para);
            $line = '';
            foreach ($words as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($line !== '' && $this->width($try, $size, $bold) > $maxW) {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            $lines[] = $line;
        }
        $first = true;
        foreach ($lines as $line) {
            $this->ensure($lead);
            $this->y -= $lead;
            $x = self::M + $indent;
            $font = $bold ? 'F2' : 'F1';
            if ($first && $prefix !== '') {
                $this->pages[$this->cur] .= sprintf("BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x - $this->width($prefix, $size, $bold) - 3, $this->y, self::esc($prefix));
            }
            $this->pages[$this->cur] .= sprintf("BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $this->y, self::esc($line));
            $first = false;
        }
        $this->y -= $after;
    }

    public function space(float $pt): void
    {
        $this->y -= $pt;
    }

    public function rule(): void
    {
        $this->ensure(8);
        $this->y -= 4;
        $this->pages[$this->cur] .= sprintf("0.8 w 0.75 G %.2F %.2F m %.2F %.2F l S\n", self::M, $this->y, self::W - self::M, $this->y);
        $this->y -= 6;
    }

    /** JPEG-Datei einbetten (skaliert auf Seitenbreite, max. 230 pt hoch). */
    public function jpeg(string $path): void
    {
        $info = @getimagesize($path);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) return;
        $data = file_get_contents($path);
        if ($data === false) return;
        [$w, $h] = $info;
        $cs = ($info['channels'] ?? 3) === 1 ? '/DeviceGray' : (($info['channels'] ?? 3) === 4 ? '/DeviceCMYK' : '/DeviceRGB');
        $maxW = self::W - 2 * self::M;
        $scale = min($maxW / $w, 230 / $h, 1.0 * 1.5);
        $dw = $w * $scale;
        $dh = $h * $scale;
        $this->ensure($dh + 6);
        $this->y -= $dh;
        $this->images[] = [$data, $w, $h, $cs];
        $n = count($this->images);
        $this->pages[$this->cur] .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n", $dw, $dh, self::M, $this->y, $n);
        $this->pageImages[$this->cur][] = $n;
        $this->y -= 8;
    }

    private array $pageImages = [];

    public function output(): string
    {
        $total = count($this->pages);
        $objs = [];
        $add = function (string $body) use (&$objs): int {
            $objs[] = $body;
            return count($objs);
        };
        $objs[] = ''; // 1: Katalog (später)
        $objs[] = ''; // 2: Seiten (später)
        $f1 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $f2 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $imgObj = [];
        foreach ($this->images as $i => [$data, $w, $h, $cs]) {
            $imgObj[$i + 1] = $add(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream", $w, $h, $cs, strlen($data), $data));
        }
        $kids = [];
        foreach ($this->pages as $p => $content) {
            $footer = sprintf("BT /F1 8 Tf %.2F %.2F Td (%s) Tj ET\nBT /F1 8 Tf %.2F %.2F Td (%s) Tj ET\n",
                self::M, 30, self::esc(self::enc($this->footer)),
                self::W - self::M - 52, 30, self::esc(self::enc('Seite ' . ($p + 1) . ' von ' . $total)));
            $content .= $footer;
            $stream = function_exists('gzcompress') ? gzcompress($content, 6) : $content;
            $filter = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';
            $c = $add(sprintf("<< /Length %d%s >>\nstream\n%s\nendstream", strlen($stream), $filter, $stream));
            $xo = '';
            foreach ($this->pageImages[$p] ?? [] as $n) $xo .= sprintf('/Im%d %d 0 R ', $n, $imgObj[$n]);
            $kids[] = $add(sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> /XObject << %s>> >> /Contents %d 0 R >>', self::W, self::H, $f1, $f2, $xo, $c));
        }
        $objs[0] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[1] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', array_map(fn($k) => $k . ' 0 R', $kids)), count($kids));
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[$i + 1] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) $out .= sprintf("%010d 00000 n \n", $o);
        $out .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF", count($objs) + 1, $xref);
        return $out;
    }
}

/** Protokoll-HTML (bereinigt) in das PDF übernehmen: Absätze, Listen, Zeilenumbrüche, JPEG-Bilder. */
function pdf_add_html(SimplePdf $pdf, PDO $pdo, string $html): void
{
    if (trim($html) === '') return;
    $imgDir = dirname(__DIR__, 2) . '/strahlemaennkes-private/meeting-images/';
    if (!preg_match('/<[^>]+>/', $html)) $html = nl2br(htmlspecialchars($html, ENT_QUOTES, 'UTF-8'));
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) return;
    $buf = '';
    $indent = 0.0;
    $prefix = '';
    $flush = function () use (&$buf, &$indent, &$prefix, $pdf): void {
        $t = trim(preg_replace("/[ \t]*\n[ \t]*/", "\n", $buf));
        if ($t !== '') $pdf->text($t, 10.5, false, $indent, $prefix, 3);
        $buf = '';
        $prefix = '';
    };
    $walk = function (DOMNode $node) use (&$walk, &$buf, &$indent, &$prefix, $flush, $pdf, $pdo, $imgDir): void {
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMText) {
                $buf .= str_replace("\n", ' ', $c->nodeValue);
                continue;
            }
            if (!($c instanceof DOMElement)) continue;
            $tag = strtolower($c->tagName);
            if ($tag === 'br') { $buf .= "\n"; continue; }
            if ($tag === 'img') {
                $flush();
                if (preg_match('/meeting-image\.php\?id=(\d+)/', (string)$c->getAttribute('src'), $m)) {
                    $q = $pdo->prepare('SELECT storage_name FROM meeting_images WHERE id=?');
                    $q->execute([(int)$m[1]]);
                    $name = $q->fetchColumn();
                    if ($name && is_file($imgDir . $name)) $pdf->jpeg($imgDir . $name);
                }
                continue;
            }
            if ($tag === 'ul' || $tag === 'ol') {
                $flush();
                $n = 0;
                foreach ($c->childNodes as $li) {
                    if (!($li instanceof DOMElement) || strtolower($li->tagName) !== 'li') continue;
                    $n++;
                    $indent = 16;
                    $prefix = $tag === 'ol' ? $n . '.' : '-';
                    $walk($li);
                    $flush();
                    $indent = 0;
                }
                continue;
            }
            if (in_array($tag, ['p', 'div', 'h2', 'h3', 'blockquote'], true)) {
                $flush();
                $walk($c);
                $flush();
                continue;
            }
            $walk($c);
        }
    };
    $walk($body);
    $flush();
}

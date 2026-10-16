<?php
declare(strict_types=1);
namespace App\Content;

/** A closed registry: configuration is typed plain text, never templates or HTML. */
final class ElementTypes
{
    public const ALL = [
        'hero' => ['Hero-Banner', 'Layout', 'flag'], 'text' => ['Textfeld', 'Layout', 'align-left'], 'cta' => ['CTA-Banner', 'Layout', 'bullhorn'],
        'image' => ['Bild', 'Medien', 'image'], 'gallery' => ['Galerie', 'Medien', 'images'], 'image_slider' => ['Bilder-Slider', 'Medien', 'film'], 'video' => ['Video', 'Medien', 'video'],
        'contact_person' => ['Ansprechpartner', 'Inhalte', 'user'], 'event' => ['Veranstaltung', 'Inhalte', 'calendar-days'], 'company' => ['Unternehmen', 'Inhalte', 'store'], 'portal_contact' => ['Kontaktinformationen Werbekreis', 'Inhalte', 'address-card'], 'board_list' => ['Vorstandsliste', 'Inhalte', 'users'],
        'categories' => ['Unternehmenskategorien', 'Dynamische Inhalte', 'tags'], 'offers' => ['Angebote', 'Dynamische Inhalte', 'tag'], 'news' => ['Neuigkeiten', 'Dynamische Inhalte', 'newspaper'], 'event_list' => ['Veranstaltungsliste', 'Dynamische Inhalte', 'calendar'], 'company_list' => ['Unternehmensliste', 'Dynamische Inhalte', 'building'], 'jobs' => ['Jobs', 'Dynamische Inhalte', 'briefcase'], 'voucher' => ['Gutschein', 'Dynamische Inhalte', 'gift'],
    ];
    public static function types(): array { return array_keys(self::ALL); }
    /** field => [label, kind, default, choices?] */
    public static function fields(string $type): array
    {
        $heading = ['heading' => ['Überschrift', 'text', '']];
        $text = ['text' => ['Text', 'textarea', '']];
        $eyebrow = ['eyebrow' => ['Dachzeile', 'text', '']];
        $button = ['buttonLabel' => ['Button-Beschriftung', 'text', ''], 'buttonUrl' => ['Link URL', 'url', '']];
        $list = $heading + ['limit' => ['Anzahl (1–12)', 'integer', 3], 'showAllLink' => ['Alle anzeigen verlinken', 'bool', true]];
        return match ($type) {
            'hero' => $eyebrow + $heading + $text + $button + [
                'showCompanySearch' => ['Unternehmenssuche anzeigen', 'bool', false], 'showPopularCategories' => ['Beliebte Kategorien anzeigen', 'bool', false],
                'searchPlaceholder' => ['Such-Platzhalter', 'text', ''], 'asideHeading' => ['Seitlicher Titel', 'textarea', ''], 'asideText' => ['Seitlicher Text', 'text', ''],
                'variant' => ['Darstellung', 'choice', 'default', ['default', 'light', 'dark', 'image']],
            ],
            'text' => $heading + ['body' => ['Text (Platzhalter erlaubt)', 'textarea', ''], 'variant' => ['Darstellung', 'choice', 'default', ['default', 'narrow', 'card', 'notice']]],
            'image' => ['layout' => ['Layout', 'choice', 'normal', ['normal', 'wide', 'full', 'left', 'right']], 'linkUrl' => ['Link URL', 'url', '']],
            'gallery' => ['columns' => ['Spalten', 'choice', '3', ['2', '3', '4']]],
            'image_slider' => ['autoplay' => ['Automatisch abspielen', 'bool', false], 'interval' => ['Intervall (ms, mindestens 3000)', 'integer', 5000]],
            'contact_person' => ['name' => ['Name', 'text', ''], 'role' => ['Funktion', 'text', ''], 'company' => ['Unternehmen', 'text', ''], 'phone' => ['Telefon', 'phone', ''], 'email' => ['E-Mail', 'email', '']] + $text,
            'video' => $heading + ['videoUrl' => ['YouTube-/Vimeo-URL', 'video', ''], 'caption' => ['Bildunterschrift', 'textarea', '']],
            'cta' => $eyebrow + $heading + $text + $button + ['variant' => ['Darstellung', 'choice', 'green', ['green', 'dark', 'light']]],
            'categories' => $heading + ['subheading' => ['Unterzeile', 'text', ''], 'limit' => ['Anzahl (0 = alle)', 'integer', 0]],
            'offers', 'news', 'event_list', 'jobs' => $list,
            'company_list' => $list + ['subheading' => ['Unterzeile', 'text', '']],
            'voucher' => $eyebrow + $heading + $text + ['buttonLabel' => ['Button-Beschriftung', 'text', '']],
            'portal_contact' => ['showAddress' => ['Adresse anzeigen', 'bool', true], 'showPerson' => ['Kontaktperson anzeigen', 'bool', true], 'showPhone' => ['Telefon anzeigen', 'bool', true], 'showEmail' => ['E-Mail anzeigen', 'bool', true]],
            default => [],
        };
    }
    public static function defaults(string $type): array { return array_map(static fn(array $field) => $field[2], self::fields($type)); }
    public static function safeUrl(string $url): bool
    {
        if ($url === '') { return true; }
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { return false; }
        return (str_starts_with($url, '/') && !str_starts_with($url, '//')) || (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL));
    }
    public static function videoEmbed(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || isset($parts['user']) || isset($parts['port'])) { return null; }
        $host = strtolower($parts['host'] ?? ''); $path = $parts['path'] ?? '';
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = $host === 'youtu.be' ? substr($path, 1) : ($path === '/watch' ? ($query['v'] ?? '') : (preg_match('#^/(?:shorts|embed)/([a-zA-Z0-9_-]{11})$#D', $path, $m) ? $m[1] : ''));
            return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/D', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true) && preg_match('#^/(?:video/)?([0-9]+)$#D', $path, $m)) { return 'https://player.vimeo.com/video/'.$m[1].'?dnt=1'; }
        return null;
    }
    public static function errors(string $type, array $data): array
    {
        $errors = []; $fields = self::fields($type);
        foreach ($data as $key => $_) { if (!isset($fields[$key])) { $errors[$key] = 'Unbekanntes Konfigurationsfeld.'; } }
        foreach ($fields as $key => $field) {
            $value = $data[$key] ?? $field[2]; $kind = $field[1];
            if ($kind === 'bool') { if (!is_bool($value)) { $errors[$key] = 'Ungültiger Schalter.'; } continue; }
            if ($kind === 'integer') {
                $min = $key === 'interval' ? 3000 : ($type === 'categories' ? 0 : 1);
                $max = $key === 'interval' ? 60000 : ($type === 'categories' ? 1000 : 12);
                if (!is_int($value) || $value < $min || $value > $max) { $errors[$key] = 'Anzahl außerhalb des erlaubten Bereichs.'; } continue;
            }
            if (!is_string($value) || mb_strlen($value) > ($kind === 'textarea' ? 50000 : 2048) || preg_match('/<[^>]*>/', $value)) { $errors[$key] = 'Nur Klartext ohne HTML erlaubt.'; continue; }
            if ($kind === 'choice' && !in_array($value, $field[3], true)) { $errors[$key] = 'Ungültige Darstellung.'; }
            if ($kind === 'url' && !self::safeUrl($value)) { $errors[$key] = 'Nur interne Links oder HTTP/HTTPS erlaubt.'; }
            if ($kind === 'video' && self::videoEmbed($value) === null) { $errors[$key] = 'Bitte eine gültige YouTube-/Vimeo-URL angeben.'; }
            if ($kind === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { $errors[$key] = 'Ungültige E-Mail-Adresse.'; }
            if ($kind === 'phone' && $value !== '' && !preg_match('/^\+?[0-9 ()\/.-]+$/D', $value)) { $errors[$key] = 'Ungültige Telefonnummer.'; }
        }
        return $errors;
    }
}

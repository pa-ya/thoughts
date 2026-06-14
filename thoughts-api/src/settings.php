<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function default_visual_settings(): array
{
    return [
        'theme_mode' => 'system',
        'accent_color' => '#2563eb',
        'base_font_size' => '16',
        'font_family' => 'system',
        'density' => 'comfortable',
    ];
}

function visual_setting_keys(): array
{
    return array_keys(default_visual_settings());
}

function visual_theme_mode_options(): array
{
    return [
        'system' => 'System',
        'light' => 'Light',
        'dark' => 'Dark',
    ];
}

function visual_font_family_options(): array
{
    return [
        'system' => 'System',
        'serif' => 'Serif',
        'mono' => 'Mono',
    ];
}

function visual_density_options(): array
{
    return [
        'comfortable' => 'Comfortable',
        'compact' => 'Compact',
    ];
}

function normalize_hex_color(string $value): ?string
{
    $value = trim($value);

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return null;
    }

    return strtolower($value);
}

function normalize_base_font_size($value): ?string
{
    $size = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 14,
            'max_range' => 20,
        ],
    ]);

    return $size === false ? null : (string) $size;
}

function validate_visual_settings_input(array $input): array
{
    $errors = [];
    $data = [];

    $themeMode = (string) ($input['theme_mode'] ?? '');

    if (!array_key_exists($themeMode, visual_theme_mode_options())) {
        $errors['theme_mode'] = 'Choose a valid theme mode.';
    } else {
        $data['theme_mode'] = $themeMode;
    }

    $accentColor = normalize_hex_color((string) ($input['accent_color'] ?? ''));

    if ($accentColor === null) {
        $errors['accent_color'] = 'Choose a valid accent color.';
    } else {
        $data['accent_color'] = $accentColor;
    }

    $baseFontSize = normalize_base_font_size($input['base_font_size'] ?? null);

    if ($baseFontSize === null) {
        $errors['base_font_size'] = 'Base font size must be between 14 and 20.';
    } else {
        $data['base_font_size'] = $baseFontSize;
    }

    $fontFamily = (string) ($input['font_family'] ?? '');

    if (!array_key_exists($fontFamily, visual_font_family_options())) {
        $errors['font_family'] = 'Choose a valid font family.';
    } else {
        $data['font_family'] = $fontFamily;
    }

    $density = (string) ($input['density'] ?? '');

    if (!array_key_exists($density, visual_density_options())) {
        $errors['density'] = 'Choose a valid density.';
    } else {
        $data['density'] = $density;
    }

    return [
        'data' => $data,
        'errors' => $errors,
    ];
}

function fetch_visual_settings(): array
{
    $defaults = default_visual_settings();
    $placeholders = implode(', ', array_fill(0, count($defaults), '?'));
    $statement = db()->prepare(
        "SELECT setting_key, setting_value
        FROM settings
        WHERE setting_key IN ({$placeholders})"
    );
    $statement->execute(array_keys($defaults));
    $settings = $defaults;

    foreach ($statement->fetchAll() as $row) {
        $key = (string) $row['setting_key'];
        $value = (string) $row['setting_value'];

        if (array_key_exists($key, $defaults)) {
            $settings[$key] = $value;
        }
    }

    $validated = validate_visual_settings_input($settings);

    return $validated['errors'] === [] ? $validated['data'] : $defaults;
}

function save_visual_settings(array $settings): void
{
    $statement = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
        VALUES (:setting_key, :setting_value)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach (visual_setting_keys() as $key) {
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => $settings[$key],
        ]);
    }
}

function mix_hex_color(string $hex, string $target, float $weight): string
{
    $hex = ltrim($hex, '#');
    $target = ltrim($target, '#');
    $channels = [];

    for ($i = 0; $i < 3; $i++) {
        $sourceChannel = hexdec(substr($hex, $i * 2, 2));
        $targetChannel = hexdec(substr($target, $i * 2, 2));
        $channels[] = (int) round($sourceChannel + (($targetChannel - $sourceChannel) * $weight));
    }

    return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
}

function font_family_stack(string $fontFamily): string
{
    return match ($fontFamily) {
        'serif' => 'Georgia, "Times New Roman", serif',
        'mono' => '"SFMono-Regular", Consolas, "Liberation Mono", monospace',
        default => 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    };
}

function visual_settings_style_attribute(array $settings): string
{
    $accent = normalize_hex_color((string) $settings['accent_color']) ?? default_visual_settings()['accent_color'];

    $properties = [
        '--accent' => $accent,
        '--accent-strong' => mix_hex_color($accent, '#000000', 0.18),
        '--accent-soft' => mix_hex_color($accent, '#ffffff', 0.88),
        '--accent-muted' => mix_hex_color($accent, '#ffffff', 0.78),
        '--custom-base-font-size' => (normalize_base_font_size($settings['base_font_size']) ?? '16') . 'px',
        '--custom-font-family' => font_family_stack((string) $settings['font_family']),
    ];

    $chunks = [];

    foreach ($properties as $name => $value) {
        $chunks[] = $name . ': ' . $value;
    }

    return implode('; ', $chunks) . ';';
}

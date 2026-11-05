<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renders PHP templates from app/Views.
 * A page template is rendered first, then wrapped in a layout that receives it as $content.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }

        return self::capture($layout, $data + ['content' => $content]) ;
    }

    private static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }

        // Layouts receive the page HTML as $content, so it must not be overwritten by page data.
        $data['content'] = $data['content'] ?? '';
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}

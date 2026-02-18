<?php

if (!function_exists('render_email_template')) {
    function render_email_template($templateName, array $vars = [])
    {
        $templateName = trim((string)$templateName);
        if ($templateName === '') {
            return '';
        }

        $templatePath = __DIR__ . '/' . $templateName . '.php';
        if (!is_file($templatePath)) {
            return '';
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return (string)ob_get_clean();
    }
}

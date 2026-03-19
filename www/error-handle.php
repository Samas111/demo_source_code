<?php

declare(strict_types=1);

const ERROR_LOG_FILE = __DIR__ . '/../logs/error.log';
const DEBUG = false;

const SENSITIVE_PATTERNS = [
    'DEMO',
    'DEMO',
    'DEMO',
    'password',
    'token',
    'token_hash',
    'Authorization',
    'DEMO',
    'DEMO',
];

error_reporting(E_ALL);

if (DEBUG === true) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
}

ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG_FILE);

function maskSensitive(string $message): string
{
    foreach (SENSITIVE_PATTERNS as $key) {
        $message = preg_replace(
            '/(' . preg_quote($key, '/') . '\s*=\s*)([^;\s]+)/i',
            '$1[HIDDEN]',
            $message
        );
    }
    return $message;
}

function logError(string $type, string $message, string $file, int $line): string
{
    $id = bin2hex(random_bytes(6));

    $log = sprintf(
        "[%s] [%s] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $id,
        maskSensitive($message),
        $file,
        $line
    );

    error_log($log);

    return $id;
}

function renderError(string $title, string $message, ?string $errorId = null): void
{
    static $rendering = false;
    if ($rendering) exit(1);
    $rendering = true;

    while (ob_get_level()) ob_end_clean();

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    $debugBlock = ($errorId && DEBUG === true)
        ? "<p class='error-id'>Kód chyby: <span>{$errorId}</span></p>"
        : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b0b0f">
<title>Chyba · Dancefy</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        min-height: 100dvh;
        background: #0b0b0f;
        color: #eaeaf0;
        font-family: -apple-system, BlinkMacSystemFont, 'Inter', system-ui, sans-serif;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px 24px;
        padding-bottom: max(32px, env(safe-area-inset-bottom));
    }

    .card {
        width: 100%;
        max-width: 400px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0;
    }

    .icon-wrap {
        width: 80px;
        height: 80px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 28px;
        font-size: 36px;
    }

    h1 {
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.5px;
        color: #fff;
        margin-bottom: 12px;
        line-height: 1.2;
    }

    .subtitle {
        font-size: 15px;
        color: rgba(255,255,255,.5);
        line-height: 1.6;
        margin-bottom: 36px;
        max-width: 300px;
    }

    .btn-primary {
        width: 100%;
        padding: 16px;
        background: #FF1B73;
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        text-decoration: none;
        display: block;
        margin-bottom: 12px;
        transition: opacity .15s;
        -webkit-tap-highlight-color: transparent;
    }
    .btn-primary:active { opacity: .8; }

    .btn-secondary {
        width: 100%;
        padding: 16px;
        background: #1a1a24;
        color: rgba(255,255,255,.7);
        font-size: 15px;
        font-weight: 500;
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 14px;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: background .15s;
        -webkit-tap-highlight-color: transparent;
    }
    .btn-secondary:active { background: #22222f; }

    .ig-icon {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        background: linear-gradient(135deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ig-icon svg { display: block; }

    .divider {
        width: 100%;
        height: 1px;
        background: rgba(255,255,255,.06);
        margin: 24px 0;
    }

    .error-id {
        font-size: 12px;
        color: rgba(255,255,255,.2);
    }
    .error-id span {
        font-family: monospace;
        color: rgba(255,255,255,.35);
    }
</style>
</head>
<body>
<div class="card">
    <div class="icon-wrap">⚠️</div>
    <h1>{$title}</h1>
    <p class="subtitle">{$message}</p>

    <a href="javascript:history.back()" class="btn-primary">Zkusit znovu</a>

    <a href="https://www.instagram.com/dancefyapp" target="_blank" rel="noopener" class="btn-secondary">
        <span class="ig-icon">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                <circle cx="12" cy="12" r="4"/>
                <circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/>
            </svg>
        </span>
        Kontaktovat podporu @dancefyapp
    </a>

    {$debugBlock}
    <div class="divider"></div>
    <p class="error-id">Dancefy &copy; <?= date('Y') ?></p>
</div>
</body>
</html>
HTML;

    exit;
}

if (DEBUG === false) {
    
    set_exception_handler(function (Throwable $e) {

        $id = logError(
            'EXCEPTION',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        if (DEBUG === true) {
            echo "<pre>";
            echo $e;
            echo "</pre>";
            exit;
        }

        renderError(
            'Něco se pokazilo',
            'Nastal problém se spracováním vašeho požadavku, kontaktujte prosím podporu.',
            $id
        );
    });

    set_exception_handler(function (Throwable $e) {
        $id = logError(
            'EXCEPTION',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        renderError(
            'Něco se pokazilo',
            'Nastal problém se spracováním vašeho požadavku, kontaktujte prosím podporu.',
            $id
        );
    });

    register_shutdown_function(function () {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {

            $id = logError(
                'FATAL',
                $error['message'],
                $error['file'],
                $error['line']
            );

            if (DEBUG === true) {
                echo "<pre>";
                print_r($error);
                echo "</pre>";
                exit;
            }

            renderError(
                'Něco se pokazilo',
                'Aplikace se zastavila. Kontaktujte prosím podporu.',
                $id
            );
        }
    });

}

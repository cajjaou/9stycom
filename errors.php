<?php
// errors.php - Global error and exception logger for 9sty & JB

// Production-safe defaults. Keep detailed diagnostics in the server log only.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function app_debug_enabled(): bool
{
    return in_array(strtolower(trim((string) getenv('APP_DEBUG'))), ['1', 'true', 'yes', 'on'], true);
}

function app_exception_details(Throwable $exception): array
{
    $message = $exception->getMessage();
    // Never include credentials if a library happens to put them in an error.
    $message = preg_replace('/(password|passwd|secret|api[_-]?key)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message);

    return [
        'type' => get_class($exception),
        'message' => $message,
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ];
}

function app_exception_response(Throwable $exception, string $publicMessage = 'An unexpected error occurred. Please try again later.'): void
{
    http_response_code(500);
    $debug = app_debug_enabled() ? app_exception_details($exception) : null;
    $headers = implode('\n', headers_list());

    if (stripos($headers, 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => false, 'message' => $debug ? $debug['message'] : $publicMessage];
        if ($debug) {
            $payload['debug'] = $debug;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }

    if ($debug) {
        echo '<!doctype html><html><meta charset="utf-8"><title>Application error</title>';
        echo '<body style="font:16px/1.5 system-ui,sans-serif;padding:2rem">';
        echo '<h1>Application error (APP_DEBUG enabled)</h1><pre>';
        echo htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '</pre></body></html>';
        return;
    }

    echo $publicMessage;
}

// Log custom PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $timestamp = date('Y-m-d H:i:s');
    $errorType = match ($errno) {
        E_USER_ERROR => 'Fatal Error',
        E_USER_WARNING => 'Warning',
        E_USER_NOTICE => 'Notice',
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_DEPRECATED => 'Deprecated',
        default => 'Error (' . $errno . ')'
    };
    
    error_log("[$timestamp] [$errorType] $errstr in $errfile on line $errline");
    return true;
});

// Log exceptions (e.g. database errors)
set_exception_handler(function(Throwable $exception) {
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] [Exception] " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . PHP_EOL;
    $message .= "Stack trace:" . PHP_EOL . $exception->getTraceAsString() . PHP_EOL;
    error_log($message);
    
    app_exception_response($exception);
    exit;
});

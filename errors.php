<?php
// errors.php - Global error and exception logger for 9sty & JB

// Production-safe defaults. Keep detailed diagnostics in the server log only.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

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
    
    http_response_code(500);
    echo 'An unexpected error occurred. Please try again later.';
    exit;
});

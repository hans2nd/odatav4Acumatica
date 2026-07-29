<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers\Mappers;

use App\DTO\Connections\ConnectionError;

class SqlServerErrorMapper
{
    /**
     * Maps a Throwable to a strongly typed ConnectionError.
     */
    public function map(\Throwable $e): ConnectionError
    {
        $message = $e->getMessage();
        $code = $e->getCode();
        
        $sqlState = null;
        $nativeError = null;

        if ($e instanceof \PDOException) {
            $sqlState = is_string($code) ? $code : null;
            if (isset($e->errorInfo[1])) {
                $nativeError = (string) $e->errorInfo[1];
            }
        } else {
            // Attempt to extract SQLSTATE from string if not explicitly available
            if (preg_match('/SQLSTATE\[([A-Z0-9]+)\]/', $message, $matches)) {
                $sqlState = $matches[1];
            }
        }

        $mappedCode = 'ERROR_UNREACHABLE';
        $title = 'Connection Failed';
        $userMessage = 'An unexpected error occurred while attempting to connect.';

        if ($sqlState === '28000') {
            $mappedCode = 'INVALID_CREDENTIALS';
            $title = 'Authentication Failed';
            $userMessage = 'The provided username or password is incorrect.';
        } elseif (str_contains($message, '18456')) {
            $mappedCode = 'LOGIN_FAILED';
            $title = 'Login Failed';
            $userMessage = 'Login failed for the specified user.';
        } elseif ($sqlState === '42S02' || str_contains($message, '42S02')) {
            $mappedCode = 'TABLE_NOT_FOUND';
            $title = 'Table Not Found';
            $userMessage = 'The specified table or object was not found.';
        } elseif (str_contains($message, '4060')) {
            $mappedCode = 'DATABASE_NOT_FOUND';
            $title = 'Database Not Found';
            $userMessage = 'The requested database could not be opened.';
        } elseif (str_contains($message, '18452')) {
            $mappedCode = 'WINDOWS_AUTH_REQUIRED';
            $title = 'Authentication Type Mismatch';
            $userMessage = 'The server expects Windows Authentication, but SQL Login was provided.';
        } elseif ($sqlState === '08001' || str_contains($message, 'certificate chain')) {
            $mappedCode = 'TLS_NEGOTIATION_FAILED';
            $title = 'TLS / Certificate Error';
            $userMessage = 'Failed to establish a secure connection. Verify the Trust Server Certificate setting.';
        } elseif ($sqlState === 'HYT00' || str_contains($message, 'HYT00')) {
            $mappedCode = 'LOGIN_TIMEOUT';
            $title = 'Connection Timeout';
            $userMessage = 'The server did not respond in time. Verify the host is reachable.';
        } elseif (str_contains($message, 'IMSSP') || str_contains($message, 'architecture mismatch') || str_contains($message, 'could not find driver')) {
            $mappedCode = 'DRIVER_CONFIGURATION_ERROR';
            $title = 'Driver Configuration Error';
            $userMessage = 'The underlying ODBC driver or PDO configuration is missing or misconfigured.';
        }

        return new ConnectionError(
            code: $mappedCode,
            title: $title,
            message: $userMessage,
            technical_message: $message,
            sql_state: $sqlState,
            native_error_code: $nativeError
        );
    }
}

<?php

namespace App\Exceptions;

use Exception;

class SmsApiException extends Exception
{
    protected $statusCode;

    public function __construct(string $message = "", int $statusCode = 400, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render($request)
    {
        return response()->json([
            'code' => 'error',
            'message' => $this->getMessage()
        ], $this->statusCode);
    }
}
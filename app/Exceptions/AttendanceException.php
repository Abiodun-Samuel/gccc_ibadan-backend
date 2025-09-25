<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class AttendanceException extends Exception
{
    protected int $statusCode;

    public function __construct(string $message = "", int $statusCode = 400, Exception $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $this->getMessage(),
            'data' => null
        ], $this->statusCode);
    }

    /**
     * Get the status code for the exception.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

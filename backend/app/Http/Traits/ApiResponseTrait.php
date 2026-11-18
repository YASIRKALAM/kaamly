<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Send a standardized success JSON response.
     */
    public function successResponse(
        mixed $data = null,
        ?string $message = null,
        array $meta = [],
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ], $meta),
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Send a standardized error JSON response.
     */
    public function errorResponse(
        string $code,
        string $message,
        mixed $details = null,
        int $statusCode = 400,
        array $meta = []
    ): JsonResponse {
        $errorPayload = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $errorPayload['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $errorPayload,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ], $meta),
        ], $statusCode);
    }
}

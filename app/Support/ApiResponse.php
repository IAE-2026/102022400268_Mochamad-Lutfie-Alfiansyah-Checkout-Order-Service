<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => array_merge(self::defaultMeta(), $meta),
        ], $status);
    }

    public static function error(string $message, mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    private static function defaultMeta(): array
    {
        return [
            'service_name' => config('services.iae.service_name', 'Checkout-Order-Service'),
            'api_version' => config('services.iae.api_version', 'v1'),
        ];
    }
}

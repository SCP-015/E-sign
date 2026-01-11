<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    public static function error(string $message = 'Error', int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    public static function fromService(array $result): JsonResponse
    {
        $status = $result['status'] ?? 'success';
        $data = $result['data'] ?? null;
        $message = $result['message'] ?? 'OK';
        $code = $result['code'] ?? ($status === 'success' ? 200 : 400);

        return response()->json([
            'status' => $status,
            'data' => $data,
            'message' => $message,
        ], $code);
    }
}

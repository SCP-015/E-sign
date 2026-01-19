<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class ApiResponse
{
    private static function toCamelCaseKey(string $key): string
    {
        if (strpos($key, '_') === false) {
            return $key;
        }

        $key = strtolower($key);
        $key = preg_replace_callback('/_([a-z0-9])/', function ($m) {
            return strtoupper($m[1]);
        }, $key);

        return $key;
    }

    private static function camelizeData(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map([self::class, 'camelizeData'], $value);
        }

        $out = [];
        foreach ($value as $k => $v) {
            $newKey = is_string($k) ? self::toCamelCaseKey($k) : $k;
            $out[$newKey] = self::camelizeData($v);
        }

        return $out;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $code = 200, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => 'success',
            'success' => true,
            'data' => self::camelizeData($data),
            'message' => $message,
        ], $extra), $code);
    }

    public static function error(string $message = 'Error', int $code = 400, mixed $data = null, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => 'error',
            'success' => false,
            'data' => self::camelizeData($data),
            'message' => $message,
        ], $extra), $code);
    }

    public static function fromService(array $result, array $extra = []): JsonResponse
    {
        $status = $result['status'] ?? 'success';
        $data = $result['data'] ?? null;
        $message = $result['message'] ?? 'OK';
        $code = $result['code'] ?? ($status === 'success' ? 200 : 400);
        $success = $status === 'success';

        return response()->json(array_merge([
            'status' => $status,
            'success' => $success,
            'data' => self::camelizeData($data),
            'message' => $message,
        ], $extra), $code);
    }
}

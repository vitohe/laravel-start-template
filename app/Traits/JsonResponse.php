<?php

namespace App\Traits;

use App\Models\RequestLog;
use Illuminate\Support\Str;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

trait JsonResponse
{
    protected function jsonData(
        string|array|int|AnonymousResourceCollection $data = '',
        array $meta = [],
        int $statusCode = 200,
    ) {
        $arr = [];

        // 翻页数据单独处理
        if (
            is_object($data)
            && is_countable($data)
            && $data instanceof AnonymousResourceCollection
            && property_exists($data, 'resource')
            && property_exists($data->resource, 'total')
        ) {
            $arr = $data;
            $pagnation_meta = [
                'total' => $data->total(),
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'last_page' => $data->lastPage(),
            ];

            $meta = array_merge($meta, $pagnation_meta);
        } else {
            $arr = $data;
        }

        $meta = array_merge([
            'request_id' => RequestLog::genRequestId(),
            // 'request_at' => date('Y-m-d H:i:s', request()->server->get('REQUEST_TIME')),
            'request_at' => (new \Carbon\Carbon(request()->server->get('REQUEST_TIME')))->setTimezone('PST')->toIso8601String(),
        ], $meta);

        $responseData = [
            'status' => $statusCode == 200 ? 'success' : 'error',
            'code' => $statusCode,
            'meta' => $meta,
        ];

        match ($statusCode) {
            200 => $responseData['data'] = $arr,
            default => $responseData['message'] = $arr,
        };

        // 排序数据
        $sort = ['status', 'code', 'meta', 'data', 'message', 'error'];

        $responseData = array_reduce($sort, function ($sortedArr, $key) use ($responseData) {
            if (array_key_exists($key, $responseData)) {
                $sortedArr[$key] = $responseData[$key];
            }

            return $sortedArr;
        }, []);

        $response = response()->json($responseData);

        RequestLog::record(['response' => $response->getData(true)]);

        return $response;
    }

    protected function jsonError(string|int $data, $statusCode = 400)
    {
        $buildin_errors = config('errors');

        if (is_numeric($data) && isset($buildin_errors[$data])) {
            $message = $buildin_errors[$data];
            $statusCode = $data;
        } else {
            $message = $data;
        }

        return $this->jsonData($message, [], $statusCode);
    }
}

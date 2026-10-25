<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function paginated($paginator, Closure $map): JsonResponse
    {
        return response()->json([
            'data' => $paginator->getCollection()->map($map)->values(),
            'meta' => ['page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()],
        ]);
    }

    protected function error(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return response()->json(['error' => array_filter(['code' => $code, 'message' => $message, 'details' => $details])], $status);
    }

    /** Jalankan aksi alur kerja; pelanggaran aturan menjadi HTTP 422 dengan pesan jelas. */
    protected function run(Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (WorkflowException $e) {
            return $this->error(422, 'workflow_violation', $e->getMessage());
        }
    }
}

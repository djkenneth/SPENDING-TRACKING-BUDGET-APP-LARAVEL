<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     operationId="healthCheck",
     *     tags={"System"},
     *     summary="Health check",
     *     description="Check if API is running",
     *     @OA\Response(
     *         response=200,
     *         description="API is healthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="timestamp", type="string", example="2025-01-15T10:00:00Z"),
     *             @OA\Property(property="version", type="string", example="1.0.0")
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }
}

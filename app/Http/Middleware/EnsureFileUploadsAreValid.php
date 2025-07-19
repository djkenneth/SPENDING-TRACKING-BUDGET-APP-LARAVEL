<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFileUploadsAreValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if request has files and validate file size
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');

            // Check file size (2MB max)
            if ($file->getSize() > 2048 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'File size exceeds maximum allowed size of 2MB'
                ], 400);
            }

            // Check file type
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Only JPEG, PNG, JPG, GIF, and SVG files are allowed'
                ], 400);
            }
        }

        return $next($request);
    }
}

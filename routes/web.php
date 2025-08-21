<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation Route (exclude from middleware)
Route::get('/documentation', function () {
    return redirect('/api/documentation');
});


// Add this route to serve the api-docs.json file
// Route::get('/docs/api-docs.json', function () {
//     $path = storage_path('api-docs/api-docs.json');

//     if (!file_exists($path)) {
//         abort(404, 'API documentation file not found. Please run: php artisan l5-swagger:generate');
//     }

//     return response()->file($path, [
//         'Content-Type' => 'application/json',
//     ]);
// });


// Route::get('/documentation/api-docs.json', function () {
//     $path = storage_path('api-docs/api-docs.json');

//     if (!file_exists($path)) {
//         return response()->json([
//             'error' => 'API documentation not found. Please run: php artisan l5-swagger:generate'
//         ], 404);
//     }

//     return response()->file($path);
// });

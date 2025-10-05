<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HandleMultipartFormData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Log the request details for debugging
        Log::info('HandleMultipartFormData Middleware', [
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'has_files' => $request->hasFile('images'),
            'all_input' => $request->all(),
            'files' => $request->allFiles(),
        ]);

        // Handle PUT requests with multipart/form-data
        if ($request->isMethod('PUT') && str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            // Parse the raw input to get form data
            $input = $request->all();
            
            // Log parsed input
            Log::info('Parsed multipart form data', [
                'input' => $input,
                'has_name' => isset($input['name']),
                'has_price' => isset($input['price']),
                'has_category_id' => isset($input['category_id']),
            ]);
            
            // Merge the parsed data back into the request
            $request->merge($input);
        }

        return $next($request);
    }
}
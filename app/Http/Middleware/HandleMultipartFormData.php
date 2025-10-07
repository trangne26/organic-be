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
            'raw_content' => $request->getContent(),
        ]);

        // Handle PUT requests with multipart/form-data
        if ($request->isMethod('PUT') && str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            // For PUT requests, we need to manually parse the multipart data
            $content = $request->getContent();
            $boundary = $this->extractBoundary($request->header('Content-Type'));
            
            if ($boundary) {
                $parsedData = $this->parseMultipartData($content, $boundary);
                
                Log::info('Parsed multipart form data', [
                    'parsed_data' => $parsedData,
                    'has_name' => isset($parsedData['name']),
                    'has_price' => isset($parsedData['price']),
                    'has_category_id' => isset($parsedData['category_id']),
                ]);
                
                // Merge the parsed data back into the request
                $request->merge($parsedData);
            }
        }

        return $next($request);
    }

    private function extractBoundary($contentType)
    {
        if (preg_match('/boundary=(.+)$/', $contentType, $matches)) {
            return '--' . trim($matches[1]);
        }
        return null;
    }

    private function parseMultipartData($content, $boundary)
    {
        $data = [];
        $parts = explode($boundary, $content);
        
        foreach ($parts as $part) {
            if (strpos($part, 'Content-Disposition: form-data') !== false) {
                // Extract field name
                if (preg_match('/name="([^"]+)"/', $part, $matches)) {
                    $fieldName = $matches[1];
                    
                    // Extract field value
                    $lines = explode("\r\n", $part);
                    $value = '';
                    $inValue = false;
                    
                    foreach ($lines as $line) {
                        if ($inValue) {
                            $value .= $line . "\r\n";
                        } elseif (trim($line) === '') {
                            $inValue = true;
                        }
                    }
                    
                    $value = trim($value);
                    
                    // Handle array fields like images[]
                    if (strpos($fieldName, '[]') !== false) {
                        $fieldName = str_replace('[]', '', $fieldName);
                        if (!isset($data[$fieldName])) {
                            $data[$fieldName] = [];
                        }
                        $data[$fieldName][] = $value;
                    } else {
                        $data[$fieldName] = $value;
                    }
                }
            }
        }
        
        return $data;
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiPerformanceMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        
        // Добавляем заголовки для оптимизации
        $response = $next($request);
        
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        // Добавляем заголовки производительности
        $response->headers->set('X-Response-Time', $duration . 'ms');
        $response->headers->set('X-RateLimit-Limit', '3000');
        $response->headers->set('X-RateLimit-Remaining', '2999'); // Это нужно будет вычислять динамически
        
        // Кеширование для GET запросов
        if ($request->isMethod('GET')) {
            $response->headers->set('Cache-Control', 'public, max-age=10');
        }
        
        // Логируем медленные запросы
        if ($duration > 1000) {
            Log::warning('Slow API request', [
                'duration' => $duration,
                'action' => $request->get('action'),
                'ip' => $request->ip()
            ]);
        }
        
        return $response;
    }
}
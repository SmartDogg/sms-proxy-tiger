<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmsProxyService;
use App\Http\Requests\GetNumberRequest;
use App\Http\Requests\GetSmsRequest;
use App\Http\Requests\CancelNumberRequest;
use App\Http\Requests\GetStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class SmsProxyController extends Controller
{
    private SmsProxyService $smsService;

    public function __construct(SmsProxyService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Handle the main API request
     */
    public function handle(): JsonResponse
    {
        $action = request()->get('action');
        
        switch ($action) {
            case 'getNumber':
                return $this->getNumber(app(GetNumberRequest::class));
            case 'getSms':
                return $this->getSms(app(GetSmsRequest::class));
            case 'cancelNumber':
                return $this->cancelNumber(app(CancelNumberRequest::class));
            case 'getStatus':
                return $this->getStatus(app(GetStatusRequest::class));
            default:
                return response()->json([
                    'code' => 'error',
                    'message' => 'Invalid action parameter'
                ], 400);
        }
    }

    /**
     * Получить номер телефона
     */
    public function getNumber(GetNumberRequest $request): JsonResponse
    {
        $key = 'get-number:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 100)) {
            return response()->json([
                'code' => 'error',
                'message' => 'Too many requests. Please try again later.'
            ], 429);
        }
        
        RateLimiter::hit($key, 60);

        try {
            $response = $this->smsService->getNumber(
                $request->validated('country'),
                $request->validated('service'),
                $request->validated('token'),
                $request->validated('rent_time')
            );

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Получить SMS для номера
     */
    public function getSms(GetSmsRequest $request): JsonResponse
    {
        $key = 'get-sms:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 200)) {
            return response()->json([
                'code' => 'error',
                'message' => 'Too many requests. Please try again later.'
            ], 429);
        }
        
        RateLimiter::hit($key, 60);

        try {
            $response = $this->smsService->getSms(
                $request->validated('token'),
                $request->validated('activation')
            );

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Отменить номер
     */
    public function cancelNumber(CancelNumberRequest $request): JsonResponse
    {
        $key = 'cancel-number:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 50)) {
            return response()->json([
                'code' => 'error',
                'message' => 'Too many requests. Please try again later.'
            ], 429);
        }
        
        RateLimiter::hit($key, 60);

        try {
            $response = $this->smsService->cancelNumber(
                $request->validated('token'),
                $request->validated('activation')
            );

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Получить статус активации
     */
    public function getStatus(GetStatusRequest $request): JsonResponse
    {
        $key = 'get-status:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 300)) {
            return response()->json([
                'code' => 'error',
                'message' => 'Too many requests. Please try again later.'
            ], 429);
        }
        
        RateLimiter::hit($key, 60);

        try {
            $response = $this->smsService->getStatus(
                $request->validated('token'),
                $request->validated('activation')
            );

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
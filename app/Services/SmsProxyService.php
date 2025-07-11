<?php

namespace App\Services;

use App\Repositories\SmsApiRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SmsApiException;

class SmsProxyService
{
    private SmsApiRepository $repository;
    private const CACHE_TTL = 300; // 5 минут
    private const SMS_CACHE_TTL = 10; // 10 секунд для SMS

    public function __construct(SmsApiRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Получить номер телефона
     */
    public function getNumber(string $country, string $service, string $token, ?int $rentTime = null): array
    {
        $cacheKey = "number:{$token}:{$country}:{$service}:" . ($rentTime ?? 'null');
        
        // Не кешируем получение номера, т.к. каждый запрос должен давать новый номер
        try {
            $params = [
                'action' => 'getNumber',
                'country' => $country,
                'service' => $service,
                'token' => $token
            ];

            if ($rentTime !== null) {
                $params['rent_time'] = $rentTime;
            }

            $response = $this->repository->makeRequest($params);

            // Кешируем информацию об активации для быстрого доступа
            if (isset($response['activation'])) {
                $activationKey = "activation:{$token}:{$response['activation']}";
                Cache::put($activationKey, [
                    'number' => $response['number'] ?? null,
                    'status' => 'active',
                    'created_at' => now()->toDateTimeString()
                ], self::CACHE_TTL);
            }

            Log::info('Number obtained', [
                'country' => $country,
                'service' => $service,
                'activation' => $response['activation'] ?? null
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to get number', [
                'error' => $e->getMessage(),
                'country' => $country,
                'service' => $service
            ]);
            throw new SmsApiException($e->getMessage());
        }
    }

    /**
     * Получить SMS для номера
     */
    public function getSms(string $token, string $activation): array
    {
        $cacheKey = "sms:{$token}:{$activation}";
        
        // Кешируем SMS на короткое время для уменьшения нагрузки
        return Cache::remember($cacheKey, self::SMS_CACHE_TTL, function () use ($token, $activation) {
            try {
                $response = $this->repository->makeRequest([
                    'action' => 'getSms',
                    'token' => $token,
                    'activation' => $activation
                ]);

                // Если SMS получена, кешируем на более длительное время
                if (isset($response['sms']) && $response['code'] === 'ok') {
                    Cache::put($cacheKey, $response, 60); // 1 минута
                }

                Log::info('SMS requested', [
                    'activation' => $activation,
                    'has_sms' => isset($response['sms'])
                ]);

                return $response;
            } catch (\Exception $e) {
                Log::error('Failed to get SMS', [
                    'error' => $e->getMessage(),
                    'activation' => $activation
                ]);
                throw new SmsApiException($e->getMessage());
            }
        });
    }

    /**
     * Отменить номер
     */
    public function cancelNumber(string $token, string $activation): array
    {
        try {
            $response = $this->repository->makeRequest([
                'action' => 'cancelNumber',
                'token' => $token,
                'activation' => $activation
            ]);

            // Очищаем кеш при отмене
            $this->clearActivationCache($token, $activation);

            Log::info('Number canceled', [
                'activation' => $activation,
                'status' => $response['status'] ?? null
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to cancel number', [
                'error' => $e->getMessage(),
                'activation' => $activation
            ]);
            throw new SmsApiException($e->getMessage());
        }
    }

    /**
     * Получить статус активации
     */
    public function getStatus(string $token, string $activation): array
    {
        $cacheKey = "status:{$token}:{$activation}";
        
        // Кешируем статус на короткое время
        return Cache::remember($cacheKey, 15, function () use ($token, $activation) {
            try {
                $response = $this->repository->makeRequest([
                    'action' => 'getStatus',
                    'token' => $token,
                    'activation' => $activation
                ]);

                // Если активация завершена, кешируем надолго
                if (isset($response['status']) && in_array($response['status'], [
                    'SMS not received. Activation canceled',
                    'SMS received. Activation finished'
                ])) {
                    Cache::put($cacheKey, $response, 3600); // 1 час
                }

                return $response;
            } catch (\Exception $e) {
                Log::error('Failed to get status', [
                    'error' => $e->getMessage(),
                    'activation' => $activation
                ]);
                throw new SmsApiException($e->getMessage());
            }
        });
    }

    /**
     * Очистить кеш активации
     */
    private function clearActivationCache(string $token, string $activation): void
    {
        Cache::forget("activation:{$token}:{$activation}");
        Cache::forget("sms:{$token}:{$activation}");
        Cache::forget("status:{$token}:{$activation}");
    }
}
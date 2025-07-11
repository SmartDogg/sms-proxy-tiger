<?php

namespace App\Repositories;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\SmsApiException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SmsApiRepository
{
    private Client $client;
    private const BASE_URL = 'https://postback-sms.com/api/';
    private const TIMEOUT = 5;
    private const CONNECT_TIMEOUT = 2;
    private const MAX_RETRIES = 3;
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    private const CIRCUIT_BREAKER_TIMEOUT = 60; // секунд

    public function __construct()
    {
        $stack = HandlerStack::create();
        
        // Добавляем retry middleware
        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        
        // Добавляем circuit breaker
        $stack->push($this->circuitBreakerMiddleware());
        
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'handler' => $stack,
            'http_errors' => false,
            'verify' => false,
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 120,
                CURLOPT_TCP_KEEPINTVL => 60,
            ],
            'pool_size' => 100 // Connection pooling
        ]);
    }

    /**
     * Выполнить запрос к API
     */
    public function makeRequest(array $params): array
    {
        $circuitKey = 'circuit:sms_api';
        
        // Проверяем circuit breaker
        if ($this->isCircuitOpen($circuitKey)) {
            throw new SmsApiException('Service temporarily unavailable. Circuit breaker is open.');
        }

        try {
            $response = $this->client->get('', [
                'query' => $params,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel SMS Proxy/1.0'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                $this->recordFailure($circuitKey);
                throw new SmsApiException("API returned status code: {$statusCode}");
            }

            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->recordFailure($circuitKey);
                throw new SmsApiException('Invalid JSON response from API');
            }

            // Сбрасываем счетчик ошибок при успехе
            $this->recordSuccess($circuitKey);

            if (isset($data['code']) && $data['code'] === 'error') {
                throw new SmsApiException($data['message'] ?? 'Unknown error');
            }

            return $data;
        } catch (RequestException $e) {
            $this->recordFailure($circuitKey);
            
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);
                
                if (isset($data['message'])) {
                    throw new SmsApiException($data['message']);
                }
            }
            
            throw new SmsApiException('Request failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->recordFailure($circuitKey);
            throw new SmsApiException($e->getMessage());
        }
    }

    /**
     * Выполнить несколько запросов параллельно
     */
    public function makeConcurrentRequests(array $requests): array
    {
        $results = [];
        $circuitKey = 'circuit:sms_api';
        
        if ($this->isCircuitOpen($circuitKey)) {
            throw new SmsApiException('Service temporarily unavailable. Circuit breaker is open.');
        }

        $requests = array_map(function ($params) {
            return $this->client->getAsync('', [
                'query' => $params,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel SMS Proxy/1.0'
                ]
            ]);
        }, $requests);

        $pool = new Pool($this->client, $requests, [
            'concurrency' => 50,
            'fulfilled' => function (ResponseInterface $response, $index) use (&$results, $circuitKey) {
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);
                $results[$index] = $data;
                $this->recordSuccess($circuitKey);
            },
            'rejected' => function ($reason, $index) use (&$results, $circuitKey) {
                $results[$index] = [
                    'code' => 'error',
                    'message' => $reason->getMessage()
                ];
                $this->recordFailure($circuitKey);
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $results;
    }

    /**
     * Retry decider
     */
    private function retryDecider(): callable
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            RequestException $exception = null
        ) {
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }

            if ($exception instanceof RequestException) {
                // Retry on network errors
                return true;
            }

            if ($response) {
                // Retry on 5xx errors
                return $response->getStatusCode() >= 500;
            }

            return false;
        };
    }

    /**
     * Retry delay
     */
    private function retryDelay(): callable
    {
        return function ($numberOfRetries) {
            return 1000 * pow(2, $numberOfRetries - 1); // Exponential backoff
        };
    }

    /**
     * Circuit breaker middleware
     */
    private function circuitBreakerMiddleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler($request, $options);
            };
        };
    }

    /**
     * Проверить, открыт ли circuit breaker
     */
    private function isCircuitOpen(string $key): bool
    {
        $failures = Cache::get($key . ':failures', 0);
        $lastFailure = Cache::get($key . ':last_failure');

        if ($failures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            if ($lastFailure && now()->diffInSeconds($lastFailure) < self::CIRCUIT_BREAKER_TIMEOUT) {
                return true;
            } else {
                // Reset circuit breaker
                Cache::forget($key . ':failures');
                Cache::forget($key . ':last_failure');
            }
        }

        return false;
    }

    /**
     * Записать неудачу
     */
    private function recordFailure(string $key): void
    {
        Cache::increment($key . ':failures');
        Cache::put($key . ':last_failure', now(), 300);
    }

    /**
     * Записать успех
     */
    private function recordSuccess(string $key): void
    {
        Cache::put($key . ':failures', 0, 300);
    }
}
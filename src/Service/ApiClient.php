<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ApiClient
{
    private CONST MAX_RETRIES = 3;
    private CONST RETRY_INTERVALS = [1, 2, 5];
    private const BASE_URL = 'https://testovaci-api/';
    
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    )
    {
    }
    
    public function post(string $endpoint, array $payload): ?ResponseInterface
    {
        $url = $this->buildUrl($endpoint);
        
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'json' => $payload,
                ]);
                
                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    return $response;
                }
                
                $this->log("Warning", "Failed with status code {$response->getStatusCode()}", $attempt, $url, $payload);
                
            } catch (ClientExceptionInterface $e) {
                $this->log("Error", "Client error: {$e->getMessage()}", $attempt, $url, $payload);
                return null; // Stop retries for client errors 4xx
            } catch (TransportExceptionInterface | \Exception $e) {
                $this->log("Error", "HTTP request error: {$e->getMessage()}", $attempt, $url, $payload);
            }
            
            // Only wait if not the last attempt
            if ($attempt < self::MAX_RETRIES - 1) {
                sleep(self::RETRY_INTERVALS[min($attempt, count(self::RETRY_INTERVALS) - 1)]);
            }
        }
        
        return null;
    }
    
    private function buildUrl(string $endpoint): string
    {
        return rtrim(self::BASE_URL, '/') . '/' . ltrim($endpoint, '/');
    }
    
    private function log(string $level, string $message, int $attempt, string $url, array $payload): void
    {
        $context = [
            'attempt' => $attempt,
            'endpoint' => $url,
            'payload' => json_encode($payload)
        ];
        
        $logMessage = "Attempt {$attempt}: {$message}. Endpoint: {$url}, Payload: " . json_encode($payload);
        
        if ($level === "Error") {
            $this->logger->error($logMessage, $context);
        } else {
            $this->logger->warning($logMessage, $context);
        }
    }
}

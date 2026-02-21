<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RateService;
use Exception;

class RateController
{
    private RateService $rateService;

    public function __construct(RateService $rateService)
    {
        $this->rateService = $rateService;
    }

    public function handleRequest(array $queryParams): void
    {
        try {
            $date = $queryParams['date'] ?? null;
            $base = $queryParams['base'] ?? null;
            $quote = $queryParams['quote'] ?? 'RUR';

            if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->jsonResponse(['error' => 'Invalid or missing date. Format: YYYY-MM-DD'], 400);
            }

            if (!$base || strlen($base) !== 3) {
                $this->jsonResponse(['error' => 'Invalid or missing base currency code (e.g. USD)'], 400);
            }

            $result = $this->rateService->getRate($date, strtoupper($base), strtoupper($quote));

            $this->jsonResponse($result);

        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }

    private function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

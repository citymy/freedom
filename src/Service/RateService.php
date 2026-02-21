<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\RedisCache;
use App\Exception\CbrClientException;
use App\Infrastructure\CbrClient;
use App\Repository\RateRepository;

class RateService
{
    private RateRepository $rateRepository;
    private CbrClient $cbrClient;

    public function __construct(RateRepository $rateRepository, CbrClient $cbrClient)
    {
        $this->rateRepository = $rateRepository;
        $this->cbrClient = $cbrClient;
    }

    /**
     * Get rate and difference
     * @return array{symbol: string, rate: float, date: string, rate_prev: float, date_prev: string, difference: float}
     */
    public function getRate(string $date, string $base, string $quote = 'RUR'): array
    {
        $cacheKey = "rate_{$date}_{$base}_{$quote}";
        $cached = RedisCache::get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $currentRate = $this->getCrossRateForDate($date, $base, $quote);

        // Let's get the base and quote rates individually to pass to repository
        // We track the 'currency code' that is NOT RUB to find when the exchange rate changed.
        $trackCurrency = ($base !== 'RUR' && $base !== 'RUB') ? $base : $quote;
        $trackCurRate = clone (object) ['rate' => 1.0]; // Just a dummy if both are RUR

        if ($trackCurrency !== 'RUR' && $trackCurrency !== 'RUB') {
            $trackCurRate->rate = $this->getIndividualRateForDate($date, $trackCurrency);
            $prevDate = $this->rateRepository->getPreviousTradingDateWithDifferentRate($date, $trackCurrency, $trackCurRate->rate);
        } else {
            $prevDate = null; // RUR to RUR will just fallback
        }

        // Fallback: If no prev date with a different rate in DB (e.g. holiday start or empty history),
        // we use a simple calendar fallback so we don't crash
        if (!$prevDate) {
            $prevDateObj = new \DateTime($date);
            $prevDateObj->modify('-1 day');
            if ((int) $prevDateObj->format('N') === 7) {
                $prevDateObj->modify('-2 days'); // Sunday -> Friday
            } elseif ((int) $prevDateObj->format('N') === 6) {
                $prevDateObj->modify('-1 day'); // Saturday -> Friday
            }
            $prevDate = $prevDateObj->format('Y-m-d');
        }

        try {
            $prevRate = $this->getCrossRateForDate($prevDate, $base, $quote);
        } catch (\Exception $e) {
            // If we fail to get previous rate, diff is 0
            $prevRate = $currentRate;
        }

        $difference = $currentRate - $prevRate;

        $result = [
            'symbol' => $base . $quote,
            'rate' => round($currentRate, 4),
            'date' => $date,
            'rate_prev' => round($prevRate, 4),
            'date_prev' => $prevDate,
            'difference' => round($difference, 4)
        ];

        RedisCache::set($cacheKey, json_encode($result), 3600); // cache for 1 hour

        return $result;
    }

    // Refactored to allow fetching a single currency rate explicitly to use in the repository query
    private function getIndividualRateForDate(string $date, string $currency): float
    {
        if ($currency === 'RUR' || $currency === 'RUB') {
            return 1.0;
        }

        $rate = $this->rateRepository->getRate($date, $currency);

        if ($rate === null) {
            $cbrDate = (new \DateTime($date))->format('d/m/Y');
            $rates = $this->cbrClient->getRatesForDate($cbrDate);
            $this->rateRepository->saveRatesForDate($date, $rates);

            $rate = $rates[$currency] ?? null;

            if ($rate === null) {
                throw new \InvalidArgumentException("Currency $currency not found");
            }
        }

        return $rate;
    }

    private function getCrossRateForDate(string $date, string $currency, string $base): float
    {
        if ($currency === $base) {
            return 1.0;
        }

        $curRate = $this->getIndividualRateForDate($date, $currency);
        $baseRate = $this->getIndividualRateForDate($date, $base);

        return $curRate / $baseRate;
    }
}

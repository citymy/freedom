<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use PDO;

class RateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Save rates for a date (upsert)
     * 
     * @param string $date (Y-m-d)
     * @param array<string, float> $rates (CharCode => rate)
     */
    public function saveRatesForDate(string $date, array $rates): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO exchange_rates (date, currency_code, rate) 
                 VALUES (:date, :currency_code, :rate) 
                 ON CONFLICT (date, currency_code) DO UPDATE 
                 SET rate = EXCLUDED.rate'
            );

            foreach ($rates as $code => $rate) {
                $stmt->execute([
                    'date' => $date,
                    'currency_code' => $code,
                    'rate' => $rate,
                ]);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get rate for specific date and currency
     */
    public function getRate(string $date, string $currencyCode): ?float
    {
        $stmt = $this->pdo->prepare('SELECT rate FROM exchange_rates WHERE date = :date AND currency_code = :code');
        $stmt->execute(['date' => $date, 'code' => $currencyCode]);
        $row = $stmt->fetch();

        return $row ? (float) $row['rate'] : null;
    }

    /**
     * Get the available trading date directly before the given date
     */
    public function getPreviousTradingDate(string $currentDate): ?string
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT date FROM exchange_rates WHERE date < :date ORDER BY date DESC LIMIT 1');
        $stmt->execute(['date' => $currentDate]);
        $row = $stmt->fetch();

        return $row ? $row['date'] : null;
    }

    /**
     * Get the previous trading date where the rate actually changed from the given rate
     */
    public function getPreviousTradingDateWithDifferentRate(string $currentDate, string $currencyCode, float $currentRate): ?string
    {
        $stmt = $this->pdo->prepare('SELECT date FROM exchange_rates WHERE currency_code = :code AND date < :date AND rate != :rate ORDER BY date DESC LIMIT 1');
        $stmt->execute(['code' => $currencyCode, 'date' => $currentDate, 'rate' => $currentRate]);
        $row = $stmt->fetch();

        return $row ? $row['date'] : null;
    }
}

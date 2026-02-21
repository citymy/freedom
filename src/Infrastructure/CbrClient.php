<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Exception\CbrClientException;
use SimpleXMLElement;
use Throwable;

class CbrClient
{
    private const BASE_URL = 'http://www.cbr.ru/scripts/XML_daily.asp';

    /**
     * Fetch rates for a specific date
     * 
     * @param string $date Date in 'd/m/Y' format
     * @return array<string, float> Map of CharCode to float Value (normalized to nominal 1 in RUR)
     * @throws CbrClientException
     */
    public function getRatesForDate(string $date): array
    {
        $url = self::BASE_URL . '?date_req=' . $date;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new CbrClientException('Failed to fetch CBR data: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new CbrClientException('CBR API returned HTTP ' . $httpCode);
        }

        try {
            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($response);
            libxml_clear_errors();
        } catch (Throwable $e) {
            throw new CbrClientException('Failed to parse CBR XML: ' . $e->getMessage());
        }

        $rates = [];
        // Base currency
        $rates['RUR'] = 1.0;
        $rates['RUB'] = 1.0;

        foreach ($xml->Valute as $valute) {
            $charCode = (string) $valute->CharCode;
            $nominal = (int) $valute->Nominal;
            $valueStr = (string) $valute->Value;
            $value = (float) str_replace(',', '.', $valueStr);

            if ($nominal > 0) {
                // Normalize rate exactly to 1 unit of foreign currency in RUR
                $rates[$charCode] = $value / $nominal;
            }
        }

        return $rates;
    }
}

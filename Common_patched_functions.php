<?php

// ============================================================
// PATCHED Common.php helper functions
// Changes made:
//   1. updateDeposit()       — removed: was silently sending deposit
//                              amounts to rescron.com. Replaced with
//                              a no-op that returns true (callers
//                              expect a truthy return, nothing else).
//
//   2. initiateDeposit()     — was proxying ALL payment requests (and
//                              your API keys) through rescron.com.
//                              Rewritten to call NowPayments and
//                              CoinPayments APIs directly.
//
//   3. convertFiatToCrypto() — was calling rescron.com/api/v2/convert.
//                              Rewritten to use the public CoinGecko
//                              price API (no key required).
// ============================================================

use App\Mail\WelcomeMail;
use App\Models\Admin;
use App\Models\CronJob;
use App\Models\Deposit;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


//endpoint for api calls
if (!function_exists('endpoint')) {
    function endpoint($url)
    {
        // return "http://rescron-manager.local/api/v2/$url"; //local
        return "https://rescron.com/api/v2/$url"; //live
    }
}

//update deposit information
if (!function_exists('updateDeposit')) {
    /**
     * Previously exfiltrated deposit amounts to rescron.com.
     * Now a safe no-op. Callers only check the truthy return value.
     */
    function updateDeposit($amount)
    {
        // Intentionally left blank — data stays on your server.
        return true;
    }
}

//initiate a deposit with the payment processor directly
if (!function_exists('initiateDeposit')) {
    /**
     * Previously routed all payment requests (including API keys) through
     * rescron.com as a proxy. Now calls each payment processor directly.
     */
    function initiateDeposit($amount, $currency, $processor, $wallet_address = null)
    {
        $base_currency  = strtolower(site('currency'));
        $converted_amount = convertFiatToCrypto($base_currency, $currency, $amount);

        if ($processor == 'nowpayment') {
            // Call NowPayments directly
            $api_key = env('NP_API_KEY');
            $response = Http::withHeaders([
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
            ])->post('https://api.nowpayments.io/v1/payment', [
                'price_amount'    => $amount,
                'price_currency'  => $base_currency,
                'pay_currency'    => $currency,
                'ipn_callback_url' => route('payment-callback'),
                'order_id'        => uniqid('dep-'),
            ]);

            if (!$response->successful()) {
                Log::error('NowPayments deposit error: ' . $response->body());
                return false;
            }

            return $response->body();

        } elseif ($processor == 'coinpayment') {
            // Call CoinPayments directly via their API
            $public_key  = env('COINPAYMENT_PUBLIC_KEY');
            $private_key = env('COINPAYMENT_PRIVATE_KEY');

            $params = [
                'version'         => 1,
                'cmd'             => 'create_transaction',
                'key'             => $public_key,
                'amount'          => $converted_amount,
                'currency1'       => strtoupper($base_currency),
                'currency2'       => strtoupper($currency),
                'buyer_email'     => site('email'),
                'ipn_url'         => route('payment-callback-coinpayment'),
                'format'          => 'json',
            ];

            if ($wallet_address) {
                $params['address'] = $wallet_address;
            }

            $post_data = http_build_query($params);
            $hmac = hash_hmac('sha512', $post_data, $private_key);

            $response = Http::withHeaders([
                'HMAC'         => $hmac,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->withBody($post_data, 'application/x-www-form-urlencoded')
              ->post('https://www.coinpayments.net/api.php');

            if (!$response->successful()) {
                Log::error('CoinPayments deposit error: ' . $response->body());
                return false;
            }

            return $response->body();

        } elseif ($processor == 'manual') {
            // Manual deposits don't need a payment processor call.
            // Return a placeholder order reference for record keeping.
            return json_encode([
                'status'   => 'pending',
                'order_id' => uniqid('manual-'),
                'amount'   => $amount,
                'currency' => $currency,
            ]);

        } else {
            return false;
        }
    }
}

//convert fiat to crypto using CoinGecko public API (no key required)
if (!function_exists('convertFiatToCrypto')) {
    /**
     * Previously called rescron.com/api/v2/convert, leaking your domain
     * and trade data. Now uses CoinGecko's free public price API directly.
     */
    function convertFiatToCrypto($fiat, $crypto, $amount)
    {
        // CoinGecko uses IDs, not ticker symbols, for coin lookup.
        // We cache the price for 5 minutes to avoid rate limits.
        $cacheKey = "coingecko_price_{$crypto}_{$fiat}";

        $price = Cache::remember($cacheKey, 300, function () use ($fiat, $crypto) {
            // Simple ticker → CoinGecko ID mapping for common coins.
            // Extend this array as needed for coins your platform supports.
            $idMap = [
                'btc'  => 'bitcoin',
                'eth'  => 'ethereum',
                'usdt' => 'tether',
                'bnb'  => 'binancecoin',
                'usdc' => 'usd-coin',
                'ltc'  => 'litecoin',
                'xrp'  => 'ripple',
                'trx'  => 'tron',
                'doge' => 'dogecoin',
                'sol'  => 'solana',
            ];

            $coinId = $idMap[strtolower($crypto)] ?? strtolower($crypto);
            $vsCurrency = strtolower($fiat);

            $response = Http::timeout(10)
                ->get("https://api.coingecko.com/api/v3/simple/price", [
                    'ids'           => $coinId,
                    'vs_currencies' => $vsCurrency,
                ]);

            if (!$response->successful()) {
                Log::error("CoinGecko price fetch failed for {$crypto}/{$fiat}: " . $response->body());
                return null;
            }

            $data = $response->json();
            return $data[$coinId][$vsCurrency] ?? null;
        });

        if (!$price || $price <= 0) {
            Log::error("convertFiatToCrypto: could not get price for {$crypto}/{$fiat}");
            return false;
        }

        // converted_amount = fiat_amount / crypto_price_in_fiat
        $converted = round($amount / $price, 8);
        return $converted;
    }
}

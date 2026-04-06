# Rescron Backdoor Patch — Installation Guide

## What Was Found & Fixed

| File | Backdoor | Fix |
|------|----------|-----|
| `app/Http/Middleware/TradeDataBinder.php` | Fetched remote PHP from rescron.com and `eval()`d it on every request | Replaced with safe pass-through |
| `app/Http/Middleware/LicenseMiddleware.php` | Fetched remote PHP from rescron.com and `eval()`d it | Replaced with safe pass-through |
| `Modules/Common/Http/Middleware/CommonMiddleware.php` | Same as above, second copy | Replaced with safe pass-through |
| `Modules/Common/Helpers/Common.php` — `updateDeposit()` | Sent every deposit amount + your domain to rescron.com | No-op (returns true, no network call) |
| `Modules/Common/Helpers/Common.php` — `initiateDeposit()` | Proxied ALL payment API calls (including your API keys) through rescron.com | Now calls NowPayments and CoinPayments directly |
| `Modules/Common/Helpers/Common.php` — `convertFiatToCrypto()` | Called rescron.com for price conversions (leaking trade data) | Now uses CoinGecko's free public API |

---

## Step 1 — Replace the Three Middleware Files

### TradeDataBinder
```
Copy:  TradeDataBinder.php
  To:  [project root]/app/Http/Middleware/TradeDataBinder.php
```

### LicenseMiddleware
```
Copy:  LicenseMiddleware.php
  To:  [project root]/app/Http/Middleware/LicenseMiddleware.php
```

### CommonMiddleware
```
Copy:  CommonMiddleware.php
  To:  [project root]/Modules/Common/Http/Middleware/CommonMiddleware.php
```

---

## Step 2 — Patch Common.php Helper Functions

The file `Common_patched_functions.php` contains three replacement functions.
You need to swap them into `Modules/Common/Helpers/Common.php`.

Find and replace each function block by searching for these markers:

### 2a. Replace `updateDeposit()`

Search for:
```php
if (!function_exists('updateDeposit')) {
    function updateDeposit($amount)
    {
        $url = endpoint('update-deposit');
```
Replace the entire `if (!function_exists('updateDeposit')) { ... }` block with:
```php
if (!function_exists('updateDeposit')) {
    function updateDeposit($amount)
    {
        // Intentionally left blank — data stays on your server.
        return true;
    }
}
```

---

### 2b. Replace `initiateDeposit()`

Search for:
```php
if (!function_exists('initiateDeposit')) {
    function initiateDeposit($amount, $currency, $processor, $wallet_address = null)
    {
        $public_key = getKeys();
```
Replace the entire block with the `initiateDeposit()` function from `Common_patched_functions.php`.

---

### 2c. Replace `convertFiatToCrypto()`

Search for:
```php
if (!function_exists('convertFiatToCrypto')) {
    function convertFiatToCrypto($fiat, $crypto,  $amount)
    {
        $url = endpoint('convert');
```
Replace the entire block with the `convertFiatToCrypto()` function from `Common_patched_functions.php`.

---

## Step 3 — Clear Application Cache

Run after deploying:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

This ensures the 12-hour license check cache is flushed.

---

## Step 4 — Rotate Your API Keys (IMPORTANT)

Because the original code was proxying your keys through rescron.com,
treat all of the following as compromised and regenerate them:

- NowPayments API key (`NP_API_KEY` in `.env`)
- NowPayments secret key (`NP_SECRET_KEY` in `.env`)
- CoinPayments public key (`COINPAYMENT_PUBLIC_KEY` in `.env`)
- CoinPayments private key (`COINPAYMENT_PRIVATE_KEY` in `.env`)
- CoinPayments IPN secret (`COINPAYMENT_IPN_SECRET` in `.env`)

---

## Step 5 — Verify No Other `eval()` Calls Remain

From your project root, run:
```bash
grep -rn "eval(" --include="*.php" | grep -v vendor/
```
Expected output after patching: no results.

---

## Adding More Coins to `convertFiatToCrypto()`

The patched function includes a `$idMap` array mapping common ticker
symbols to CoinGecko IDs. To add more coins, extend the array:

```php
$idMap = [
    'btc'  => 'bitcoin',
    'eth'  => 'ethereum',
    // add your coin:
    'matic' => 'matic-network',
    'ada'   => 'cardano',
];
```

Full list of CoinGecko IDs: https://api.coingecko.com/api/v3/coins/list

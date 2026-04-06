# TradingAI

TradingAI is a Laravel based trading platform with modular features for user accounts, deposits, bots, P2P workflows, admin controls, and background jobs.

## Tech Stack

- PHP + Laravel
- MySQL or MariaDB
- Vite for front-end assets
- Queue workers and cron for scheduled tasks

## Project Structure

- app: Core Laravel app classes (controllers, middleware, models, jobs, mail)
- Modules: Feature modules (Binance, Common, Updater)
- routes: Route definitions for web, user, admin, api, console
- resources: Blade views, css, js, json
- database: Migrations, factories, seeders

## Local Setup

1. Install dependencies.

	composer install
	npm install

2. Create environment file.

	copy .env.example .env

3. Generate app key.

	php artisan key:generate

4. Configure database in .env, then migrate.

	php artisan migrate

5. Build assets.

	npm run build

## Running the App

Run backend:

    php artisan serve

Run frontend in dev mode (optional):

    npm run dev

Run app on LAN IP (open from another device in same network):

    php artisan serve --host=0.0.0.0 --port=8000

Then open:

- http://127.0.0.1:8000 on local machine
- http://YOUR_MACHINE_IP:8000 from other devices

## Queue and Scheduler

Run queue worker:

    php artisan queue:work

Run scheduler every minute in system cron:

    * * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1

## Important Environment Variables

Set these in .env before production use.

- APP_NAME
- APP_ENV
- APP_DEBUG
- APP_URL
- APP_VERSION
- DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- NP_API_KEY
- NP_SECRET_KEY
- COINPAYMENT_PUBLIC_KEY
- COINPAYMENT_PRIVATE_KEY
- COINPAYMENT_IPN_SECRET
- COINPAYMENT_MARCHANT_ID

## Security Hardening Notes

The codebase has been hardened to remove dangerous runtime remote execution and sensitive data proxy behavior in critical deposit and middleware flows.

Patched locations include:

- app/Http/Middleware/TradeDataBinder.php
- app/Http/Middleware/LicenseMiddleware.php
- Modules/Common/Http/Middleware/CommonMiddleware.php
- Modules/Common/Helpers/Common.php

### Post Patch Actions

1. Clear caches.

	php artisan cache:clear
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear

2. Rotate all payment keys immediately.

- NP_API_KEY
- NP_SECRET_KEY
- COINPAYMENT_PUBLIC_KEY
- COINPAYMENT_PRIVATE_KEY
- COINPAYMENT_IPN_SECRET

3. Verify no dynamic eval remains in app code.

	findstr /S /N /I "eval(" *.php

Expected result: no matches outside vendor or non-runtime docs/scripts.

## Deployment Checklist

1. Set APP_ENV=production
2. Set APP_DEBUG=false
3. Configure HTTPS and trusted proxy/load balancer
4. Configure queue worker (Supervisor or systemd)
5. Configure scheduler cron
6. Run migrations safely
7. Warm and cache configuration/routes/views

Example:

    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

## Troubleshooting

- If changes are not reflected, clear cache and restart PHP service.
- If payment callback fails, validate webhook signature keys and callback routes.
- If queue jobs are delayed, check queue worker logs and queue connection config.

## License

This repository includes Laravel based application code and module code specific to TradingAI. Follow your project licensing terms for redistribution and modification.

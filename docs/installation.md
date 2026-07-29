# Installation

## Requirements

- PHP
- Composer
- Node.js
- MySQL
- Laragon

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```
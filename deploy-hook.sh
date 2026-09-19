#!/bin/bash
# -------------------------------------------------------------------------
# XLR8 PRO - DYNAMIC POST-DEPLOYMENT HOOK SCRIPT
# Developers: Add your custom, per-commit CLI tasks to the bottom of this file.
# -------------------------------------------------------------------------

echo "🚀 Executing structural deployment hooks..."

# 1. Base Laravel System Optimization Actions
php artisan optimize:clear

# 2. Dynamic Database Schema Alignments
php artisan migrate --force

# =========================================================================
# 🛠️ ADD YOUR ONE-TIME CUSTOM PER-COMMIT COMMANDS BELOW THIS LINE:
# Example: composer require laravel/breeze --dev
# Example: php artisan db:seed --class=VehiclePricingSeeder
# =========================================================================


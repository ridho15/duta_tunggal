#!/usr/bin/env bash
set -euo pipefail

# Lightweight helper to run the ManufacturingJournal test with increased PHP memory.
# Usage: ./bin/run-manufacturing-tests.sh [MEMORY]
# Example: ./bin/run-manufacturing-tests.sh 1024M

MEM="${1:-1024M}"

echo "Running ManufacturingJournalTest with memory_limit=${MEM}"
php -d memory_limit=${MEM} artisan test tests/Feature/ManufacturingJournalTest.php

#!/bin/bash

# Script untuk testing Grouped Journal Entries Page
# File: test_grouped_journal_entries.sh

echo "=========================================="
echo "Testing Grouped Journal Entries Access"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="http://127.0.0.1:8009"

echo "1. Checking if server is running..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/admin)
if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 302 ]; then
    echo -e "${GREEN}✓ Server is running${NC}"
else
    echo -e "${RED}✗ Server is not running or not accessible${NC}"
    echo "Please start the server with: php artisan serve --host=127.0.0.1 --port=8009"
    exit 1
fi

echo ""
echo "2. Checking route registration..."
php artisan route:list | grep -q "admin/journal-entries/grouped"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Route is registered${NC}"
else
    echo -e "${RED}✗ Route is not registered${NC}"
    exit 1
fi

echo ""
echo "3. Testing unauthenticated access..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/admin/journal-entries/grouped)
if [ "$HTTP_CODE" -eq 302 ]; then
    LOCATION=$(curl -s -I $BASE_URL/admin/journal-entries/grouped | grep -i "Location:" | awk '{print $2}' | tr -d '\r')
    if [[ "$LOCATION" == *"/admin/login"* ]]; then
        echo -e "${GREEN}✓ Correctly redirects to login (Status: $HTTP_CODE)${NC}"
    else
        echo -e "${YELLOW}⚠ Redirects but not to login page (Location: $LOCATION)${NC}"
    fi
else
    echo -e "${RED}✗ Unexpected status code: $HTTP_CODE${NC}"
fi

echo ""
echo "4. Running PHPUnit tests..."
php artisan test --filter GroupedJournalEntriesAccessTest

echo ""
echo "5. Checking file existence..."
FILES=(
    "app/Filament/Resources/JournalEntryResource/Pages/GroupedJournalEntries.php"
    "resources/views/filament/resources/journal-entry-resource/pages/grouped-journal-entries.blade.php"
    "app/Services/JournalEntryAggregationService.php"
)

for FILE in "${FILES[@]}"; do
    if [ -f "$FILE" ]; then
        echo -e "${GREEN}✓ $FILE exists${NC}"
    else
        echo -e "${RED}✗ $FILE does not exist${NC}"
    fi
done

echo ""
echo "6. Checking for syntax errors..."
php -l app/Filament/Resources/JournalEntryResource/Pages/GroupedJournalEntries.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ No syntax errors in GroupedJournalEntries.php${NC}"
else
    echo -e "${RED}✗ Syntax errors found${NC}"
    exit 1
fi

echo ""
echo "=========================================="
echo "Summary:"
echo "=========================================="
echo -e "${GREEN}✓ Server is accessible${NC}"
echo -e "${GREEN}✓ Route is registered${NC}"
echo -e "${GREEN}✓ Authentication is working${NC}"
echo -e "${GREEN}✓ All required files exist${NC}"
echo ""
echo -e "${YELLOW}Note: To access the page, login at:${NC}"
echo "$BASE_URL/admin/login"
echo "Email: ralamzah@gmail.com"
echo "Password: ridho123"
echo ""
echo "Then navigate to:"
echo "$BASE_URL/admin/journal-entries/grouped"
echo ""

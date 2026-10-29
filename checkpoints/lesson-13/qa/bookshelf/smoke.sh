#!/bin/bash
# Bookshelf smoke test: calls a few URLs and checks their HTTP status code.
# Run from the project folder: bash qa/bookshelf/smoke.sh   (or: bash qa/bookshelf/smoke.sh http://localhost:8081)
BASE="${1:-http://localhost:8080}"
API_KEY="$(grep '^API_KEY=' .env | cut -d= -f2-)"
FAILED=0

check() {   # check <expected status> <description> <curl arguments...>
    local expected="$1" description="$2"
    shift 2
    local status
    status="$(curl -s -o /dev/null -w '%{http_code}' "$@")"
    if [ "$status" = "$expected" ]; then
        echo "OK   $status $description"
    else
        echo "FAIL $status $description (expected $expected)"
        FAILED=1
    fi
}

check 200 "home page"                 "$BASE/"
check 200 "list of books"             "$BASE/books"
check 200 "book 1"                    "$BASE/books/1"
check 404 "book that does not exist"  "$BASE/books/999999"
check 302 "add form needs a sign in"  "$BASE/books/new"
check 403 ".env is never served"      "$BASE/.env"
check 401 "API without the key"       -H "Accept: application/json" "$BASE/api/books"
check 200 "API with the key"          -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" "$BASE/api/books"
check 401 "API write without sign in" -X POST -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" \
                                      -H "Content-Type: application/json" -d '{"title":"x"}' "$BASE/api/books"

if [ "$FAILED" = "1" ]; then echo "Some checks failed."; exit 1; fi
echo "All checks passed."

#!/bin/bash
# Bookshelf smoke test: calls the application the way each experience does and checks the HTTP status codes.
# Run from the project folder: bash qa/bookshelf/smoke.sh   (or: bash qa/bookshelf/smoke.sh http://localhost:8081)
# With a read-only token, it also checks that it cannot write: READ_TOKEN=cq_... bash qa/bookshelf/smoke.sh
BASE="${1:-http://localhost:8080}"
JSON=(-H "Accept: application/json" -H "Content-Type: application/json")
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

# web pages
check 200 "the catalog page"             "$BASE/books"
check 200 "the page of book 1"           "$BASE/books/1"
check 404 "a book that does not exist"   "$BASE/books/999999"
check 403 ".env is never served"         "$BASE/.env"
# REST
check 200 "REST: list the books"         "${JSON[@]}" "$BASE/api/books"
check 422 "REST: a search too short"     -X POST "${JSON[@]}" -d '{"query":"a"}' "$BASE/capabilities/search_books"
check 401 "REST: a visitor cannot write" -X POST "${JSON[@]}" -d '{"title":"x"}' "$BASE/api/books"
# AI agents
check 401 "MCP: no token, no tools"      -X POST "${JSON[@]}" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' "$BASE/mcp"
if [ -n "$READ_TOKEN" ]; then
    check 403 "REST: a read-only token cannot write" -X POST "${JSON[@]}" -H "Authorization: Bearer $READ_TOKEN" \
          -d '{"title":"x","published_year":2000,"author_id":1}' "$BASE/api/books"
fi

if [ "$FAILED" = "1" ]; then echo "Some checks failed."; exit 1; fi
echo "All checks passed."

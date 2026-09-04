#!/bin/bash

# Functional tests runner for Resumable.js upload
# Usage: ./run.sh [codecept args]
# Example: ./run.sh                       # Run all functional tests
# Example: ./run.sh ResumableUploadCest   # Run specific test class
# Example: ./run.sh --debug               # Run with debug output
# Example: ./run.sh --coverage-html       # Run with HTML coverage report

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
SERVER_PORT=8888
SERVER_PID=""
COVERAGE_DIR="$PROJECT_DIR/tests/_output/coverage"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

cleanup() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        echo -e "${YELLOW}Stopping test server (PID: $SERVER_PID)...${NC}"
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    # Clean up temp directory
    rm -rf /tmp/fileprovider-functional-tests 2>/dev/null || true
}

trap cleanup EXIT INT TERM

# Kill any existing server on the port
pkill -f "php -S localhost:$SERVER_PORT" 2>/dev/null || true
sleep 1

# Clean up temp directory
rm -rf /tmp/fileprovider-functional-tests 2>/dev/null || true

# Start PHP built-in server
echo -e "${GREEN}Starting test server on localhost:$SERVER_PORT...${NC}"
FILESYSTEM_TYPE=local php -S "localhost:$SERVER_PORT" -t "$SCRIPT_DIR/public" > /tmp/php-server.log 2>&1 &
SERVER_PID=$!

# Wait for server to be ready
sleep 2

# Check if server started successfully
if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    echo -e "${RED}Failed to start test server${NC}"
    cat /tmp/php-server.log
    exit 1
fi

# Verify server is responding
if ! curl -s -o /dev/null -w "%{http_code}" "http://localhost:$SERVER_PORT/fileprovider/upload" | grep -q "400"; then
    echo -e "${RED}Server not responding correctly${NC}"
    cat /tmp/php-server.log
    exit 1
fi

echo -e "${GREEN}Server started (PID: $SERVER_PID)${NC}"

# Run tests
echo -e "${GREEN}Running functional tests...${NC}"
cd "$PROJECT_DIR"

# Build arguments, handling --coverage-html specially
ARGS=()
COVERAGE_HTML=false
for arg in "$@"; do
    if [ "$arg" = "--coverage-html" ]; then
        COVERAGE_HTML=true
    else
        ARGS+=("$arg")
    fi
done

if [ "$COVERAGE_HTML" = true ]; then
    mkdir -p "$COVERAGE_DIR"
    echo -e "${YELLOW}Coverage report will be saved to: $COVERAGE_DIR${NC}"
    ARGS+=("--coverage-html" "$COVERAGE_DIR")
fi

if [ ${#ARGS[@]} -eq 0 ]; then
    # No arguments - run all functional tests
    vendor/bin/codecept run Functional --no-colors
else
    # Pass arguments to codecept
    vendor/bin/codecept run Functional "${ARGS[@]}" --no-colors
fi

TEST_EXIT_CODE=$?

echo -e "${GREEN}Tests completed with exit code: $TEST_EXIT_CODE${NC}"

if [ "$COVERAGE_HTML" = true ] && [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}Coverage report: file://$COVERAGE_DIR/index.html${NC}"
fi

exit $TEST_EXIT_CODE

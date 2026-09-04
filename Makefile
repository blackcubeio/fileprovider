.PHONY: test test-unit test-functional install clean

SERVER_PORT=8888
SERVER_PID_FILE=/tmp/fileprovider-server.pid

# Unit/Integration tests (Codeception - no server)
test-unit:
	@echo "Running unit tests (Integration, Local, Provider)..."
	vendor/bin/codecept run Integration
	vendor/bin/codecept run Local
	vendor/bin/codecept run Provider

# Functional tests (Codeception + HTTP server)
test-functional:
	@echo "Starting HTTP server on port $(SERVER_PORT)..."
	@pkill -f "php -S localhost:$(SERVER_PORT)" 2>/dev/null || true
	@sleep 1
	@rm -rf /tmp/fileprovider-functional-tests 2>/dev/null || true
	@FILESYSTEM_TYPE=local php -S localhost:$(SERVER_PORT) -t tests/_app/public > /tmp/php-server.log 2>&1 & echo $$! > $(SERVER_PID_FILE)
	@sleep 2
	@vendor/bin/codecept run Functional; EXIT_CODE=$$?; \
	kill $$(cat $(SERVER_PID_FILE)) 2>/dev/null || true; \
	rm -f $(SERVER_PID_FILE); \
	rm -rf /tmp/fileprovider-functional-tests 2>/dev/null || true; \
	exit $$EXIT_CODE

# All tests
test: test-unit test-functional

# Install dependencies
install:
	composer install

# Clean test artifacts
clean:
	rm -rf tests/_output/*
	rm -rf /tmp/fileprovider-functional-tests
	rm -f /tmp/php-server.log
	rm -f $(SERVER_PID_FILE)

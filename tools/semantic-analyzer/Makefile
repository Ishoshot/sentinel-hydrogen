.PHONY: build build-linux build-darwin clean test

BINARY_NAME=semantic-analyzer
BUILD_DIR=../../bin

build:
	go build -o $(BUILD_DIR)/$(BINARY_NAME) .

build-linux:
	GOOS=linux GOARCH=amd64 go build -o $(BUILD_DIR)/$(BINARY_NAME)-linux-amd64 .

build-darwin:
	GOOS=darwin GOARCH=arm64 go build -o $(BUILD_DIR)/$(BINARY_NAME)-darwin-arm64 .

build-all: build-linux build-darwin

clean:
	rm -f $(BUILD_DIR)/$(BINARY_NAME)*

test:
	go test -v ./...

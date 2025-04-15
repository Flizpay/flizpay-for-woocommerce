# FLIZpay for WooCommerce Tests

This document describes the test suite for the FLIZpay WooCommerce plugin.

## Test Suite Overview

The test suite consists of:

1. **Admin UI Test Suite** - Tests for plugin installation and admin configuration
2. **API Integration Tests** - Tests for API connectivity and transaction processing
3. **Webhook Tests** - Tests for webhook functionality and order management
4. **Express Checkout Tests** - Tests for the express checkout feature

## Running the Tests

### Admin UI Tests

The admin UI tests can be run directly from the WordPress admin dashboard:

1. Navigate to WooCommerce → Settings → Payments
2. Look for the "FLIZpay Test Suite" notice at the top of the page
3. Click the appropriate button to run specific tests or "Run All Tests"

These tests verify:
- Plugin installation and dependencies
- Admin configuration settings
- Gateway registration and availability
- Webhook configuration
- Display option settings
- Express checkout settings

### Command-Line Tests

The API, Webhook, and Express Checkout tests can be run using WP-CLI:

```bash
# API Tests
wp eval-file wp-content/plugins/flizpay-for-woocommerce/tests/api-test.php

# Webhook Tests
wp eval-file wp-content/plugins/flizpay-for-woocommerce/tests/webhook-test.php

# Express Checkout Tests
wp eval-file wp-content/plugins/flizpay-for-woocommerce/tests/express-checkout-test.php
```

## Test Descriptions

### Admin UI Tests

- **Installation Tests**: Verify WooCommerce dependencies, plugin classes, and settings
- **Admin Configuration Tests**: Check API key, webhook setup, and display options
- **Gateway Tests**: Verify gateway registration, methods, and transaction processing
- **Webhook Tests**: Check webhook endpoint registration and configuration
- **Cashback Tests**: Verify cashback functionality and display
- **Express Checkout Tests**: Check express checkout configuration and display

### API Tests

- **Webhook Generation**: Tests the generation of webhook URL and key
- **Cashback Data**: Tests retrieving merchant cashback configuration
- **Transaction Simulation**: Tests order creation and transaction initiation

### Webhook Tests

- **Transaction Complete**: Tests order completion via webhook
- **Shipping Info**: Tests shipping address processing
- **Shipping Method**: Tests shipping method selection
- **Cashback Update**: Tests updating merchant cashback information
- **Connection Test**: Tests webhook connection verification

### Express Checkout Tests

- **Configuration**: Tests express checkout settings
- **Script Registration**: Tests script and style registration
- **Button Display**: Tests button display on configured pages
- **AJAX Endpoint**: Tests AJAX endpoint for express checkout
- **Button Assets**: Tests availability of required assets

## Test Configuration

Some tests are configurable:

- **Webhook Tests**: By default, run in simulation mode (no actual webhook processing). To process webhooks, set `$simulate_only = false` in the `Flizpay_Webhook_Test` class.

## Adding New Tests

To add new tests:

1. For admin UI tests, add test methods to the `Flizpay_Test_Suite` class
2. For API/webhook/express tests, add test methods to the respective test classes
3. Register any new test types in the admin UI by updating the test buttons

## Troubleshooting

If tests fail:

1. Check WooCommerce activation and version
2. Verify FLIZpay plugin settings are properly configured
3. Ensure API key is valid and webhook is connected
4. Check that all required files and classes are present

For webhook tests, make sure the webhook URL is properly registered in WordPress rewrite rules. If not, try flushing permalinks.
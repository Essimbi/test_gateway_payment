        # Implementation Plan: CinetPay Payment Integration

## Overview

Ce plan d'implémentation décompose le module de paiement CinetPay en tâches discrètes et incrémentales. Chaque tâche construit sur les précédentes pour créer un système complet et testé. L'approche privilégie la validation précoce via des tests de propriétés pour détecter les erreurs rapidement.

## Tasks

- [x] 1. Setup project structure and dependencies
  - Install cinetpay/php-sdk package via Composer
  - Install Pest PHP testing framework with faker plugin
  - Create directory structure for payment module
  - Configure environment variables in .env.example
  - _Requirements: 2.1, 2.2, 2.3, 8.1_

- [x] 2. Create database migration and model
  - [x] 2.1 Create transactions table migration
    - Define schema with all required columns (transaction_id, user_id, amount, status, etc.)
    - Add indexes for performance (transaction_id, user_id, status, created_at)
    - Add foreign key constraint to users table
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 2.2 Write property test for transaction uniqueness
    - **Property 1: Transaction Uniqueness**
    - **Validates: Requirements 1.2**

  - [x] 2.3 Create Transaction model with relationships
    - Define fillable attributes and casts
    - Add user() relationship method
    - Add query scopes (pending, accepted, refused)
    - _Requirements: 1.3, 5.1_

  - [x] 2.4 Write property test for user association
    - **Property 2: Transaction User Association**
    - **Validates: Requirements 1.3**

  - [x] 2.5 Write property test for amount persistence
    - **Property 3: Transaction Amount Persistence**
    - **Validates: Requirements 1.4**

- [x] 3. Create PaymentStatus enum
  - [x] 3.1 Define PaymentStatus enum with three cases
    - Implement PENDING, ACCEPTED, REFUSED cases
    - Add canTransitionTo() method for validation
    - Add isTerminal() helper method
    - _Requirements: 5.1, 5.3, 5.4, 5.5, 5.6_

  - [x] 3.2 Write property test for initial status
    - **Property 12: Initial Status is PENDING**
    - **Validates: Requirements 5.2**

  - [x] 3.3 Write property test for ACCEPTED terminal state
    - **Property 13: ACCEPTED is Terminal**
    - **Validates: Requirements 5.5**

  - [x] 3.4 Write property test for REFUSED terminal state
    - **Property 14: REFUSED is Terminal**
    - **Validates: Requirements 5.6**

- [x] 4. Create exception classes
  - Define PaymentException base class
  - Define PaymentConfigurationException for config errors
  - Define CinetPayApiException for API errors
  - Define PaymentValidationException for validation errors
  - Define InvalidStatusTransitionException for status errors
  - _Requirements: 2.5, 10.1, 10.2_

- [x] 5. Implement CinetPayClient
  - [x] 5.1 Create CinetPayClient class with SDK integration
    - Initialize SDK with credentials from config
    - Implement initializePayment() method
    - Implement checkTransactionStatus() method
    - Implement validateSignature() method
    - Add fallback to Laravel HTTP Client if SDK unavailable
    - _Requirements: 8.1, 8.2, 8.3, 4.1, 4.3_

  - [x] 5.2 Write property test for API call transaction ID
    - **Property 11: API Call Uses Correct Transaction ID**
    - **Validates: Requirements 4.2**

  - [x] 5.3 Implement retry logic with exponential backoff
    - Create retryWithBackoff() private method
    - Configure 3 retry attempts with delays (1s, 2s, 4s)
    - _Requirements: 4.5, 10.3_

  - [x] 5.4 Write property test for network timeout retry
    - **Property 27: Network Timeout Retry**
    - **Validates: Requirements 10.3**

  - [x] 5.5 Write property test for SDK exception conversion
    - **Property 23: SDK Exception Conversion**
    - **Validates: Requirements 8.4**

- [x] 6. Checkpoint - Ensure all tests pass
  - Run all tests to verify foundation is solid
  - Ensure database migrations work correctly
  - Ask the user if questions arise

- [x] 7. Implement PaymentService
  - [x] 7.1 Create PaymentService class with core methods
    - Inject CinetPayClient and Transaction model dependencies
    - Implement initializePayment() method
    - Implement getTransaction() method
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 7.2 Write property test for transaction URLs definition
    - **Property 4: Transaction URLs Definition**
    - **Validates: Requirements 1.5**

  - [x] 7.3 Write property test for initiation logging
    - **Property 19: Initiation Logging**
    - **Validates: Requirements 7.1**

  - [x] 7.4 Implement verifyTransactionStatus() method
    - Call CinetPayClient to check status
    - Validate response signature
    - Return PaymentStatus enum
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 7.5 Write property test for API call logging
    - **Property 20: API Call Logging**
    - **Validates: Requirements 7.3**

  - [x] 7.6 Implement updateTransactionStatus() method
    - Validate status transition using PaymentStatus::canTransitionTo()
    - Update transaction in database
    - Log status change
    - _Requirements: 5.3, 5.4, 5.5, 5.6_

  - [x] 7.7 Write property test for status change logging
    - **Property 21: Status Change Logging**
    - **Validates: Requirements 7.4**

  - [x] 7.8 Write property test for failed verification preserves status
    - **Property 10: Failed Verification Preserves Status**
    - **Validates: Requirements 3.8**

  - [x] 7.9 Implement processIPN() method
    - Validate IPN payload
    - Log IPN notification
    - Call verifyTransactionStatus() before updating
    - Update transaction status based on verification
    - _Requirements: 3.1, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8_

  - [x] 7.10 Write property test for IPN verification before update
    - **Property 6: IPN Verification Before Update**
    - **Validates: Requirements 3.4, 4.4**

  - [x] 7.11 Write property test for status update on successful verification
    - **Property 7: Status Update on Successful Verification**
    - **Validates: Requirements 3.5**

  - [x] 7.12 Write property test for status update on failed verification
    - **Property 8: Status Update on Failed Verification**
    - **Validates: Requirements 3.6**

  - [x] 7.13 Write property test for IPN logging
    - **Property 9: IPN Logging**
    - **Validates: Requirements 3.7, 7.2**

  - [x] 7.14 Write property test for error logging
    - **Property 22: Error Logging**
    - **Validates: Requirements 7.5**

  - [x] 7.15 Write property test for credentials not in logs
    - **Property 5: Credentials Not in Logs**
    - **Validates: Requirements 2.4**

- [x] 8. Checkpoint - Ensure all tests pass
  - Run all tests to verify service layer is working
  - Verify logging is functioning correctly
  - Ask the user if questions arise

- [x] 9. Create routes and middleware configuration
  - Define web routes for payment flow (summary, initiate, return, cancel)
  - Define API route for IPN notification (POST)
  - Exclude IPN route from CSRF middleware in VerifyCsrfToken
  - Add rate limiting middleware to payment routes (10/minute)
  - _Requirements: 3.1, 3.2, 6.1_

- [-] 10. Implement CinetPayController
  - [x] 10.1 Create showPaymentSummary() method
    - Validate request data
    - Render payment summary view with amount
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [x] 10.2 Write property test for summary page shows amount
    - **Property 24: Summary Page Shows Amount**
    - **Validates: Requirements 9.2**

  - [x] 10.3 Write property test for summary page shows transaction ID
    - **Property 25: Summary Page Shows Transaction ID**
    - **Validates: Requirements 9.3**

  - [x] 10.4 Create initiatePayment() method
    - Validate request data (amount, user authentication)
    - Call PaymentService::initializePayment()
    - Redirect to CinetPay payment URL
    - Handle errors and display user-friendly messages
    - _Requirements: 1.1, 1.6, 10.1, 10.2_

  - [x] 10.5 Write property test for error response logging
    - **Property 26: Error Response Logging**
    - **Validates: Requirements 10.2**

  - [x] 10.6 Create handleIPN() method
    - Receive POST request from CinetPay
    - Call PaymentService::processIPN()
    - Return 200 OK response to prevent retries
    - Handle malformed notifications gracefully
    - _Requirements: 3.1, 3.2, 10.5_

  - [x] 10.7 Write property test for malformed IPN handling
    - **Property 28: Malformed IPN Handling**
    - **Validates: Requirements 10.5**

  - [x] 10.8 Create handleReturn() method
    - Retrieve transaction by ID
    - Call PaymentService::verifyTransactionStatus()
    - Redirect to success page if ACCEPTED
    - Redirect to failure page if REFUSED
    - Display waiting message if PENDING
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [x] 10.9 Write property test for return triggers verification
    - **Property 15: Return Triggers Verification**
    - **Validates: Requirements 6.1**

  - [x] 10.10 Write property test for success redirect
    - **Property 16: Success Redirect for ACCEPTED**
    - **Validates: Requirements 6.2**

  - [x] 10.11 Write property test for failure redirect
    - **Property 17: Failure Redirect for REFUSED**
    - **Validates: Requirements 6.3**

  - [ ] 10.12 Write property test for transaction details on result pages
    - **Property 18: Transaction Details on Result Pages**
    - **Validates: Requirements 6.5**

  - [x] 10.13 Create cancelPayment() method
    - Retrieve transaction by ID
    - Verify transaction is still PENDING
    - Redirect to home with cancellation message
    - _Requirements: 9.5_

- [x] 11. Create Blade views
  - [x] 11.1 Create payment summary view (resources/views/payment/summary.blade.php)
    - Display amount, transaction ID
    - Add "Confirmer" button to proceed
    - Add "Annuler" button to cancel
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [x] 11.2 Create success view (resources/views/payment/success.blade.php)
    - Display success message
    - Show transaction details (ID, amount)
    - Add link to return to home
    - _Requirements: 6.2, 6.5_

  - [x] 11.3 Create failure view (resources/views/payment/failure.blade.php)
    - Display failure message
    - Show transaction details (ID, amount)
    - Add link to retry or return to home
    - _Requirements: 6.3, 6.5_

  - [x] 11.4 Create pending view (resources/views/payment/pending.blade.php)
    - Display waiting message
    - Show transaction ID
    - Add auto-refresh or manual refresh button
    - _Requirements: 6.4_

- [x] 12. Create configuration file
  - Create config/cinetpay.php with all settings
  - Load credentials from environment variables
  - Define default currency, URLs, timeouts
  - Add validation for required config values
  - _Requirements: 2.1, 2.2, 2.3, 2.5_

- [ ] 13. Checkpoint - Integration testing
  - Run full test suite (unit + property tests)
  - Manually test payment flow in browser
  - Verify IPN endpoint is accessible
  - Check logs are being written correctly
  - Ask the user if questions arise

- [ ] 14. Create Transaction factory for testing
  - Define factory with realistic fake data
  - Support different status states
  - Support custom attributes
  - _Requirements: All (testing support)_

- [ ] 15. Write integration tests
  - [ ] 15.1 Test complete payment flow (happy path)
    - Create transaction → Initialize payment → Receive IPN → Verify status
    - _Requirements: 1.1, 1.6, 3.1, 3.3, 3.5, 6.2_

  - [ ] 15.2 Test payment cancellation flow
    - Create transaction → Cancel → Verify status unchanged
    - _Requirements: 9.5_

  - [ ] 15.3 Test IPN with verification failure
    - Receive IPN → Verification fails → Status unchanged
    - _Requirements: 3.8, 10.5_

  - [ ] 15.4 Test retry logic on network failure
    - Simulate network timeout → Verify 3 retries → Final failure
    - _Requirements: 4.5, 10.3, 10.4_

- [ ] 16. Create documentation
  - [ ] 16.1 Create README.md for payment module
    - Document installation steps
    - Explain environment variable configuration
    - Provide webhook configuration instructions for CinetPay dashboard
    - Include testing instructions
    - Add troubleshooting section
    - _Requirements: All (documentation)_

  - [ ] 16.2 Add inline code documentation
    - Add PHPDoc comments to all public methods
    - Document parameters and return types
    - Add usage examples in comments
    - _Requirements: All (documentation)_

- [ ] 17. Final checkpoint - Complete system verification
  - Run complete test suite with coverage report
  - Verify all 28 correctness properties are tested
  - Check that all requirements are covered
  - Review logs for any warnings or errors
  - Ensure all tests pass
  - Ask the user if questions arise

## Notes

- All tasks are required for a complete, production-ready implementation
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (minimum 100 iterations each)
- Unit tests validate specific examples and edge cases
- The implementation follows Laravel best practices and PSR standards
- All sensitive data (credentials) must be stored in .env and never committed
- Logging is critical for debugging payment issues in production

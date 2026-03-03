# Implementation Plan: Multi-Gateway Payment Support

## Overview

Ce plan d'implémentation décompose l'ajout du support multi-passerelles en tâches discrètes et incrémentales. L'approche privilégie la refactorisation progressive du code CinetPay existant vers une architecture basée sur des interfaces, puis l'ajout de Tranzak comme nouvelle passerelle. Chaque tâche construit sur les précédentes pour maintenir un système fonctionnel à chaque étape.

## Tasks

- [x] 1. Create gateway abstraction layer
  - [x] 1.1 Create PaymentGatewayInterface
    - Define interface with all required methods (getGatewayName, getGatewayType, initializePayment, verifyTransaction, handleCallback, validateCallback)
    - Add comprehensive PHPDoc comments
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [x] 1.2 Create GatewayType enum
    - Define CINETPAY and TRANZAK cases
    - Add getDisplayName(), getDescription(), getLogoPath() helper methods
    - _Requirements: 5.2_

  - [ ]* 1.3 Write property test for gateway type storage
    - **Property 1: Gateway Type Storage**
    - **Validates: Requirements 1.4, 5.1**

- [x] 2. Create exception classes for multi-gateway support
  - Create TranzakApiException class
  - Create UnsupportedGatewayException class
  - _Requirements: 8.1, 7.5_

- [x] 3. Update database schema for gateway support
  - [x] 3.1 Create migration to add gateway_type column
    - Add gateway_type column with default 'cinetpay' for backward compatibility
    - Rename cinetpay_payment_id to gateway_payment_id
    - Add index on gateway_type
    - Set gateway_type to CINETPAY for all existing transactions
    - _Requirements: 5.1, 9.1, 9.2_

  - [x] 3.2 Update Transaction model
    - Add gateway_type to fillable and casts
    - Rename cinetpay_payment_id to gateway_payment_id in fillable
    - Add scopeByGateway() query scope
    - _Requirements: 5.1_

  - [ ]* 3.3 Write property test for gateway type immutability
    - **Property 2: Gateway Type Immutability**
    - **Validates: Requirements 5.4**

- [x] 4. Checkpoint - Ensure database changes work
  - Run migration and verify schema changes
  - Verify existing transactions have gateway_type set to CINETPAY
  - Ensure all tests still pass
  - Ask the user if questions arise

- [x] 5. Refactor CinetPay to implement gateway interface
  - [x] 5.1 Create CinetPayGateway class implementing PaymentGatewayInterface
    - Inject existing CinetPayClient
    - Implement all interface methods
    - Add mapStatus() private method to convert CinetPay statuses
    - _Requirements: 2.6, 9.3, 9.4_

  - [ ]* 5.2 Write property test for CinetPay transaction gateway type
    - **Property 6: CinetPay Transaction Gateway Type**
    - **Validates: Requirements 9.3**

  - [ ]* 5.3 Write property test for CinetPay API exception type
    - **Property 13: CinetPay API Exception Type**
    - **Validates: Requirements 8.3**

- [x] 6. Create Tranzak client and gateway
  - [x] 6.1 Create TranzakClient class
    - Implement constructor with API key and App ID
    - Implement createPayment() method
    - Implement getPaymentStatus() method
    - Add retry logic with exponential backoff
    - _Requirements: 4.1, 4.2, 13.3_

  - [ ]* 6.2 Write property test for Tranzak retry configuration
    - **Property 27: Tranzak Retry Configuration**
    - **Validates: Requirements 13.3**

  - [x] 6.3 Create TranzakGateway class implementing PaymentGatewayInterface
    - Inject TranzakClient
    - Implement all interface methods
    - Add mapStatus() private method to convert Tranzak statuses
    - _Requirements: 2.7, 4.1, 4.3, 4.4_

  - [ ]* 6.4 Write property test for Tranzak transaction gateway type
    - **Property 5: Tranzak Transaction Gateway Type**
    - **Validates: Requirements 4.3**

  - [ ]* 6.5 Write property test for Tranzak API exception type
    - **Property 12: Tranzak API Exception Type**
    - **Validates: Requirements 8.2**

  - [ ]* 6.6 Write property test for Tranzak callback verification
    - **Property 10: Tranzak Callback Verification**
    - **Validates: Requirements 4.5**

- [x] 7. Checkpoint - Ensure gateway implementations work
  - Test CinetPayGateway with existing functionality
  - Test TranzakClient API calls (with mocks)
  - Ensure all tests pass
  - Ask the user if questions arise

- [x] 8. Create GatewayFactory
  - [x] 8.1 Create GatewayFactory class
    - Implement createGateway() method with match expression
    - Implement createCinetPayGateway() private method
    - Implement createTranzakGateway() private method
    - Implement getAvailableGateways() method
    - Handle missing credentials gracefully
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 3.5, 3.6, 3.7_

  - [ ]* 8.2 Write property test for gateway factory credentials injection
    - **Property 11: Gateway Factory Credentials Injection**
    - **Validates: Requirements 7.6**

  - [ ]* 8.3 Write property test for gateway selection validation
    - **Property 3: Gateway Selection Validation**
    - **Validates: Requirements 1.5**

- [x] 9. Update configuration files
  - [x] 9.1 Create config/payment.php configuration file
    - Define gateways array with cinetpay and tranzak configurations
    - Load credentials from environment variables
    - Define default gateway
    - _Requirements: 3.1, 3.2, 3.3_

  - [x] 9.2 Update .env.example with Tranzak credentials
    - Add TRANZAK_API_KEY, TRANZAK_APP_ID, TRANZAK_CURRENCY, TRANZAK_BASE_URL
    - _Requirements: 3.1, 3.2_

  - [ ]* 9.3 Write property test for credentials not in logs
    - **Property 16: Credentials Not in Logs**
    - **Validates: Requirements 3.4**

- [x] 10. Refactor PaymentService for multi-gateway support
  - [x] 10.1 Update PaymentService constructor
    - Inject GatewayFactory instead of CinetPayClient
    - Remove direct dependency on CinetPayClient
    - _Requirements: 7.1_

  - [x] 10.2 Update initializePayment() method
    - Add gatewayType parameter
    - Use GatewayFactory to create gateway instance
    - Store gateway_type in transaction
    - Update logging to include gateway information
    - _Requirements: 1.4, 5.1, 11.1, 11.2_

  - [ ]* 10.3 Write property test for gateway type in payment logs
    - **Property 21: Gateway Type in Payment Logs**
    - **Validates: Requirements 11.1, 11.2, 11.3, 11.4**

  - [x] 10.4 Update processCallback() method
    - Add gatewayType parameter
    - Use GatewayFactory to create gateway instance
    - Update logging to include gateway information
    - _Requirements: 6.4, 6.5, 11.3_

  - [ ]* 10.5 Write property test for callback delegation
    - **Property 8: Callback Delegation to Correct Handler**
    - **Validates: Requirements 6.5**

  - [x] 10.6 Update verifyTransactionStatus() method
    - Use transaction's gateway_type to select correct gateway
    - Update logging to include gateway information
    - _Requirements: 5.3, 11.4_

  - [ ]* 10.7 Write property test for correct gateway client selection
    - **Property 4: Correct Gateway Client Selection**
    - **Validates: Requirements 5.3**

  - [ ]* 10.8 Write property test for status update based on gateway verification
    - **Property 30: Status Update Based on Gateway Verification**
    - **Validates: Requirements 4.6**

  - [x] 10.9 Add getAvailableGateways() method
    - Delegate to GatewayFactory
    - _Requirements: 3.5, 3.6_

- [x] 11. Checkpoint - Ensure service layer works with multiple gateways
  - Test PaymentService with both CinetPay and Tranzak
  - Verify logging includes gateway information
  - Ensure all tests pass
  - Ask the user if questions arise

- [x] 12. Create gateway selection UI
  - [x] 12.1 Create gateway selection view (resources/views/payment/select-gateway.blade.php)
    - Display available gateways with logos, names, and descriptions
    - Show disabled state for unavailable gateways with explanation
    - Add form to submit selected gateway
    - _Requirements: 1.1, 1.2, 1.3, 10.1, 10.2, 10.3, 10.4, 10.5_

  - [ ]* 12.2 Write property test for gateway selection display content
    - **Property 17: Gateway Selection Display Content**
    - **Validates: Requirements 10.1, 10.2, 10.3**

  - [ ]* 12.3 Write property test for unavailable gateway disabled
    - **Property 18: Unavailable Gateway Disabled**
    - **Validates: Requirements 10.4**

  - [ ]* 12.4 Write property test for unavailable gateway explanation
    - **Property 19: Unavailable Gateway Explanation**
    - **Validates: Requirements 10.5**

- [-] 13. Update PaymentController for gateway selection
  - [x] 13.1 Create showGatewaySelection() method
    - Get available gateways from PaymentService
    - Render gateway selection view
    - _Requirements: 1.1_

  - [x] 13.2 Update showPaymentSummary() method
    - Accept gateway_type parameter
    - Validate gateway is supported
    - Pass gateway information to view
    - _Requirements: 1.5, 1.6_

  - [x] 13.3 Update initiatePayment() method
    - Accept gateway_type from request
    - Pass gateway_type to PaymentService
    - Handle UnsupportedGatewayException
    - _Requirements: 1.4, 1.5_

  - [ ]* 13.4 Write property test for gateway error logging
    - **Property 14: Gateway Error Logging**
    - **Validates: Requirements 8.4, 8.6**

  - [ ]* 13.5 Write property test for user-friendly error messages
    - **Property 15: User-Friendly Error Messages**
    - **Validates: Requirements 8.5**

  - [x] 13.4 Update handleReturn() method
    - Display gateway name in result views
    - _Requirements: 5.5_

  - [ ]* 13.6 Write property test for transaction display shows gateway
    - **Property 20: Transaction Display Shows Gateway**
    - **Validates: Requirements 5.5**

- [x] 14. Create gateway-specific callback routes and handlers
  - [x] 14.1 Add callback routes for each gateway
    - Define POST /api/cinetpay/callback route
    - Define POST /api/tranzak/callback route
    - Exclude both from CSRF middleware
    - _Requirements: 6.1, 6.2, 6.3, 6.6_

  - [ ]* 14.2 Write property test for CSRF exclusion
    - **Property 9: CSRF Exclusion for Callbacks**
    - **Validates: Requirements 6.6**

  - [x] 14.3 Create handleCinetPayCallback() method in controller
    - Extract payload from request
    - Call PaymentService::processCallback() with CINETPAY type
    - Return 200 OK response
    - _Requirements: 6.4, 6.5_

  - [x] 14.4 Create handleTranzakCallback() method in controller
    - Extract payload from request
    - Call PaymentService::processCallback() with TRANZAK type
    - Return 200 OK response
    - Handle malformed callbacks gracefully
    - _Requirements: 6.4, 6.5, 12.4_

  - [ ]* 14.5 Write property test for callback gateway identification
    - **Property 7: Callback Gateway Identification**
    - **Validates: Requirements 6.4**

  - [ ]* 14.6 Write property test for malformed callback rejection
    - **Property 25: Malformed Callback Rejection**
    - **Validates: Requirements 12.4**

- [x] 15. Implement Tranzak callback validation
  - [x] 15.1 Add validation logic in TranzakGateway
    - Validate payload structure
    - Verify transaction exists
    - Validate API response format
    - _Requirements: 12.1, 12.2, 12.3_

  - [ ]* 15.2 Write property test for Tranzak callback payload validation
    - **Property 22: Tranzak Callback Payload Validation**
    - **Validates: Requirements 12.1**

  - [ ]* 15.3 Write property test for Tranzak callback transaction existence
    - **Property 23: Tranzak Callback Transaction Existence**
    - **Validates: Requirements 12.2**

  - [ ]* 15.4 Write property test for Tranzak API response validation
    - **Property 24: Tranzak API Response Validation**
    - **Validates: Requirements 12.3**

  - [ ]* 15.5 Write property test for validation failure logging
    - **Property 26: Validation Failure Logging**
    - **Validates: Requirements 12.5**

- [x] 16. Checkpoint - Test complete payment flows
  - Test complete CinetPay payment flow (selection → payment → callback → verification)
  - Test complete Tranzak payment flow (selection → payment → callback → verification)
  - Verify backward compatibility with existing CinetPay transactions
  - Ensure all tests pass
  - Ask the user if questions arise

- [x] 17. Update views to show gateway information
  - [x] 17.1 Update payment summary view
    - Display selected gateway name and logo
    - _Requirements: 5.5_

  - [x] 17.2 Update success view
    - Display gateway used for payment
    - _Requirements: 5.5_

  - [x] 17.3 Update failure view
    - Display gateway used for payment
    - _Requirements: 5.5_

  - [x] 17.4 Update pending view
    - Display gateway being used
    - _Requirements: 5.5_

- [x] 18. Add gateway-specific retry configuration
  - [x] 18.1 Add retry configuration to config/payment.php
    - Define retry_attempts and timeout per gateway
    - _Requirements: 13.1, 13.2_

  - [x] 18.2 Update CinetPayClient to use gateway-specific retry config
    - Read retry config from gateway configuration
    - _Requirements: 13.4_

  - [ ]* 18.3 Write property test for CinetPay retry configuration
    - **Property 28: CinetPay Retry Configuration**
    - **Validates: Requirements 13.4**

  - [x] 18.4 Update TranzakClient to use gateway-specific retry config
    - Read retry config from gateway configuration
    - _Requirements: 13.3_

  - [ ]* 18.5 Write property test for retry logging with gateway
    - **Property 29: Retry Logging with Gateway**
    - **Validates: Requirements 13.5**

- [x] 19. Create Transaction factory updates
  - Update TransactionFactory to support gateway_type
  - Add states for different gateway types
  - _Requirements: 14.1_

- [ ] 20. Write integration tests
  - [ ]* 20.1 Test CinetPay payment flow with gateway selection
    - Select CinetPay → Initialize → Callback → Verify
    - _Requirements: 1.1, 1.4, 4.1, 6.5, 9.3_

  - [ ]* 20.2 Test Tranzak payment flow with gateway selection
    - Select Tranzak → Initialize → Callback → Verify
    - _Requirements: 1.1, 1.4, 4.1, 4.3, 4.5, 6.5_

  - [ ]* 20.3 Test gateway factory with missing credentials
    - Verify unavailable gateways are excluded from selection
    - _Requirements: 3.5, 3.6, 10.4_

  - [ ]* 20.4 Test backward compatibility
    - Verify existing CinetPay transactions still work
    - Verify existing callbacks are routed correctly
    - _Requirements: 9.1, 9.2, 9.3, 9.5_

  - [ ]* 20.5 Test unsupported gateway handling
    - Attempt to use invalid gateway type
    - Verify appropriate exception is thrown
    - _Requirements: 1.5, 7.5_

- [ ] 21. Final checkpoint - Complete system verification
  - Run complete test suite with coverage report
  - Verify all 30 correctness properties are tested
  - Check that all requirements are covered
  - Test both gateways in development environment
  - Review logs for proper gateway identification
  - Ensure backward compatibility with existing CinetPay transactions
  - Ask the user if questions arise

- [ ] 22. Documentation
  - [ ]* 22.1 Update README with multi-gateway setup instructions
    - Document Tranzak configuration
    - Explain gateway selection process
    - Add troubleshooting for multiple gateways
    - _Requirements: All (documentation)_

  - [ ]* 22.2 Add inline documentation for new classes
    - Document PaymentGatewayInterface
    - Document GatewayFactory
    - Document TranzakClient and TranzakGateway
    - _Requirements: All (documentation)_

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties (minimum 100 iterations each)
- The implementation maintains backward compatibility with existing CinetPay transactions
- Gateway selection happens before payment initialization
- Each gateway has its own callback endpoint for clear separation
- The architecture is extensible - adding new gateways requires minimal changes
- All sensitive credentials are stored in environment variables
- Logging includes gateway information for easier debugging

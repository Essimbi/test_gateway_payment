# Requirements Document

## Introduction

Ce document définit les exigences pour l'intégration d'un module de paiement CinetPay dans une application Laravel existante. Le système permettra aux utilisateurs d'effectuer des paiements via mobile money (Orange Money, MTN, Moov, etc.) en utilisant la plateforme CinetPay comme passerelle de paiement.

## Glossary

- **Payment_System**: Le module Laravel responsable de la gestion des transactions de paiement
- **CinetPay_Gateway**: La plateforme externe CinetPay qui traite les paiements mobile money
- **Transaction**: Un enregistrement de tentative de paiement avec un identifiant unique
- **IPN** (Instant Payment Notification): Notification automatique envoyée par CinetPay au système après traitement d'un paiement
- **User**: Un utilisateur authentifié de l'application Laravel
- **Payment_Status**: L'état d'une transaction (PENDING, ACCEPTED, REFUSED)
- **SDK**: Le package PHP officiel cinetpay/php-sdk pour communiquer avec l'API CinetPay
- **Webhook**: Point d'entrée HTTP pour recevoir les notifications IPN de CinetPay

## Requirements

### Requirement 1: Initialisation de Transaction

**User Story:** En tant qu'utilisateur, je veux initier un paiement depuis l'interface web, afin de pouvoir payer via mobile money.

#### Acceptance Criteria

1. WHEN a user clicks the "Payer maintenant" button, THE Payment_System SHALL create a new Transaction record with status PENDING
2. WHEN creating a Transaction, THE Payment_System SHALL generate a unique transaction_id
3. WHEN creating a Transaction, THE Payment_System SHALL associate it with the authenticated User
4. WHEN creating a Transaction, THE Payment_System SHALL store the payment amount
5. WHEN creating a Transaction, THE Payment_System SHALL define return_url and notify_url endpoints
6. WHEN a Transaction is created, THE Payment_System SHALL redirect the User to the CinetPay_Gateway payment page

### Requirement 2: Configuration et Sécurité

**User Story:** En tant qu'administrateur système, je veux gérer les credentials CinetPay de manière sécurisée, afin de protéger les informations sensibles.

#### Acceptance Criteria

1. THE Payment_System SHALL read API_KEY from environment variables
2. THE Payment_System SHALL read SITE_ID from environment variables
3. THE Payment_System SHALL read SECRET_KEY from environment variables
4. THE Payment_System SHALL NOT expose credentials in code source or logs
5. WHERE credentials are missing, THE Payment_System SHALL throw a configuration exception

### Requirement 3: Traitement des Notifications IPN

**User Story:** En tant que système, je veux recevoir et traiter les notifications de CinetPay, afin de mettre à jour le statut des transactions de manière fiable.

#### Acceptance Criteria

1. WHEN CinetPay_Gateway sends an IPN notification, THE Payment_System SHALL receive it via a POST endpoint
2. THE Payment_System SHALL exclude the IPN endpoint from CSRF middleware verification
3. WHEN receiving an IPN notification, THE Payment_System SHALL verify the transaction status by calling CinetPay_Gateway API
4. THE Payment_System SHALL NOT update Transaction status based solely on the IPN POST data
5. WHEN verification confirms payment success, THE Payment_System SHALL update Transaction status to ACCEPTED
6. WHEN verification confirms payment failure, THE Payment_System SHALL update Transaction status to REFUSED
7. WHEN an IPN is received, THE Payment_System SHALL log the notification details
8. IF verification with CinetPay_Gateway fails, THEN THE Payment_System SHALL log the error and maintain current Transaction status

### Requirement 4: Vérification de Statut de Transaction

**User Story:** En tant que système, je veux vérifier le statut réel d'une transaction auprès de CinetPay, afin de garantir l'intégrité des données.

#### Acceptance Criteria

1. WHEN verifying a Transaction, THE Payment_System SHALL call the CinetPay_Gateway status check API
2. WHEN calling CinetPay_Gateway API, THE Payment_System SHALL use the stored transaction_id
3. WHEN CinetPay_Gateway returns a status, THE Payment_System SHALL validate the response signature
4. THE Payment_System SHALL update local Transaction status only after successful API verification
5. IF CinetPay_Gateway API is unreachable, THEN THE Payment_System SHALL retry verification with exponential backoff

### Requirement 5: Gestion des Statuts de Transaction

**User Story:** En tant que développeur, je veux utiliser des statuts de transaction standardisés, afin de maintenir la cohérence du système.

#### Acceptance Criteria

1. THE Payment_System SHALL define Payment_Status as an enumeration with values: PENDING, ACCEPTED, REFUSED
2. WHEN a Transaction is created, THE Payment_System SHALL set initial status to PENDING
3. THE Payment_System SHALL allow status transitions from PENDING to ACCEPTED
4. THE Payment_System SHALL allow status transitions from PENDING to REFUSED
5. THE Payment_System SHALL NOT allow status transitions from ACCEPTED to any other status
6. THE Payment_System SHALL NOT allow status transitions from REFUSED to any other status

### Requirement 6: Expérience Utilisateur - Pages de Redirection

**User Story:** En tant qu'utilisateur, je veux être informé du résultat de mon paiement, afin de connaître si ma transaction a réussi ou échoué.

#### Acceptance Criteria

1. WHEN a User returns from CinetPay_Gateway, THE Payment_System SHALL verify the Transaction status
2. WHEN Transaction status is ACCEPTED, THE Payment_System SHALL redirect User to a success page
3. WHEN Transaction status is REFUSED, THE Payment_System SHALL redirect User to a failure page
4. WHEN Transaction status is PENDING, THE Payment_System SHALL display a waiting message
5. THE Payment_System SHALL display transaction details on success and failure pages

### Requirement 7: Journalisation et Audit

**User Story:** En tant qu'administrateur système, je veux avoir des logs détaillés des transactions, afin de faciliter le debugging et l'audit.

#### Acceptance Criteria

1. WHEN a Transaction is initiated, THE Payment_System SHALL log the transaction_id and amount
2. WHEN an IPN notification is received, THE Payment_System SHALL log the complete payload
3. WHEN CinetPay_Gateway API is called, THE Payment_System SHALL log the request and response
4. WHEN a Transaction status changes, THE Payment_System SHALL log the old and new status
5. WHEN an error occurs, THE Payment_System SHALL log the error message and stack trace
6. THE Payment_System SHALL write all logs to storage/logs/laravel.log

### Requirement 8: Intégration SDK CinetPay

**User Story:** En tant que développeur, je veux utiliser le SDK officiel CinetPay, afin de simplifier l'intégration avec l'API.

#### Acceptance Criteria

1. THE Payment_System SHALL use the cinetpay/php-sdk package for API communication
2. WHERE cinetpay/php-sdk is not available, THE Payment_System SHALL use Laravel HTTP Client as fallback
3. WHEN initializing SDK, THE Payment_System SHALL configure it with credentials from environment variables
4. THE Payment_System SHALL handle SDK exceptions and convert them to application-specific exceptions

### Requirement 9: Récapitulatif Avant Paiement

**User Story:** En tant qu'utilisateur, je veux voir un récapitulatif de mon paiement avant de procéder, afin de vérifier les informations.

#### Acceptance Criteria

1. WHEN a User initiates payment, THE Payment_System SHALL display a summary page
2. THE Payment_System SHALL display the payment amount on the summary page
3. THE Payment_System SHALL display the transaction_id on the summary page
4. THE Payment_System SHALL provide a confirmation button to proceed to CinetPay_Gateway
5. THE Payment_System SHALL provide a cancel button to abort the transaction

### Requirement 10: Gestion des Erreurs de Communication

**User Story:** En tant que système, je veux gérer les erreurs de communication avec CinetPay, afin d'assurer la résilience du système.

#### Acceptance Criteria

1. IF CinetPay_Gateway is unreachable during initialization, THEN THE Payment_System SHALL display an error message to User
2. IF CinetPay_Gateway returns an error response, THEN THE Payment_System SHALL log the error and display a user-friendly message
3. WHEN a network timeout occurs, THE Payment_System SHALL retry the request up to 3 times
4. IF all retry attempts fail, THEN THE Payment_System SHALL mark the Transaction as PENDING and notify administrators
5. THE Payment_System SHALL handle malformed IPN notifications gracefully without crashing

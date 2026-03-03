# Requirements Document

## Introduction

Ce document définit les exigences pour l'extension du module de paiement existant afin de supporter plusieurs passerelles de paiement. Le système permettra aux utilisateurs de choisir entre CinetPay et Tranzak comme passerelle de paiement, tout en maintenant une architecture cohérente et extensible pour l'ajout futur d'autres passerelles.

## Glossary

- **Payment_System**: Le module Laravel responsable de la gestion des transactions de paiement multi-passerelles
- **Payment_Gateway**: Une plateforme externe qui traite les paiements (CinetPay, Tranzak, etc.)
- **Gateway_Selector**: Le composant qui permet à l'utilisateur de choisir sa passerelle de paiement
- **Gateway_Interface**: L'interface commune que toutes les passerelles doivent implémenter
- **Tranzak_Gateway**: La nouvelle passerelle de paiement Tranzak
- **CinetPay_Gateway**: La passerelle de paiement CinetPay existante
- **Transaction**: Un enregistrement de tentative de paiement avec un identifiant unique et une référence à la passerelle utilisée
- **Gateway_Type**: Un enum définissant les passerelles disponibles (CINETPAY, TRANZAK)
- **User**: Un utilisateur authentifié de l'application Laravel

## Requirements

### Requirement 1: Sélection de Passerelle de Paiement

**User Story:** En tant qu'utilisateur, je veux choisir ma passerelle de paiement préférée, afin de pouvoir utiliser le service qui me convient le mieux.

#### Acceptance Criteria

1. WHEN a user initiates a payment, THE Payment_System SHALL display a gateway selection interface
2. THE Payment_System SHALL display CinetPay as a payment option with its logo and description
3. THE Payment_System SHALL display Tranzak as a payment option with its logo and description
4. WHEN a user selects a gateway, THE Payment_System SHALL store the selected gateway with the transaction
5. THE Payment_System SHALL validate that the selected gateway is supported before proceeding
6. IF no gateway is selected, THEN THE Payment_System SHALL prompt the user to select one

### Requirement 2: Interface Commune de Passerelle

**User Story:** En tant que développeur, je veux une interface commune pour toutes les passerelles, afin de faciliter l'ajout de nouvelles passerelles à l'avenir.

#### Acceptance Criteria

1. THE Payment_System SHALL define a Gateway_Interface with standard methods
2. THE Gateway_Interface SHALL include an initializePayment() method
3. THE Gateway_Interface SHALL include a verifyTransaction() method
4. THE Gateway_Interface SHALL include a handleCallback() method
5. THE Gateway_Interface SHALL include a getGatewayName() method
6. THE CinetPay_Gateway SHALL implement the Gateway_Interface
7. THE Tranzak_Gateway SHALL implement the Gateway_Interface

### Requirement 3: Configuration Multi-Passerelles

**User Story:** En tant qu'administrateur système, je veux configurer plusieurs passerelles de paiement, afin de supporter différents services de paiement.

#### Acceptance Criteria

1. THE Payment_System SHALL read Tranzak API_KEY from environment variables
2. THE Payment_System SHALL read Tranzak APP_ID from environment variables
3. THE Payment_System SHALL store gateway-specific configurations separately
4. THE Payment_System SHALL NOT expose any gateway credentials in code source or logs
5. WHERE Tranzak credentials are missing, THE Payment_System SHALL disable Tranzak option
6. WHERE CinetPay credentials are missing, THE Payment_System SHALL disable CinetPay option
7. WHERE all gateway credentials are missing, THEN THE Payment_System SHALL throw a configuration exception

### Requirement 4: Intégration Tranzak

**User Story:** En tant qu'utilisateur, je veux effectuer des paiements via Tranzak, afin d'avoir une alternative à CinetPay.

#### Acceptance Criteria

1. WHEN a user selects Tranzak, THE Payment_System SHALL initialize payment using Tranzak API
2. WHEN initializing Tranzak payment, THE Payment_System SHALL use the configured API_KEY and APP_ID
3. WHEN initializing Tranzak payment, THE Payment_System SHALL create a transaction with gateway_type set to TRANZAK
4. WHEN Tranzak payment is initialized, THE Payment_System SHALL redirect user to Tranzak payment page
5. WHEN Tranzak sends a callback, THE Payment_System SHALL verify the transaction status via Tranzak API
6. THE Payment_System SHALL update transaction status based on Tranzak verification response

### Requirement 5: Stockage du Type de Passerelle

**User Story:** En tant que système, je veux enregistrer quelle passerelle a été utilisée pour chaque transaction, afin de pouvoir vérifier le statut auprès de la bonne passerelle.

#### Acceptance Criteria

1. WHEN creating a Transaction, THE Payment_System SHALL store the gateway_type field
2. THE Payment_System SHALL define gateway_type as an enum with values CINETPAY and TRANZAK
3. WHEN verifying a transaction, THE Payment_System SHALL use the gateway_type to select the correct gateway client
4. THE Payment_System SHALL NOT allow changing the gateway_type after transaction creation
5. WHEN displaying transaction details, THE Payment_System SHALL show which gateway was used

### Requirement 6: Routage des Callbacks par Passerelle

**User Story:** En tant que système, je veux router les callbacks vers le bon gestionnaire de passerelle, afin de traiter correctement les notifications de chaque service.

#### Acceptance Criteria

1. THE Payment_System SHALL provide separate callback endpoints for each gateway
2. THE Payment_System SHALL define a callback endpoint for CinetPay at /api/cinetpay/callback
3. THE Payment_System SHALL define a callback endpoint for Tranzak at /api/tranzak/callback
4. WHEN receiving a callback, THE Payment_System SHALL identify the gateway from the endpoint
5. WHEN receiving a callback, THE Payment_System SHALL delegate processing to the appropriate gateway handler
6. THE Payment_System SHALL exclude all gateway callback endpoints from CSRF middleware

### Requirement 7: Factory Pattern pour Création de Clients

**User Story:** En tant que développeur, je veux un factory pour créer les clients de passerelle, afin de centraliser la logique de création et configuration.

#### Acceptance Criteria

1. THE Payment_System SHALL define a Gateway_Factory class
2. WHEN creating a gateway client, THE Gateway_Factory SHALL accept a gateway_type parameter
3. WHEN gateway_type is CINETPAY, THE Gateway_Factory SHALL return a configured CinetPay_Gateway instance
4. WHEN gateway_type is TRANZAK, THE Gateway_Factory SHALL return a configured Tranzak_Gateway instance
5. WHERE gateway_type is unsupported, THEN THE Gateway_Factory SHALL throw an exception
6. THE Gateway_Factory SHALL inject appropriate credentials for each gateway type

### Requirement 8: Gestion des Erreurs Spécifiques aux Passerelles

**User Story:** En tant que système, je veux gérer les erreurs spécifiques à chaque passerelle, afin de fournir des messages d'erreur appropriés aux utilisateurs.

#### Acceptance Criteria

1. THE Payment_System SHALL define a TranzakApiException for Tranzak-specific errors
2. WHEN a Tranzak API call fails, THE Payment_System SHALL throw a TranzakApiException
3. WHEN a CinetPay API call fails, THE Payment_System SHALL throw a CinetPayApiException
4. THE Payment_System SHALL log gateway-specific error details
5. THE Payment_System SHALL display user-friendly error messages regardless of gateway
6. THE Payment_System SHALL include gateway name in error logs for debugging

### Requirement 9: Compatibilité Ascendante

**User Story:** En tant que développeur, je veux maintenir la compatibilité avec les transactions CinetPay existantes, afin de ne pas perturber les paiements en cours.

#### Acceptance Criteria

1. WHEN migrating the database, THE Payment_System SHALL add gateway_type column with default value CINETPAY
2. THE Payment_System SHALL set gateway_type to CINETPAY for all existing transactions
3. THE Payment_System SHALL continue to traiter les callbacks CinetPay existants
4. THE Payment_System SHALL maintain all existing CinetPay functionality
5. THE Payment_System SHALL NOT break existing CinetPay integrations

### Requirement 10: Interface Utilisateur de Sélection

**User Story:** En tant qu'utilisateur, je veux une interface claire pour choisir ma passerelle, afin de comprendre facilement mes options de paiement.

#### Acceptance Criteria

1. WHEN displaying gateway selection, THE Payment_System SHALL show gateway logos
2. WHEN displaying gateway selection, THE Payment_System SHALL show gateway names
3. WHEN displaying gateway selection, THE Payment_System SHALL show brief descriptions
4. WHEN a gateway is unavailable, THE Payment_System SHALL disable its selection option
5. WHEN a gateway is unavailable, THE Payment_System SHALL display a message explaining why
6. THE Payment_System SHALL highlight the selected gateway visually

### Requirement 11: Journalisation Multi-Passerelles

**User Story:** En tant qu'administrateur système, je veux des logs distinguant les passerelles, afin de faciliter le debugging et l'audit.

#### Acceptance Criteria

1. WHEN logging payment operations, THE Payment_System SHALL include the gateway_type in log entries
2. WHEN a transaction is initiated, THE Payment_System SHALL log which gateway was selected
3. WHEN a callback is received, THE Payment_System SHALL log which gateway sent it
4. WHEN an API call is made, THE Payment_System SHALL log which gateway API was called
5. THE Payment_System SHALL use separate log channels for each gateway when appropriate

### Requirement 12: Validation des Données Tranzak

**User Story:** En tant que système, je veux valider les réponses de Tranzak, afin de garantir l'intégrité des données de paiement.

#### Acceptance Criteria

1. WHEN receiving a Tranzak callback, THE Payment_System SHALL validate the payload structure
2. WHEN receiving a Tranzak callback, THE Payment_System SHALL verify the transaction exists
3. WHEN verifying with Tranzak API, THE Payment_System SHALL validate the response format
4. THE Payment_System SHALL reject malformed Tranzak callbacks
5. THE Payment_System SHALL log validation failures with details

### Requirement 13: Retry et Timeout par Passerelle

**User Story:** En tant que système, je veux configurer les retries et timeouts par passerelle, afin d'optimiser la fiabilité pour chaque service.

#### Acceptance Criteria

1. THE Payment_System SHALL allow configuring retry attempts per gateway
2. THE Payment_System SHALL allow configuring timeout duration per gateway
3. WHEN a Tranzak API call times out, THE Payment_System SHALL retry according to Tranzak configuration
4. WHEN a CinetPay API call times out, THE Payment_System SHALL retry according to CinetPay configuration
5. THE Payment_System SHALL log each retry attempt with gateway information

### Requirement 14: Tests Multi-Passerelles

**User Story:** En tant que développeur, je veux tester chaque passerelle indépendamment, afin de garantir que chacune fonctionne correctement.

#### Acceptance Criteria

1. THE Payment_System SHALL provide test fixtures for each gateway
2. THE Payment_System SHALL allow mocking gateway responses in tests
3. THE Payment_System SHALL test gateway selection logic
4. THE Payment_System SHALL test gateway factory creation
5. THE Payment_System SHALL test callback routing for each gateway

# Payment Test — Documentation technique

Application Laravel de gestion des paiements multi-passerelles (CinetPay et Tranzak). Elle permet d’initier des paiements via Mobile Money (Orange, MTN, Moov) et cartes bancaires en Afrique.

## Table des matières

- [Stack technique](#stack-technique)
- [Architecture](#architecture)
- [Structure du projet](#structure-du-projet)
- [Module de paiement](#module-de-paiement)
- [Flux de paiement](#flux-de-paiement)
- [Configuration](#configuration)
- [Routes et API](#routes-et-api)
- [Modèle de données](#modèle-de-données)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Tests](#tests)
- [Installation](#installation)

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Framework | Laravel 12.x |
| PHP | ^8.2 |
| Base de données | SQLite / MySQL (configurable) |
| SDK paiement | cinetpay/cinetpay-php ^1.9 |
| Tests | Pest 3.x, PHPUnit 11.x |

---

## Architecture

L’application suit une architecture en couches avec le **pattern Strategy** pour les passerelles de paiement.

```
┌─────────────────────────────────────────────────────────────────┐
│                    Controllers (CinetPayController)              │
│  showGatewaySelection | initiatePayment | handleReturn | ...     │
└─────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                      PaymentService                              │
│  initializePayment | verifyTransactionStatus | processCallback   │
└─────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                      GatewayFactory                              │
│              createGateway(GatewayType) → PaymentGatewayInterface│
└─────────────────────────────────────────────────────────────────┘
                                  │
              ┌───────────────────┴───────────────────┐
              ▼                                       ▼
┌─────────────────────────┐             ┌─────────────────────────┐
│   CinetPayGateway       │             │    TranzakGateway       │
│   (PaymentGatewayInterface)           │  (PaymentGatewayInterface)
└───────────┬─────────────┘             └───────────┬─────────────┘
            │                                       │
            ▼                                       ▼
┌─────────────────────────┐             ┌─────────────────────────┐
│   CinetPayClient        │             │    TranzakClient        │
│   (SDK CinetPay)        │             │  (HTTP API Tranzak)     │
└─────────────────────────┘             └─────────────────────────┘
```

---

## Structure du projet

```
app/
├── GatewayType.php                 # Enum des types de passerelle
├── PaymentStatus.php               # Enum des statuts de paiement
├── Exceptions/Payment/             # Exceptions métier
│   ├── CinetPayApiException.php
│   ├── TranzakApiException.php
│   ├── PaymentConfigurationException.php
│   ├── PaymentException.php
│   ├── PaymentValidationException.php
│   ├── InvalidStatusTransitionException.php
│   └── UnsupportedGatewayException.php
├── Http/Controllers/Payment/
│   └── CinetPayController.php      # Contrôleur unifié paiement
├── Models/
│   ├── Transaction.php
│   └── User.php
└── Services/Payment/
    ├── PaymentGatewayInterface.php # Contrat commun des passerelles
    ├── GatewayFactory.php          # Factory des passerelles
    ├── PaymentService.php          # Logique métier paiement
    ├── CinetPayClient.php          # Client CinetPay (SDK)
    ├── CinetPayGateway.php         # Implémentation CinetPay
    ├── TranzakClient.php           # Client HTTP Tranzak
    └── TranzakGateway.php          # Implémentation Tranzak

config/
├── payment.php                     # Configuration multi-passerelles
└── cinetpay.php                    # Configuration CinetPay

routes/
├── web.php                         # Routes paiement (sélection, init, return)
└── api.php                         # Callbacks IPN (POST)
```

---

## Module de paiement

### Interface `PaymentGatewayInterface`

Toutes les passerelles implémentent ce contrat :

| Méthode | Rôle |
|---------|------|
| `getGatewayName()` | Nom affiché (ex. "CinetPay", "Tranzak") |
| `getGatewayType()` | Valeur de l’enum `GatewayType` |
| `initializePayment($data)` | Création du paiement, retour `payment_url` + `payment_id` |
| `verifyTransaction($transactionId)` | Vérification du statut côté passerelle |
| `handleCallback($payload)` | Traitement du webhook / IPN |
| `validateCallback($payload)` | Vérification de l’authenticité du callback |

### Passerelles supportées

#### CinetPay
- Mobile Money : Orange, MTN, Moov
- Devise par défaut : XOF
- Validation par signature HMAC

#### Tranzak
- Mobile Money + cartes bancaires
- Devise par défaut : XAF
- API REST (`/xp021/v1/request/create`, `/request/details`)
- Webhooks TPN ( Transaction Payment Notification ) avec payload `resource`

### Statuts de paiement

| `PaymentStatus` | Description |
|-----------------|-------------|
| `PENDING`       | En attente |
| `ACCEPTED`      | Accepté |
| `REFUSED`       | Refusé / annulé / échoué |

Transitions autorisées : `PENDING` → `ACCEPTED` ou `REFUSED`. Les statuts `ACCEPTED` et `REFUSED` sont terminaux.

---

## Flux de paiement

```
1. Sélection de la passerelle
   GET /payment/select-gateway?amount=5000
   → Liste des passerelles configurées

2. Récapitulatif
   GET /payment/summary (amount, gateway_type)
   → Page de confirmation

3. Initiation
   POST /payment/initiate (amount, gateway_type, metadata)
   → Création Transaction (PENDING)
   → Appel API passerelle
   → Redirection vers payment_url

4. Paiement côté passerelle
   L’utilisateur paie sur le portail CinetPay ou Tranzak.

5. Callback (IPN / webhook)
   POST /api/{cinetpay|tranzak}/callback
   → Validation du payload
   → Vérification via API passerelle
   → Mise à jour statut Transaction

6. Retour utilisateur
   GET /payment/return/{transactionId}
   → Vérification du statut
   → Affichage success / failure / pending
```

---

## Configuration

### Variables d’environnement (`.env`)

```env
# Passerelle par défaut
PAYMENT_DEFAULT_GATEWAY=cinetpay

# CinetPay
CINETPAY_API_KEY=
CINETPAY_SITE_ID=
CINETPAY_SECRET_KEY=
CINETPAY_CURRENCY=XOF
CINETPAY_NOTIFY_URL="${APP_URL}/api/cinetpay/callback"
CINETPAY_RETURN_URL="${APP_URL}/payment/return"
CINETPAY_TIMEOUT=30
CINETPAY_RETRY_ATTEMPTS=3

# Tranzak
TRANZAK_API_KEY=
TRANZAK_APP_ID=
TRANZAK_CURRENCY=XAF
TRANZAK_BASE_URL=https://dsapi.tranzak.me
TRANZAK_NOTIFY_URL="${APP_URL}/api/tranzak/callback"
TRANZAK_RETURN_URL="${APP_URL}/payment/return"
TRANZAK_TIMEOUT=30
TRANZAK_RETRY_ATTEMPTS=3
```

### Fichier `config/payment.php`

- Configuration centralisée des passerelles
- Backoff exponentiel pour les retries : `[1, 2, 4]` secondes
- URLs de callback et de retour par passerelle

---

## Routes et API

### Routes web (authentification requise pour initier)

| Méthode | URI | Action |
|---------|-----|--------|
| GET | `/payment/select-gateway` | Choix de la passerelle |
| GET | `/payment/summary` | Récapitulatif avant paiement |
| POST | `/payment/initiate` | Initiation et redirection vers la passerelle |
| GET | `/payment/return/{transactionId}` | Retour après paiement |
| GET | `/payment/cancel/{transactionId}` | Annulation |

### Endpoints API (callbacks)

| Méthode | URI | Rôle |
|---------|-----|------|
| POST | `/api/cinetpay/callback` | IPN CinetPay |
| POST | `/api/tranzak/callback` | Webhook Tranzak (TPN) |
| POST | `/api/cinetpay/ipn` | Ancien endpoint IPN (compatibilité) |

---

## Modèle de données

### Table `transactions`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint | Clé primaire |
| `transaction_id` | string (unique) | Identifiant interne (ex. `TXN_uuid`) |
| `user_id` | bigint | Utilisateur |
| `amount` | decimal(10,2) | Montant |
| `currency` | string(3) | XOF, XAF, etc. |
| `status` | enum | `pending`, `accepted`, `refused` |
| `gateway_type` | string | `cinetpay` ou `tranzak` |
| `gateway_payment_id` | string (nullable) | ID côté passerelle |
| `return_url` | text | URL de retour |
| `notify_url` | text | URL de callback |
| `metadata` | json (nullable) | Métadonnées (description, payment_url, etc.) |
| `verified_at` | timestamp (nullable) | Date de vérification du statut |

Index : `transaction_id`, `user_id`, `status`, `gateway_type`, `created_at`.

---

## Gestion des erreurs

| Exception | Cas d’usage |
|-----------|-------------|
| `PaymentConfigurationException` | Credentials manquants pour une passerelle |
| `PaymentException` | Erreur générique paiement |
| `PaymentValidationException` | Payload callback invalide |
| `TranzakApiException` | Erreur API Tranzak |
| `CinetPayApiException` | Erreur SDK CinetPay |
| `InvalidStatusTransitionException` | Transition de statut interdite |
| `UnsupportedGatewayException` | Passerelle non supportée |

Les callbacks renvoient toujours **200 OK** pour éviter les relances côté passerelle, même en cas d’erreur de traitement.

---

## Tests

- **Framework** : Pest 3.x
- **Structure** : `tests/Unit/`, `tests/Feature/`

### Tests unitaires
- `TranzakClientTest` : client API, retry, validation des réponses
- `TranzakGatewayTest` : initialisation, vérification, callbacks, mapping des statuts
- `CinetPayGatewayTest` : intégration avec le SDK CinetPay
- `GatewayFactoryTest` : création des passerelles, disponibilité

### Tests feature
- `CompletePaymentFlowTest` : flux complet CinetPay et Tranzak
- `MultiGatewayPaymentServiceTest` : `PaymentService` avec les deux passerelles

### Exécution

```bash
composer test
# ou
php artisan test
```

---

## Installation

### Prérequis

- PHP ^8.2
- Composer
- Extensions : BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

### Étapes

```bash
# Cloner le dépôt
git clone <repo-url>
cd payment-test

# Installer les dépendances
composer install

# Copier et configurer l'environnement
cp .env.example .env
php artisan key:generate

# Base de données (SQLite par défaut)
touch database/database.sqlite
php artisan migrate

# Démarrer le serveur
php artisan serve
```

### Configuration des passerelles

Renseigner les clés dans `.env` pour CinetPay et/ou Tranzak. Les passerelles sans credentials valides sont exclues automatiquement de la liste proposée à l’utilisateur.

### URLs de callback

Les URLs `CINETPAY_NOTIFY_URL` et `TRANZAK_NOTIFY_URL` doivent être accessibles depuis Internet (ngrok ou déploiement) pour recevoir les webhooks en production.

---

## Licence

Ce projet est sous licence MIT.

<<<<<<< HEAD
# Finsova Payment Aggregation Dashboard

Backend-driven fintech dashboard for bill payments, wallet operations, merchant commissions, and payout processing.

## Overview

**Finsova Payment Aggregation Dashboard** is a fintech operations platform built to support bill payment services through a wallet-led transaction model.  
Customers can first **load money into their wallet** using supported payment methods such as **UPI, cards, and integrated third-party payment gateways**, and then use the wallet balance to perform bill-pay and related transactions.

The platform also supports:
- **Wallet load management**
- **Bill payment services**
- **Third-party payment gateway integrations**
- **Direct bank integrations**
- **Retailer and channel partner commission structures**
- **Commission withdrawal to whitelisted beneficiary accounts**
- **Payout execution via API or dashboard**
- **Transaction visibility, reconciliation, and status tracking**

This README is based on the project context provided by the owner and the Postman payout/API collection, which shows token generation, payout creation, order creation, balance fetch, and payout status flows.

## Core Business Flow

### 1) Wallet Load
Customers can add funds to their wallet using:
- UPI
- Debit/Credit Card
- Third-party payment gateways such as **Razorpay** and **PayU**
- Direct bank-supported payment flow where applicable

### 2) Bill Pay / Service Usage
After wallet load, users consume the available wallet balance to access bill payment and service transactions through the dashboard ecosystem.

### 3) Commission Model
The dashboard supports a configurable commission structure for:
- Retailers
- Channel partners

Commission logic can be configured and tracked within the system to support partner-led distribution.

### 4) Commission Withdrawal / Payout
Approved withdrawals are processed only to **whitelisted accounts**.  
Payouts can be initiated through:
- Dashboard-based workflow
- API-based workflow
- Third-party payout integrations
- Direct bank integration

## Key Features

- Wallet-based bill payment workflow
- Wallet load using multiple payment methods
- Payment gateway integration support
- Direct bank integration support
- Commission management for retailers and channel partners
- Whitelisted beneficiary payout flow
- Payout status tracking
- Transaction history and reporting dashboard
- Secure token-based API access
- Callback/webhook-based transaction updates

## Integrations

### Payment Collection / Pay-in
Supported integration patterns include:
- Razorpay
- PayU
- Other integrated PG providers
- Direct bank flow (for selected cases)

### Payout / Withdrawal
Supported payout patterns include:
- Third-party payout partners
- Direct bank payout integration
- Manual/dashboard-triggered payout handling
- API-triggered payout handling

### Banking
Direct bank integration includes **Union Bank** in the current business flow described by the project owner.

## API Architecture Summary

The Postman collection shows a token-based API model where an access token is generated first and then used for protected operations. It also shows payout callbacks, order creation, balance fetch, and transaction-status verification. ''

### Main API Capabilities Seen in Collection
- Create access token
- Create payout
- Create payment order
- Fetch balance
- Check transaction status
- Handle payout callback updates

## Authentication Flow

1. Generate access token
2. Pass bearer token in `Authorization` header
3. Use required client headers / secret headers configured per integration
4. Ensure **IP and webhook URLs are whitelisted** for pay-in and payout communication, as stated in the API collection description. ''

## Example API Flow

> Note: The examples below are intentionally sanitized. Do **not** publish real client keys, secrets, tokens, or production URLs in a public repository.

### 1. Create Access Token

**Endpoint**
```http
POST /api/v1/createtoken
```

**Headers**
```http
client-key: <your-client-key>
client-secret: <your-client-secret>
```

**cURL**
```bash
curl --request POST '<BASE_URL>/api/v1/createtoken' \
  --header 'client-key: <your-client-key>' \
  --header 'client-secret: <your-client-secret>'
```

**Sample Response**
```json
{
  "status": true,
  "token": "<jwt-token>",
  "token_expires_in": "2026-02-09 17:11:04",
  "message": "Token generated successfully."
}
```

This access-token flow is documented in the uploaded Postman collection.

### 2. Create Wallet Load / Payment Order

**Endpoint**
```http
POST /api/v1/create/orders
```

**Purpose**
Creates a payment order for wallet loading or pay-in initiation.

**Headers**
```http
Authorization: Bearer <token>
client-secret: <client-secret>
Content-Type: application/json
```

**Sample Request**
```json
{
  "txn_id": "26032310585390306",
  "reqLat": "26.2809915",
  "reqLong": "73.0124281",
  "customer_mobile": "9876543210",
  "customer_email": "test@example.com",
  "customer_name": "Rahul Sharma",
  "cardType": "CreditCard",
  "cardNetwork": "VISA CARD",
  "amount": 101
}
```

**Sample Response**
```json
{
  "status": "SUCCESS",
  "message": "success",
  "status_code": 200,
  "data": {
    "pgstatus": "SUCCESS",
    "transaction_id": "26032310585390306",
    "reference_id": null,
    "payment_url": "<redirect-url>",
    "transfer_mode": "UPI",
    "timestamp": "2026-03-23 12:39:36"
  }
}
```

The payment-order endpoint and response pattern are shown in the uploaded Postman collection.

### 3. Create Payout

**Endpoint**
```http
POST /api/v1/payouts
```

**Purpose**
Transfers funds to a whitelisted beneficiary account for commission withdrawal or merchant payout use cases.

**Headers**
```http
Authorization: Bearer <token>
client-secret: <client-secret>
Content-Type: application/json
```

**Sample Request**
```json
{
  "reqLat": "26.273987709800267",
  "reqLong": "73.00436536810876",
  "txn_id": "26032010121341695",
  "bene_account_number": "50100816685237",
  "bene_mobile": "9999999999",
  "bene_email": "beneficiary@example.com",
  "ifsc_code": "HDFC0000587",
  "bank_name": "HDFC Bank",
  "bank_branch": "Main Branch",
  "bene_name": "John Doe",
  "amount": 100.50,
  "transfer_mode": "IMPS"
}
```

**Success / Pending / Failed Responses**
```json
{
  "success": true,
  "status": "pending",
  "message": "Confirmation pending from partner bank",
  "status_code": 201,
  "data": {
    "transaction_id": "H2345678926",
    "reference_id": "225708",
    "utr": null,
    "transfer_mode": "IMPS",
    "timestamp": "2025-07-31 09:25:16"
  }
}
```

```json
{
  "success": true,
  "status": "success",
  "message": "Transaction successful",
  "status_code": 200,
  "data": {
    "transaction_id": "H2345678926",
    "reference_id": "225708",
    "utr": "520625338668",
    "transfer_mode": "IMPS",
    "timestamp": "2025-07-31 09:25:16"
  }
}
```

```json
{
  "success": true,
  "status": "failed",
  "message": "Transaction failed",
  "status_code": 400,
  "data": {
    "transaction_id": "H2345678926",
    "reference_id": "225708",
    "utr": null,
    "transfer_mode": "IMPS",
    "timestamp": "2025-07-31 09:25:16"
  }
}
```

These payout request/response patterns are taken from the uploaded Postman collection.

### 4. Check Transaction Status

**Endpoint**
```http
POST /api/v1/payouts/status
```

**Sample Request**
```json
{
  "txn_id": "H2345678927"
}
```

**Sample Success Response**
```json
{
  "success": true,
  "status": "success",
  "message": "Transaction successful",
  "status_code": 200,
  "data": {
    "transaction_id": "H2345678927",
    "reference_id": "215368",
    "utr": "520625348668",
    "transfer_mode": "IMPS",
    "timestamp": "2025-07-31 12:09:04"
  }
}
```

The status API and its sample responses are present in the uploaded Postman collection.

### 5. Fetch Balance

**Endpoint**
```http
POST /api/v1/fetchbalance
```

**Purpose**
Used to retrieve available balance for payout or transaction processing operations, as shown in the Postman collection.

## Callback / Webhook Model

The Postman collection includes an example payout callback payload with:
- HTTP code
- transaction status
- message
- transaction id
- reference id
- UTR
- transfer mode
- amount
- timestamp

This indicates the platform supports asynchronous status updates from integrated partners and banks.

**Sample Callback Structure**
```json
{
  "http_code": 200,
  "status": "Success",
  "message": "Transaction updated successfully.",
  "data": {
    "transaction_id": "SSM124630413",
    "reference_id": "Y0YFXY8QG8XTVSIX",
    "utr": "525612007094",
    "transfer_mode": "IMPS",
    "amount": 100,
    "timestamp": "2025-07-31 09:25:16"
  }
}
```

## Suggested System Components

A production-ready architecture for this dashboard would typically include:

- **Admin Dashboard**
  - User / retailer / partner management
  - Wallet monitoring
  - Commission configuration
  - Payout approval / monitoring

- **Wallet Service**
  - Wallet ledger
  - Load balance events
  - Debit / credit tracking

- **Transaction Service**
  - Bill-pay transactions
  - Payment-order creation
  - Settlement and reconciliation

- **Commission Engine**
  - Retailer commission rules
  - Channel partner commission rules
  - Withdrawal eligibility

- **Payout Engine**
  - Beneficiary validation
  - Whitelist enforcement
  - Bank / PG routing
  - Status polling and callbacks

- **Integration Layer**
  - Razorpay / PayU connectors
  - Direct bank API connectors
  - Webhook handlers

- **Reporting & Audit**
  - Ledger export
  - Transaction history
  - Commission reports
  - Payout logs
  - Audit trail

## Security Considerations

- Never commit live API secrets, bearer tokens, or bank credentials
- Enforce IP whitelisting for callbacks and server-to-server APIs
- Validate webhook signatures where supported
- Use role-based access for admin, retailer, and partner operations
- Restrict payouts to approved whitelisted beneficiary accounts
- Maintain audit logs for commission and payout actions

## Recommended README Improvements for Interview Value

To make this repository stronger for recruiters and interviewers, add:

### 1. Architecture Diagram
Show:
- Customer
- Wallet load flow
- PG integrations
- Direct bank integration
- Commission engine
- Payout engine
- Webhook/callback processing

### 2. Tech Stack Section
Example:
- Laravel / PHP
- MySQL / PostgreSQL
- REST APIs
- Razorpay / PayU integration
- Union Bank integration
- JWT / token auth
- Docker / AWS (if used in deployment)

### 3. Database Modules
Mention main entities:
- users
- wallets
- wallet_transactions
- bill_pay_transactions
- commissions
- beneficiaries
- payouts
- payout_callbacks
- partners / retailers

### 4. Scale / Ownership Metrics
Add real numbers such as:
- transactions per day / month
- wallet loads processed
- payout success rate
- reconciliation volume
- number of retailers / partners served
- latency improvements
- failure-retry reductions

## Disclaimer

This repository README intentionally uses **sanitized examples** and avoids exposing confidential implementation secrets, production credentials, or private business-sensitive values.
=======
# payment-aggrication-dashboard
>>>>>>> 573c55ec488d26f0f5ef66a7a978eb38ea9e7abd

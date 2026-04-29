<?php

return [

  /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

  'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
  ],

  'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

  'resend' => [
    'key' => env('RESEND_KEY'),
  ],

  'slack' => [
    'notifications' => [
      'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
      'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
  ],
  'bulkpe' => [
    'base_url' => env('BULKPE_API_BASE_URL', 'https://api.indiapayments.org/client/'),
    'auth_token' => env('BULKPE_AUTH_TOKEN'),
    'timeout' => (int) env('BULKPE_TIMEOUT', 120),
    'log_channel' => env('BULKPE_LOG_CHANNEL', 'daily'),
  ],
  'buckbox' => [
    'api_key' => env('BUCKBOX_API_KEY'),
    'secret_key' => env('BUCKBOX_SECRET_KEY'),
    'eny_key' => env('BUCKBOX_ENY_KEY'),
    'merchant_id' => env('BUCKBOX_MERCHANT_ID'),
    'merchant_name' => env('BUCKBOX_MERCHANT_NAME'),
    'merchant_email' => env('BUCKBOX_MERCHANT_EMAIL'),
  ],
  'bbps' => [
    'base_url' => env('BBPS_API_BASE_URL', 'https://stgapi.billavenue.com'),
    'biller_info_api_url' => env('BBPS_BILLER_INFO_API_URL'),
    'bill_fetch_api_url' => env('BBPS_BILL_FETCH_API_URL'),
    'plan_pull_api_url' => env('BBPS_PLAN_PULL_API_URL'),
    'bill_validation_api_url' => env('BBPS_BILL_VALIDATION_API_URL'),
    'working_key' => env('BBPS_WORKING_KEY'),
    'access_code' => env('BBPS_ACCESS_CODE'),
    'institute_id' => env('BBPS_INSTITUTE_ID'),
    'institute_name' => env('BBPS_INSTITUTE_NAME'),
    'agent_id' => env('BBPS_AGENTID'),
    'complaint_register_api_url' => env('BBPS_COMPLAINT_REGISTER_API_URL'),
    'complaint_track_api_url' => env('BBPS_COMPLAINT_TRACK_API_URL'),
  ],

  'finkeda_payin' => [
    'base_url' => 'https://apigateway.finkeda.com',
    'client_id' => env('FINKEDA_PAYIN_CLIENT_ID'),
    'agent_id' => env('FINKEDA_PAYIN_AGENT_ID'),
    'counter_id' => env('FINKEDA_PAYIN_COUNTER_ID'),
    'timeout' => env('FINKEDA_PAYIN_TIMEOUT', 120),
    'log_channel' => env('FINKEDA_PAYIN_LOG_CHANNEL', 'daily'),

    // bearerData defaults
    'request_source' => env('FINKEDA_PAYIN_REQUEST_SOURCE', 'WEB'),
    'version' => env('FINKEDA_PAYIN_VERSION', '1.0'),
    'app_name' => env('FINKEDA_PAYIN_APP_NAME', 'TERMINAL'),
    'scope' => env('FINKEDA_PAYIN_SCOPE', 'TERMINAL LOAD'),
  ],
  'finkeda_payout' => [
    'base_url' => env('FINKEDA_PAYOUT_BASE_URL'),
    'client_id' => env('FINKEDA_PAYOUT_CLIENT_ID'),
    'timeout' => 120,
    'log_channel' => 'daily',
  ],
  'sms' => [
    'url' => env('SMS_URL'),
    'api_key' => env('SMS_API_KEY'),
    'username' => env('SMS_USERNAME'),
    'password' => env('SMS_PASSWORD'),
    'sender' => env('SMS_SENDER'),
    'entity_id' => env('SMS_ENTITY_ID'),
    'complaint_template_id' => env('SMS_COMPLAINT_TEMPLATE_ID'),
    'otp_template_id' => env('SMS_OTP_TEMPLATE_ID'),
    'payment_template_id' => env('SMS_PAYMENT_TEMPLATE_ID'),
  ],
  'finsova' => [
    'username' => env('FINSOVA_USERNAME'),
    'password' => env('FINSOVA_PASSWORD'),
    'aes_key' => env('FINSOVA_AES_KEY'),
    'aes_iv'  => env('FINSOVA_AES_IV'),
    'sender_code' => env('FINSOVA_SENDER_CODE', 'Finsova'),
    'remitter_acc'     => env('FINSOVA_REMITTER_ACC'),
    'remitter_name'    => env('FINSOVA_REMITTER_NAME'),
    'remitter_address' => env('FINSOVA_REMITTER_ADDRESS'),
    'remitter_mobile'  => env('FINSOVA_REMITTER_MOBILE'),
    'remitter_email'   => env('FINSOVA_REMITTER_EMAIL'),
    'token_url' => env(
      'FINSOVA_TOKEN_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlerpb/1/Finsova/FinsovaServiceGroups/tokenGenApi'
    ),
    'fund_transfer_url' => env(
      'FINSOVA_FUND_TRANSFER_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlersb/1/Finsova/FinsovaServiceGroups/fundTransferServiceExternal'
    ),

    'fund_status_url' => env(
      'FINSOVA_FUND_STATUS_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlersb/1/Finsova/FinsovaServiceGroups/fundStatusService'
    ),
    'bankstatement_url' => env(
      'FINSOVA_BANKSTATEMENT_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlerpb/1/Finsova/FinsovaServiceGroups/fetchAccountStatementApi'
    ),
    'checkbal_url' => env(
      'FINSOVA_CHECKBAL_URL',
      'https://apim.unionbankofindia.bank.in/BankServices/handlerpb/1/Finsova/FinsovaServiceGroups/fetchAccountStatementApi'
    ),
  ],
  'bluswap' => [
    'base_url' => env('BLUSWAP_BASE_URL', 'https://api.bluswap.co'),
    'api_key' => env('BLUSWAP_API_KEY'),
    'timeout' => env('BLUSWAP_TIMEOUT', 60),
    'log_channel' => env('BLUSWAP_LOG_CHANNEL', 'daily'),
  ],
];

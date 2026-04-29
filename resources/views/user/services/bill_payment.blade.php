@extends('layouts/layoutMaster')
@section('title', 'Bill Payment')

@section('vendor-style')
  <style>
    .service-box {
      padding: 15px;
      border-radius: 12px;
      transition: 0.3s ease;
    }

    .service-box:hover {
      background: #f8f9fa;
      transform: translateY(-4px);
    }

    .icon-box i {
      font-size: 26px;
    }

    .bg-light-warning {
      background: #fff4e5;
    }

    .bg-light-info {
      background: #e6f7ff;
    }

    .bg-light-danger {
      background: #ffeaea;
    }

    .bg-light-primary {
      background: #e7f1ff;
    }

    .service-box {
      background: #fff;
      padding: 20px 10px;
      border-radius: 15px;
      transition: all 0.3s ease;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .service-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .icon-box {
      width: 60px;
      height: 60px;
      margin: auto;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    p {
      color: #002F36 !important;
    }
  </style>
@endsection

@section('vendor-script')
@endsection

@section('content')
  <div class="card">
    <div class="card-header px-1 pt-1 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 pt-2 ps-4">Bill Payment</h5>
      <img src="{{ asset('assets/img/logo/bbpsPrimaryLogo.jpg') }}" alt="Bharat-Connect" style="height: 60px;">
    </div>

    <div class="card-body">
      <!-- BILL PAYMENTS SECTION -->
      <div class="mb-5">
        <div class="row g-4">
          <!-- Agent Collection -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Agent Collection') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <circle cx="20" cy="14" r="5" stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M10 30C10 24 14 21 20 21C26 21 30 24 30 30" stroke="#5B3BF5" stroke-width="1.6" />
                  <circle cx="28" cy="12" r="2" fill="#F59E0B" />
                </svg>
                <p class="mt-2">Agent Collection</p>
              </div>
            </a>
          </div>

          <!-- Broadband -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'broadbandPostpaid') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- router box -->
                  <rect x="9" y="26" width="22" height="8" rx="2" stroke="#5B3BF5" stroke-width="1.6" />
                  <!-- LED dots -->
                  <circle cx="15" cy="30" r="1.2" fill="#5B3BF5" />
                  <circle cx="19" cy="30" r="1.2" fill="#F59E0B" />
                  <circle cx="23" cy="30" r="1.2" fill="#5B3BF5" opacity="0.35" /> <!-- antenna -->
                  <line x1="27" y1="26" x2="29" y2="20" stroke="#5B3BF5" stroke-width="1.5"
                    stroke-linecap="round" /> <!-- wifi arcs above router -->
                  <path d="M20 25C17.5 25 15 23.5 13 21.5" stroke="#5B3BF5" stroke-width="1.4" stroke-linecap="round"
                    fill="none" opacity="0.35" />
                  <path d="M20 25C18 25 16.5 24 15.5 23" stroke="#5B3BF5" stroke-width="1.4" stroke-linecap="round"
                    fill="none" opacity="0.6" />
                  <path d="M20 25C22.5 25 25 23.5 27 21.5" stroke="#5B3BF5" stroke-width="1.4" stroke-linecap="round"
                    fill="none" opacity="0.35" />
                  <path d="M20 25C22 25 23.5 24 24.5 23" stroke="#5B3BF5" stroke-width="1.4" stroke-linecap="round"
                    fill="none" opacity="0.6" />
                  <circle cx="20" cy="25" r="1.5" fill="#5B3BF5" />
                </svg>
                <p class="mt-2">Broadband Postpaid</p>
              </div>
            </a>
          </div>

          <!-- Cable TV -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Cable TV') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="8" y="12" width="24" height="16" rx="2" stroke="#5B3BF5" stroke-width="1.6" />
                  <line x1="14" y1="30" x2="26" y2="30" stroke="#5B3BF5" stroke-width="1.6" />
                  <line x1="20" y1="28" x2="20" y2="32" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <path d="M18 18L24 20L18 22Z" fill="#F59E0B" />
                </svg>
                <p class="mt-2">Cable TV</p>
              </div>
            </a>
          </div>

          <!-- Clubs and Associations -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Clubs and Associations') }}"
              class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <circle cx="15" cy="16" r="4" stroke="#5B3BF5" stroke-width="1.6" />
                  <circle cx="25" cy="16" r="4" stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M8 28C8 24 11 22 15 22" stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M32 28C32 24 29 22 25 22" stroke="#5B3BF5" stroke-width="1.6" />
                  <circle cx="20" cy="12" r="1.5" fill="#F59E0B" />
                </svg>
                <p class="mt-2">Clubs and Associations</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'credit-card') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="6" y="12" width="28" height="18" rx="3" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="6" y1="18" x2="34" y2="18" stroke="#5B3BF5"
                    stroke-width="2.5" />
                  <rect x="10" y="22" width="8" height="3" rx="1" fill="#F59E0B" />
                  <circle cx="28" cy="23.5" r="2.5" stroke="#5B3BF5" stroke-width="1.4" />
                  <circle cx="26" cy="23.5" r="2.5" stroke="#5B3BF5" stroke-width="1.4" opacity="0.4" />
                </svg>
                <p class="mt-2">Credit Card</p>
              </div>
            </a>
          </div>

          <!-- Donation -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Donation') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <path
                    d="M20 30C20 30 10 24 10 17C10 13 13 11 16 11C18 11 20 13 20 13C20 13 22 11 24 11C27 11 30 13 30 17C30 24 20 30 20 30Z"
                    stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M20 16V22M17 19H23" stroke="#F59E0B" stroke-width="1.5" />
                </svg>
                <p class="mt-2">Donation</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'education-fees') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <polygon points="20,9 32,16 20,23 8,16" stroke="#5B3BF5" stroke-width="1.6" stroke-linejoin="round"
                    fill="none" />
                  <path d="M14 19V26C14 26 16.5 30 20 30C23.5 30 26 26 26 26V19" stroke="#5B3BF5" stroke-width="1.6"
                    stroke-linecap="round" />
                  <line x1="32" y1="16" x2="32" y2="23" stroke="#5B3BF5"
                    stroke-width="1.6" stroke-linecap="round" />
                  <path d="M30 23C30 23 32 24.5 34 23" stroke="#F59E0B" stroke-width="1.6" stroke-linecap="round" />
                </svg>
                <p class="mt-2">Education Fees</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'dth') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- dish bowl -->
                  <path d="M12 28C12 28 12 20 20 16C28 12 32 16 32 16" stroke="#5B3BF5" stroke-width="1.6"
                    stroke-linecap="round" fill="none" />
                  <path d="M12 28C12 28 18 24 22 17" stroke="#5B3BF5" stroke-width="1.6" stroke-linecap="round"
                    fill="none" /> <!-- dish rim -->
                  <path d="M12 28Q16 22 22 17" stroke="#5B3BF5" stroke-width="1.2" opacity="0.3" fill="none" />
                  <!-- stand pole -->
                  <line x1="12" y1="28" x2="10" y2="33" stroke="#5B3BF5"
                    stroke-width="1.6" stroke-linecap="round" />
                  <line x1="8" y1="33" x2="14" y2="33" stroke="#5B3BF5"
                    stroke-width="1.6" stroke-linecap="round" /> <!-- signal dot + waves from dish -->
                  <circle cx="22" cy="17" r="1.5" fill="#F59E0B" />
                  <path d="M25 14C26.2 15.2 26.2 17.2 25 18.5" stroke="#F59E0B" stroke-width="1.4"
                    stroke-linecap="round" fill="none" />
                  <path d="M27 12C29.5 14.5 29.5 18.5 27 21" stroke="#F59E0B" stroke-width="1.4" stroke-linecap="round"
                    fill="none" opacity="0.5" />
                </svg>
                <p class="mt-2">DTH</p>
              </div>
            </a>
          </div>

          <!-- eChallan -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'eChallan') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="12" y="10" width="16" height="20" rx="2" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="15" y1="16" x2="25" y2="16" stroke="#5B3BF5"
                    stroke-width="1.4" />
                  <line x1="15" y1="20" x2="25" y2="20" stroke="#5B3BF5"
                    stroke-width="1.4" />
                  <circle cx="20" cy="26" r="1.5" fill="#F59E0B" />
                </svg>
                <p class="mt-2">eChallan</p>
              </div>
            </a>
          </div>

          <!-- Electricity -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Electricity') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <!-- bulb glass -->
                  <path
                    d="M15 18C15 14.134 17.239 11 20 11C22.761 11 25 14.134 25 18C25 20.5 23.5 22.5 22 24V26H18V24C16.5 22.5 15 20.5 15 18Z"
                    stroke="#5B3BF5" stroke-width="1.6" stroke-linejoin="round" /> <!-- base rings -->
                  <line x1="18" y1="26" x2="22" y2="26" stroke="#5B3BF5"
                    stroke-width="1.5" stroke-linecap="round" />
                  <line x1="18.5" y1="28" x2="21.5" y2="28" stroke="#5B3BF5"
                    stroke-width="1.5" stroke-linecap="round" /> <!-- filament / bolt inside -->
                  <path d="M21 15L18.5 18.5H21L19 22" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round"
                    stroke-linejoin="round" />
                </svg>
                <p class="mt-2">Electricity</p>
              </div>
            </a>
          </div>

          <!-- EV Recharge -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'EV Recharge') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="10" y="18" width="14" height="8" rx="2" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <circle cx="14" cy="28" r="2" stroke="#5B3BF5" stroke-width="1.6" />
                  <circle cx="20" cy="28" r="2" stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M26 16L30 20M30 20L26 24" stroke="#F59E0B" stroke-width="1.6" />
                </svg>
                <p class="mt-2">EV Recharge</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'fastag') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- card shape -->
                  <rect x="7" y="14" width="26" height="16" rx="2.5" stroke="#5B3BF5"
                    stroke-width="1.6" /> <!-- card stripe top -->
                  <line x1="7" y1="19" x2="33" y2="19" stroke="#5B3BF5"
                    stroke-width="2.2" /> <!-- FASTAG text on card --> <text x="20" y="27.5" text-anchor="middle"
                    font-family="Inter,sans-serif" font-size="5.5" font-weight="700" fill="#5B3BF5"
                    letter-spacing="0.5">FASTag</text> <!-- wifi/signal on top-left of card -->
                  <path d="M11 16.5C11 16.5 11 15 12.5 14.5" stroke="#F59E0B" stroke-width="1.3" stroke-linecap="round"
                    fill="none" />
                  <path d="M10 17.5C10 17.5 10 14 13 13" stroke="#F59E0B" stroke-width="1.3" stroke-linecap="round"
                    fill="none" opacity="0.5" />
                </svg>
                <p class="mt-2">Fastag</p>
              </div>
            </a>
          </div>
          <!-- Fleet Card Recharge -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Fleet Card Recharge') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="8" y="14" width="24" height="14" rx="2" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="8" y1="18" x2="32" y2="18" stroke="#5B3BF5"
                    stroke-width="2" />
                  <circle cx="26" cy="23" r="2" fill="#F59E0B" />
                </svg>
                <p class="mt-2">Fleet Card Recharge</p>
              </div>
            </a>
          </div>

          <!-- GAS -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'gas') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- stove top -->
                  <rect x="9" y="23" width="22" height="9" rx="2" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="9" y1="27" x2="31" y2="27" stroke="#5B3BF5"
                    stroke-width="1" opacity="0.3" /> <!-- burners -->
                  <circle cx="16" cy="29" r="2.5" stroke="#5B3BF5" stroke-width="1.4" />
                  <circle cx="24" cy="29" r="2.5" stroke="#5B3BF5" stroke-width="1.4" /> <!-- knob -->
                  <rect x="9" y="20" width="22" height="3" rx="1" stroke="#5B3BF5"
                    stroke-width="1.4" /> <!-- flame -->
                  <path
                    d="M20 19C20 19 18 15 20 12C20 12 21.5 14.5 21 16.5C22.5 15 22 12.5 24 11C24 11 24.5 14.5 22 17C23 16.5 24 17.5 23.5 19H20Z"
                    stroke="#F59E0B" stroke-width="1.2" stroke-linejoin="round" fill="none" />
                </svg>
                <p class="mt-2">Gas</p>
              </div>
            </a>
          </div>

          <!-- Housing Society -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Housing Society') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <path d="M10 20L20 12L30 20V30H10Z" stroke="#5B3BF5" stroke-width="1.6" />
                  <rect x="16" y="24" width="8" height="6" stroke="#5B3BF5" stroke-width="1.4" />
                  <circle cx="20" cy="18" r="1.5" fill="#F59E0B" />
                </svg>
                <p class="mt-2">Housing Society</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <!-- Main Insurance Category -->
            <a data-bs-toggle="collapse" href="#insuranceServices" role="button" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <path d="M20 8L30 12V20C30 26 25.5 31.5 20 33C14.5 31.5 10 26 10 20V12L20 8Z" stroke="#5B3BF5"
                    stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M17 19H23M17 17H23M20 17V23M18.5 19L22.5 23" stroke="#F59E0B" stroke-width="1.5"
                    stroke-linecap="round" />
                </svg>
                <p class="mt-2">Insurance</p>
              </div>
            </a>
          </div>

          <div class="collapse mt-3" id="insuranceServices">
            <div class="row g-4">
              <!-- Health Insurance -->
              <div class="col-md-2 col-6">
                <a href="{{ route('service.bill-payment.category', 'health-insurance') }}"
                  class="text-decoration-none">
                  <div class="service-box text-center h-100">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                      <path d="M20 10L29 13.5V20C29 25.5 25 30 20 32C15 30 11 25.5 11 20V13.5L20 10Z" stroke="#5B3BF5"
                        stroke-width="1.6" />
                      <path d="M16.5 20.5H23.5M20 17V24" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    <p class="mt-2">Health Insurance</p>
                  </div>
                </a>
              </div>
              <!-- Motor Insurance -->
              <div class="col-md-2 col-6">
                <a href="{{ route('service.bill-payment.category', 'motor-insurance') }}" class="text-decoration-none">
                  <div class="service-box text-center h-100">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                      <path d="M8 22V20L12 15H28L32 20V22" stroke="#5B3BF5" stroke-width="1.6" />
                      <rect x="8" y="22" width="24" height="5" rx="1.5" stroke="#5B3BF5"
                        stroke-width="1.6" />
                      <circle cx="14" cy="30" r="3" stroke="#5B3BF5" stroke-width="1.6" />
                      <circle cx="26" cy="30" r="3" stroke="#5B3BF5" stroke-width="1.6" />
                      <circle cx="14" cy="30" r="1" fill="#F59E0B" />
                      <circle cx="26" cy="30" r="1" fill="#F59E0B" />
                    </svg>
                    <p class="mt-2">Motor Insurance</p>
                  </div>
                </a>
              </div>
            </div>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'landlinePostpaid') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <path
                    d="M11 16C11 16 12.5 12 15.5 13L17.5 16.5C17.5 16.5 15.5 18.5 16 19.5C17 21.5 21.5 26.5 23 27C24 27.5 26 25.5 26 25.5L28.5 27.5C29 30 26 31 26 31C26 31 16 29.5 11 20C10 18 11 16 11 16Z"
                    stroke="#5B3BF5" stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M21 11C23.5 11.5 26 13.5 27 16.5" stroke="#5B3BF5" stroke-width="1.5" stroke-linecap="round"
                    fill="none" opacity="0.45" />
                  <path d="M21 14C22.5 14.5 24 16 24.5 18" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round"
                    fill="none" />
                </svg>
                <p class="mt-2">Landline Postpaid</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'loanRepayment') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- bank building -->
                  <rect x="9" y="24" width="22" height="7" rx="1.5" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <rect x="9" y="21" width="22" height="3.5" rx="0" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="13" y1="21" x2="13" y2="24" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="17" y1="21" x2="17" y2="24" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="21" y1="21" x2="21" y2="24" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="25" y1="21" x2="25" y2="24" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <path d="M9 21L20 13L31 21" stroke="#5B3BF5" stroke-width="1.6" stroke-linejoin="round" />
                  <!-- rupee symbol -->
                  <path d="M18 27.5H22M18 26H22M20 26V30.5M19 27.5L22 30.5" stroke="#F59E0B" stroke-width="1.4"
                    stroke-linecap="round" />
                </svg>
                <p class="mt-2">Loan Repayment</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'lpg-gas') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- cylinder body -->
                  <path
                    d="M14 20C14 18 16.5 17 20 17C23.5 17 26 18 26 20V31C26 32.5 23.3 34 20 34C16.7 34 14 32.5 14 31V20Z"
                    stroke="#5B3BF5" stroke-width="1.6" />
                  <ellipse cx="20" cy="20" rx="6" ry="2" stroke="#5B3BF5"
                    stroke-width="1.6" /> <!-- shoulder -->
                  <path d="M16 17C16 15 17.5 14 20 14C22.5 14 24 15 24 17" stroke="#5B3BF5" stroke-width="1.4"
                    fill="none" /> <!-- neck -->
                  <rect x="18.5" y="11" width="3" height="3" rx="0.8" stroke="#5B3BF5"
                    stroke-width="1.4" /> <!-- valve bar -->
                  <line x1="15.5" y1="11" x2="24.5" y2="11" stroke="#F59E0B"
                    stroke-width="2" stroke-linecap="round" /> <!-- label stripe -->
                  <line x1="14.5" y1="25" x2="25.5" y2="25" stroke="#5B3BF5"
                    stroke-width="1" opacity="0.25" />
                </svg>
                <p class="mt-2">LPG Gas</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'mobilePostpaid') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- phone -->
                  <rect x="10" y="9" width="13" height="22" rx="2.5" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="13.5" y1="13" x2="19.5" y2="13" stroke="#5B3BF5"
                    stroke-width="1.2" stroke-linecap="round" opacity="0.4" />
                  <circle cx="16.5" cy="28" r="1" fill="#5B3BF5" /> <!-- bill receipt on right -->
                  <rect x="24" y="14" width="10" height="13" rx="1.5" stroke="#5B3BF5"
                    stroke-width="1.5" />
                  <line x1="26.5" y1="18" x2="31.5" y2="18" stroke="#F59E0B"
                    stroke-width="1.4" stroke-linecap="round" />
                  <line x1="26.5" y1="21" x2="31.5" y2="21" stroke="#5B3BF5"
                    stroke-width="1.2" stroke-linecap="round" opacity="0.4" />
                  <line x1="26.5" y1="24" x2="30" y2="24" stroke="#5B3BF5"
                    stroke-width="1.2" stroke-linecap="round" opacity="0.4" />
                </svg>
                <p class="mt-2">Mobile Postpaid</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Mobile Prepaid') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- phone body -->
                  <rect x="13" y="8" width="14" height="22" rx="2.5" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="17" y1="12" x2="23" y2="12" stroke="#5B3BF5"
                    stroke-width="1.4" stroke-linecap="round" opacity="0.4" />
                  <circle cx="20" cy="27" r="1" fill="#5B3BF5" />
                  <!-- recharge ↑ arrow outside top right -->
                  <path d="M26 10V7M26 7L28 9M26 7L24 9" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round"
                    stroke-linejoin="round" />
                  <path d="M24 8.5C24 8.5 22 8 21.5 10" stroke="#F59E0B" stroke-width="1.3" stroke-linecap="round"
                    fill="none" />
                </svg>
                <p class="mt-2">Mobile Prepaid</p>
              </div>
            </a>
          </div>

          <!-- Municipal Services -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Municipal Services') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="10" y="18" width="20" height="12" stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M10 18L20 10L30 18" stroke="#5B3BF5" stroke-width="1.6" />
                  <circle cx="20" cy="24" r="2" fill="#F59E0B" />
                </svg>
                <p class="mt-2">Municipal Services</p>
              </div>
            </a>
          </div>

          <!-- Recurring Deposit -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Recurring Deposit') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <circle cx="20" cy="22" r="8" stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M20 16V24L24 26" stroke="#F59E0B" stroke-width="1.6" />
                </svg>
                <p class="mt-2">Recurring Deposit</p>
              </div>
            </a>
          </div>

          <!-- Rental -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Rental') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <path d="M10 20L20 12L30 20V30H10Z" stroke="#5B3BF5" stroke-width="1.6" />
                  <line x1="15" y1="26" x2="25" y2="26" stroke="#F59E0B"
                    stroke-width="1.6" />
                </svg>
                <p class="mt-2">Rental</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'subscription') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="7" y="12" width="26" height="18" rx="2.5" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <rect x="11" y="16" width="18" height="10" rx="1" stroke="#5B3BF5"
                    stroke-width="1.2" opacity="0.4" />
                  <line x1="20" y1="30" x2="20" y2="34" stroke="#5B3BF5"
                    stroke-width="1.6" stroke-linecap="round" />
                  <line x1="15" y1="34" x2="25" y2="34" stroke="#5B3BF5"
                    stroke-width="1.6" stroke-linecap="round" /> <!-- play button -->
                  <path d="M17 19L24 21L17 23V19Z" fill="#F59E0B" stroke="#F59E0B" stroke-width="0.5"
                    stroke-linejoin="round" />
                </svg>
                <p class="mt-2">Subscription</p>
              </div>
            </a>
          </div>

          <!-- Municipal Taxes -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Municipal Taxes') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <path d="M10 20L20 12L30 20" stroke="#5B3BF5" stroke-width="1.6" />
                  <rect x="12" y="20" width="16" height="10" stroke="#5B3BF5" stroke-width="1.6" />
                  <path d="M18 24H22" stroke="#F59E0B" stroke-width="1.6" />
                </svg>
                <p class="mt-2">Municipal Taxes</p>
              </div>
            </a>
          </div>

          <!-- National Pension System -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'National Pension System') }}"
              class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <circle cx="20" cy="14" r="5" stroke="#5B3BF5" stroke-width="1.6" />
                  <rect x="14" y="20" width="12" height="10" stroke="#5B3BF5" stroke-width="1.6" />
                  <circle cx="20" cy="25" r="1.5" fill="#F59E0B" />
                </svg>
                <p class="mt-2">National Pension System</p>
              </div>
            </a>
          </div>

          <!-- NCMC Recharge -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'NCMC Recharge') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="8" y="14" width="24" height="14" rx="2" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <circle cx="26" cy="21" r="2" fill="#F59E0B" />
                </svg>
                <p class="mt-2">NCMC Recharge</p>
              </div>
            </a>
          </div>

          <!-- Prepaid Meter -->
          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Prepaid Meter') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <rect x="14" y="12" width="12" height="16" rx="2" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="18" y1="18" x2="22" y2="18" stroke="#5B3BF5"
                    stroke-width="1.4" />
                  <path d="M20 22L18 25H21L19 28" stroke="#F59E0B" stroke-width="1.4" />
                </svg>
                <p class="mt-2">Prepaid Meter</p>
              </div>
            </a>
          </div>


          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'Mobile') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"> <!-- phone body -->
                  <rect x="13" y="8" width="14" height="22" rx="2.5" stroke="#5B3BF5"
                    stroke-width="1.6" />
                  <line x1="17" y1="12" x2="23" y2="12" stroke="#5B3BF5"
                    stroke-width="1.4" stroke-linecap="round" opacity="0.4" />
                  <circle cx="20" cy="27" r="1" fill="#5B3BF5" />
                  <!-- recharge ↑ arrow outside top right -->
                  <path d="M26 10V7M26 7L28 9M26 7L24 9" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round"
                    stroke-linejoin="round" />
                  <path d="M24 8.5C24 8.5 22 8 21.5 10" stroke="#F59E0B" stroke-width="1.3" stroke-linecap="round"
                    fill="none" />
                </svg>
                <p class="mt-2">Mobile</p>
              </div>
            </a>
          </div>

          <div class="col-md-2 col-6">
            <a href="{{ route('service.bill-payment.category', 'water') }}" class="text-decoration-none">
              <div class="service-box text-center h-100">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                  <path
                    d="M20 9C20 9 11 19.5 11 24.5C11 29.194 15.029 33 20 33C24.971 33 29 29.194 29 24.5C29 19.5 20 9 20 9Z"
                    stroke="#5B3BF5" stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M14.5 25.5C14.5 25.5 15.5 22 18.5 21" stroke="#F59E0B" stroke-width="1.5"
                    stroke-linecap="round" />
                </svg>
                <p class="mt-2">Water</p>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer text-center">
      Need assistance?
      <a href="{{ route('complaint.create') }}">
        Register a Complaint
      </a>
    </div>
  </div>
@endsection

@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
      // $('#billerDropdown').change(function() {
      //   let billerId = $(this).val();
      //   $.get("/bbps/biller-fields/" + billerId, function(res) {
      //     $('#dynamicFields').html('');
      //     res.customer_params.forEach(field => {
      //       $('#dynamicFields').append(`
    //         <div class="mb-3">
    //           <label>${field.paramName}</label>
    //           <input type="text" name="${field.paramName}" class="form-control" required>
    //         </div>
    //       `);
      //     });
      //     $('#customerFormSection').show();
      //   });
      // });
    });
  </script>
@endsection

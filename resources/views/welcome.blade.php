<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-PESA Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .mpesa-green {
            background: linear-gradient(135deg, #00a651 0%, #007a3d 100%);
        }

        .card-shadow {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .input-focus:focus {
            border-color: #00a651;
            box-shadow: 0 0 0 3px rgba(0, 166, 81, 0.1);
        }

        /* FIXED: Remove phone-input class padding conflict */
        .phone-prefix-container {
            position: relative;
        }

        .phone-prefix {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
            font-weight: 500;
            pointer-events: none;
            z-index: 10;
        }

        .phone-input-field {
            padding-left: 5.5rem !important;
        }

        .amount-input-field {
            padding-left: 4.5rem !important;
        }

        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #00a651;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }

        .slide-up {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .payment-method {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .payment-method:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .payment-method.active {
            border-color: #00a651;
            background: rgba(0, 166, 81, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-8 px-4">
    <div class="max-w-md mx-auto">
        <!-- Logo/Header -->
        <div class="text-center mb-8 slide-up">
            <div class="inline-block mpesa-green text-white p-4 rounded-2xl mb-4">
                <i class="fas fa-mobile-alt text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">M-PESA Payment</h1>
            <p class="text-gray-600">Fast, secure, and reliable payments</p>
        </div>

        <!-- Main Payment Card -->
        <div class="bg-white rounded-2xl card-shadow p-8 slide-up" style="animation-delay: 0.1s;">

            <!-- Success Message -->
            @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg slide-up">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <p class="text-green-700 font-medium">{{ session('success') }}</p>
                </div>
            </div>
            @endif

            <!-- Error Message -->
            @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg slide-up">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                    <p class="text-red-700 font-medium">{{ session('error') }}</p>
                </div>
            </div>
            @endif

            <!-- Payment Form -->
            <form id="paymentForm" action="{{ route('mpesa.process') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Payment Method Selection -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Payment Method</label>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="payment-method active border-2 border-gray-200 rounded-xl p-4 text-center" data-type="till">
                            <i class="fas fa-store text-2xl text-green-600 mb-2"></i>
                            <p class="text-xs font-medium text-gray-700">Buy Goods</p>
                        </div>
                        <div class="payment-method border-2 border-gray-200 rounded-xl p-4 text-center" data-type="paybill">
                            <i class="fas fa-building text-2xl text-blue-600 mb-2"></i>
                            <p class="text-xs font-medium text-gray-700">Paybill</p>
                        </div>
                        <div class="payment-method border-2 border-gray-200 rounded-xl p-4 text-center" data-type="send">
                            <i class="fas fa-paper-plane text-2xl text-purple-600 mb-2"></i>
                            <p class="text-xs font-medium text-gray-700">Send Money</p>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="paymentType" value="till">
                </div>

                <!-- Till/Paybill Number (conditionally shown) -->
                <div id="tillPaybillContainer" style="display: none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <span id="tillPaybillLabel">Till Number</span>
                    </label>
                    <input
                        type="text"
                        name="till_paybill_number"
                        id="tillPaybillNumber"
                        placeholder="Enter till/paybill number"
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 input-focus transition-all duration-200"
                    >
                </div>

                <!-- Account Number (for Paybill only) -->
                <div id="accountContainer" style="display: none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Account Number
                    </label>
                    <input
                        type="text"
                        name="account_number"
                        id="accountNumber"
                        placeholder="Enter account number"
                        class="w-full border-2 border-gray-200 rounded-xl py-3 px-4 input-focus transition-all duration-200"
                    >
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Phone Number
                    </label>
                    <div class="phone-prefix-container">
                        <span class="phone-prefix">🇰🇪 +254</span>
                        <input
                            type="tel"
                            name="phone"
                            id="phoneInput"
                            placeholder="712345678"
                            class="phone-input-field w-full border-2 border-gray-200 rounded-xl py-3 px-4 input-focus transition-all duration-200"
                            required
                            pattern="[0-9]{9}"
                            maxlength="9"
                        >
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Enter your M-PESA registered number</p>
                </div>

                <!-- Amount -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Amount (KES)
                    </label>
                    <div class="phone-prefix-container">
                        <span class="phone-prefix">KES</span>
                        <input
                            type="number"
                            name="amount"
                            id="amountInput"
                            placeholder="100"
                            min="1"
                            class="amount-input-field w-full border-2 border-gray-200 rounded-xl py-3 px-4 input-focus transition-all duration-200"
                            required
                        >
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Minimum amount: KES 1</p>
                </div>

                <!-- Quick Amount Buttons -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quick Select</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" class="quick-amount border-2 border-gray-200 rounded-lg py-2 text-sm font-medium text-gray-700 hover:border-green-500 hover:text-green-600 transition-all" data-amount="100">100</button>
                        <button type="button" class="quick-amount border-2 border-gray-200 rounded-lg py-2 text-sm font-medium text-gray-700 hover:border-green-500 hover:text-green-600 transition-all" data-amount="500">500</button>
                        <button type="button" class="quick-amount border-2 border-gray-200 rounded-lg py-2 text-sm font-medium text-gray-700 hover:border-green-500 hover:text-green-600 transition-all" data-amount="1000">1K</button>
                        <button type="button" class="quick-amount border-2 border-gray-200 rounded-lg py-2 text-sm font-medium text-gray-700 hover:border-green-500 hover:text-green-600 transition-all" data-amount="5000">5K</button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    id="submitBtn"
                    class="w-full mpesa-green text-white py-4 rounded-xl font-semibold text-lg hover:shadow-lg transform hover:-translate-y-1 transition-all duration-200 flex items-center justify-center space-x-2"
                >
                    <i class="fas fa-lock"></i>
                    <span id="btnText">Pay with M-PESA</span>
                    <div id="btnLoader" class="loader hidden ml-2"></div>
                </button>

                <!-- Security Badge -->
                <div class="flex items-center justify-center space-x-4 text-xs text-gray-500 pt-4 border-t border-gray-100">
                    <div class="flex items-center space-x-1">
                        <i class="fas fa-shield-alt text-green-600"></i>
                        <span>256-bit SSL</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <i class="fas fa-lock text-green-600"></i>
                        <span>Secure Payment</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <i class="fas fa-check-circle text-green-600"></i>
                        <span>PCI Compliant</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Features -->
        <div class="mt-8 grid grid-cols-3 gap-4 text-center">
            <div class="slide-up" style="animation-delay: 0.2s;">
                <div class="text-green-600 text-2xl mb-2">
                    <i class="fas fa-bolt"></i>
                </div>
                <p class="text-xs font-medium text-gray-700">Instant</p>
                <p class="text-xs text-gray-500">Real-time processing</p>
            </div>
            <div class="slide-up" style="animation-delay: 0.3s;">
                <div class="text-blue-600 text-2xl mb-2">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <p class="text-xs font-medium text-gray-700">Secure</p>
                <p class="text-xs text-gray-500">Bank-level security</p>
            </div>
            <div class="slide-up" style="animation-delay: 0.4s;">
                <div class="text-purple-600 text-2xl mb-2">
                    <i class="fas fa-headset"></i>
                </div>
                <p class="text-xs font-medium text-gray-700">Support</p>
                <p class="text-xs text-gray-500">24/7 assistance</p>
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
                this.classList.add('active');
                const type = this.dataset.type;
                document.getElementById('paymentType').value = type;

                // Show/hide till/paybill fields
                const tillPaybillContainer = document.getElementById('tillPaybillContainer');
                const accountContainer = document.getElementById('accountContainer');
                const tillPaybillLabel = document.getElementById('tillPaybillLabel');

                if (type === 'till') {
                    tillPaybillContainer.style.display = 'block';
                    accountContainer.style.display = 'none';
                    tillPaybillLabel.textContent = 'Till Number';
                } else if (type === 'paybill') {
                    tillPaybillContainer.style.display = 'block';
                    accountContainer.style.display = 'block';
                    tillPaybillLabel.textContent = 'Paybill Number';
                } else {
                    tillPaybillContainer.style.display = 'none';
                    accountContainer.style.display = 'none';
                }
            });
        });

        // Quick amount selection
        document.querySelectorAll('.quick-amount').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('amountInput').value = this.dataset.amount;
                document.querySelectorAll('.quick-amount').forEach(b => {
                    b.classList.remove('border-green-500', 'text-green-600', 'bg-green-50');
                });
                this.classList.add('border-green-500', 'text-green-600', 'bg-green-50');
            });
        });

        // Phone number formatting
        document.getElementById('phoneInput').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = value.substring(1);
            }
            if (value.startsWith('254')) {
                value = value.substring(3);
            }
            e.target.value = value.substring(0, 9);
        });

        // Form submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');

            btn.disabled = true;
            btnText.textContent = 'Processing...';
            btnLoader.classList.remove('hidden');
        });
    </script>
</body>
</html>

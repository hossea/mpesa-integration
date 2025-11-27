<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>M-PESA Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade { transition: all 0.3s ease; }
    </style>
    <script>
        function toggleFields() {
            const type = document.getElementById("paymentType").value;

            // Hide all
            document.getElementById("phoneField").classList.add("hidden");

            // Show relevant fields (all payment types need phone + amount)
            if (type === "send" || type === "till" || type === "paybill") {
                document.getElementById("phoneField").classList.remove("hidden");
            }
        }
    </script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md fade">
        <h1 class="text-2xl font-bold text-gray-700 mb-6 text-center">M-PESA Payment</h1>

        {{-- SUCCESS MESSAGE --}}
        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-3 rounded mb-5 text-center">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('mpesa.process') }}" method="POST" class="space-y-4">
            @csrf

            {{-- Payment Type --}}
            <div>
                <label class="block text-gray-600 mb-1 font-medium">Payment Type</label>
                <select id="paymentType" name="type"
                        onchange="toggleFields()"
                        class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-green-400">
                    <option value="till" selected>Buy Goods (Till)</option>
                    <option value="paybill">Paybill</option>
                    <option value="send">Send Money</option>
                </select>
            </div>

            {{-- Phone Number (All types need it) --}}
            <div id="phoneField" class="fade">
                <label class="block text-gray-600 mb-1 font-medium">Phone Number</label>
                <input type="text" name="phone" placeholder="2547XXXXXXXX"
                       class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-green-400" required>
            </div>

            {{-- Amount --}}
            <div>
                <label class="block text-gray-600 mb-1 font-medium">Amount</label>
                <input type="number" name="amount" placeholder="Enter amount"
                       class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-green-400" required>
            </div>

            {{-- Hidden Till/Paybill Defaults --}}
            <input type="hidden" name="till" value="123456">
            <input type="hidden" name="paybill" value="987654">

            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-semibold text-lg transition">
                Pay with M-PESA
            </button>
        </form>
    </div>

</body>
</html>

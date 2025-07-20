<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sistem Pembayaran PPP</title>
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif; /* Menggunakan font Inter */
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .package-card {
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            height: 100%;
            border-radius: 10px; /* Sudut membulat */
        }
        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        .selected-package {
            border: 2px solid #0d6efd;
            background-color: #f8f9fa;
        }
        #payment-history {
            max-height: 400px;
            overflow-y: auto;
        }
        .badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 5px; /* Sudut membulat */
        }
        .spinner-container {
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
            border-radius: 8px 8px 0 0; /* Sudut membulat hanya di atas */
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            background-color: #e9ecef;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            border-radius: 8px; /* Sudut membulat */
            padding: 10px 20px;
            font-weight: 600;
            transition: background-color 0.3s, border-color 0.3s, box-shadow 0.3s;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
        }
        .btn-secondary {
            border-radius: 8px; /* Sudut membulat */
        }
        .modal-content {
            border-radius: 15px; /* Sudut membulat */
        }
        .modal-header {
            border-radius: 15px 15px 0 0; /* Sudut membulat hanya di atas */
        }
        .input-group-text {
            border-radius: 8px 0 0 8px; /* Sudut membulat */
        }
        .form-control {
            border-radius: 0 8px 8px 0; /* Sudut membulat */
        }
        .input-group .form-control:focus {
            z-index: 0; /* Fix for Bootstrap 5 focus issue with input-group */
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-wifi me-2"></i>Pembayaran Pelanggan PPPoE</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text rounded-start-pill"><i class="fas fa-user"></i></span>
                                    <input type="text" id="username" class="form-control rounded-end-pill" placeholder="Masukkan username atau ID pelanggan">
                                    <button class="btn btn-primary ms-2 rounded-pill" onclick="searchUser()">
                                        <i class="fas fa-search me-2"></i>Cari
                                    </button>
                                </div>
                                <div id="error-message" class="text-danger mt-2"></div>
                            </div>
                        </div>

                        <!-- User Info Section -->
                        <div id="user-info" class="d-none">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-info text-white rounded-top-lg">
                                            <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Informasi Pelanggan</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <strong><i class="fas fa-user me-2"></i>Username:</strong> <span id="user-username"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong><i class="fas fa-circle me-2"></i>Status:</strong> <span id="user-status" class="badge"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong><i class="fas fa-wallet me-2"></i>Saldo:</strong> Rp<span id="user-balance"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong><i class="fas fa-calendar-alt me-2"></i>Berlaku hingga:</strong> <span id="user-expired"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-info text-white rounded-top-lg">
                                            <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Paket Saat Ini</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <strong><i class="fas fa-tag me-2"></i>Nama Paket:</strong> <span id="current-package-name"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong><i class="fas fa-tachometer-alt me-2"></i>Kecepatan:</strong> <span id="current-package-speed"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong><i class="fas fa-money-bill-wave me-2"></i>Harga:</strong> Rp<span id="current-package-price"></span>
                                            </div>
                                            <button id="renew-package-btn" class="btn btn-sm btn-primary mt-2 rounded-pill" onclick="payCurrentPackage()">
                                                <i class="fas fa-sync-alt me-2"></i>Perpanjang Paket
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Options Section -->
                <div id="payment-section" class="d-none">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white rounded-top-lg">
                            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Pilihan Pembayaran</h4>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-4" id="paymentTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="packages-tab" data-bs-toggle="tab" data-bs-target="#packages" type="button" role="tab">
                                        <i class="fas fa-boxes me-2"></i>Pilih Paket
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="deposit-tab" data-bs-toggle="tab" data-bs-target="#deposit" type="button" role="tab">
                                        <i class="fas fa-money-bill-wave me-2"></i>Deposit Manual
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="paymentTabContent">
                                <div class="tab-pane fade show active" id="packages" role="tabpanel">
                                    <div class="row" id="package-list">
                                        <div class="col-12 text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2">Memuat daftar paket...</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="deposit" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="amount" class="form-label">Jumlah Deposit</label>
                                                <div class="input-group">
                                                    <span class="input-group-text rounded-start-pill">Rp</span>
                                                    <input type="number" id="amount" class="form-control rounded-end-pill" placeholder="Minimal Rp10.000" min="10000">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="deposit-note" class="form-label">Catatan (Opsional)</label>
                                                <textarea id="deposit-note" class="form-control rounded-lg" rows="2" placeholder="Contoh: Deposit bulan Juni"></textarea>
                                            </div>
                                            <button class="btn btn-primary rounded-pill" onclick="payManual()">
                                                <i class="fas fa-money-bill-wave me-2"></i>Deposit Sekarang
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-info rounded-lg">
                                                <h5><i class="fas fa-info-circle me-2"></i>Informasi Deposit</h5>
                                                <ul class="mb-0">
                                                    <li>Deposit akan ditambahkan ke saldo pelanggan</li>
                                                    <li>Saldo dapat digunakan untuk pembelian paket</li>
                                                    <li>Minimal deposit Rp10.000</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History Section -->
                    <div class="card">
                        <div class="card-header bg-primary text-white rounded-top-lg">
                            <h4 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Pembayaran</h4>
                        </div>
                        <div class="card-body">
                            <div id="payment-history">
                                <table class="table table-striped table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-calendar me-2"></i>Tanggal</th>
                                            <th><i class="fas fa-money-bill-wave me-2"></i>Jumlah</th>
                                            <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                            <th><i class="fas fa-sticky-note me-2"></i>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="payment-history-body">
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <p class="mt-2">Memuat riwayat pembayaran...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-xl">
                <div class="modal-header bg-primary text-white rounded-top-xl">
                    <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Konfirmasi Pembayaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="payment-confirmation-text"></p>
                    <div class="alert alert-info rounded-lg">
                        <i class="fas fa-info-circle me-2"></i>
                        Anda akan diarahkan ke halaman pembayaran Midtrans
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill" id="confirm-payment-btn">
                        <i class="fas fa-check me-2"></i>Lanjutkan Pembayaran
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Base API URL, make sure this matches your Laravel API prefix
        const API_BASE = '{{ url('/api') }}'; 
        let currentUser = null;
        let availablePackages = [];
        let selectedPackage = null; // This will hold the selected package object
        let paymentModal = null;

        document.addEventListener('DOMContentLoaded', function() {
            paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            
            // Enable search on Enter key
            document.getElementById('username').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchUser();
                }
            });
        });

        async function searchUser() {
            const usernameInput = document.getElementById('username');
            const errorElement = document.getElementById('error-message');
            const identifier = usernameInput.value.trim();
            
            // Reset display immediately at the very beginning of the function
            errorElement.textContent = ''; 
            document.getElementById('user-info').classList.add('d-none');
            document.getElementById('payment-section').classList.add('d-none');

            if (!identifier) {
                errorElement.textContent = "Silakan masukkan username atau ID pelanggan";
                return;
            }

            try {
                errorElement.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mencari pelanggan...';
                
                // Try as username first
                let userRes = await fetch(`${API_BASE}/ppp-users/by-username/${encodeURIComponent(identifier)}`);
                
                // If not found, try as ID
                if (userRes.status === 404 && /^\d+$/.test(identifier)) {
                    userRes = await fetch(`${API_BASE}/ppp-users/by-id/${identifier}`);
                }

                if (!userRes.ok) {
                    // If response is not OK, try to parse JSON for more specific error messages
                    const errorData = await userRes.json();
                    throw new Error(errorData.message || "Pelanggan tidak ditemukan");
                }

                const response = await userRes.json();
                
                if (!response.success) {
                    throw new Error(response.message || "Data pelanggan tidak valid");
                }

                currentUser = response.data;
                displayUserInfo(currentUser);
                await loadAvailablePackages();
                await loadPaymentHistory();
                
                document.getElementById('payment-section').classList.remove('d-none');
                // Clear the spinner/loading message explicitly on success
                errorElement.textContent = ''; 
            } catch (error) {
                let errorMessage = error.message;
                // Add more informative message if the error is "Validation Error"
                if (errorMessage === "Validation Error") {
                    errorMessage += ". Pastikan username atau ID pelanggan valid dan tidak mengandung karakter yang tidak diizinkan.";
                }
                errorElement.textContent = errorMessage;
                console.error('Error:', error);
            }
        }

        function displayUserInfo(user) {
            const userInfoSection = document.getElementById('user-info');
            userInfoSection.classList.remove('d-none');
            
            document.getElementById('user-username').textContent = user.username;
            
            const statusBadge = document.getElementById('user-status');
            statusBadge.textContent = user.status;
            statusBadge.className = 'badge ' + (
                user.status === 'active' ? 'bg-success' : 
                user.status === 'suspended' ? 'bg-warning' : 'bg-danger'
            );
            
            document.getElementById('user-balance').textContent = parseFloat(user.balance).toLocaleString('id-ID');
            document.getElementById('user-expired').textContent = new Date(user.expired_at).toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            if (user.package) {
                document.getElementById('current-package-name').textContent = user.package.name;
                document.getElementById('current-package-speed').textContent = user.package.speed_limit;
                document.getElementById('current-package-price').textContent = parseFloat(user.package.price).toLocaleString('id-ID');
            } else {
                // Clear current package info if user has no package
                document.getElementById('current-package-name').textContent = '-';
                document.getElementById('current-package-speed').textContent = '-';
                document.getElementById('current-package-price').textContent = '0';
            }
        }

        async function loadAvailablePackages() {
            try {
                const packageList = document.getElementById('package-list');
                packageList.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat daftar paket...</p>
                    </div>
                `;
                
                const packageRes = await fetch(`${API_BASE}/packages/active`);
                if (!packageRes.ok) {
                    throw new Error("Gagal memuat daftar paket");
                }
                
                const response = await packageRes.json();
                availablePackages = response.data || response; // Adjust based on your API response structure
                
                packageList.innerHTML = '';
                
                if (availablePackages.length === 0) {
                    packageList.innerHTML = `
                        <div class="col-12 text-center py-4">
                            <p class="text-muted">Tidak ada paket tersedia</p>
                        </div>
                    `;
                    return;
                }
                
                availablePackages.forEach(pkg => {
                    const packageItem = document.createElement('div');
                    packageItem.className = 'col-md-6 mb-3';
                    packageItem.innerHTML = `
                        <div class="card package-card h-100" data-package-id="${pkg.id}">
                            <div class="card-header bg-secondary text-white rounded-top-lg">
                                <h5 class="card-title mb-0"><i class="fas fa-box-open me-2"></i>${pkg.name}</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><i class="fas fa-tachometer-alt me-2"></i>${pkg.speed_limit}</p>
                                <p class="card-text"><i class="fas fa-calendar-day me-2"></i>${pkg.duration_days} Hari</p>
                                <h4 class="text-primary">Rp${parseFloat(pkg.price).toLocaleString('id-ID')}</h4>
                            </div>
                            <div class="card-footer bg-transparent">
                                <button class="btn btn-primary w-100 rounded-pill" onclick="event.stopPropagation(); showPaymentConfirmation(${pkg.price}, 'Pembelian paket ${pkg.name}', ${pkg.id})">
                                    <i class="fas fa-shopping-cart me-2"></i>Pilih Paket
                                </button>
                            </div>
                        </div>
                    `;
                    packageList.appendChild(packageItem);
                });
            } catch (error) {
                document.getElementById('error-message').textContent = "Gagal memuat daftar paket";
                console.error('Error loading packages:', error);
            }
        }

        async function loadPaymentHistory() {
            try {
                if (!currentUser || !currentUser.id) return;
                
                const historyBody = document.getElementById('payment-history-body');
                historyBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Memuat riwayat pembayaran...</p>
                        </td>
                    </tr>
                `;
                
                const historyRes = await fetch(`${API_BASE}/payments/history/${currentUser.id}`);
                if (!historyRes.ok) {
                    throw new Error("Gagal memuat riwayat pembayaran");
                }
                
                const response = await historyRes.json();
                const payments = response.data || response; // Adjust based on your API response structure
                
                historyBody.innerHTML = '';
                
                if (payments.length === 0) {
                    historyBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4">Tidak ada riwayat pembayaran</td>
                        </tr>
                    `;
                    return;
                }
                
                payments.forEach(payment => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${new Date(payment.created_at).toLocaleDateString('id-ID', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric'
                        })}</td>
                        <td>Rp${parseFloat(payment.amount).toLocaleString('id-ID')}</td>
                        <td>
                            <span class="badge ${payment.status === 'success' || payment.status === 'settlement' ? 'bg-success' : 
                                payment.status === 'pending' || payment.status === 'challenge' ? 'bg-warning' : 'bg-danger'}">
                                ${payment.status}
                            </span>
                        </td>
                        <td>${payment.description || '-'}</td>
                    `;
                    historyBody.appendChild(row);
                });
            } catch (error) {
                console.error('Error loading payment history:', error);
                document.getElementById('payment-history-body').innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>Gagal memuat riwayat
                        </td>
                    </tr>
                `;
            }
        }

        // Modified to accept packageId
        function showPaymentConfirmation(amount, description, packageId = null) {
            if (!currentUser) {
                showAlert('danger', 'Silakan cari pelanggan terlebih dahulu.');
                return;
            }
            
            const confirmationText = document.getElementById('payment-confirmation-text');
            confirmationText.innerHTML = `
                <p>Anda akan melakukan pembayaran untuk:</p>
                <div class="alert alert-light rounded-lg">
                    <h5 class="mb-2">${description}</h5>
                    <p class="mb-1"><strong>Jumlah:</strong> Rp${parseFloat(amount).toLocaleString('id-ID')}</p>
                    <p class="mb-0"><strong>Pelanggan:</strong> ${currentUser.username}</p>
                </div>
            `;
            
            const confirmBtn = document.getElementById('confirm-payment-btn');
            confirmBtn.onclick = function() {
                processPayment(amount, description, packageId); // Pass packageId
                paymentModal.hide();
            };
            
            paymentModal.show();
        }

        function payCurrentPackage() {
            if (!currentUser?.package) {
                showAlert('danger', "Tidak dapat menemukan paket saat ini untuk diperpanjang.");
                return;
            }
            
            showPaymentConfirmation(
                currentUser.package.price, 
                `Perpanjangan paket ${currentUser.package.name}`,
                currentUser.package.id // Pass the current package ID
            );
        }

        function payManual() {
            const amountInput = document.getElementById('amount');
            const amount = amountInput.value.trim();
            const note = document.getElementById('deposit-note').value.trim();
            const errorElement = document.getElementById('error-message');
            
            if (!amount || isNaN(amount) || parseFloat(amount) < 10000) {
                errorElement.textContent = "Masukkan jumlah deposit minimal Rp10.000";
                amountInput.focus();
                return;
            }
            
            errorElement.textContent = '';
            const description = note ? `Deposit: ${note}` : "Deposit saldo";
            // For manual deposit, packageId is null
            showPaymentConfirmation(amount, description, null); 
        }

        // Modified to accept packageId
        async function processPayment(amount, description, packageId = null) {
            const errorElement = document.getElementById('error-message');
            errorElement.textContent = ''; // Clear error message at the start of payment process
            
            console.log('Memulai proses pembayaran...');
            console.log('Data yang akan dikirim ke backend:', {
                user_id: currentUser.id,
                amount: parseFloat(amount),
                description: description,
                package_id: packageId
            });

            try {
                const res = await fetch(`${API_BASE}/payments/process`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: currentUser.id,
                        amount: parseFloat(amount),
                        description: description,
                        package_id: packageId // Send packageId to the backend
                    })
                });

                console.log('Respons dari backend (raw):', res);
                const data = await res.json();
                console.log('Respons dari backend (parsed JSON):', data);
                
                if (!data.success) {
                    throw new Error(data.message || "Gagal memproses pembayaran");
                }

                console.log('Snap token diterima:', data.snap_token);
                // Open Midtrans payment popup
                snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        console.log('Pembayaran Midtrans berhasil:', result);
                        showAlert('success', 'Pembayaran berhasil!');
                        searchUser(); // Refresh user data after successful payment
                    },
                    onPending: function(result) {
                        console.log('Pembayaran Midtrans menunggu konfirmasi:', result);
                        showAlert('info', 'Pembayaran menunggu konfirmasi');
                        searchUser(); // Refresh user data to show pending status
                    },
                    onError: function(error) {
                        console.error('Pembayaran Midtrans gagal:', error);
                        showAlert('danger', 'Pembayaran gagal: ' + (error.message || 'Silakan coba lagi'));
                    },
                    onClose: function() {
                        console.log('Payment popup closed by user');
                        showAlert('info', 'Pembayaran dibatalkan atau ditutup.');
                        // You might want to refresh user data here too, or not, depending on desired UX
                        searchUser(); 
                    }
                });
            } catch (error) {
                console.error("Terjadi kesalahan saat memproses pembayaran:", error);
                errorElement.textContent = error.message;
                showAlert('danger', error.message);
            }
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3 rounded-lg`;
            alertDiv.style.zIndex = '9999';
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }, 5000);
        }
    </script>
</body>
</html>

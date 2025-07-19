<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sistem Pembayaran PPP</title>
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .package-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .selected-package {
            border: 2px solid #0d6efd;
            background-color: #f8f9fa;
        }
        #payment-history {
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Pembayaran Pelanggan</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="text" id="username" class="form-control" placeholder="Masukkan username atau ID pelanggan">
                                    <button class="btn btn-primary" onclick="searchUser()">
                                        <i class="fas fa-search me-2"></i>Cari
                                    </button>
                                </div>
                                <div id="error-message" class="text-danger mt-2"></div>
                            </div>
                        </div>

                        <!-- User Info Section -->
                        <div id="user-info" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0">Informasi Pelanggan</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <strong>Username:</strong> <span id="user-username"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Status:</strong> <span id="user-status" class="badge bg-success"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Saldo:</strong> Rp<span id="user-balance"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Berlaku hingga:</strong> <span id="user-expired"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0">Paket Saat Ini</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <strong>Nama Paket:</strong> <span id="current-package-name"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Kecepatan:</strong> <span id="current-package-speed"></span>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Harga:</strong> Rp<span id="current-package-price"></span>
                                            </div>
                                            <button id="renew-package-btn" class="btn btn-sm btn-primary mt-2" onclick="payCurrentPackage()">
                                                Perpanjang Paket
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Options Section -->
                <div id="payment-section" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Pilihan Pembayaran</h4>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-4" id="paymentTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="packages-tab" data-bs-toggle="tab" data-bs-target="#packages" type="button" role="tab">Pilih Paket</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="deposit-tab" data-bs-toggle="tab" data-bs-target="#deposit" type="button" role="tab">Deposit Manual</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="paymentTabContent">
                                <div class="tab-pane fade show active" id="packages" role="tabpanel">
                                    <div class="row" id="package-list">
                                        <!-- Packages will be loaded here -->
                                        <div class="col-12 text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="deposit" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="amount" class="form-label">Jumlah Deposit</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" id="amount" class="form-control" placeholder="Minimal Rp10.000" min="10000">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="deposit-note" class="form-label">Catatan (Opsional)</label>
                                                <textarea id="deposit-note" class="form-control" rows="2"></textarea>
                                            </div>
                                            <button class="btn btn-primary" onclick="payManual()">
                                                <i class="fas fa-money-bill-wave me-2"></i>Deposit Sekarang
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History Section -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Riwayat Pembayaran</h4>
                        </div>
                        <div class="card-body">
                            <div id="payment-history">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="payment-history-body">
                                        <!-- Payment history will be loaded here -->
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="payment-confirmation-text"></p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Anda akan diarahkan ke halaman pembayaran Midtrans
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="confirm-payment-btn">Lanjutkan Pembayaran</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        const API_BASE = '{{ url('/api') }}';
        let currentUser = null;
        let selectedPackage = null;
        let paymentModal = null;

        document.addEventListener('DOMContentLoaded', function() {
            paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        });

        async function searchUser() {
            const usernameInput = document.getElementById('username');
            const errorElement = document.getElementById('error-message');
            const identifier = usernameInput.value.trim();
            
            // Reset display
            errorElement.textContent = '';
            document.getElementById('user-info').style.display = 'none';
            document.getElementById('payment-section').style.display = 'none';

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
                    throw new Error("Pelanggan tidak ditemukan");
                }

                const response = await userRes.json();
                
                if (!response.success) {
                    throw new Error(response.message || "Data pelanggan tidak valid");
                }

                currentUser = response.data;
                displayUserInfo(currentUser);
                await loadAvailablePackages();
                await loadPaymentHistory();
                
                document.getElementById('payment-section').style.display = 'block';
                errorElement.textContent = '';
            } catch (error) {
                errorElement.textContent = error.message;
                console.error('Error:', error);
            }
        }

        function displayUserInfo(user) {
            const userInfoSection = document.getElementById('user-info');
            userInfoSection.style.display = 'block';
            
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
            }
        }

        async function loadAvailablePackages() {
            try {
                const packageList = document.getElementById('package-list');
                packageList.innerHTML = `
                    <div class="col-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;
                
                const packageRes = await fetch(`${API_BASE}/packages/active`);
                if (!packageRes.ok) {
                    throw new Error("Gagal memuat daftar paket");
                }
                
                const response = await packageRes.json();
                const packages = response.data || response;
                
                packageList.innerHTML = '';
                
                packages.forEach(pkg => {
                    const packageItem = document.createElement('div');
                    packageItem.className = 'col-md-6 mb-3';
                    packageItem.innerHTML = `
                        <div class="card package-card h-100" onclick="selectPackage(this, ${pkg.id})" data-package-id="${pkg.id}">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">${pkg.name}</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><i class="fas fa-tachometer-alt me-2"></i>${pkg.speed_limit}</p>
                                <p class="card-text"><i class="fas fa-calendar-day me-2"></i>${pkg.duration_days} Hari</p>
                                <h4 class="text-primary">Rp${parseFloat(pkg.price).toLocaleString('id-ID')}</h4>
                            </div>
                            <div class="card-footer bg-transparent">
                                <button class="btn btn-primary w-100" onclick="showPaymentConfirmation(${pkg.price}, 'Pembelian paket ${pkg.name}')">
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

        function selectPackage(element, packageId) {
            // Remove selected class from all packages
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected-package');
            });
            
            // Add selected class to clicked package
            element.classList.add('selected-package');
            
            // Find the selected package from available packages
            selectedPackage = availablePackages.find(pkg => pkg.id == packageId);
        }

        async function loadPaymentHistory() {
            try {
                if (!currentUser || !currentUser.id) return;
                
                const historyBody = document.getElementById('payment-history-body');
                historyBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </td>
                    </tr>
                `;
                
                const historyRes = await fetch(`${API_BASE}/payments/history/${currentUser.id}`);
                if (!historyRes.ok) {
                    throw new Error("Gagal memuat riwayat pembayaran");
                }
                
                const response = await historyRes.json();
                const payments = response.data || response;
                
                historyBody.innerHTML = '';
                
                if (payments.length === 0) {
                    historyBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center">Tidak ada riwayat pembayaran</td>
                        </tr>
                    `;
                    return;
                }
                
                payments.forEach(payment => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${new Date(payment.created_at).toLocaleDateString('id-ID')}</td>
                        <td>Rp${parseFloat(payment.amount).toLocaleString('id-ID')}</td>
                        <td>
                            <span class="badge ${payment.status === 'success' ? 'bg-success' : 
                              payment.status === 'pending' ? 'bg-warning' : 'bg-danger'}">
                                ${payment.status}
                            </span>
                        </td>
                        <td>${payment.description || '-'}</td>
                    `;
                    historyBody.appendChild(row);
                });
            } catch (error) {
                console.error('Error loading payment history:', error);
            }
        }

        function showPaymentConfirmation(amount, description) {
            if (!currentUser) return;
            
            const confirmationText = document.getElementById('payment-confirmation-text');
            confirmationText.innerHTML = `
                Anda akan melakukan pembayaran untuk:<br>
                <strong>${description}</strong><br>
                Sebesar: <strong>Rp${parseFloat(amount).toLocaleString('id-ID')}</strong><br>
                Pelanggan: <strong>${currentUser.username}</strong>
            `;
            
            const confirmBtn = document.getElementById('confirm-payment-btn');
            confirmBtn.onclick = function() {
                processPayment(amount, description);
                paymentModal.hide();
            };
            
            paymentModal.show();
        }

        function payCurrentPackage() {
            if (!currentUser?.package) {
                document.getElementById('error-message').textContent = "Tidak dapat menemukan paket saat ini";
                return;
            }
            
            showPaymentConfirmation(
                currentUser.package.price, 
                `Perpanjangan paket ${currentUser.package.name}`
            );
        }

        function payManual() {
            const amount = document.getElementById('amount').value.trim();
            const note = document.getElementById('deposit-note').value.trim();
            
            if (!amount || isNaN(amount) || amount < 10000) {
                document.getElementById('error-message').textContent = "Masukkan jumlah deposit minimal Rp10.000";
                return;
            }
            
            const description = note ? `Deposit: ${note}` : "Deposit saldo";
            showPaymentConfirmation(amount, description);
        }

        async function processPayment(amount, description) {
            try {
                const res = await fetch(`${API_BASE}/payments/process`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        user_id: currentUser.id,
                        amount: amount,
                        description: description
                    })
                });

                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.message || "Gagal memproses pembayaran");
                }

                // Open Midtrans payment popup
                snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        alert('Pembayaran berhasil!');
                        searchUser(); // Refresh user data
                    },
                    onPending: function(result) {
                        alert('Pembayaran menunggu konfirmasi');
                        searchUser(); // Refresh user data
                    },
                    onError: function(error) {
                        alert('Pembayaran gagal: ' + error.message);
                    }
                });
            } catch (error) {
                console.error("Payment error:", error);
                document.getElementById('error-message').textContent = error.message;
            }
        }
    </script>
</body>
</html>
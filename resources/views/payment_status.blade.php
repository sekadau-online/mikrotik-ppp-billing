<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .status-card {
            max-width: 500px;
            text-align: center;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-pending { color: #ffc107; }
    </style>
</head>
<body>
    <div class="card status-card">
        @if($status === 'success')
            <i class="fas fa-check-circle status-icon status-success"></i>
            <h1 class="text-success">Pembayaran Berhasil!</h1>
        @elseif($status === 'error')
            <i class="fas fa-times-circle status-icon status-error"></i>
            <h1 class="text-danger">Pembayaran Gagal!</h1>
        @else
            <i class="fas fa-hourglass-half status-icon status-pending"></i>
            <h1 class="text-warning">Pembayaran Menunggu!</h1>
        @endif

        <p class="lead">{{ $message }}</p>
        @if(isset($order_id) && $order_id !== 'N/A')
            <p><strong>Order ID:</strong> {{ $order_id }}</p>
        @endif
        <a href="{{ route('customer.payment.index') }}" class="btn btn-primary mt-3">Kembali ke Halaman Pembayaran</a>
    </div>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement En Cours</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .pending-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .pending-icon {
            font-size: 64px;
            color: #f39c12;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        h1 {
            color: #f39c12;
            margin-bottom: 20px;
        }
        .pending-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
        }
        .transaction-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-refresh {
            background-color: #3498db;
            color: white;
        }
        .btn-refresh:hover {
            background-color: #2980b9;
        }
        .btn-home {
            background-color: #95a5a6;
            color: white;
        }
        .btn-home:hover {
            background-color: #7f8c8d;
        }
        .auto-refresh-info {
            margin-top: 20px;
            color: #999;
            font-size: 14px;
        }
    </style>
    <script>
        // Auto-refresh every 5 seconds
        setTimeout(function() {
            window.location.reload();
        }, 5000);
    </script>
</head>
<body>
    <div class="pending-container">
        <div class="pending-icon">⏳</div>
        <h1>Paiement En Cours</h1>
        <p class="pending-message">Votre paiement est en cours de traitement. Veuillez patienter...</p>
        
        <div class="transaction-details">
            <div class="detail-row">
                <span class="detail-label">ID de Transaction:</span>
                <span class="detail-value">{{ $transaction_id }}</span>
            </div>
            @if(isset($gateway))
            <div class="detail-row">
                <span class="detail-label">Passerelle de Paiement:</span>
                <span class="detail-value">
                    <img src="{{ $gateway->getLogoPath() }}" alt="{{ $gateway->getDisplayName() }}" style="height: 20px; vertical-align: middle; margin-right: 8px;">
                    {{ $gateway->getDisplayName() }}
                </span>
            </div>
            @elseif(isset($gatewayName))
            <div class="detail-row">
                <span class="detail-label">Passerelle de Paiement:</span>
                <span class="detail-value">{{ $gatewayName }}</span>
            </div>
            @endif
        </div>
        
        <div class="button-group">
            <button onclick="window.location.reload()" class="btn btn-refresh">Actualiser</button>
            <a href="/" class="btn btn-home">Retour à l'accueil</a>
        </div>
        
        <p class="auto-refresh-info">Cette page se rafraîchira automatiquement dans 5 secondes...</p>
    </div>
</body>
</html>

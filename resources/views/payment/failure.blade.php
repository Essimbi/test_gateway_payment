<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Échoué</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .failure-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .failure-icon {
            font-size: 64px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .failure-message {
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
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .amount {
            font-size: 20px;
            color: #e74c3c;
            font-weight: bold;
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
        .btn-retry {
            background-color: #f39c12;
            color: white;
        }
        .btn-retry:hover {
            background-color: #e67e22;
        }
        .btn-home {
            background-color: #3498db;
            color: white;
        }
        .btn-home:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="failure-container">
        <div class="failure-icon">✗</div>
        <h1>Paiement Échoué</h1>
        <p class="failure-message">{{ $message ?? 'Votre paiement n\'a pas pu être traité.' }}</p>
        
        <div class="transaction-details">
            <div class="detail-row">
                <span class="detail-label">ID de Transaction:</span>
                <span class="detail-value">{{ $transaction_id }}</span>
            </div>
            @if(isset($amount))
            <div class="detail-row">
                <span class="detail-label">Montant:</span>
                <span class="detail-value amount">{{ number_format($amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
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
            <a href="/payment/summary?amount={{ $amount ?? 0 }}" class="btn btn-retry">Réessayer</a>
            <a href="/" class="btn btn-home">Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récapitulatif du Paiement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .summary-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .amount {
            font-size: 24px;
            color: #2ecc71;
            font-weight: bold;
        }
        .button-group {
            margin-top: 30px;
            display: flex;
            gap: 15px;
        }
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        .btn-confirm {
            background-color: #2ecc71;
            color: white;
        }
        .btn-confirm:hover {
            background-color: #27ae60;
        }
        .btn-cancel {
            background-color: #e74c3c;
            color: white;
        }
        .btn-cancel:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>
    <div class="summary-container">
        <h1>Récapitulatif du Paiement</h1>
        
        @if($errors->any())
            <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: left; border: 1px solid #f5c6cb;">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        @if(session('error'))
            <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; border: 1px solid #f5c6cb;">
                {{ session('error') }}
            </div>
        @endif
        
        @if(session('info'))
            <div style="background-color: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; border: 1px solid #bee5eb;">
                {{ session('info') }}
            </div>
        @endif
        
        <div class="detail-row">
            <span class="detail-label">Montant:</span>
            <span class="detail-value amount">{{ number_format($amount, 0, ',', ' ') }} FCFA</span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">ID de Transaction:</span>
            <span class="detail-value">{{ $transaction_id ?? 'Sera généré' }}</span>
        </div>
        
        @if(isset($gateway))
        <div class="detail-row">
            <span class="detail-label">Passerelle de Paiement:</span>
            <span class="detail-value">
                <img src="{{ $gateway->getLogoPath() }}" alt="{{ $gateway->getDisplayName() }}" style="height: 24px; vertical-align: middle; margin-right: 8px;">
                {{ $gateway->getDisplayName() }}
            </span>
        </div>
        @endif
        
        <div class="button-group">
            <form action="{{ route('payment.initiate') }}" method="POST" style="flex: 1;">
                @csrf
                @if($transaction_id)
                <input type="hidden" name="transaction_id" value="{{ $transaction_id }}">
                @endif
                <input type="hidden" name="amount" value="{{ $amount }}">
                <input type="hidden" name="gateway_type" value="{{ $gateway->value }}">
                <button type="submit" class="btn btn-confirm">Confirmer</button>
            </form>
            
            @if($transaction_id)
            <a href="{{ route('payment.cancel', ['transactionId' => $transaction_id]) }}" class="btn btn-cancel">Annuler</a>
            @else
            <a href="{{ route('payment.select-gateway', ['amount' => $amount]) }}" class="btn btn-cancel">Annuler</a>
            @endif
        </div>
    </div>
</body>
</html>

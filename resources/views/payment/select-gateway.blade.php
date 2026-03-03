<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choisir une Passerelle de Paiement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .gateway-selection-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .gateways-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .gateway-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .gateway-card:hover:not(.disabled) {
            border-color: #3498db;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.2);
            transform: translateY(-2px);
        }
        .gateway-card.selected {
            border-color: #2ecc71;
            background-color: #f0fdf4;
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.2);
        }
        .gateway-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f8f9fa;
        }
        .gateway-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .gateway-logo {
            width: 100%;
            height: 80px;
            object-fit: contain;
            margin-bottom: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
        }
        .gateway-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .gateway-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            text-align: center;
            margin-bottom: 10px;
        }
        .gateway-status {
            text-align: center;
            margin-top: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        .status-unavailable {
            background-color: #f8d7da;
            color: #721c24;
        }
        .unavailable-reason {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 13px;
            color: #856404;
            text-align: center;
        }
        .selection-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #2ecc71;
            color: white;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
        }
        .gateway-card.selected .selection-indicator {
            display: flex;
        }
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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
            transition: background-color 0.3s ease;
        }
        .btn-continue {
            background-color: #2ecc71;
            color: white;
        }
        .btn-continue:hover:not(:disabled) {
            background-color: #27ae60;
        }
        .btn-continue:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }
        .btn-cancel {
            background-color: #e74c3c;
            color: white;
        }
        .btn-cancel:hover {
            background-color: #c0392b;
        }
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .no-gateways-message {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
        }
        .no-gateways-message h2 {
            color: #856404;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="gateway-selection-container">
        <h1>Choisir une Passerelle de Paiement</h1>
        <p class="subtitle">Sélectionnez votre méthode de paiement préférée</p>
        
        @if(session('error'))
            <div class="error-message">
                {{ session('error') }}
            </div>
        @endif
        
        @if(empty($availableGateways) && empty($unavailableGateways))
            <div class="no-gateways-message">
                <h2>Aucune Passerelle Disponible</h2>
                <p>Aucune passerelle de paiement n'est actuellement configurée. Veuillez contacter l'administrateur.</p>
            </div>
        @else
            <form action="{{ route('payment.summary') }}" method="GET" id="gateway-form">
                <div class="gateways-grid">
                    @foreach($availableGateways as $gateway)
                        <label class="gateway-card" for="gateway-{{ $gateway->value }}">
                            <input 
                                type="radio" 
                                name="gateway_type" 
                                id="gateway-{{ $gateway->value }}" 
                                value="{{ $gateway->value }}"
                                required
                            >
                            <div class="selection-indicator">✓</div>
                            
                            <img 
                                src="{{ $gateway->getLogoPath() }}" 
                                alt="{{ $gateway->getDisplayName() }} Logo" 
                                class="gateway-logo"
                                onerror="this.style.display='none'"
                            >
                            
                            <div class="gateway-name">{{ $gateway->getDisplayName() }}</div>
                            <div class="gateway-description">{{ $gateway->getDescription() }}</div>
                            
                            <div class="gateway-status">
                                <span class="status-badge status-available">Disponible</span>
                            </div>
                        </label>
                    @endforeach
                    
                    @foreach($unavailableGateways as $gateway)
                        <div class="gateway-card disabled">
                            <img 
                                src="{{ $gateway->getLogoPath() }}" 
                                alt="{{ $gateway->getDisplayName() }} Logo" 
                                class="gateway-logo"
                                onerror="this.style.display='none'"
                            >
                            
                            <div class="gateway-name">{{ $gateway->getDisplayName() }}</div>
                            <div class="gateway-description">{{ $gateway->getDescription() }}</div>
                            
                            <div class="gateway-status">
                                <span class="status-badge status-unavailable">Non Disponible</span>
                            </div>
                            
                            <div class="unavailable-reason">
                                Cette passerelle n'est pas configurée. Veuillez contacter l'administrateur pour l'activer.
                            </div>
                        </div>
                    @endforeach
                </div>
                
                @if(!empty($availableGateways))
                    <!-- Hidden fields to pass through payment data -->
                    @if(isset($amount))
                        <input type="hidden" name="amount" value="{{ $amount }}">
                    @endif
                    @if(isset($transaction_id))
                        <input type="hidden" name="transaction_id" value="{{ $transaction_id }}">
                    @endif
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-continue" id="continue-btn" disabled>
                            Continuer
                        </button>
                        <a href="/" class="btn btn-cancel">Annuler</a>
                    </div>
                @else
                    <div class="button-group">
                        <a href="/" class="btn btn-cancel" style="flex: none; padding: 15px 40px;">Retour à l'accueil</a>
                    </div>
                @endif
            </form>
        @endif
    </div>
    
    <script>
        // Handle gateway card selection
        document.querySelectorAll('.gateway-card:not(.disabled)').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.gateway-card').forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    
                    // Enable continue button
                    document.getElementById('continue-btn').disabled = false;
                }
            });
        });
        
        // Enable continue button if a gateway is already selected (e.g., on page reload)
        const selectedRadio = document.querySelector('input[type="radio"]:checked');
        if (selectedRadio) {
            selectedRadio.closest('.gateway-card').classList.add('selected');
            document.getElementById('continue-btn').disabled = false;
        }
    </script>
</body>
</html>

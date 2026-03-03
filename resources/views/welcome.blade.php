@extends('layouts.app')

@section('content')
<div style="max-width: 600px; margin: 2rem auto;">
    <h1 style="text-align: center; font-size: 2rem; margin-bottom: 1rem; font-weight: bold;">
        Système de Paiement
    </h1>
    
    <p style="text-align: center; color: #666; margin-bottom: 2rem;">
        Bienvenue sur notre plateforme de paiement sécurisée
    </p>

    @auth
        <div style="background: #fff; padding: 2rem; border: 1px solid #dee2e6; border-radius: 0.25rem; margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; font-weight: 600;">
                Initier un paiement
            </h2>

            <form method="GET" action="{{ route('payment.select-gateway') }}">
                <div style="margin-bottom: 1.5rem;">
                    <label for="amount" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                        Montant (FCFA)
                    </label>
                    <input 
                        id="amount" 
                        type="number" 
                        name="amount" 
                        min="100" 
                        step="1" 
                        value="1000"
                        required
                        style="width: 100%; padding: 0.75rem; border: 1px solid #ced4da; border-radius: 0.25rem; font-size: 1.125rem;"
                        placeholder="Entrez le montant"
                    >
                    <small style="color: #666; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                        Montant minimum: 100 FCFA
                    </small>
                </div>

                <button 
                    type="submit" 
                    style="width: 100%; padding: 0.75rem; background-color: #28a745; color: white; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 1rem; font-weight: 500;"
                >
                    Continuer vers le paiement
                </button>
            </form>
        </div>

        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 0.25rem; border-left: 4px solid #007bff;">
            <h3 style="font-size: 1.125rem; margin-bottom: 0.75rem; font-weight: 600;">
                Gateways de paiement disponibles
            </h3>
            <ul style="list-style: disc; padding-left: 1.5rem; color: #666;">
                <li>CinetPay</li>
                <li>Tranzak</li>
            </ul>
        </div>
    @else
        <div style="background: #fff3cd; padding: 1.5rem; border: 1px solid #ffc107; border-radius: 0.25rem; text-align: center;">
            <p style="margin-bottom: 1rem; color: #856404;">
                Vous devez être connecté pour effectuer un paiement
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a 
                    href="{{ route('login') }}" 
                    style="padding: 0.5rem 1.5rem; background-color: #007bff; color: white; text-decoration: none; border-radius: 0.25rem; display: inline-block;"
                >
                    Se connecter
                </a>
                <a 
                    href="{{ route('register') }}" 
                    style="padding: 0.5rem 1.5rem; background-color: #28a745; color: white; text-decoration: none; border-radius: 0.25rem; display: inline-block;"
                >
                    S'inscrire
                </a>
            </div>
        </div>
    @endauth
</div>
@endsection

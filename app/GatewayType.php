<?php

namespace App;

/**
 * Gateway Type Enum
 * 
 * Defines the supported payment gateway types.
 * Each gateway type includes helper methods for display information.
 * 
 * Validates: Requirements 5.2
 */
enum GatewayType: string
{
    case CINETPAY = 'cinetpay';
    case TRANZAK = 'tranzak';
    
    /**
     * Get the display name for the gateway
     * 
     * Returns a human-readable name suitable for UI display.
     * 
     * @return string Display name
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::CINETPAY => 'CinetPay',
            self::TRANZAK => 'Tranzak',
        };
    }
    
    /**
     * Get the description for the gateway
     * 
     * Returns a brief description of the payment methods supported
     * by this gateway.
     * 
     * @return string Gateway description
     */
    public function getDescription(): string
    {
        return match($this) {
            self::CINETPAY => 'Paiement via Mobile Money (Orange, MTN, Moov)',
            self::TRANZAK => 'Paiement via Mobile Money et cartes bancaires',
        };
    }
    
    /**
     * Get the logo path for the gateway
     * 
     * Returns the path to the gateway's logo image for UI display.
     * 
     * @return string Logo file path
     */
    public function getLogoPath(): string
    {
        return match($this) {
            self::CINETPAY => '/images/gateways/cinetpay.png',
            self::TRANZAK => '/images/gateways/tranzak.png',
        };
    }
}

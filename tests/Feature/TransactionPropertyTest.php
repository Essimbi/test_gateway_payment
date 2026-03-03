<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Feature: cinetpay-payment-integration, Property 1: Transaction Uniqueness
test('all generated transaction IDs are unique', function () {
    $transactionIds = [];
    
    // Generate 100 transactions
    for ($i = 0; $i < 100; $i++) {
        $transaction = Transaction::factory()->create();
        $transactionIds[] = $transaction->transaction_id;
    }
    
    // Verify all IDs are unique
    expect($transactionIds)
        ->toHaveCount(100)
        ->and(array_unique($transactionIds))
        ->toHaveCount(100);
});

// Feature: cinetpay-payment-integration, Property 2: Transaction User Association
test('every transaction is associated with exactly one authenticated user', function () {
    // Generate 100 transactions
    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);
        
        // Verify transaction is associated with exactly one user
        expect($transaction->user)->not->toBeNull()
            ->and($transaction->user->id)->toBe($user->id)
            ->and($transaction->user_id)->toBe($user->id);
    }
});

// Feature: cinetpay-payment-integration, Property 3: Transaction Amount Persistence
test('transaction amount persists correctly when retrieved from database', function () {
    // Generate 100 transactions with random amounts
    for ($i = 0; $i < 100; $i++) {
        $amount = fake()->randomFloat(2, 100, 100000);
        $transaction = Transaction::factory()->create(['amount' => $amount]);
        
        // Retrieve transaction from database
        $retrievedTransaction = Transaction::find($transaction->id);
        
        // Verify amount persists correctly
        expect($retrievedTransaction->amount)->toBe(number_format($amount, 2, '.', ''));
    }
});

// Feature: cinetpay-payment-integration, Property 12: Initial Status is PENDING
test('all newly created transactions have initial status PENDING', function () {
    // Generate 100 transactions
    for ($i = 0; $i < 100; $i++) {
        $transaction = Transaction::factory()->create();
        
        // Verify initial status is PENDING
        expect($transaction->status)->toBe(\App\PaymentStatus::PENDING)
            ->and($transaction->status->value)->toBe('pending');
    }
});

// Feature: cinetpay-payment-integration, Property 13: ACCEPTED is Terminal
test('ACCEPTED status cannot transition to any other status', function () {
    // Test 100 times with different target statuses
    for ($i = 0; $i < 100; $i++) {
        $transaction = Transaction::factory()->create(['status' => \App\PaymentStatus::ACCEPTED]);
        
        // Verify ACCEPTED is terminal
        expect($transaction->status->isTerminal())->toBeTrue()
            ->and($transaction->status->canTransitionTo(\App\PaymentStatus::PENDING))->toBeFalse()
            ->and($transaction->status->canTransitionTo(\App\PaymentStatus::ACCEPTED))->toBeFalse()
            ->and($transaction->status->canTransitionTo(\App\PaymentStatus::REFUSED))->toBeFalse();
    }
});

// Feature: cinetpay-payment-integration, Property 14: REFUSED is Terminal
test('REFUSED status cannot transition to any other status', function () {
    // Test 100 times with different target statuses
    for ($i = 0; $i < 100; $i++) {
        $transaction = Transaction::factory()->create(['status' => \App\PaymentStatus::REFUSED]);
        
        // Verify REFUSED is terminal
        expect($transaction->status->isTerminal())->toBeTrue()
            ->and($transaction->status->canTransitionTo(\App\PaymentStatus::PENDING))->toBeFalse()
            ->and($transaction->status->canTransitionTo(\App\PaymentStatus::ACCEPTED))->toBeFalse()
            ->and($transaction->status->canTransitionTo(\App\PaymentStatus::REFUSED))->toBeFalse();
    }
});

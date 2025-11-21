<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use App\Models\EventTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\Status;

class EventTransactionController extends Controller
{
    public function index(EventRegistration $registration)
    {
        $transactions = $registration->transactions()->latest()->get();
        return $this->successResponse($transactions, 'Transactions fetched successfully');
    }

    public function store(Request $request, EventRegistration $registration)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'payment_method' => 'nullable|string',
            'note' => 'nullable|string',
            'transaction_reference' => 'nullable|string',
        ]);

        $transaction = EventTransaction::updateOrCreate(
            [
                'event_registration_id' => $registration->id,
                'transaction_reference' => $validated['transaction_reference'],
            ],
            [
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? null,
                'status' => 'completed',
                'note' => $validated['note'] ?? null,
            ]
        );
        return $this->successResponse($transaction, 'Transaction saved successfully');
    }

    public function update(Request $request, EventTransaction $transaction)
    {
        $request->validate([
            'status' => ['required', Rule::in(Status::values())],
        ]);

        $transaction->update([
            'status' => $request->status
        ]);
        return $this->successResponse($transaction, 'Transaction updated successfully');
    }
}

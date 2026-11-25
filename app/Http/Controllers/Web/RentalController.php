<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RentalOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use DomainException;

class RentalController extends Controller
{
    public function create()
    {
        $items = DB::table('inventory_items')
            ->where('stock_quantity', '>', 0)
            ->where('rental_price', '>', 0)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('rental.form', compact('items'));
    }

    public function store(
        Request $request,
        RentalOrderService $service
    ) {
        $data = $request->validate([
            'rental_start_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'rental_end_date' => [
                'required',
                'date',
                'after_or_equal:rental_start_date',
            ],
            'rentals' => [
                'required',
                'array',
                'min:1',
            ],
            'rentals.*.inventory_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:inventory_items,id',
            ],
            'rentals.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'payment_method' => [
                'required',
                'string',
            ],
            'dp_amount' => [
                'nullable',
                'numeric',
                'min:1',
            ],
            'proof' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        try {
            $order = $service->create(
                Auth::user(),
                $data,
                $request->file('proof')
            );

            return redirect()
                ->route('pesanan.show', $order->code)
                ->with('success', 'Pesanan rental berhasil dikirim.');

        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                'rentals' => $e->getMessage(),
            ]);
        }
    }
}
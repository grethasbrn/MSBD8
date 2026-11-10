<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\HonorRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pengaturan sanggar (DP %, kuota harian, rekening, WhatsApp) dan tarif honor per peran. */
class SettingsController extends Controller
{
    private const RULES = [
        'dp_percent'          => 'numeric|min:1|max:100',
        'max_events_per_day'  => 'integer|min:1|max:20',
        'wa_number'           => 'regex:/^[0-9]{9,16}$/',
        'bank_name'           => 'string|max:60',
        'bank_account_number' => 'string|max:60',
        'bank_account_name'   => 'string|max:100',
        'qris_url'            => 'nullable|string|max:500',
    ];

    public function index(): JsonResponse
    {
        return response()->json(AppSetting::orderBy('key')->get(['key', 'value', 'description']));
    }

    public function update(Request $r): JsonResponse
    {
        $d = $r->validate(array_map(fn ($rule) => 'sometimes|' . $rule, self::RULES));
        foreach ($d as $key => $value) {
            AppSetting::where('key', $key)->update(['value' => (string) ($value ?? '')]);
        }
        return $this->index();
    }

    public function honorRates(): JsonResponse
    {
        return response()->json(HonorRate::orderBy('role')->get());
    }

    public function updateHonorRate(Request $r, string $role): JsonResponse
    {
        $d = $r->validate(['amount' => 'required|numeric|min:0']);
        $rate = HonorRate::findOrFail($role);
        $rate->update(['amount' => $d['amount']]);
        return response()->json($rate->refresh());
    }
}

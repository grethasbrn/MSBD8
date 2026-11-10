<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\GalleryItem;
use App\Models\InventoryItem;
use App\Models\Package;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CRUD data master untuk Owner: packages | addons | inventory | gallery. */
class MasterDataController extends Controller
{
    /** @return array{0: class-string, 1: array<string,mixed>} */
    private function config(string $resource, ?int $id = null): array
    {
        return match ($resource) {
            'packages' => [Package::class, [
                'name'             => 'required|string|max:150',
                'category'         => 'required|in:tari,musik',
                'description'      => 'nullable|string',
                'price'            => 'required|numeric|min:0',
                'dancers_needed'   => 'integer|min:0|max:100',
                'musicians_needed' => 'integer|min:0|max:100',
                'helpers_needed'   => 'integer|min:0|max:100',
                'image_url'        => 'nullable|string|max:500',
                'is_active'        => 'boolean',
            ]],
            'addons' => [Addon::class, [
                'name'            => 'required|string|max:150',
                'description'     => 'nullable|string',
                'price'           => 'required|numeric|min:0',
                'extra_dancers'   => 'integer|min:0|max:100',
                'extra_musicians' => 'integer|min:0|max:100',
                'is_active'       => 'boolean',
            ]],
            'inventory' => [InventoryItem::class, [
                'code'        => ['required', 'string', 'max:50', Rule::unique('inventory_items', 'code')->ignore($id)],
                'name'        => 'required|string|max:150',
                'category'    => 'required|in:kostum,aksesoris,alat_musik',
                'status'      => 'in:tersedia,rusak,hilang',   // "dipinjam" hanya diatur lewat peminjaman
                'description' => 'nullable|string',
                'image_url'   => 'nullable|string|max:500',
                'subcategory' => 'nullable|string|max:100',   // kelompok di halaman katalog
                'unit_label'  => 'string|max:20',             // Unit / Set / Set Lengkap
            ]],
            'gallery' => [GalleryItem::class, [
                'title'        => 'required|string|max:150',
                'description'  => 'nullable|string',
                'media_url'    => 'required|string|max:500',
                'event_date'   => 'nullable|date_format:Y-m-d',
                'is_published' => 'boolean',
            ]],
            default => abort(404),
        };
    }

    public function index(string $resource): JsonResponse
    {
        [$model] = $this->config($resource);
        return response()->json($model::orderByDesc('id')->get());
    }

    public function store(Request $r, string $resource): JsonResponse
    {
        [$model, $rules] = $this->config($resource);
        return response()->json($model::create($r->validate($rules))->refresh(), 201);
    }

    public function update(Request $r, string $resource, int $id): JsonResponse
    {
        [$model, $rules] = $this->config($resource, $id);
        $item = $model::findOrFail($id);
        $data = $r->validate(array_map(fn ($rule) => is_array($rule) ? array_merge(['sometimes'], $rule) : 'sometimes|' . $rule, $rules));

        if ($resource === 'inventory' && isset($data['status'])
            && DB::table('item_loans')->where('item_id', $id)->whereNull('returned_at')->exists()) {
            throw new DomainException('Barang sedang dipinjam. Status diubah lewat pengembalian barang oleh Helper.');
        }

        $item->update($data);
        return response()->json($item->refresh());
    }

    /** Paket & add-on dinonaktifkan (riwayat pesanan tetap utuh); lainnya dihapus. */
    public function destroy(string $resource, int $id): JsonResponse
    {
        [$model] = $this->config($resource);
        $item = $model::findOrFail($id);

        if (in_array($resource, ['packages', 'addons'], true)) {
            $item->update(['is_active' => false]);
            return response()->json(['message' => 'Dinonaktifkan.']);
        }
        $item->delete();
        return response()->json(['message' => 'Dihapus.']);
    }
}

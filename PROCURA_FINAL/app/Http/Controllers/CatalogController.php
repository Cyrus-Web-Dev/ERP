<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $items = CatalogItem::query()
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")->orWhere('sku', 'ilike', "%{$s}%")))
            ->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Catalog/Index', ['items' => $items, 'filters' => $request->only(['category', 'search'])]);
    }

    public function store(Request $request)
    {
        CatalogItem::create($this->validated($request));

        return back()->with('success', 'Barang ditambahkan ke katalog.');
    }

    public function update(Request $request, CatalogItem $catalogItem)
    {
        $catalogItem->update($this->validated($request, $catalogItem));

        return back()->with('success', 'Katalog diperbarui.');
    }

    public function destroy(CatalogItem $catalogItem)
    {
        $catalogItem->delete();

        return back()->with('success', 'Barang dihapus dari katalog.');
    }

    private function validated(Request $request, ?CatalogItem $item = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:40', Rule::unique('catalog_items', 'sku')->ignore($item?->id)],
            'name' => 'required|string|max:200', 'category' => ['required', Rule::in(array_keys(config('procura.categories')))],
            'unit' => 'required|string|max:20', 'reference_price' => 'required|numeric|min:0', 'description' => 'nullable|string|max:500', 'is_active' => 'boolean',
        ], ['sku.unique' => 'SKU sudah dipakai.', 'sku.required' => 'SKU wajib diisi.', 'name.required' => 'Nama barang wajib diisi.']);
    }
}

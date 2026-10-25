<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendors = Vendor::withCount('orders')->withSum(['orders as spend' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")->orWhere('code', 'ilike', "%{$s}%")->orWhere('contact_person', 'ilike', "%{$s}%")))
            ->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Vendors/Index', ['vendors' => $vendors, 'filters' => $request->only(['status', 'search'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = $this->nextCode();
        $v = Vendor::create($data);
        ActivityLogger::log('vendor.created', "Vendor {$v->code} {$v->name} ditambahkan", $v);

        return back()->with('success', "Vendor {$v->name} ditambahkan.");
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($this->validated($request));
        ActivityLogger::log('vendor.updated', "Vendor {$vendor->code} diperbarui", $vendor);

        return back()->with('success', 'Vendor diperbarui.');
    }

    public function destroy(Vendor $vendor)
    {
        if ($vendor->orders()->exists() || $vendor->quotations()->exists()) {
            return back()->withErrors(['general' => 'Vendor sudah punya riwayat penawaran/PO dan tidak bisa dihapus. Ubah statusnya menjadi nonaktif.']);
        }
        $vendor->delete();

        return back()->with('success', 'Vendor dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:200', 'category' => ['nullable', Rule::in(array_keys(config('procura.categories')))],
            'contact_person' => 'nullable|string|max:120', 'email' => 'nullable|email|max:200', 'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:500', 'npwp' => 'nullable|string|max:30', 'bank_name' => 'nullable|string|max:60',
            'bank_account' => 'nullable|string|max:40', 'bank_holder' => 'nullable|string|max:120',
            'payment_terms_days' => 'required|integer|min:0|max:365', 'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted'])], 'notes' => 'nullable|string|max:1000',
        ], ['name.required' => 'Nama vendor wajib diisi.', 'email.email' => 'Format email tidak valid.']);
    }

    private function nextCode(): string
    {
        $n = (int) Vendor::max('id') + 1;

        return sprintf('VND-%04d', $n);
    }
}

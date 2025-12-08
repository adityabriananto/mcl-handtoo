<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TplPrefix;
use Illuminate\Validation\Rule;

class TplPrefixController extends Controller
{
    /**
     * Menampilkan daftar konfigurasi 3PL Prefix (Dashboard Home).
     */
    public function index(Request $request)
    {
        $configurations = TplPrefix::orderBy('updated_at', 'desc')->get();

        $query = TplPrefix::orderBy('updated_at', 'desc');

        // Menerapkan Search Filter
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->input('search') . '%';
            $query->where('tpl_name', 'like', $searchTerm)
                ->orWhereJsonContains('prefixes', $request->input('search'));
                // Note: orWhereJsonContains berfungsi untuk kolom JSON (prefixes)
        }

        // Menerapkan Status Filter
        if ($request->filled('status')) {
            $query->where('is_active', (int)$request->input('status'));
        }

        $configurations = $query->get();

        $totalConfigs = $configurations->count();
        $activeConfigs = $configurations->where('is_active', true)->count();

        $lastUpdated = $configurations->max('updated_at');
        $lastUpdatedFormatted = $lastUpdated ? $lastUpdated->format('M d, Y H:i:s') : 'N/A';

        return view('tpl_prefix.index', [
            'configurations' => $configurations,
            'totalConfigs' => $totalConfigs,
            'activeConfigs' => $activeConfigs,
            'lastUpdated' => $lastUpdatedFormatted,
        ]);
    }

    /**
     * Menampilkan formulir untuk membuat konfigurasi baru.
     */
    public function create()
    {
        return view('tpl_prefix.create');
    }

    /**
     * Menyimpan konfigurasi baru ke database.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // Memastikan nama kurir unik saat pembuatan
            'tpl_name' => 'required|string|max:255|unique:tpl_prefixes,tpl_name',
            'prefixes_input' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $prefixesArray = $this->processPrefixes($validatedData['prefixes_input']);

        TplPrefix::create([
            'tpl_name' => $validatedData['tpl_name'],
            'prefixes' => $prefixesArray,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('tpl.config.index')->with('success', '3PL Prefix configuration added successfully!');
    }

    // --- Metode CRUD Tambahan ---

    /**
     * Menampilkan formulir untuk mengedit konfigurasi spesifik.
     */
    public function edit(TplPrefix $config)
    {
        // Karena 'prefixes' adalah array, kita perlu mengubahnya kembali menjadi string
        // yang dipisahkan koma untuk ditampilkan di textarea.
        $config->prefixes_input = implode(', ', $config->prefixes);

        return view('tpl_prefix.edit', compact('config'));
    }

    /**
     * Memperbarui konfigurasi spesifik di database.
     */
    public function update(Request $request, TplPrefix $config)
    {
        $validatedData = $request->validate([
            // Memastikan nama kurir unik kecuali untuk instance saat ini
            'tpl_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tpl_prefixes')->ignore($config->id),
            ],
            'prefixes_input' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $prefixesArray = $this->processPrefixes($validatedData['prefixes_input']);

        $config->update([
            'tpl_name' => $validatedData['tpl_name'],
            'prefixes' => $prefixesArray,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('tpl.config.index')->with('success', 'Configuration updated successfully!');
    }

    /**
     * Menghapus konfigurasi spesifik dari database.
     */
    public function destroy(TplPrefix $config)
    {
        $config->delete();
        return redirect()->route('tpl.config.index')->with('success', 'Configuration deleted successfully!');
    }

    // --- Helper Method ---

    /**
     * Memproses string prefixes menjadi array yang bersih (uppercase, unik).
     */
    protected function processPrefixes(string $prefixesInput): array
    {
        return collect(explode(',', $prefixesInput))
                    ->map(fn($p) => trim(strtoupper($p)))
                    ->filter()
                    ->unique()
                    ->toArray();
    }
}

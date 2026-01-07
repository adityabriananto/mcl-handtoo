<?php

namespace App\Http\Controllers;

use App\Models\ClientApi;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientApiController extends Controller
{
    /**
     * Menampilkan daftar semua client
     */
    public function index()
    {
        $clients = ClientApi::latest()->get();
        return view('client_api.index', compact('clients'));
    }

    /**
     * Menampilkan form pendaftaran client baru
     */
    public function create()
    {
        return view('client_api.create');
    }

    /**
     * Menyimpan data client baru ke database
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'client_name' => 'required|string|max:255',
            'client_code' => 'required|string|unique:client_apis,client_code|max:50',
            'client_url'  => 'required|url',
            'client_token'=> 'nullable|string|unique:client_apis,client_token'
        ]);

        // 2. Simpan Data
        // Note: Logic pembuatan token otomatis sudah kita buat di Model (Boot method)
        ClientApi::create([
            'client_name'  => $request->client_name,
            'client_code'  => strtoupper($request->client_code), // Paksa huruf besar untuk kode
            'client_url'   => $request->client_url,
            'client_token' => $request->client_token,
        ]);

        return redirect()->route('client_api.index')
            ->with('success', 'Client API berhasil didaftarkan!');
    }

    public function edit($id)
    {
        $client = ClientApi::findOrFail($id);
        return view('client_api.edit', compact('client'));
    }

    // Memproses pembaruan data
    public function update(Request $request, $id)
    {
        $client = ClientApi::findOrFail($id);

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_code' => 'required|string|max:50|unique:client_apis,client_code,' . $id,
            'client_url'  => 'required|url',
            'client_token'=> 'nullable|string|unique:client_apis,client_token,' . $id
        ]);

        $client->update($validated);

        return redirect()->route('client_api.index')
            ->with('success', "Data client <b>{$client->client_name}</b> berhasil diperbarui!");
    }

    /**
     * Menghapus data client
     */
    public function destroy($id)
    {
        $client = ClientApi::findOrFail($id);
        $client->delete();

        return redirect()->route('client_api.index')
            ->with('success', 'Client berhasil dihapus.');
    }

    /**
     * Opsional: Fitur untuk me-regenerate token baru jika bocor
     */
    public function refreshToken($id)
    {
        $client = ClientApi::findOrFail($id);
        $client->update([
            'client_token' => Str::random(40)
        ]);

        return back()->with('success', 'API Token untuk ' . $client->client_name . ' telah diperbarui!');
    }
}

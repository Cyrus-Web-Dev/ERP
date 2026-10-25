<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show()
    {
        return view('profile.show', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id_user, 'id_user')],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'riwayat_pendidikan' => ['nullable', 'string', 'max:2000'],
            'foto_profil' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('foto_profil')) {
            if ($user->foto_profil) {
                Storage::disk('public')->delete('profil/'.$user->foto_profil);
            }
            $data['foto_profil'] = basename($request->file('foto_profil')->store('profil', 'public'));
        }

        $user->update($data);

        ActivityLog::catat('users', 'UPDATE', 'Ubah Profil', 'Memperbarui informasi profil sendiri');

        return back()->with('success', 'Profil berhasil diperbarui!');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password_baru' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['password_lama'], $user->password)) {
            throw ValidationException::withMessages(['password_lama' => 'Password lama tidak sesuai.']);
        }

        $user->update(['password' => Hash::make($data['password_baru'])]);

        ActivityLog::catat('users', 'UPDATE', 'Ubah Password', 'Mengubah password sendiri');

        return back()->with('success', 'Password berhasil diubah!');
    }
}

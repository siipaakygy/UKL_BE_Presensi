<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PresenceController extends Controller
{
    /**
     * Fungsi untuk mencatat presensi user.
     */
    public function store(Request $request)
{
    // Ambil user yang sedang login
    $user = Auth::user();

    // Validasi input request
    $validated = $request->validate([
        'status' => 'required|in:hadir,izin,sakit',
        'date' => 'nullable|date|after_or_equal:today' // Validasi untuk memastikan tanggal yang valid
    ]);

    // Tanggal presensi: jika `date` tidak diisi, gunakan tanggal hari ini
    $date = $validated['date'] ?? now()->toDateString();

    // Cek apakah user yang login adalah admin
    if ($user->role === 'admin') {
        // Validasi input untuk admin (memerlukan user_id)
        $adminValidated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $adminValidated['user_id'];
    } else {
        // Cek apakah siswa mencoba melakukan presensi untuk siswa lain
        if ($request->has('user_id') && $request->input('user_id') != $user->id) {
            return response()->json([
                'status' => 'gagal',
                'message' => 'Anda tidak bisa melakukan presensi untuk siswa lain.'
            ], 403);
        }

        $userId = $user->id;
    }

    // Cek apakah sudah ada presensi pada tanggal yang sama
    $existingPresence = Presence::where('user_id', $userId)
        ->where('date', $date)
        ->first();

    if ($existingPresence) {
        return response()->json([
            'status' => 'gagal',
            'message' => 'Anda sudah melakukan presensi pada tanggal ini. Silakan lakukan presensi besok.'
        ], 400);
    }

    // Menyimpan presensi baru
    $presence = Presence::create([
        'user_id' => $userId,
        'date' => $date,
        'time' => now()->toTimeString(),  // Menggunakan waktu saat ini untuk waktu
        'status' => $validated['status'],
    ]);

    // Mengembalikan response JSON
    return response()->json([
        'status' => 'sukses',
        'message' => 'Presensi berhasil dicatat',
        'data' => [
            'id' => $presence->id,
            'user_id' => $presence->user_id,
            'date' => $presence->date,
            'time' => $presence->time,
            'status' => $presence->status,
        ]
    ]);
}
public function riwayat(Request $request, $user_id = null)
{
    $user = auth()->user();

    // Admin dapat melihat riwayat presensi siapa saja
    if ($user->role === 'admin') {
        // Jika admin tidak menyertakan user_id, tampilkan semua presensi
        $presences = $user_id 
            ? Presence::where('user_id', $user_id)->get()
            : Presence::all();
    } 
    // Siswa hanya bisa melihat riwayatnya sendiri
    else if ($user->role === 'siswa') {
        // Jika siswa mencoba mengakses presensi siswa lain
        if ($user_id && $user_id != $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk melihat riwayat presensi siswa lain.'
            ], 403);
        }

        // Menampilkan riwayat presensi siswa yang sedang login
        $presences = Presence::where('user_id', $user->id)->get();
    } else {
        return response()->json([
            'message' => 'Role tidak dikenali.'
        ], 403);
    }

    // Jika tidak ada presensi yang ditemukan
    if ($presences->isEmpty()) {
        return response()->json([
            'message' => 'Riwayat presensi tidak ditemukan.'
        ], 404);
    }

    return response()->json([
        'message' => 'Riwayat presensi ditemukan.',
        'data' => $presences
    ]);
}
public function riwayatByUserId($user_id)
{
    // Cek apakah user yang login adalah admin
    $user = Auth::user();

    if ($user->role !== 'admin') {
        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk mengakses riwayat presensi ini.'
        ], 403); // Forbidden
    }

    // Ambil riwayat presensi berdasarkan user_id
    $presences = Presence::where('user_id', $user_id)->get();

    if ($presences->isEmpty()) {
        return response()->json([
            'message' => 'Riwayat presensi tidak ditemukan untuk user ini.'
        ], 404); // Not Found
    }

    return response()->json([
        'message' => 'Riwayat presensi ditemukan.',
        'data' => $presences
    ]);
}

public function summary(Request $request, $user_id)
{
    // Ambil user yang sedang login
    $user = auth()->user();

    // Hanya admin atau user itu sendiri yang bisa melihat summary
    if ($user->role !== 'admin' && $user->id != $user_id) {
        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk melihat rekap kehadiran ini.'
        ], 403);
    }

    // Ambil bulan dari parameter, jika tidak ada gunakan bulan saat ini
    $month = $request->input('month') ?? now()->format('m-Y');

    // Pecah bulan dan tahun dari input
    [$monthNumber, $year] = explode('-', $month);

    // Query rekap kehadiran bulanan untuk user
    $summary = Presence::where('user_id', $user_id)
        ->whereMonth('date', $monthNumber)
        ->whereYear('date', $year)
        ->selectRaw('
            SUM(CASE WHEN status = "hadir" THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN status = "izin" THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN status = "sakit" THEN 1 ELSE 0 END) AS sakit
        ')
        ->first();

    if (!$summary) {
        return response()->json([
            'status' => 'error',
            'message' => 'Rekap kehadiran tidak ditemukan.'
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'data' => [
            'user_id' => $user_id,
            'month' => $month,
            'attendance_summary' => [
                'hadir' => (int) $summary->hadir,
                'izin' => (int) $summary->izin,
                'sakit' => (int) $summary->sakit,
            ]
        ]
    ]);
}
}

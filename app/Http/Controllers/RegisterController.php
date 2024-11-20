<?php

namespace App\Http\Controllers;

use App\Models\PenggunaParkir;
use App\Models\Kendaraan;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    protected $kategoriArray = [];
    protected $jenisKendaraanArray = [];
    protected $warnaKendaraanArray = [];

    public function __construct()
    {
        // Inisialisasi nilai enum
        $this->kategoriArray = $this->getEnumValues('pengguna_parkir', 'kategori');
        $this->jenisKendaraanArray = $this->getEnumValues('kendaraan', 'jenis');
        $this->warnaKendaraanArray = $this->getEnumValues('kendaraan', 'warna');
    }

    public function showRegistrationForm()
    {
        // Mengirim nilai enum ke tampilan register
        return view('auth.register', [
            'kategoriArray' => $this->kategoriArray,
            'jenisKendaraanArray' => $this->jenisKendaraanArray,
            'warnaKendaraanArray' => $this->warnaKendaraanArray
        ]);
    }

    protected function getEnumValues($table, $column)
    {
        $result = DB::select("SHOW COLUMNS FROM `$table` WHERE Field = ?", [$column]);
        if (count($result) > 0) {
            $type = $result[0]->Type;
            preg_match('/^enum\((.*)\)$/', $type, $matches);
            $enum = [];

            foreach (explode(',', $matches[1]) as $value) {
                $enum[] = trim($value, "'");
            }

            return $enum;
        }

        return []; // Kembalikan array kosong jika tidak ada hasil
    }

    public function register(RegisterRequest $request) // Gunakan RegisterRequest
    {
        try {
            // Simpan foto profil pengguna
            $fotoPath = $request->file('foto')->store('profil', 'public');

            // Buat instance PenggunaParkir
            $pengguna = new PenggunaParkir();
            $pengguna->id_pengguna = $request->kategori !== 'Tamu' ? $request->id_pengguna : 'Tamu_' . uniqid();
            $pengguna->nama = $request->nama;
            $pengguna->email = $request->email;
            $pengguna->password = $request->password;
            $pengguna->foto = $fotoPath;
            $pengguna->kategori = $request->kategori;
            $pengguna->status = 'nonaktif';
            $pengguna->save();

            // Simpan foto kendaraan
            $fotoKendaraanPath = $request->file('foto_kendaraan')->store('kendaraan', 'public');

            $kendaraan = new Kendaraan();
            $kendaraan->plat_nomor = $request->plat_nomor;
            $kendaraan->jenis = $request->jenis;
            $kendaraan->warna = ucwords(strtolower($request->warna));
            $kendaraan->foto = $fotoKendaraanPath;
            $kendaraan->id_pengguna = $pengguna->id_pengguna;
            $kendaraan->save();

            // Buat QR Code untuk plat nomor kendaraan
            $qrCodePath = 'images/qrcodes/' . $kendaraan->plat_nomor . '.png';
            QrCode::format('png')->size(300)->generate($kendaraan->plat_nomor, storage_path('app/public/' . $qrCodePath));

            // Simpan path QR Code
            $kendaraan->qr_code = $qrCodePath;
            $kendaraan->save();

            return redirect()->route('login')->with('pendaftaran', 'Pendaftaran anda berhasil. Tunggu konfirmasi dari pengelola parkir.');
        } catch (\Exception $e) {
            Log::error('Error during registration: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.']);
        }
    }
}
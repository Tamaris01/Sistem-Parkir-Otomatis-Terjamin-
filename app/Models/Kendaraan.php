<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use SimpleSoftwareIO\QrCode\Facades\QrCode; // Import QrCode package
use Illuminate\Http\Request; // Import Request untuk digunakan dalam method store
use Illuminate\Support\Facades\Validator; // Tambahkan ini untuk mengimpor Validator

class Kendaraan extends Model
{
    use HasFactory;

    protected $table = 'kendaraan';

    public $timestamps = false;

    protected $primaryKey = 'plat_nomor'; // Kunci primer adalah plat_nomor
    public $incrementing = false; // Non-incrementing karena plat_nomor bukan tipe auto-increment
    protected $keyType = 'string'; // Tipe kunci primer adalah string
    // Assuming 'plat_nomor' is your primary key

    protected $fillable = [
        'id_pengguna',
        'jenis',
        'warna',
        'plat_nomor',
        'qr_code',
        'foto',

    ];

    // Menambahkan boot untuk auto generate QR code
    public static function boot()
    {
        parent::boot();

        // Saat kendaraan akan disimpan
        static::creating(function ($kendaraan) {
            // Pastikan plat_nomor tidak null
            if ($kendaraan->plat_nomor) {
                // Generate QR code berdasarkan plat_nomor
                $qrCodeContent = 'Plat Nomor: ' . $kendaraan->plat_nomor;

                // Tentukan lokasi file untuk menyimpan QR code
                $qrCodePath = 'images/qrcodes/' . $kendaraan->plat_nomor . '.png'; // Update path untuk menyimpan QR Code

                // Buat QR code dan simpan ke public path
                QrCode::format('png')->size(200)->generate($qrCodeContent, public_path($qrCodePath)); // Ganti storage_path dengan public_path

                // Simpan path QR code ke database
                $kendaraan->qr_code = $qrCodePath;
            }
        });
    }

    // Relasi dengan PenggunaParkir
    public function penggunaParkir()
    {
        return $this->belongsTo(PenggunaParkir::class, 'id_pengguna', 'id_pengguna');
    }

    // Relasi dengan riwayat parkir
    public function riwayatParkir()
    {
        return $this->hasMany(RiwayatParkir::class, 'plat_nomor', 'plat_nomor');
    }

    // Method untuk menyimpan data kendaraan
    public static function storeKendaraan(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'id_pengguna' => 'required|string|max:255',
            'jenis' => 'required|integer', // Pastikan jenis adalah integer
            'warna' => 'required|string|max:50', // Validasi untuk warna
            'plat_nomor' => 'required|string|max:255|unique:kendaraan,plat_nomor', // Unik untuk plat nomor
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Jika validasi gagal, kembalikan dengan pesan error
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput(); // Mengembalikan input yang dimasukkan agar tidak hilang
        }

        // Periksa apakah pengguna sudah memiliki kendaraan
        if (self::where('id_pengguna', $request->id_pengguna)->exists()) {
            return back()->withErrors(['id_pengguna' => 'Pengguna sudah memiliki kendaraan terdaftar.'])->withInput();
        }

        // Simpan foto kendaraan di folder images/kendaraan
        $fotoKendaraanPath = $request->file('foto')->store('images/kendaraan', 'public');

        // Buat instance kendaraan dan simpan
        $kendaraan = new Kendaraan();
        $kendaraan->id_pengguna = $request->id_pengguna;
        $kendaraan->jenis = $request->jenis;
        $kendaraan->warna = $request->warna; // Simpan warna dari request
        $kendaraan->plat_nomor = $request->plat_nomor;
        $kendaraan->foto = $fotoKendaraanPath;
        $kendaraan->save(); // Ini akan memicu method boot untuk generate QR Code

        return back()->with('success', 'Kendaraan berhasil ditambahkan dan QR Code telah dihasilkan!');
    }
}

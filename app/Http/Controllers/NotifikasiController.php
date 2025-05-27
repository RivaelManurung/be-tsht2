<?php

namespace App\Http\Controllers;

use App\Events\StockMinimumReached;
use App\Models\Barang;
use App\Models\BarangGudang;
use App\Models\Gudang;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotifikasiController extends Controller
{
    public function index()
    {
        return response()->json(Notifikasi::where('read', false)->get());
    }

    public function markAsRead($id)
    {
        $notifikasi = Notifikasi::findOrFail($id);
        $notifikasi->read = true;
        $notifikasi->save();

        return response()->json(['message' => 'Notifikasi sudah dibaca']);
    }
    function checkStockAndNotify($barangId, $gudangId, $stokTersedia)
    {
        $barang = Barang::find($barangId);
        $gudang = Gudang::find($gudangId);
        $barangGudang = BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->first();

        $stokMinimum = $barang->stok_minimum ?? 1;

        if ($stokTersedia == 0 && !$barangGudang->notified) {
            $title = "Stok Barang Habis";
            $message = "Stok barang {$barang->barang_nama} di gudang {$gudang->name} telah habis.";

            Notifikasi::create([
                'title' => $title,
                'message' => $message,
                'barang_id' => $barangId,
                'gudang_id' => $gudangId,
                'read' => false
            ]);

            $barangGudang->update(['notified' => true]);
            event(new StockMinimumReached($title, $message, $barangId, $gudangId));

        } elseif ($stokTersedia <= $stokMinimum && !$barangGudang->notified) {
            $title = "Stok Barang Minimum Tercapai";
            $message = "Stok barang {$barang->barang_nama} di gudang {$gudang->name} telah mencapai batas minimum.";

            Notifikasi::create([
                'title' => $title,
                'message' => $message,
                'barang_id' => $barangId,
                'gudang_id' => $gudangId,
                'read' => false
            ]);

            $barangGudang->update(['notified' => true]);
            event(new StockMinimumReached($title, $message, $barangId, $gudangId));
        }

        // Reset flag notified jika stok kembali naik di atas batas minimum
        if ($stokTersedia > $stokMinimum && $barangGudang->notified) {
            $barangGudang->update(['notified' => false]);
        }
    }

}

<?php

namespace App\Repositories;

use App\Models\{Transaction, TransactionDetail, Barang, BarangGudang, Gudang, User, Satuan};
use Illuminate\Support\Facades\Auth;
use Exception;

class TransactionRepository
{
    public function createTransaction($request)
    {
        $user = Auth::user();
        $userId = $user->id;
        $gudangId = $this->getGudangIdByUserId($userId);

        $transaction = Transaction::create([
            'user_id' => $userId,
            'transaction_type_id' => $request['transaction_type_id'],
            'transaction_code' => $this->generateTransactionCode($request['transaction_type_id']),
            'transaction_date' => now(),
            'description' => $request['description'] ?? null,
        ]);

        foreach ($request['items'] as $item) {
            $item['gudang_id'] = $gudangId;
            $this->processTransactionItem($transaction->id, $item, $request['transaction_type_id']);
        }

        return $transaction->load([
            'user:id,name',
            'transactionType:id,name',
            'transactionDetails.barang:id,barang_kode,barang_nama,satuan_id',
            'transactionDetails.gudang:id,name',
        ]);
    }

    private function getGudangIdByUserId($userId)
    {
        $gudang = Gudang::where('user_id', $userId)->first();
        if (!$gudang) {
            throw new Exception("Tidak ditemukan gudang yang terdaftar untuk user login.");
        }
        return $gudang->id;
    }

    private function generateTransactionCode($typeId)
    {
        $prefixes = [
            1 => 'MSK',
            2 => 'KLR',
            3 => 'PJM',
            4 => 'KMB',
            5 => 'MTC',
            6 => 'FIX'
        ];

        $prefix = $prefixes[$typeId] ?? 'UNK';

        $lastTransaction = Transaction::where('transaction_type_id', $typeId)->latest('id')->first();
        $number = $lastTransaction ? str_pad($lastTransaction->id + 1, 3, '0', STR_PAD_LEFT) : '001';

        return "TRX-{$prefix}-{$number}";
    }

    private function processTransactionItem($transactionId, $item, $transactionType)
    {
        $barang = Barang::where('barang_kode', $item['barang_kode'])->firstOrFail();

        $barangGudang = BarangGudang::where('barang_id', $barang->id)
            ->where('gudang_id', $item['gudang_id'])
            ->first();

        if (in_array($transactionType, [2, 3, 4, 5, 6]) && !$barangGudang) {
            throw new Exception("Barang {$barang->barang_nama} belum tersedia di gudang. Masukkan terlebih dahulu.");
        }

        $this->validateItemTransaction($barang, $barangGudang, $item, $transactionType);

        match ($transactionType) {
            1 => $this->handleBarangMasuk($barang, $item),
            2 => $this->handleBarangKeluar($barang->id, $item),
            3 => $this->handlePeminjaman($barang->id, $item),
            4 => $this->handlePengembalian($barang->id, $item),
            5 => $this->handleMaintanance($barang->id, $item),
            6 => $this->handleMaintenanceReturn($barang->id, $item),
        };

        TransactionDetail::create([
            'transaction_id' => $transactionId,
            'barang_id' => $barang->id,
            'gudang_id' => $item['gudang_id'],
            'quantity' => $item['quantity'],
            'tanggal_kembali' => $transactionType == 4 ? now() : null,
        ]);
    }

    public function validateItemTransaction($barang, $barangGudang, $item, $transactionType)
    {
        $validTransactionType = match (true) {
            $barang->barangcategory_id == 1 => in_array($transactionType, [1, 2]),
            $barang->barangcategory_id == 2 => in_array($transactionType, [1, 3, 4, 5, 6]),
            default => false,
        };

        if (!$validTransactionType) {
            throw new Exception("Jenis transaksi tidak valid untuk barang {$barang->barang_nama}.");
        }

        if (in_array($transactionType, [2, 3]) && (!$barangGudang || $barangGudang->stok_tersedia < $item['quantity'])) {
            throw new Exception("Stok tidak mencukupi untuk barang {$barang->barang_nama}.");
        }

        if ($transactionType == 4 && (!$barangGudang || $barangGudang->stok_dipinjam < $item['quantity'])) {
            throw new Exception("Barang {$barang->barang_nama} dikembalikan lebih banyak dari yang dipinjam.");
        }
    }

    private function getOrCreateBarangGudang($barangId, $gudangId)
    {
        $barangGudang = BarangGudang::firstOrCreate(
            [
                'barang_id' => $barangId,
                'gudang_id' => $gudangId
            ],
            ['stok_tersedia' => 0, 'stok_dipinjam' => 0, 'stok_maintenance' => 0]
        );
        return $barangGudang;
    }

    private function handleBarangMasuk($barang, $item)
    {
        $this->getOrCreateBarangGudang($barang->id, $item['gudang_id']);
        BarangGudang::where('barang_id', $barang->id)
            ->where('gudang_id', $item['gudang_id'])
            ->increment('stok_tersedia', $item['quantity']);
    }

    private function handleBarangKeluar($barangId, $item)
    {
        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->decrement('stok_tersedia', $item['quantity']);
    }

    private function handlePeminjaman($barangId, $item)
    {
        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->decrement('stok_tersedia', $item['quantity']);

        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->increment('stok_dipinjam', $item['quantity']);
    }

    private function handlePengembalian($barangId, $item)
    {
        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->increment('stok_tersedia', $item['quantity']);

        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->decrement('stok_dipinjam', $item['quantity']);
    }

    private function handleMaintanance($barangId, $item)
    {
        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->increment('stok_maintenance', $item['quantity']);

        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->decrement('stok_tersedia', $item['quantity']);
    }

    private function handleMaintenanceReturn($barangId, $item)
    {
        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->increment('stok_tersedia', $item['quantity']);

        BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $item['gudang_id'])
            ->decrement('stok_maintenance', $item['quantity']);
    }

    public function findBarangByKode($kode)
    {
        return Barang::where('barang_kode', $kode)->first();
    }

    public function find($id)
    {
        $transaction = Transaction::with([
            'user:id,name',
            'transactionType:id,name',
            'transactionDetails.barang:id,barang_kode,barang_nama,satuan_id',
            'transactionDetails.gudang:id,name'
        ])->find($id);

        if (!$transaction) {
            throw new Exception('Transaksi tidak ditemukan.');
        }

        return $transaction;
    }

    public function findBarangById($id)
    {
        $barang = Barang::find($id);
        if (!$barang) {
            throw new Exception('Barang tidak ditemukan.');
        }
        return $barang;
    }

    public function updateTransactionWithDetails($id, array $data)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            throw new Exception('Transaksi tidak ditemukan.');
        }

        $user = User::find($transaction->user_id);
        if (!$user || !$user->gudang_id) {
            throw new Exception("Gudang tidak ditemukan untuk user ini.");
        }

        $transaction->update([
            'transaction_type_id' => $data['transaction_type_id'],
            'description' => $data['description'] ?? $transaction->description,
        ]);

        foreach ($data['items'] as $item) {
            $barang = Barang::where('barang_kode', $item['barang_kode'])->first();

            if (!$barang) {
                throw new Exception("Barang dengan kode {$item['barang_kode']} tidak ditemukan.");
            }

            // Update Barang attributes (name, satuan)
            $barangUpdates = [];
            if (isset($item['barang_nama']) && $item['barang_nama'] !== $barang->barang_nama) {
                $barangUpdates['barang_nama'] = $item['barang_nama'];
            }
            if (isset($item['satuan_id']) && $item['satuan_id'] !== $barang->satuan_id) {
                $satuan = Satuan::find($item['satuan_id']);
                if (!$satuan) {
                    throw new Exception("Satuan dengan ID {$item['satuan_id']} tidak ditemukan.");
                }
                $barangUpdates['satuan_id'] = $item['satuan_id'];
            }
            if ($barangUpdates) {
                $barang->update($barangUpdates);
            }

            // Find existing TransactionDetail or create new
            $transactionDetail = TransactionDetail::where('transaction_id', $transaction->id)
                ->where('barang_id', $barang->id)
                ->first();

            $oldQuantity = $transactionDetail ? $transactionDetail->quantity : 0;
            $newQuantity = $item['quantity'];

            // Validate stock before updating
            $barangGudang = $this->getOrCreateBarangGudang($barang->id, $user->gudang_id);
            $this->validateStockUpdate($barang, $barangGudang, $oldQuantity, $newQuantity, $data['transaction_type_id']);

            if ($transactionDetail) {
                // Update existing detail
                $transactionDetail->update([
                    'quantity' => $newQuantity,
                    'description' => $item['description'] ?? $transactionDetail->description,
                    'gudang_id' => $user->gudang_id,
                    'tanggal_kembali' => $data['transaction_type_id'] == 4 ? now() : $transactionDetail->tanggal_kembali,
                ]);
            } else {
                // Create new detail
                $transactionDetail = TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'barang_id' => $barang->id,
                    'quantity' => $newQuantity,
                    'gudang_id' => $user->gudang_id,
                    'description' => $item['description'] ?? null,
                    'tanggal_kembali' => $data['transaction_type_id'] == 4 ? now() : null,
                ]);
            }

            // Adjust stock based on quantity change
            $quantityDiff = $newQuantity - $oldQuantity;
            if ($quantityDiff != 0) {
                $this->adjustStock($barang->id, $user->gudang_id, $quantityDiff, $data['transaction_type_id']);
            }
        }

        // Remove TransactionDetails not included in the update
        $updatedBarangIds = collect($data['items'])->pluck('barang_kode')->map(function ($kode) {
            return Barang::where('barang_kode', $kode)->first()->id;
        })->toArray();

        TransactionDetail::where('transaction_id', $transaction->id)
            ->whereNotIn('barang_id', $updatedBarangIds)
            ->get()
            ->each(function ($detail) use ($user) {
                // Reverse stock for deleted details
                $quantity = $detail->quantity;
                $this->adjustStock($detail->barang_id, $user->gudang_id, -$quantity, $detail->transaction->transaction_type_id);
                $detail->delete();
            });

        return $this->find($transaction->id);
    }

    private function validateStockUpdate($barang, $barangGudang, $oldQuantity, $newQuantity, $transactionType)
    {
        $quantityDiff = $newQuantity - $oldQuantity;

        if (in_array($transactionType, [2, 3]) && $quantityDiff > 0) {
            if (!$barangGudang || $barangGudang->stok_tersedia < $quantityDiff) {
                throw new Exception("Stok tidak mencukupi untuk barang {$barang->barang_nama}.");
            }
        }

        if ($transactionType == 4 && $quantityDiff > 0) {
            if (!$barangGudang || $barangGudang->stok_dipinjam < $quantityDiff) {
                throw new Exception("Barang {$barang->barang_nama} dikembalikan lebih banyak dari yang dipinjam.");
            }
        }

        if ($transactionType == 5 && $quantityDiff > 0) {
            if (!$barangGudang || $barangGudang->stok_tersedia < $quantityDiff) {
                throw new Exception("Stok tidak mencukupi untuk maintenance barang {$barang->barang_nama}.");
            }
        }

        if ($transactionType == 6 && $quantityDiff > 0) {
            if (!$barangGudang || $barangGudang->stok_maintenance < $quantityDiff) {
                throw new Exception("Stok maintenance tidak mencukupi untuk barang {$barang->barang_nama}.");
            }
        }
    }

    private function adjustStock($barangId, $gudangId, $quantityDiff, $transactionType)
    {
        $barangGudang = BarangGudang::where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->firstOrFail();

        match ($transactionType) {
            1 => $barangGudang->increment('stok_tersedia', $quantityDiff),
            2 => $barangGudang->decrement('stok_tersedia', $quantityDiff),
            3 => [
                $barangGudang->decrement('stok_tersedia', $quantityDiff),
                $barangGudang->increment('stok_dipinjam', $quantityDiff),
            ],
            4 => [
                $barangGudang->increment('stok_tersedia', $quantityDiff),
                $barangGudang->decrement('stok_dipinjam', $quantityDiff),
            ],
            5 => [
                $barangGudang->increment('stok_maintenance', $quantityDiff),
                $barangGudang->decrement('stok_tersedia', $quantityDiff),
            ],
            6 => [
                $barangGudang->increment('stok_tersedia', $quantityDiff),
                $barangGudang->decrement('stok_maintenance', $quantityDiff),
            ],
            default => throw new Exception("Jenis transaksi tidak valid."),
        };
    }
}
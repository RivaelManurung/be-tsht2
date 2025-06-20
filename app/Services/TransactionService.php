<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\BarangGudang;
use App\Models\Gudang;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    protected $transactionRepo;

    public function __construct(TransactionRepository $transactionRepo)
    {
        $this->transactionRepo = $transactionRepo;
    }

    public function processTransaction($request)
    {
        DB::beginTransaction();
        try {
            $transaction = $this->transactionRepo->createTransaction($request);
            DB::commit();
            return [
                'success' => true,
                'message' => 'Transaksi berhasil!',
                'data' => $transaction
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Transaksi gagal!',
                'error' => $e->getMessage()
            ];
        }
    }

    public function checkBarcode($barcode, $userId = null)
    {
        $barang = $this->transactionRepo->findBarangByKode($barcode);

        if (!$barang) {
            return [
                'success' => 'false',
                'message' => 'Barang tidak ditemukan.',
            ];
        }

        $stokTersedia = 0;
        if ($userId) {
            $gudang = Gudang::where('user_id', $userId)->first();
            if ($gudang) {
                $barangGudang = BarangGudang::where('barang_id', $barang->id)
                    ->where('gudang_id', $gudang->id)
                    ->first();
                $stokTersedia = $barangGudang ? $barangGudang->stok_tersedia : 0;
            }
        }

        return [
            'success' => 'true',
            'data' => [
                'barang_kode' => $barang->barang_kode,
                'barang_nama' => $barang->barang_nama,
                'kategori' => $barang->category ? $barang->category->name : null,
                'stok_tersedia' => $stokTersedia,
                'gambar' => asset('storage/' . $barang->barang_gambar),
                'satuan' => $barang->satuan ? $barang->satuan->name : 'Tidak Diketahui',
            ]
        ];
    }

    public function find($id)
    {
        try {
            return $this->transactionRepo->find($id);
        } catch (Exception $e) {
            return null;
        }
    }

    public function updateTransaction($id, $data)
    {
        DB::beginTransaction();
        try {
            Log::info('Updating transaction', ['id' => $id, 'data' => $data]);

            $transaction = $this->transactionRepo->find($id);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction not found.',
                ];
            }

            $user = User::find($data['user_id']);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Determine gudang_id: use user's warehouse or preserve existing
            $gudangId = null;
            if ($user->role_id !== 1) {
                $gudang = Gudang::where('user_id', $user->id)->first();
                if (!$gudang) {
                    throw new Exception('No warehouse assigned to this user');
                }
                $gudangId = $gudang->id;
            }

            $transaction->update([
                'transaction_type_id' => $data['transaction_type_id'],
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'],
                'user_id' => $data['user_id'],
            ]);

            // Load current transaction details with their related barang
            $currentDetails = $transaction->transactionDetails()->with('barang')->get()->keyBy(function ($detail) {
                return $detail->barang->barang_kode; // Key by barang_kode from the related barang
            });

            $isBarangMasuk = $transaction->transaction_type_id == 1;

            foreach ($data['items'] as $item) {
                $barang = Barang::where('barang_kode', $item['barang_kode'])->first();
                if (!$barang) {
                    throw new Exception('Barang not found: ' . $item['barang_kode']);
                }

                $existingDetail = $currentDetails->get($item['barang_kode']);
                $oldQuantity = $existingDetail ? $existingDetail->quantity : 0;

                // Use existing gudang_id if available, else use determined gudangId or default to 1
                $detailGudangId = $existingDetail ? $existingDetail->gudang_id : ($gudangId ?? 1);

                $transaction->transactionDetails()->updateOrCreate(
                    ['barang_id' => $barang->id], // Use barang_id instead of barang_kode
                    [
                        'barang_id' => $barang->id,
                        'gudang_id' => $detailGudangId,
                        'quantity' => $item['quantity'],
                        'barang_nama' => $item['barang_nama'] ?? $barang->barang_nama,
                        'satuan_id' => $item['satuan_id'] ?? null,
                        'description' => $item['description'] ?? null,
                    ]
                );

                if ($isBarangMasuk && $detailGudangId) {
                    $barangGudang = BarangGudang::where('barang_id', $barang->id)
                        ->where('gudang_id', $detailGudangId)
                        ->first();

                    if (!$barangGudang) {
                        $barangGudang = BarangGudang::create([
                            'barang_id' => $barang->id,
                            'gudang_id' => $detailGudangId,
                            'stok_tersedia' => 0,
                            'stok_dipinjam' => 0,
                            'stok_maintenance' => 0,
                        ]);
                    }

                    $quantityDiff = $item['quantity'] - $oldQuantity;
                    $newStock = $barangGudang->stok_tersedia + $quantityDiff;

                    if ($newStock < 0) {
                        throw new Exception('Stock cannot be negative for barang: ' . $item['barang_kode']);
                    }

                    $barangGudang->update(['stok_tersedia' => $newStock]);
                }
            }

            // Collect barang_ids from the new items
            $newBarangIds = collect($data['items'])->map(function ($item) {
                $barang = Barang::where('barang_kode', $item['barang_kode'])->first();
                return $barang ? $barang->id : null;
            })->filter()->toArray();

            // Delete transaction details for barang_ids not in the new items
            $transaction->transactionDetails()
                ->whereNotIn('barang_id', $newBarangIds)
                ->get()
                ->each(function ($detail) use ($isBarangMasuk) {
                    if ($isBarangMasuk && $detail->gudang_id) {
                        $barangGudang = BarangGudang::where('barang_id', $detail->barang_id)
                            ->where('gudang_id', $detail->gudang_id)
                            ->first();
                        if ($barangGudang) {
                            $newStock = $barangGudang->stok_tersedia - $detail->quantity;
                            if ($newStock < 0) {
                                throw new Exception('Stock cannot be negative for barang: ' . $detail->barang->barang_kode);
                            }
                            $barangGudang->update(['stok_tersedia' => $newStock]);
                        }
                    }
                    $detail->delete();
                });

            DB::commit();
            return [
                'success' => true,
                'message' => 'Transaksi berhasil diperbarui.',
                'data' => $this->transactionRepo->find($id)
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update transaction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Transaksi gagal diperbarui!',
                'error' => $e->getMessage()
            ];
        }
    }

    public function updateBarangDetails($barangId, $type, $newValue, $user, $gudangId)
    {
        DB::beginTransaction();
        try {
            $barang = $this->transactionRepo->findBarangById($barangId);

            if (!$barang) {
                return [
                    'success' => false,
                    'message' => 'Item not found.',
                ];
            }

            // Fetch the specified Gudang
            $gudang = Gudang::find($gudangId);

            if (!$gudang) {
                return [
                    'success' => false,
                    'message' => 'Warehouse not found.',
                ];
            }

            if ($type === 'stok') {
                $barangGudang = BarangGudang::where('barang_id', $barangId)
                    ->where('gudang_id', $gudang->id)
                    ->first();

                if (!$barangGudang) {
                    // Create a new barang_gudangs record if none exists
                    $barangGudang = BarangGudang::create([
                        'barang_id' => $barangId,
                        'gudang_id' => $gudang->id,
                        'stok_tersedia' => 0,
                        'stok_dipinjam' => 0,
                        'stok_maintenance' => 0,
                    ]);
                }

                $newValue = (int) $newValue;
                if ($newValue < 0) {
                    return [
                        'success' => false,
                        'message' => 'Stock cannot be negative.',
                    ];
                }

                $barangGudang->update(['stok_tersedia' => $newValue]);
            } elseif ($type === 'nama') {
                $barang->update(['barang_nama' => $newValue]);
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid update type. Use "stok" or "nama".',
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'message' => ucfirst($type) . ' updated successfully.',
                'data' => [
                    'barang_id' => $barang->id,
                    'gudang_id' => $gudang->id,
                    $type => $newValue,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to update item details.',
                'error' => $e->getMessage(),
            ];
        }
    }
}

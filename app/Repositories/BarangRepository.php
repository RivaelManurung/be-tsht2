<?php

namespace App\Repositories;

use App\Models\Barang;

class BarangRepository
{
    public function getAll($userId = null, $isSuperadmin = false, $isAdmin = false)
{
    $query = Barang::with(['gudangs' => function ($query) {
        $query->withPivot('stok_tersedia', 'stok_dipinjam', 'stok_maintenance');
    }]);

    if (!$isSuperadmin && !$isAdmin && $userId !== null) {
        // Operator hanya bisa melihat barang dari gudang miliknya
        $query->whereHas('gudangs', function ($gudangQuery) use ($userId) {
            $gudangQuery->where('user_id', $userId);
        });
    }

    return $query->get();
}


    public function findById($id)
    {
        return Barang::with(['gudangs' => function ($query) {
            $query->withPivot('stok_tersedia', 'stok_dipinjam', 'stok_maintenance');
        }])->find($id);
    }


    public function create(array $data)
    {
        return Barang::create($data);
    }

    public function update(Barang $barang, array $data)
    {
        return $barang->update($data);
    }

    public function delete(Barang $barang)
    {
        return $barang->delete();
    }
}

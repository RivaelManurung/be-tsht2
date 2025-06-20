<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangGudang extends Model
{
    protected $table = 'barang_gudangs';

    // Disable primary key
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'barang_id',
        'gudang_id',
        'stok_tersedia',
        'stok_dipinjam',
        'stok_maintenance',
    ];

    // Ensure no primary key is assumed
    public function getKey()
    {
        return null;
    }

    // Custom update method for composite key
    public function update(array $attributes = [], array $options = [])
    {
        if (!$this->exists) {
            return false;
        }

        return $this->newQuery()
            ->where('barang_id', $this->barang_id)
            ->where('gudang_id', $this->gudang_id)
            ->update(array_merge($attributes, [
                'updated_at' => $this->freshTimestamp(),
            ]));
    }

    // Override save to handle composite key
    public function save(array $options = [])
    {
        if ($this->exists) {
            return $this->update($this->getDirty(), $options);
        }

        return parent::save($options);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
<?php

namespace App\Services;

use App\Events\BarangCategoryCreated;
use App\Events\BarangCategoryUpdated;
use App\Events\BarangCategoryDeleted;
use App\Repositories\BarangCategoryRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class BarangCategoryService
{
    protected $barangCategoryRepository;

    public function __construct(BarangCategoryRepository $barangCategoryRepository)
    {
        $this->barangCategoryRepository = $barangCategoryRepository;
    }

    public function getAll()
    {
        return $this->barangCategoryRepository->getAll();
    }

    public function getById($id)
    {
        return $this->barangCategoryRepository->findById($id);
    }

    public function create(array $data)
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255|unique:barang_categories,name',
        ], [
            'name.required' => 'Nama kategori barang wajib diisi',
            'name.unique' => 'Nama kategori barang sudah digunakan',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data['slug'] = Str::slug($data['name']);

        $barangCategory = $this->barangCategoryRepository->create($data);
        event(new BarangCategoryCreated($barangCategory));

        return $barangCategory;
    }

    public function update($id, array $data)
    {
        $barangCategory = $this->barangCategoryRepository->findById($id);
        if (!$barangCategory) {
            throw new \Exception('Kategori barang tidak ditemukan');
        }

        $validator = Validator::make($data, [
            'name' => "required|string|max:255|unique:barang_categories,name,{$barangCategory->id}",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data['slug'] = Str::slug($data['name']);

        $this->barangCategoryRepository->update($barangCategory, $data);
        event(new BarangCategoryUpdated($barangCategory));

        return $barangCategory;
    }

    public function delete($id)
    {
        $barangCategory = $this->barangCategoryRepository->findById($id);
        if (!$barangCategory) {
            throw new \Exception('Kategori barang tidak ditemukan');
        }

        $this->barangCategoryRepository->delete($barangCategory);
        event(new BarangCategoryDeleted($id));

        return true;
    }
}
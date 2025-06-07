<?php

namespace App\Services;

use App\Events\SatuanCreated;
use App\Events\SatuanUpdated;
use App\Events\SatuanDeleted;
use App\Models\Satuan;
use App\Repositories\SatuanRepository;
use Illuminate\Support\Str;

class SatuanService
{
    protected $satuanRepository;

    public function __construct(SatuanRepository $satuanRepository)
    {
        $this->satuanRepository = $satuanRepository;
    }

    public function getAll()
    {
        return $this->satuanRepository->getAll();
    }

    public function getById($id)
    {
        return $this->satuanRepository->findById($id);
    }

    public function findTrashedByName($name)
    {
        return $this->satuanRepository->findTrashedByName($name);
    }

    public function create(array $data)
    {
        $validatedData = $this->validateData($data);
        $validatedData['slug'] = Str::slug($validatedData['name']);
        $validatedData['user_id'] = auth()->id();

        $satuan = $this->satuanRepository->create($validatedData);
        event(new SatuanCreated($satuan));

        return $satuan;
    }

    public function update($id, array $data)
    {
        $validatedData = $this->validateData($data, $id);
        $validatedData['slug'] = Str::slug($validatedData['name']);
        $validatedData['user_id'] = auth()->id();

        $satuan = $this->satuanRepository->update($id, $validatedData);
        event(new SatuanUpdated($satuan));

        return $satuan;
    }

    public function delete($id)
    {
        $satuan = $this->satuanRepository->findById($id);

        if (!$satuan) {
            throw new \Exception('Satuan not found');
        }

        $this->satuanRepository->delete($satuan);
        event(new SatuanDeleted($id));

        return true;
    }

    public function restore($id)
    {
        $satuan = $this->satuanRepository->restore($id);
        if ($satuan) {
            event(new SatuanCreated($satuan)); // Trigger created event on restore
        }
        return $satuan;
    }

    private function validateData(array $data, $id = null)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', "unique:satuans,name,$id,id"],
            'description' => ['nullable', 'string'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];

        return validator($data, $rules)->validate();
    }
}
<?php

namespace App\Repositories;

use App\Models\Web;

class WebRepository
{
    public function getAll()
    {
        return Web::all();
    }

    public function findById($id)
    {
        return Web::findOrFail($id); // Removed eager loading unless needed
    }

    public function create(array $data)
    {
        return Web::create($data);
    }

    public function update(Web $web, array $data)
    {
        $web->update($data);
        return $web;
    }

    public function delete(Web $web)
    {
        return $web->delete();
    }
}
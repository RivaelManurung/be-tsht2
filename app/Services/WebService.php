<?php

namespace App\Services;

use App\Repositories\WebRepository;
use Illuminate\Support\Facades\Cache;

class WebService
{
    protected $repository;

    public function __construct(WebRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getAll()
    {
        return Cache::remember('webs_all', 3600, function () {
            return $this->repository->getAll();
        });
    }

    public function getById($id)
    {
        return Cache::remember("web_{$id}", 3600, function () use ($id) {
            return $this->repository->findById($id);
        });
    }

    public function create(array $data)
    {
        $web = $this->repository->create($data);
        Cache::forget('webs_all');
        return $web;
    }

    public function update($id, array $data)
    {
        $web = $this->repository->findById($id);
        $updatedWeb = $this->repository->update($web, $data);
        Cache::forget("web_{$id}");
        Cache::forget('webs_all');
        return $updatedWeb;
    }

    public function delete($id)
    {
        $web = $this->repository->findById($id);
        $this->repository->delete($web);
        Cache::forget("web_{$id}");
        Cache::forget('webs_all');
    }
}
<?php

namespace App\Console\Commands;

use App\Events\BarangCategoryCreated;
use App\Models\BarangCategory;
use Illuminate\Console\Command;

class TestBarangCategoryEvent extends Command
{
    protected $signature = 'test:barang-category-event';
    protected $description = 'Test broadcasting BarangCategoryCreated event';

    public function handle()
    {
        $category = BarangCategory::create([
            'name' => 'Test Kategori',
            'slug' => 'test-kategori'
        ]);

        event(new BarangCategoryCreated($category));

        $this->info('BarangCategoryCreated event broadcasted!');
    }
}
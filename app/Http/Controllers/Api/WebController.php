<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebResource;
use App\Services\WebService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Melihovv\Base64ImageDecoder\Base64ImageDecoder;

class WebController extends Controller
{
    protected $service;

    public function __construct(WebService $service)
    {
        $this->service = $service;
    }

    // App\Http\Controllers\Api\WebController.php

public function index()
{
    return WebResource::collection($this->service->getAll());
}

public function show($id)
{
    if ((int)$id !== 1) {
        return response()->json(['message' => 'Data Tidak Ada.'], 404);
    }

    return new WebResource($this->service->getById($id));
}
public function update(Request $request, $id)
{
    $request->validate([
        'web_nama' => 'sometimes|required|string|max:255',
        'web_deskripsi' => 'nullable|string',
        'web_logo' => 'nullable|string',
    ]);

    $web = $this->service->getById($id);
    $data = $request->only(['web_nama', 'web_deskripsi']);
    $data['user_id'] = Auth::id();

    if ($request->has('web_logo')) {
        $data['web_logo'] = $this->replaceImage($web->web_logo, $request->web_logo);
    }

        $web = $this->service->update($id, $data);
        return new WebResource($web);
    }


    private function handleImageUpload($base64Image)
    {
        return $base64Image ? uploadBase64Image($base64Image, 'img/web') : 'default_image.png';
    }

    private function replaceImage($oldImage, $newBase64)
    {
        if ($oldImage && $oldImage !== 'default_image.png') {
            Storage::disk('public')->delete($oldImage);
        }
        return uploadBase64Image($newBase64, 'img/web');
    }
}

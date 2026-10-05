<?php

namespace App\Http\Controllers;

use App\Services\ProductGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerProductImageController extends Controller
{
    public function __construct(private ProductGallery $gallery) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(
            ['image' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048'],
            ['image.max' => 'Image must be 2MB or smaller.', 'image.image' => 'File must be an image.', 'image.mimes' => 'Use a JPG, PNG, or WEBP image.']
        );

        return response()->json($this->gallery->stage($request->file('image')), 201);
    }

    public function destroy(string $token): JsonResponse
    {
        $this->gallery->discard($token);

        return response()->json(['ok' => true]);
    }
}

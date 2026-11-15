<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Rider\DocumentRenewal;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Renewing a document from the rider app, any time: a new driver's license (both sides, its
 * number and expiry) or OR/CR (1 to 5 files and the registration's expiry). The station hub
 * manager approves it; until then the one on file stays in force.
 */
class RiderDocumentController extends Controller
{
    public function store(Request $request, string $type, DocumentRenewal $renewal): JsonResponse
    {
        $image = 'file|mimes:jpg,jpeg,png,heic,webp|max:8192';
        $rider = Actor::rider();

        if ($type === 'drivers_license') {
            $data = $request->validate([
                'license_no' => 'required|string|max:30',
                'expiry_date' => 'required|date|after_or_equal:today',
                'front' => "required|{$image}",
                'back' => "required|{$image}",
            ]);
            $files = [[$request->file('front'), 'front'], [$request->file('back'), 'back']];
        } else {
            $data = $request->validate([
                'expiry_date' => 'required|date|after_or_equal:today',
                'files' => 'required|array|min:1|max:5',
                'files.*' => 'file|mimes:jpg,jpeg,png,heic,webp,pdf|max:8192',
            ]);
            $files = array_map(fn ($file) => [$file, null], array_values($request->file('files')));
        }

        try {
            $update = $renewal->submit($rider, $type, $data, $files);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Your new {$update->label()} was sent. Your station will review it; the one on file stays valid until then.",
            'rider' => RiderProfile::of($rider->fresh()),
        ], 201);
    }
}

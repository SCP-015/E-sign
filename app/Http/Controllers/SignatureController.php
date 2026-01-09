<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    /**
     * Get all signatures for authenticated user
     */
    public function index(Request $request)
    {
        $signatures = Signature::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($signature) {
                return [
                    'id' => $signature->id,
                    'name' => $signature->name,
                    'image_type' => $signature->image_type,
                    'is_default' => $signature->is_default,
                    'created_at' => $signature->created_at,
                ];
            });

        return response()->json($signatures);
    }

    /**
     * Upload/create a new signature
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'image' => 'required|file|mimes:png,svg|max:2048', // Max 2MB
            'is_default' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $file = $request->file('image');
        
        // Determine image type
        $extension = strtolower($file->getClientOriginalExtension());
        $imageType = $extension === 'svg' ? 'svg' : 'png';
        
        // Store file in private storage with email-based folder
        $email = strtolower($user->email);
        $filename = 'signature_' . uniqid() . '.' . $extension;
        $path = $file->storeAs("{$email}/signatures", $filename, 'private');

        // If this is set as default, unset other defaults
        if ($request->boolean('is_default')) {
            Signature::where('user_id', $user->id)->update(['is_default' => false]);
        }

        // Create signature record
        $signature = Signature::create([
            'user_id' => $user->id,
            'name' => $request->input('name', 'My Signature'),
            'image_path' => "private/{$path}",
            'image_type' => $imageType,
            'is_default' => $request->boolean('is_default', false),
        ]);

        return response()->json([
            'message' => 'Signature uploaded successfully',
            'signature' => [
                'id' => $signature->id,
                'name' => $signature->name,
                'image_type' => $signature->image_type,
                'is_default' => $signature->is_default,
                'created_at' => $signature->created_at,
            ],
        ], 201);
    }

    /**
     * Get signature image
     */
    public function getImage(Request $request, $id)
    {
        $user = $request->user();
        $signature = Signature::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Remove 'private/' prefix if present
        $relativePath = str_replace('private/', '', $signature->image_path);
        $filePath = Storage::disk('private')->path($relativePath);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Signature image not found'], 404);
        }

        $mimeType = $signature->image_type === 'svg' ? 'image/svg+xml' : 'image/png';

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * Set signature as default
     */
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();
        $signature = Signature::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Unset all other defaults
        Signature::where('user_id', $user->id)->update(['is_default' => false]);
        
        // Set this one as default
        $signature->update(['is_default' => true]);

        return response()->json([
            'message' => 'Signature set as default',
            'signature' => [
                'id' => $signature->id,
                'name' => $signature->name,
                'is_default' => true,
            ],
        ]);
    }

    /**
     * Delete a signature
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $signature = Signature::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Delete the file
        $relativePath = str_replace('private/', '', $signature->image_path);
        if (Storage::disk('private')->exists($relativePath)) {
            Storage::disk('private')->delete($relativePath);
        }

        // Delete the record
        $signature->delete();

        return response()->json([
            'message' => 'Signature deleted successfully',
        ]);
    }
}

<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'images'])
            ->latest()
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'digital_file' => 'required|file|mimes:zip,pdf,txt,epub,doc,docx,rar|max:51200', // 50MB Max
            'images' => 'nullable|array|max:15',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'cover_index' => 'nullable|integer',
        ]);

        $filePath = null;
        $originalName = null;
        $fileSize = null;

        // Store Digital Product File
        if ($request->hasFile('digital_file')) {
            $file = $request->file('digital_file');
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $filePath = $file->store('digital_products', 'public');
        }

        $product = Product::create([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'status' => $request->status,
            'file_path' => $filePath,
            'file_original_name' => $originalName,
            'file_size' => $fileSize,
        ]);

        // Process images
        if ($request->hasFile('images')) {
            $coverIndex = (int) $request->input('cover_index', 0);
            foreach ($request->file('images') as $index => $file) {
                $path = $file->store('products', 'public');
                $product->images()->create([
                    'image_path' => $path,
                    'order' => $index,
                    'is_cover' => ($index === $coverIndex),
                ]);
            }
        }

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product->load(['category', 'images']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'digital_file' => 'nullable|file|mimes:zip,pdf,txt,epub,doc,docx,rar|max:51200',
            'images' => 'nullable|array|max:15',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'cover_index' => 'nullable|integer',
            'retained_image_ids' => 'nullable|array',
        ]);

        // Update digital file if a new file is uploaded
        if ($request->hasFile('digital_file')) {
            // Delete old file
            if ($product->file_path && Storage::disk('public')->exists($product->file_path)) {
                Storage::disk('public')->delete($product->file_path);
            }

            $file = $request->file('digital_file');
            $product->file_original_name = $file->getClientOriginalName();
            $product->file_size = $file->getSize();
            $product->file_path = $file->store('digital_products', 'public');
        }

        $product->update([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'status' => $request->status,
        ]);

        // Retain / Reorder / Delete product images
        $retainedIds = $request->input('retained_image_ids', []);
        $imagesToDelete = $product->images()->whereNotIn('id', $retainedIds)->get();

        foreach ($imagesToDelete as $img) {
            if (Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
            $img->delete();
        }

        foreach ($retainedIds as $orderIndex => $imageId) {
            ProductImage::where('id', $imageId)
                ->where('product_id', $product->id)
                ->update(['order' => $orderIndex]);
        }

        if ($request->hasFile('images')) {
            $startOrder = count($retainedIds);
            foreach ($request->file('images') as $index => $file) {
                $path = $file->store('products', 'public');
                $product->images()->create([
                    'image_path' => $path,
                    'order' => $startOrder + $index,
                    'is_cover' => false,
                ]);
            }
        }

        if ($request->has('cover_image_id')) {
            $product->images()->update(['is_cover' => false]);
            $product->images()->where('id', $request->cover_image_id)->update(['is_cover' => true]);
        }

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->load(['category', 'images']),
        ]);
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->status = $product->status === 'active' ? 'inactive' : 'active';
        $product->save();

        return response()->json([
            'message' => "Product status changed to {$product->status}.",
            'product' => $product->load(['category', 'images']),
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Delete digital file
        if ($product->file_path && Storage::disk('public')->exists($product->file_path)) {
            Storage::disk('public')->delete($product->file_path);
        }

        // Delete product images
        foreach ($product->images as $img) {
            if (Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    private const SORTABLE_COLUMNS = ['title', 'type', 'price', 'quantity', 'status', 'created_at'];

    public function index(Request $request)
    {
        $query = Product::with(['category', 'images']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('file_original_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        if (!in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = min((int) $request->input('per_page', 10), 100);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:digital,physical',
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'digital_file' => 'required_if:type,digital|nullable|file|mimes:zip,pdf,txt,epub,doc,docx,rar|max:51200',
            'images' => 'nullable|array|max:15',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'cover_index' => 'nullable|integer',
        ]);

        $filePath = null;
        $originalName = null;
        $fileSize = null;

        if ($request->type === 'digital' && $request->hasFile('digital_file')) {
            $file = $request->file('digital_file');
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $filePath = $file->store('digital_products', 'public');
        }

        $product = Product::create([
            'type' => $request->type,
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
            'type' => 'required|in:digital,physical',
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

        if ($request->type === 'physical') {
            if ($product->file_path && Storage::disk('public')->exists($product->file_path)) {
                Storage::disk('public')->delete($product->file_path);
            }
            $product->file_path = null;
            $product->file_original_name = null;
            $product->file_size = null;
        } elseif ($request->hasFile('digital_file')) {
            if ($product->file_path && Storage::disk('public')->exists($product->file_path)) {
                Storage::disk('public')->delete($product->file_path);
            }
            $file = $request->file('digital_file');
            $product->file_original_name = $file->getClientOriginalName();
            $product->file_size = $file->getSize();
            $product->file_path = $file->store('digital_products', 'public');
        }

        $product->update([
            'type' => $request->type,
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'status' => $request->status,
        ]);

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

        if ($product->file_path && Storage::disk('public')->exists($product->file_path)) {
            Storage::disk('public')->delete($product->file_path);
        }

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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = File::query();

        if ($request->filled('original_name')) {
            $query->where('original_name', 'like', '%' . $request->original_name . '%');
        }
        if ($request->filled('mime_type')) {
            $query->where('mime_type', 'like', $request->mime_type . '%');
        }

        return $this->paginate($query, $request);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('uploads/' . date('Ymd'), 'public');

        $file = File::create([
            'user_id' => $request->user()->id,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'disk' => 'public',
        ]);

        return $this->success([
            'id' => $file->id,
            'url' => $file->url,
            'original_name' => $file->original_name,
            'path' => $file->path,
        ], '上传成功');
    }

    public function destroy(Request $request)
    {
        $file = File::findOrFail($request->id);
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return $this->success(null, '删除成功');
    }
}

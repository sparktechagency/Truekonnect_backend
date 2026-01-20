<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait UploadFile
{
    public function uploadFile(UploadedFile $file, string $folder, ?string $oldFilePath = null):string
    {
        if ($oldFilePath && File::exists(public_path($oldFilePath))) {
            File::delete(public_path($oldFilePath));
        }

        $fullFolderPath = public_path($folder);
        if (!File::exists($fullFolderPath)) {
            File::makeDirectory($fullFolderPath, 0755, true);
        }

        $manager = new ImageManager(new Driver());

        $img = $manager->read($file);

        $quality = 85;
        $webp = $img->toWebp(quality: 85);
        while (strlen((string) $webp) > 100 * 1024 && $quality > 50) {
            $quality -= 5;
            $webp = $img->toWebp(quality: $quality);
        }

        $fileName = time() . '.webp';
//        $fileName = $file->getClientOriginalName();

        $file->move($fullFolderPath, $fileName);

        return $folder . $fileName;
    }

    public function deleteFile(string $path):void
    {
        File::delete(public_path($path));
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    public function upload(UploadedFile $file, string $directory, ?string $previousFilename = null): string
    {
        $safeName = $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $newName = $safeName.'-'.uniqid().'.'.$file->guessExtension();

        $file->move($directory, $newName);

        if ($previousFilename !== null) {
            $this->remove($directory, $previousFilename);
        }

        return $newName;
    }

    public function remove(string $directory, string $filename): void
    {
        @unlink($directory.'/'.$filename);
    }

    /**
     * Duplicates an existing file in the same directory under a fresh unique
     * name and returns that name. If the source file is missing, the original
     * filename is returned unchanged (caller keeps the reference).
     */
    public function copy(string $directory, string $filename): string
    {
        $source = $directory.'/'.$filename;
        if (!is_file($source)) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = $this->slugger->slug(pathinfo($filename, PATHINFO_FILENAME));
        $newName = $base.'-'.uniqid().($extension !== '' ? '.'.$extension : '');

        copy($source, $directory.'/'.$newName);

        return $newName;
    }
}

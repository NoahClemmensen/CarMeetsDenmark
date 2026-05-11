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
}

<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    public function __construct(
        private string $uploadsDirectory,
        private SluggerInterface $slugger
    ) {
    }

    public function upload(UploadedFile $file, string $subdirectory = ''): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        $targetDirectory = $this->uploadsDirectory;
        if ($subdirectory) {
            $targetDirectory .= '/' . $subdirectory;
        }

        try {
            $file->move($targetDirectory, $fileName);
        } catch (FileException $e) {
            throw new \Exception('Failed to upload file: ' . $e->getMessage());
        }

        return $fileName;
    }

    public function delete(string $filename, string $subdirectory = ''): void
    {
        $filepath = $this->uploadsDirectory;
        if ($subdirectory) {
            $filepath .= '/' . $subdirectory;
        }
        $filepath .= '/' . $filename;

        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}

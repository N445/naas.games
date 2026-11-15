<?php

namespace App\Screenshot;

use App\Entity\Screenshot;
use App\Entity\ScreenshotOriginal;
use App\Entity\StoredImage;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class ScreenshotStorage
{
    private const string ORIGINALS_DIRECTORY = 'originals';

    public function __construct(
        #[Autowire('%app.screenshots_directory%')]
        private string $directory,
        private CacheManager $imagineCache,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(UploadedFile $upload, string $hash): StoredImage
    {
        return $this->moveUpload($upload, $hash, date('Y/m'));
    }

    public function storeOriginal(UploadedFile $upload, string $hash): StoredImage
    {
        return $this->moveUpload($upload, $hash, self::ORIGINALS_DIRECTORY.'/'.date('Y/m'));
    }

    /**
     * Copies (never moves) a file from a library folder that may be read-only.
     */
    public function copyOriginal(string $sourcePath, string $hash): StoredImage
    {
        $source = new File($sourcePath);
        $relativePath = sprintf('%s/%s/%s.%s', self::ORIGINALS_DIRECTORY, date('Y/m'), Uuid::v7(), $source->guessExtension() ?? strtolower($source->getExtension()));
        $stored = $this->describe($sourcePath, $relativePath, $source->getFilename(), (string) $source->getMimeType(), (int) $source->getSize(), $hash);
        $this->filesystem->copy($sourcePath, $this->absolutePathOf($relativePath));

        return $stored;
    }

    public function absolutePath(Screenshot|ScreenshotOriginal $image): string
    {
        return $this->absolutePathOf($image->getPath());
    }

    public function delete(Screenshot|ScreenshotOriginal $image): void
    {
        $this->imagineCache->remove($image->getPath());
        $this->filesystem->remove($this->absolutePath($image));
    }

    private function moveUpload(UploadedFile $upload, string $hash, string $directory): StoredImage
    {
        $relativePath = sprintf('%s/%s.%s', $directory, Uuid::v7(), $upload->guessExtension() ?? 'bin');
        $stored = $this->describe($upload->getPathname(), $relativePath, $upload->getClientOriginalName(), (string) $upload->getMimeType(), (int) $upload->getSize(), $hash);
        $upload->move(dirname($this->absolutePathOf($relativePath)), basename($relativePath));

        return $stored;
    }

    private function describe(string $sourcePath, string $relativePath, string $originalName, string $mimeType, int $size, string $hash): StoredImage
    {
        $dimensions = getimagesize($sourcePath) ?: [0, 0];

        return new StoredImage($relativePath, $originalName, $mimeType, $size, $dimensions[0], $dimensions[1], $hash);
    }

    private function absolutePathOf(string $relativePath): string
    {
        return $this->directory.'/'.$relativePath;
    }
}

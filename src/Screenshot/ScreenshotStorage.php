<?php

namespace App\Screenshot;

use App\Entity\Screenshot;
use App\Entity\StoredImage;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class ScreenshotStorage
{
    public function __construct(
        #[Autowire('%app.screenshots_directory%')]
        private string $directory,
        private CacheManager $imagineCache,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(UploadedFile $upload, string $hash): StoredImage
    {
        $relativePath = sprintf('%s/%s.%s', date('Y/m'), Uuid::v7(), $upload->guessExtension() ?? 'bin');
        $dimensions = getimagesize($upload->getPathname()) ?: [0, 0];
        $stored = new StoredImage(
            $relativePath,
            $upload->getClientOriginalName(),
            (string) $upload->getMimeType(),
            (int) $upload->getSize(),
            $dimensions[0],
            $dimensions[1],
            $hash,
        );

        $upload->move(dirname($this->absolutePathOf($relativePath)), basename($relativePath));

        return $stored;
    }

    public function absolutePath(Screenshot $screenshot): string
    {
        return $this->absolutePathOf($screenshot->getPath());
    }

    public function delete(Screenshot $screenshot): void
    {
        $this->imagineCache->remove($screenshot->getPath());
        $this->filesystem->remove($this->absolutePath($screenshot));
    }

    private function absolutePathOf(string $relativePath): string
    {
        return $this->directory.'/'.$relativePath;
    }
}

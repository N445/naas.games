<?php

namespace App\Ai;

use App\Entity\Screenshot;
use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;

final readonly class AnalysisImage
{
    private const string FILTER = 'ai_analysis';

    public function __construct(
        private DataManager $dataManager,
        private FilterManager $filterManager,
    ) {
    }

    /**
     * @return array{type: 'image', source: array{type: 'base64', mediaType: string, data: string}}
     */
    public function claudeBlock(Screenshot $screenshot): array
    {
        $image = $this->resize($screenshot);

        return ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => (string) $image->getMimeType(), 'data' => base64_encode($image->getContent())]];
    }

    public function dataUri(Screenshot $screenshot): string
    {
        $image = $this->resize($screenshot);

        return sprintf('data:%s;base64,%s', $image->getMimeType(), base64_encode($image->getContent()));
    }

    private function resize(Screenshot $screenshot): BinaryInterface
    {
        return $this->filterManager->applyFilter($this->dataManager->find(self::FILTER, $screenshot->getPath()), self::FILTER);
    }
}

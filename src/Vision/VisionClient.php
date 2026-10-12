<?php

namespace App\Vision;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class VisionClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(VISION_URL)%')]
        private string $baseUrl,
    ) {
    }

    public function isAvailable(): bool
    {
        return '' !== $this->baseUrl;
    }

    public function embedFile(string $path): Embedding
    {
        return $this->embed(DataPart::fromPath($path));
    }

    public function embedContent(string $content, string $filename): Embedding
    {
        return $this->embed(new DataPart($content, $filename));
    }

    private function embed(DataPart $image): Embedding
    {
        $form = new FormDataPart(['file' => $image]);
        $response = $this->httpClient->request('POST', $this->baseUrl.'/embed', [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
            'timeout' => 60,
        ]);

        return Embedding::fromVector($response->toArray()['vector']);
    }
}

<?php

namespace App\Ai\Provider;

use App\Ai\AnalysisImage;
use App\Ai\TagPrompt;
use App\Ai\TagSuggester;
use App\Ai\TagSuggestion;
use App\Entity\Screenshot;
use App\Vision\VisualContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MistralTagSuggester implements TagSuggester
{
    private const string ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

    public function __construct(
        private HttpClientInterface $httpClient,
        private AnalysisImage $analysisImage,
        private TagPrompt $prompt,
        #[Autowire('%env(MISTRAL_API_KEY)%')]
        private string $apiKey,
        #[Autowire('%env(MISTRAL_MODEL)%')]
        private string $model,
    ) {
    }

    public function isAvailable(): bool
    {
        return '' !== $this->apiKey;
    }

    public function suggest(Screenshot $screenshot, VisualContext $context): TagSuggestion
    {
        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'auth_bearer' => $this->apiKey,
            'timeout' => 120,
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->prompt->instructions()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => $this->prompt->request($screenshot, $context)],
                        ['type' => 'image_url', 'image_url' => $this->analysisImage->dataUri($screenshot)],
                    ]],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'screenshot_tags', 'schema' => $this->prompt->schema(), 'strict' => true],
                ],
            ],
        ]);

        return TagSuggestion::fromJson($response->toArray()['choices'][0]['message']['content']);
    }
}

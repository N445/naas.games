<?php

namespace App\Ai;

use App\Ai\Provider\ClaudeTagSuggester;
use App\Ai\Provider\LocalTagSuggester;
use App\Ai\Provider\MistralTagSuggester;
use App\Entity\Screenshot;
use App\Vision\VisualContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TagSuggester::class)]
final readonly class FallbackTagSuggester implements TagSuggester
{
    /**
     * @var list<TagSuggester>
     */
    private array $providers;

    public function __construct(
        MistralTagSuggester $mistral,
        ClaudeTagSuggester $claude,
        LocalTagSuggester $local,
        private LoggerInterface $logger,
    ) {
        $this->providers = array_values(array_filter([$mistral, $claude, $local], static fn (TagSuggester $provider): bool => $provider->isAvailable()));
    }

    public function isAvailable(): bool
    {
        return [] !== $this->providers;
    }

    public function suggest(Screenshot $screenshot, VisualContext $context): TagSuggestion
    {
        $failure = new \LogicException('Aucun fournisseur d\'IA disponible (MISTRAL_API_KEY, ANTHROPIC_API_KEY ou VISION_URL).');
        foreach ($this->providers as $provider) {
            try {
                return $provider->suggest($screenshot, $context);
            } catch (\Throwable $failure) {
                $this->logger->warning('Échec du tagging IA via {provider}, passage au suivant.', ['provider' => $provider::class, 'exception' => $failure]);
            }
        }

        throw $failure;
    }
}

<?php

namespace App\Ai\Provider;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use App\Ai\AnalysisImage;
use App\Ai\TagPrompt;
use App\Ai\TagSuggester;
use App\Ai\TagSuggestion;
use App\Entity\Screenshot;
use App\Vision\VisualContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ClaudeTagSuggester implements TagSuggester
{
    public function __construct(
        private AnalysisImage $analysisImage,
        private TagPrompt $prompt,
        #[Autowire('%env(ANTHROPIC_API_KEY)%')]
        private string $apiKey,
        #[Autowire('%env(CLAUDE_MODEL)%')]
        private string $model,
    ) {
    }

    public function isAvailable(): bool
    {
        return '' !== $this->apiKey;
    }

    public function suggest(Screenshot $screenshot, VisualContext $context): TagSuggestion
    {
        $message = (new Client(apiKey: $this->apiKey))->beta->messages->create(
            maxTokens: 8000,
            messages: [['role' => 'user', 'content' => [
                $this->analysisImage->claudeBlock($screenshot),
                ['type' => 'text', 'text' => $this->prompt->request($screenshot, $context)],
            ]]],
            model: $this->model,
            fallbacks: 'default',
            outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $this->prompt->schema()]],
            system: [['type' => 'text', 'text' => $this->prompt->instructions(), 'cacheControl' => ['type' => 'ephemeral']]],
            betas: ['server-side-fallback-2026-07-01'],
        );

        return TagSuggestion::fromJson($this->answer($message));
    }

    private function answer(BetaMessage $message): string
    {
        if ('refusal' === $message->stopReason) {
            throw new \RuntimeException('Claude a refusé d\'analyser cette image.');
        }

        foreach ($message->content as $block) {
            if ('text' === $block->type) {
                return $block->text;
            }
        }

        throw new \UnexpectedValueException(sprintf('Réponse de Claude sans texte (stop_reason : %s).', $message->stopReason));
    }
}

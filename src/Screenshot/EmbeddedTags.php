<?php

namespace App\Screenshot;

use App\Entity\Tag;

final readonly class EmbeddedTags
{
    private const array NAMESPACES = [
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'digiKam' => 'http://www.digikam.org/ns/1.0/',
        'lr' => 'http://ns.adobe.com/lightroom/1.0/',
        'dc' => 'http://purl.org/dc/elements/1.1/',
    ];

    private const array SOURCES = [
        '//digiKam:TagsList//rdf:li' => '/',
        '//lr:hierarchicalSubject//rdf:li' => '|',
        '//dc:subject//rdf:li' => null,
    ];

    /**
     * @return list<string> tag paths, deepest branches only
     */
    public function read(string $absolutePath): array
    {
        $xpath = $this->xmp($absolutePath);
        if (null === $xpath) {
            return [];
        }

        foreach (self::SOURCES as $query => $separator) {
            $paths = $this->paths($xpath, $query, $separator);
            if ([] !== $paths) {
                return $this->leaves($paths);
            }
        }

        return [];
    }

    private function xmp(string $absolutePath): ?\DOMXPath
    {
        if (!preg_match('#<x:xmpmeta.*?</x:xmpmeta>#s', (string) file_get_contents($absolutePath), $packet)) {
            return null;
        }

        $document = new \DOMDocument();
        if (!@$document->loadXML($packet[0])) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }

    /**
     * @return list<string>
     */
    private function paths(\DOMXPath $xpath, string $query, ?string $separator): array
    {
        $paths = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            $segments = null === $separator ? [$node->textContent] : explode($separator, $node->textContent);
            $paths[] = implode(Tag::PATH_SEPARATOR, array_filter(array_map(trim(...), $segments)));
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function leaves(array $paths): array
    {
        $isBranch = static fn (string $path): bool => [] !== array_filter($paths, static fn (string $other): bool => str_starts_with($other, $path.Tag::PATH_SEPARATOR));

        return array_values(array_filter($paths, static fn (string $path): bool => !$isBranch($path)));
    }
}

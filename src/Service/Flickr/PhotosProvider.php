<?php

namespace App\Service\Flickr;

use App\Model\Flickr\Photo;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PhotosProvider
{
    public function __construct(
        private readonly HttpClientInterface   $httpClient,
        private readonly DenormalizerInterface $denormalizer,
    )
    {
    }

    /**
     * @return Photo[]
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getPhotos(): array
    {
        $response = $this->getResponse();

        if (200 !== $response->getStatusCode()) {
            return [];
        }

        $data = $response->toArray();
        $photosRaw = $data['photoset']['photo'];
        return $this->denormalizer->denormalize($photosRaw, Photo::class . '[]');
    }

    private function getResponse()
    {
        $cache = new FilesystemAdapter();

        $method = 'GET';
        $url = 'https://api.flickr.com/services/rest/';
        $options = [
            'query' => [
//                'method' => 'flickr.people.getPublicPhotos',
                'method' => 'flickr.photosets.getPhotos',
                'api_key' => $_ENV['FLICKR_API_KEY'],
//                'user_id' => $_ENV['FLICKR_API_SECRET'],
//                'user_id' => $_ENV['FLICKR_USER_ID'],
                'photoset_id' => '72177720313649458',
                'format' => 'json',
                'nojsoncallback' => 1,
//                'per_page' => 20, // Nombre d'images que tu veux
//                'page' => 1,
            ],
        ];

        $cacheKey = md5($method . $url . json_encode($options));

        return $cache->get($cacheKey, function (ItemInterface $item) use ($method, $url, $options): ResponseInterface {
            $item->expiresAfter(3600);

            return $this->httpClient->request($method, $url, $options);
        });
    }
}
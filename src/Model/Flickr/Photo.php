<?php

namespace App\Model\Flickr;

class Photo
{
    private ?string $id;
    private ?string $secret;
    private ?string $server;
    private ?int $farm;
    private ?string $title;
    private ?string $isprimary;
    private ?int $ispublic;
    private ?int $isfriend;
    private ?int $isfamily;

    public function getImageUrl(): string
    {
        return sprintf('https://live.staticflickr.com/%s/%s_%s_b.jpg',
            $this->server,
            $this->id,
            $this->secret,
        );
    }

    public function getDetailUrl(): string
    {
        return sprintf('https://www.flickr.com/photos/%s/%s',
            $_ENV['FLICKR_USER_ID'],
            $this->id,
        );
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): Photo
    {
        $this->id = $id;
        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): Photo
    {
        $this->secret = $secret;
        return $this;
    }

    public function getServer(): ?string
    {
        return $this->server;
    }

    public function setServer(?string $server): Photo
    {
        $this->server = $server;
        return $this;
    }

    public function getFarm(): ?int
    {
        return $this->farm;
    }

    public function setFarm(?int $farm): Photo
    {
        $this->farm = $farm;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): Photo
    {
        $this->title = $title;
        return $this;
    }

    public function getIsprimary(): ?string
    {
        return $this->isprimary;
    }

    public function setIsprimary(?string $isprimary): Photo
    {
        $this->isprimary = $isprimary;
        return $this;
    }

    public function getIspublic(): ?int
    {
        return $this->ispublic;
    }

    public function setIspublic(?int $ispublic): Photo
    {
        $this->ispublic = $ispublic;
        return $this;
    }

    public function getIsfriend(): ?int
    {
        return $this->isfriend;
    }

    public function setIsfriend(?int $isfriend): Photo
    {
        $this->isfriend = $isfriend;
        return $this;
    }

    public function getIsfamily(): ?int
    {
        return $this->isfamily;
    }

    public function setIsfamily(?int $isfamily): Photo
    {
        $this->isfamily = $isfamily;
        return $this;
    }
}
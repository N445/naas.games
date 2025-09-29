<?php

namespace App\Entity\Link;

use App\Repository\Link\LinkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: LinkRepository::class)]
class Link
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column]
    private ?int $display_order = null;

    /**
     * @var Collection<int, Stats>
     */
    #[ORM\OneToMany(targetEntity: Stats::class, mappedBy: 'link', orphanRemoval: true)]
    private Collection $stats;

    public function __construct()
    {
        $this->stats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->display_order;
    }

    public function setDisplayOrder(int $display_order): static
    {
        $this->display_order = $display_order;

        return $this;
    }

    /**
     * @return Collection<int, Stats>
     */
    public function getStats(): Collection
    {
        return $this->stats;
    }

    public function addStat(Stats $stat): static
    {
        if (!$this->stats->contains($stat)) {
            $this->stats->add($stat);
            $stat->setLink($this);
        }

        return $this;
    }

    public function removeStat(Stats $stat): static
    {
        if ($this->stats->removeElement($stat)) {
            // set the owning side to null (unless already changed)
            if ($stat->getLink() === $this) {
                $stat->setLink(null);
            }
        }

        return $this;
    }
}

<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An author is a user of Mastodon who has subscribed to the website in order to make their statuses appear on the website.
 * @ORM\Entity(repositoryClass="App\Repository\AuthorRepository")
 */
class Author {
    public const STATE_NEW = 0;
    public const STATE_IMPORTING_STATUSES = 1;
    public const STATE_OK = 2;

    /**
     * An unique identifier to distinguish each author from another. This identifier is proper to the website
     * and does not reflect the identifier on their instance.
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $idMastodon;

    /**
     * The author username. It has usually the form "username@instance.tld", for instance "Gargron@mastodon.social".
     * @ORM\Column(type="string", length=255)
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $displayName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $avatar;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Status", mappedBy="author", orphanRemoval=true)
     * @ORM\OrderBy({"date" = "DESC"})
     */
    private $statuses;

    /**
     * @ORM\Column(type="integer")
     */
    private $state;

    public function __construct()
    {
        $this->statuses = new ArrayCollection();
        $this->state = self::STATE_NEW;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    /**
     * @return Collection|Status[]
     */
    public function getStatuses(): Collection
    {
        return $this->statuses;
    }

    public function addStatus(Status $status): self
    {
        if (!$this->statuses->contains($status)) {
            $this->statuses[] = $status;
            $status->setAuthor($this);
        }

        return $this;
    }

    public function removeStatus(Status $status): self
    {
        if ($this->statuses->contains($status)) {
            $this->statuses->removeElement($status);
            // set the owning side to null (unless already changed)
            if ($status->getAuthor() === $this) {
                $status->setAuthor(null);
            }
        }

        return $this;
    }

    public function getIdMastodon(): ?int
    {
        return $this->idMastodon;
    }

    public function setIdMastodon(int $idMastodon): self
    {
        $this->idMastodon = $idMastodon;

        return $this;
    }

    public function getState(): ?int
    {
        return $this->state;
    }

    public function setState(int $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getProfileUrl(): string {
        $param = preg_split("#@#", $this->getUsername());

        return 'https://' . $param[1] . '/@' . $param[0];
    }

    /**
     * @return Collection|Status[]
     */
    public function getWhitelistedStatuses(): Collection {
        $statuses = new ArrayCollection();

        foreach($this->statuses as $status) {
            if(!$status->isBlacklisted()) {
                $statuses->add($status);
            }
        }

        return $statuses;
    }
}

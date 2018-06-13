<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\StatusRepository")
 */
class Status
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Author", inversedBy="statuses")
     * @ORM\JoinColumn(nullable=false)
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $url;

    /**
     * @ORM\Column(type="integer")
     */
    private $favorited;

    /**
     * @ORM\Column(type="integer")
     */
    private $reblogged;

    /**
     * @ORM\Column(type="boolean")
     */
    private $blacklisted;

    public function getId()
    {
        return $this->id;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getFavorited(): ?int
    {
        return $this->favorited;
    }

    public function setFavorited(int $favorited): self
    {
        $this->favorited = $favorited;

        return $this;
    }

    public function getReblogged(): ?int
    {
        return $this->reblogged;
    }

    public function setReblogged(int $reblogged): self
    {
        $this->reblogged = $reblogged;

        return $this;
    }

    public function isBlacklisted(): ?bool
    {
        return $this->blacklisted;
    }

    public function setBlacklisted(bool $blacklisted): self
    {
        $this->blacklisted = $blacklisted;

        return $this;
    }
}

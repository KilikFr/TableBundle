<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entity with an object identifier (Symfony Uid), as found in many applications.
 */
#[ORM\Entity]
#[ORM\Table(name: 'kilik_document')]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 64)]
    private string $title;

    public function __construct(Uuid $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}

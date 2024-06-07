<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation\Groups;
use Symfony\component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=AuthorRepository::class)
 * @ApiResource()
 */
class Author
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"getBooks", "getAuthors"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"getBooks", "getAuthors"})
     *@Assert\NotBlank
     *@Assert\Length(
     *                 min=5,
     *                 max= 40,
     *                 minMessage= "Le nom de l'auteur doit faire au moins {{limit}} caractères",
     *                 maxMessage= "Le nom de l'auteur ne doit pas dépasser {{limit}} caractères")
     */
    private $lastName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"getBooks", "getAuthors"})
     * @Assert\Length(
     *                 min=5,
     *                 max= 40,
     *                 minMessage= "Le nom de l'auteur doit faire au moins {{limit}} caractères",
     *                 maxMessage= "Le nom de l'auteur ne doit pas dépasser {{limit}} caractères")
     */
    private $firstName;

    /**
     * @ORM\OneToMany(targetEntity=Book::class, mappedBy="author")
     * @Groups({"getAuthors"})
     */
    private $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): self
    {
        if (!$this->books->contains($book)) {
            $this->books[] = $book;
            $book->setAuthor($this);
        }

        return $this;
    }

    public function removeBook(Book $book): self
    {
        if ($this->books->removeElement($book)) {
            // set the owning side to null (unless already changed)
            if ($book->getAuthor() === $this) {
                $book->setAuthor(null);
            }
        }

        return $this;
    }
}

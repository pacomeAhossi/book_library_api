<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use  Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class BookController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des livres
     * 
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des livres",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="La page que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Le nombre d'éléments que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Books")
     * 
     * @Route("/api/books", name="app_books", methods={"GET"})
     */
    public function getAllBooks(BookRepository $bookRepo, SerializerInterface $serializer, Request $request,
                                TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepo, $page, $limit, $serializer) {
            $item->tag("booksCache");
            $bookList = $bookRepo->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializer->serialize($bookList, 'json', $context);
        });
        
        // $idCache = "getAllBooks-" . $page . "-" . $limit;
        // $bookList = $cachePool->get($idCache, function (ItemInterface $item) use ($bookRepo, $page, $limit) {
        //     $item->tag("booksCache");
        //     return $bookRepo->findAllWithPagination($page, $limit);
        // });

        // $context = SerializationContext::create()->setGroups(['getBooks']);
        // $jsonBookList = $serializer->serialize($bookList, 'json', $context);

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }


    /**
     * Cette méthode permet de récupérer un livre en particulier en fonction de son id
     * 
     * @OA\Tag(name="Books")
     * 
     * @Route("/api/books/{id}", name="app_books_detail", methods={"GET"})
     */
    public function getDetailBook(Book $book, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $context->setVersion($version);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);

    }

    //Suppression d'un livre

    /**
     * Cette méthode permet de supprimer un livre en particulier en fonction de son id
     * 
     * @OA\Response(
     *     response=204,
     *     description="Ressource livre supprimée"
     * )
     * 
     *  @OA\Response(
     *     response=404,
     *     description="Ressource non trouvée"
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     * @Route("/api/books/{id}", name="app_delete_books", methods={"DELETE"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas le droit nécessaire pour modifier un livre")
     *
     */
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);

    }


    /**
     * Cette méthode permet d'insérer un nouveau livre
     * 
     * @OA\Response(
     *     response=201,
     *     description="Retourne le livre créé",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     * @OA\Parameter(
     *     name="title",
     *     in="query",
     *     description="Le titre du livre à créer",
     *     @OA\Schema(type="string")
     * )
     *
     * @OA\Parameter(
     *     name="coverText",
     *     in="query",
     *     description="La quatrième de couverture",
     *     @OA\Schema(type="text")
     * )
     * @OA\Parameter(
     *     name="author",
     *     in="query",
     *     description="Id de l'author",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Books")
     * 
     * @Route("/api/books", name="app_books_create", methods={"POST"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas le droit nécessaire pour créer un livre")
     */
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator,
        TagAwareCacheInterface $cache): JsonResponse {

        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        dd($book);

        // On vérifie les erreurs
        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            //throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["booksCache"]);

        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $jsonBook = $serializer->serialize($book, 'json', $context);
		
        $location = $urlGenerator->generate('app_books_detail', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

		return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" => $location], true);	
    }
    


    // Update d'un livre

     /**
      * Cette méthode permet de modifier un livre en fonction de son id
      
     * @Route("/api/books/{id}", name="app_update_books", methods={"PUT"})
     * @IsGranted("ROLE_ADMIN", message="Vous n'avez pas le droit nécessaire pour modifier un livre")
     */
    public function updateBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
                                Book $currentBook, AuthorRepository $authorRepo, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        //Vérification de la validité des attributs de l'objet avant la persistance
        $errors = $validator->validate($currentBook);
        if($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        //Récupération du contenu envoyé sous forme de tableau
        $content = $request->toArray();
        //Récupération de l'id de l'author
        $idAuthor = $content['idAuthor'] ?? -1;
        // On recherche l'author qui possede cet id et on lui assigne le livre
        $currentBook->setAuthor($authorRepo->find($idAuthor));
        
        $em->persist($currentBook);
        $em->flush();
         
        // On vide le cache
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);

    }
}

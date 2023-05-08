<?php

namespace App\Controller;

use App\Entity\Book;
use OpenApi\Annotations as OA;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use  Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookController extends AbstractController
{
    #[Route('api/books/{id}', name: 'detailBook', methods: ['GET'])]
    /**
     * @OA\Get(
     *     path="/api/books/{id}",
     *     @OA\Response(response="200", description="An example endpoint")
     * )
     */
    public function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    #[Route('api/books', name: 'books', methods: ['GET'])]
    public function getBookList(BookRepository         $bookRepository,
                                SerializerInterface    $serializer,
                                Request                $request,
                                TagAwareCacheInterface $cachePool): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . $limit;
        $bookList = $cachePool->get($idCache, function (ItemInterface $item) use (
            $bookRepository, $page, $limit
        ) {
            $item->tag("booksCache");
            return $bookRepository->findAllWithPagination($page, $limit);
        });
        
        $jsonBookList = $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);

        return new JsonResponse(['books' => $jsonBookList, Response::HTTP_OK, [], true]);
    }

    /**
     * @throws ORMException
     */
    #[Route('api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em): JsonResponse
    {
        try {
            $em->remove($book);
            $em->flush();
        } catch (ORMException $e) {
            throw new ORMException('erreur : ' . $e);
        }
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('api/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(Request                $request,
                               SerializerInterface    $serializer,
                               EntityManagerInterface $em,
                               AuthorRepository       $authorRepository,
                               UrlGeneratorInterface  $urlGenerator,
                               ValidatorInterface     $validator): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        $errors = $validator->validate($book);

        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true);
        }

        $content = $request->toArray();

        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['Location' => $location], true);

    }

    #[Route('api/books/{id}', name: "updateBook", methods: ['PUT'])]
    public function updateBook(Request                $request,
                               SerializerInterface    $serializer,
                               Book                   $currentBook,
                               EntityManagerInterface $em): JsonResponse
    {
        $updateBook = $serializer->deserialize($request->getContent(),
            Book::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);

        $em->persist($updateBook);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}

<?php

namespace TestApp\Controller\Tags;

use TestApp;
use Symfony\Component\Routing\Annotation\Route;
use RestApiBundle\Mapping\OpenApi as Docs;

/**
 * @Route("/tag2")
 */
class Tag2Controller
{
    /**
     * @var TestApp\Repository\BookRepository
     */
    private $bookRepository;

    public function __construct(TestApp\Repository\BookRepository $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @Docs\Endpoint(title="Book response model details", tags={"tag2"})
     *
     * @Route(methods="GET")
     */
    public function getGenreAction(): TestApp\ResponseModel\Book
    {
        $book = $this->bookRepository->find(1);

        return new TestApp\ResponseModel\Book($book);
    }
}

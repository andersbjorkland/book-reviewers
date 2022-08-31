<?php

namespace App\Controller;

use App\Model\Book;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;

class BookController extends ContentController
{
    private static $allowed_actions = [
        'view' // This is the action that will be called when a user clicks on a book title
    ];

    // The index action will be called when the user visits the root of the BookController at '/book'.
    // It displays a paginated book results lists for the current query.
    public function index()
    {
        // Get the current query from the request
        $search = $this->request->getVar("q") ?? '';

        // If you like to specify a sort order, we can do it like this.
        // We won't work more on this feature for this context, but it's something to improve upon.
        $sort = $this->request->getVar("sort");
        $fields = DataObject::getSchema()->fieldSpecs(Book::class);
        if (!key_exists($sort, $fields)) {
            $sort = 'Title';
        }

        if ($search) {
            $books = Book::get()

                // Only show books that have at least one review
                ->filter([
                    'Reviews.Count():GreaterThan' => 0
                ])

                // A rudimentary search for title or author.
                ->filterAny([
                    'Title:PartialMatch' => $search,
                    'Authors.Name:PartialMatch' => $search,
                ]);
        } else {
            $books = Book::get()->sort($sort);
        }

        return $this->customise([
            'Layout' => $this
                ->customise([
                    'Books' => (new PaginatedList($books, $this->getRequest()))
                        ->setPageLength(5),
                    'Query' => $search,
                ])
                ->renderWith('Layout/BookHolder'),

        ])->renderWith(['Page']);
    }

    // This action will be called when a user clicks on a book title and will display the book and its reviews (with a paginated list).
    public function view()
    {
        $book = Book::get()->filter([
            'VolumeID' => $this->request->param('ID')  // We get the ID from the URI (instead of as a query parameter)
        ])->first();


        return $this->customise([
            'Book' => $book,
            'Layout' => $this
                ->customise([
                    'Book' => $book,
                    'Reviews' => (new PaginatedList($book->Reviews(), $this->getRequest()))
                        ->setPageLength(5),
                ])
                ->renderWith('Layout/BookPage'),
        ])->renderWith(['Page']);
    }
}

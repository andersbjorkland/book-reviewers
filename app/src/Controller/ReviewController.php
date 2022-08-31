<?php

namespace App\Controller;

use App\Admin\ReviewAdmin;
use App\Model\Author; // <-- This is new
use App\Model\Book; // <-- This is new
use App\Model\Review;
use App\Service\GoogleBookParser;
use GuzzleHttp\Client;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class ReviewController extends ContentController
{
    private static $allowed_actions = [
        'index', // this action is normally inferred, let's add it explicitly
        'book',  // We add the "book" action as an allowed action.
        'ReviewForm'    // We add the "ReviewForm" action as an allowed action.
    ];

    public function index(HTTPRequest $request)
    {
        $search = $request->getVar('q');
        $searchQuery = "q=" . $search;

        $startIndex = $request->getVar("startIndex") ?? 0;

        $langRestriction = $request->getVar("langRestrict") ?? 'any';
        $langRestrictionQuery = $langRestriction ? "&langRestrict=" . $langRestriction : "";

        $maxResults = $request->getVar('maxResults') ?? 10;
        $maxResultsQuery = '&maxResults=' . $maxResults;

        $client = new Client();

        // Get language codes
        $response = $client->request('GET', 'https://gist.githubusercontent.com/jrnk/8eb57b065ea0b098d571/raw/936a6f652ebddbe19b1d100a60eedea3652ccca6/ISO-639-1-language.json');
        $languageCodes = [["code" => "any", "name" => "Any"]];
        array_push($languageCodes, ...json_decode($response->getBody()->getContents(), true));

        $books = [];
        $pagination = [];

        if ($search) {
            $basicQuery = $searchQuery
                . $langRestrictionQuery
                . $maxResultsQuery;

            $response = $client->request('GET', 'https://www.googleapis.com/books/v1/volumes?'. $basicQuery);
            $responseContent = json_decode($response->getBody()->getContents(), true);
            $books = GoogleBookParser::parseAll($responseContent);

            $pagination = $this->paginator('/review?' . $basicQuery, $responseContent['totalItems'], $startIndex, $maxResults);
            $pagination['pages'] = ArrayList::create($pagination['pages']);
            $pagination = ArrayList::create([$pagination]);
        }

        return $this->customise([
            'Layout' => $this
                ->customise([
                    'Books' => ArrayList::create($books),
                    'Pagination' => $pagination,
                    'Query' => $search,
                    'Languages' => ArrayList::create($languageCodes),
                    'LangRestriction' => $langRestriction
                ])
                ->renderWith('Layout/Books'),

        ])->renderWith(['Page']);
    }

    // Add the book-method. This method will be called if the user requests `/review/book/{$volumeId}`.
    public function book(HTTPRequest $request)
    {
        $volumeId = $request->param('ID'); // This retrieves the ID-parameter from the url.

        // Next we query the Google Books API to get the book details.
        $client = new Client();
        $response = $client->request('GET', 'https://www.googleapis.com/books/v1/volumes/' . $volumeId);
        $responseContent = json_decode($response->getBody()->getContents(), true);
        $googleBook = GoogleBookParser::parse($responseContent);

        // We create new Author objects and store them if they do not currently exist in our database.
        $authors = [];
        foreach ($googleBook["authors"] as $googleAuthor) {
            $author = Author::get()->filter(['Name' => $googleAuthor->AuthorName])->first();

            if (!$author) {
                $author = Author::create();
                $author->Name = $googleAuthor->AuthorName;

                $names = explode(" ", $googleAuthor->AuthorName);
                $author->GivenName = $names[0];
                if (count($names) > 2) {
                    $additionalName = "";

                    for ($i = 1; $i < count($names) - 1; $i++) {
                        $additionalName .= $names[$i] . " ";
                    }

                    $author->AdditionalName = $additionalName;
                }
                $author->FamilyName = $names[count($names) - 1];

                $author->write();

                $authors[] = $author;
            }
        }

        // We create a new Book object and store it in our database (if it doesn't exist already).
        $book = Book::get()->filter(['VolumeID' => $volumeId])->first();
        if (!$book) {
            $book = Book::create();
            $book->VolumeID = $volumeId;
            $book->Title = $googleBook["title"];
            $book->ISBN = $googleBook["isbn"];
            $book->Description = $googleBook["description"];

            foreach ($authors as $author) {
                $book->Authors()->add($author);
            }

            $book->write();
        }

        // We return a response which will be rendered with a new layout called 'Review'.
        return $this->customise([
            'Layout' => $this
                ->customise([
                    'Book' => $googleBook,
                ])
                ->renderWith('Layout/Review'),
        ])->renderWith(['Page']);

    }

    public function ReviewForm()
    {
        // The Form is rendered on a page where an ID parameter is present. We will fetch a Book object from the database based on the ID.
        $volumeId = $this->request->param('ID');
        $book = Book::get()->filter(['VolumeID' => $volumeId])->first();

        // If the current user has reviewed the book before, we will fetch the review from the database.
        // This allows us to pre-fill the form with the user's previous review.
        $currentUser = Security::getCurrentUser();
        $review = Review::get()->filter([
            'MemberID' => $currentUser->ID,
            'BookID' => $book->ID ?? 0
        ])->first();

        // We create a list of fields for the form.
        $fields = new FieldList(
            [
                // This field holds a ID for the review if it already exists. (This lets us update the review instead of creating a new one.)
                HiddenField::create(
                    'ReviewId',
                    'ReviewId',
                    $review ? $review->ID : null
                ),

                // This field holds the ID of the book. It's required so we can store a relation to the book that the review is for.
                HiddenField::create(
                    'VolumeId',
                    'VolumeId',
                    $volumeId
                ),

                // The next 3 fields are the review itself - a heading (title), a rating and a review (the text body).
                TextField::create(
                    'Title',
                    'Title',
                    $review ? $review->Title : null
                ),
                DropdownField::create(
                    'Rating',
                    'Rating',
                    [
                        '1' => 1,
                        '2' => 2,
                        '3' => 3,
                        '4' => 4,
                        '5' => 5
                    ]
                )->setValue($review ? $review->Rating : null),
                TextareaField::create(
                    'Review',
                    'Review',
                    $review ? $review->Review : null
                )
            ]
        );

        // We create a form connected to this controller and give it a name 'ReviewForm'
        // and pass the fields we created above.
        // We also create a new field list which creates a submit button named `Submit` and "connect" it with the method 'doReview'. It also controls that the required fields are filled.
        $form = Form::create(
            $this,
            'ReviewForm',
            $fields,
            new FieldList(
                FormAction::create('doReview', 'Submit')),
            new RequiredFields('Title', 'Rating'));

        // Normally the form would suffice as it is if this was based on a Page-object, but we are not, so we will set the form-action to the URL that will handle the form submission instead.
        $form->setFormAction('/review/ReviewForm/');

        return $form;
    }

    // This is the method tasked with handling the form submission.
    public function doReview($data, Form $form)
    {
        $book = Book::get()->filter(['VolumeID' => $data['VolumeId']])->first();

        // If a review already exists, the form will have a ReviewId field with the ID for it. Otherwise we create a new review.
        $review = $data['ReviewId'] ? Review::get_by_id($data['ReviewId']) : Review::create();

        // We set the review's properties based on the form data and save it.
        $review->Title = $data['Title'];
        $review->Rating = $data['Rating'];
        $review->Review = $data['Review'];
        $review->Member = Security::getCurrentUser();
        $review->Book = $book;
        $review->write();

        $form->sessionMessage('Your review has been saved', 'good');
        return $this->redirectBack();
    }

    /**
     * Returns an array with links to pages with the necessary query parameters
     */
    protected function paginator($query, $count, $startIndex, $perPage): array
    {
        $pagination = [
            'start' => false,
            'current' => false,
            'previous' => false,
            'next' => false,
            'totalPages' => 0,
            'pages' => false,
        ];

        $currentPage = ceil($startIndex / $perPage) + 1;

        $previousIndex = $startIndex - $perPage;
        if ($previousIndex < 0) {
            $previousIndex = false;
        }

        $nextIndex = $perPage * ($currentPage);
        if ($nextIndex > $count) {
            $nextIndex = false;
        }

        $pagination['start'] = [
            'page' => $previousIndex > 0 ? 1 : false,
            'link' => $previousIndex > 0 ? $query . '&startIndex=0' : false,
        ];

        $pagination['current'] = [
            'page' => $currentPage,
            'link' => $query . '&startIndex=' . $startIndex
        ];
        $pagination['previous'] = [
            'page' => $previousIndex !== false ? $currentPage - 1 : false,
            'link' => $previousIndex !== false ? $query . '&startIndex=' . $previousIndex : false,
        ];
        $pagination['next'] = [
            'page' => $nextIndex ? $currentPage + 1 : false,
            'link' => $nextIndex ? $query . '&startIndex=' . $nextIndex : false,
        ];

        $totalPages = ceil($count / $perPage);
        $pagination['totalPages'] = $totalPages;
        $pages = [];

        for ($i = 0; $i < 3; $i++) {
            $page = $currentPage + $i - 1;

            if ($currentPage == 1) {
                $page = $currentPage + $i;
            }

            if ($page > $totalPages) {
                break;
            }
            if ($page < 1) {
                continue;
            }

            $pages[] = [
                'page' => $page,
                'link' => $query . '&startIndex=' . ($page - 1) * $perPage,
                'currentPage' => $page == $currentPage
            ];
            $pagination['pages'] = $pages;
        }

        return $pagination;
    }

    public function init()
    {
        parent::init();

        if(!Permission::check('CMS_ACCESS_' . ReviewAdmin::class)) {
            return $this->redirect('/Security/login?BackURL=' . $this->request->getURL());
        }
    }

}

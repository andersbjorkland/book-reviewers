<?php

namespace App\Controller;

use GuzzleHttp\Client;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;

class ReviewController extends ContentController
{
    private static $allowed_actions = [
        'index'
    ];

    public function index(HTTPRequest $request)
    {
        $q = $request->getVar('q');
        $langRestriction = $request->getVar('lang') ? "&langRestrict=" . $request->getVar('lang') : "";

        if ($q) {

            $client = new Client();
            $response = $client->request('GET',   'https://www.googleapis.com/books/v1/volumes?q=' . $q . $langRestriction);
            $data = json_decode($response->getBody()->getContents(), true);

            return $data['items'][0]['volumeInfo']['title'];  // "Harry Potter and the Philosopher's Stone"
        }

        return "Sorry, no valid query parameter found.";
    }
}

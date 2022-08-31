<?php

namespace App\Controller;

use App\Model\Review;
use SilverStripe\CMS\Controllers\ContentController;

class HomepageController extends ContentController
{
    public function LatestReviews()
    {
        $reviews = Review::get()->sort('Created', 'DESC')->limit(5);
        return $reviews;
    }

}

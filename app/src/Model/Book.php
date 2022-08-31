<?php

namespace App\Model;

use App\Admin\ReviewAdmin;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

class Book extends DataObject
{
    private static $table_name = "Book";

    private static $db = [
        'VolumeID' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'ISBN' => 'Varchar(255)',
        'Description' => 'Text',
    ];

    private static $has_many = [
        'Reviews' => Review::class,
    ];

    private static $many_many = [
        'Authors' => Author::class
    ];

    private static $casting = [
        'DescriptionHTML' => 'HTMLText'
    ];

    public function DescriptionHTML()
    {
        return $this->Description;
    }

    public function getAverageRating()
    {
        $reviews = $this->Reviews();
        $total = 0;
        foreach ($reviews as $review) {
            $total += $review->Rating;
        }
        return $total / $reviews->count();
    }

    public function getAverageRatingStars()
    {
        $rating = $this->getAverageRating();
        $stars = '';
        for ($i = 1; $i <= $rating; $i++) {
            $stars .= '⭐';
        }
        return $stars;
    }

    public function canView($member = null)
    {
        return Permission::check('CMS_ACCESS_' . ReviewAdmin::class, 'any', $member);
    }

    public function canEdit($member = null)
    {
        return Permission::check('CMS_ACCESS_' . ReviewAdmin::class, 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check('CMS_ACCESS_' . ReviewAdmin::class, 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_' . ReviewAdmin::class, 'any', $member);
    }
}

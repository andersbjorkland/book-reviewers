<?php

namespace App\Model;

use App\Admin\ReviewAdmin;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class Review extends DataObject
{
    private static $table_name = "Review";

    private static $db = [
        'Title' => 'Varchar',
        'Rating' => 'Int',
        'Review' => 'Text'
    ];

    private static $has_one = [
        'Book' => Book::class,
        'Member' => Member::class
    ];

    private static $owns = [
        'Book',
        'Member'
    ];

    private static $summary_fields = [
        'Title',
        'Book.Title'
    ];

    public function populateDefaults()
    {
        $this->Member = Security::getCurrentUser();
        parent::populateDefaults();
    }

    public function getRatingStars()
    {
        $rating = $this->Rating;
        $stars = '';
        for ($i = 1; $i <= $rating; $i++) {
            $stars .= '⭐';
        }
        return $stars;
    }

    public function validate()
    {
        $result = parent::validate();

        if ($this->Rating < 1 || $this->Rating > 5) {
            $result->addError('Rating must be between 1 and 5');
        }

        if ($this->Member != Security::getCurrentUser()) {
            $result->addError('Only you may be the reviewer of a book that YOU review.');
        }

        return $result;
    }

    public function canView($member = null)
    {
        return Permission::check('CMS_ACCESS_' . ReviewAdmin::class, 'any', $member);
    }

    public function canEdit($member = null)
    {
        $reviewer = $this->Member()->ID;
        $currentUser = Security::getCurrentUser()->ID;
        if ($reviewer === $currentUser) {
            return true;
        } else {
            return false;
        }
    }

    public function canDelete($member = null)
    {
        $reviewer = $this->Member()->ID;
        $currentUser = Security::getCurrentUser()->ID;
        if ($reviewer === $currentUser) {
            return true;
        } else {
            return false;
        }
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_' . ReviewAdmin::class, 'any', $member);
    }
}

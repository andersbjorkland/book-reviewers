<?php

namespace App\Controller;

use App\Form\ValidatedAliasField;
use App\Form\ValidatedEmailField;
use App\Form\ValidatedPasswordField;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Security\Member; // Will be used later when we do register a new member.

class RegistrationController extends ContentController
{
    private static $allowed_actions = [
        'registerForm'
    ];

    public function registerForm()
    {
        $fields = new FieldList(
            ValidatedAliasField::create( 'alias', 'Alias')->addExtraClass('text'),
            ValidatedEmailField::create('email', 'Email'),
            ValidatedPasswordField::create('password', 'Password'),
        );

        $actions = new FieldList(
            FormAction::create(
                'doRegister',   // methodName
                'Register'      // Label
            )
        );

        $required = new RequiredFields('alias', 'email', 'password');

        $form = new Form($this, 'RegisterForm', $fields, $actions, $required);

        return $form;
    }

    public function doRegister($data, Form $form)
    {
        // To be detailed later
    }
}

<?php

use Simcify\Router;
use Simcify\Exceptions\Handler;
use Simcify\Middleware\Authenticate;
use Pecee\Http\Middleware\BaseCsrfVerifier;
use Simcify\Middleware\RedirectIfAuthenticated;

/**
 * ,------,
 * | NOTE | CSRF Tokens are checked on all PUT, POST and GET requests. It
 * '------' should be passed in a hidden field named "csrf-token" or a header
 *          (in the case of AJAX without credentials) called "X-CSRF-TOKEN"
 *  */
Router::csrfVerifier(new BaseCsrfVerifier());

// Router::group(array(
//     'prefix' => '/landa'
// ), function()
// {
    
    Router::group(array(
        'exceptionHandler' => Handler::class
    ), function()
    {
        
        Router::group(array(
            'middleware' => Simcify\Middleware\Authenticate::class
        ), function()
        {
            //Dashboard
            Router::get('/', 'Dashboard@get');
            Router::get('/', 'Dashboard@get');

            //Schools
            Router::get('/schools', 'School@get');
            Router::post('/schools/delete', 'School@delete');
            Router::post('/school/create', 'School@create');
            Router::post('/school/update', 'School@update');
            Router::post('/school/update/view', 'School@updateview');
            Router::post('/schools/sendemail', 'School@sendemail');
            Router::post('/schools/sendsms', 'School@sendsms');

            //Branches
            Router::get('/branches', 'Branch@get');
            Router::post('/branch/switch', 'Branch@switcher');
            Router::post('/branch/delete', 'Branch@delete');
            Router::post('/branch/update', 'Branch@update');
            Router::post('/branch/update/view', 'Branch@updateview');
            Router::post('/branch/create', 'Branch@create');
            Router::post('/branch/sendemail', 'Branch@sendemail');
            Router::post('/branch/sendsms', 'Branch@sendsms');

            // settings
            Router::get('/settings', 'Settings@get');
            Router::post('/settings/update/profile', 'Settings@updateprofile');
            Router::post('/settings/update/company', 'Settings@updatecompany');
            Router::post('/settings/update/system', 'Settings@updatesystem');
            Router::post('/settings/update/reminders', 'Settings@updatereminders');
            Router::post('/settings/update/password', 'Settings@updatepassword');

            //Staff
            Router::get('/staff', 'Staff@get');
            Router::post('/staff/create', 'Staff@create');

            //Students
            Router::get('/students', 'Student@get');
            Router::post('/student/create', 'Student@create');
            Router::post('/student/enroll/course', 'Student@addcourse');

            //Scheduling
            Router::get('/scheduling', 'Schedule@get');
            Router::post('/schedule/create', 'Schedule@create');
            Router::post('/schedule/events/fetch', 'Schedule@fetch');
            Router::post('/schedule/update', 'Schedule@update');
            Router::post('/schedule/delete', 'Schedule@delete');
            Router::post('/schedule/update/view', 'Schedule@updateview');

            //Profile
            Router::get('/profile/{userid}', 'Profile@get', array(
                'as' => 'userid'
            ));
            Router::post('/profile/update', 'Profile@update');
            Router::post('/profile/delete', 'Profile@delete');
            Router::post('/profile/send/sms', 'Profile@sendsms');
            Router::post('/profile/send/email', 'Profile@sendemail');
            Router::post('/profile/payment/add', 'Profile@addpayment');
            Router::post('/profile/note/add', 'Profile@addnote');
            Router::post('/profile/note/read', 'Profile@readnote');
            Router::post('/profile/note/delete', 'Profile@deletenote');
            Router::post('/profile/note/update', 'Profile@updatenote');
            Router::post('/profile/note/update/view', 'Profile@updatenoteview');
            Router::post('/profile/attachment/upload', 'Profile@uploadattachment');
            Router::post('/profile/attachment/delete', 'Profile@deleteattachment');

            //Notification
            Router::get('/notifications', 'Notification@get');
            Router::post('/notifications/read', 'Notification@read');

            //Send SMS/Email
            Router::post('/send/sms', 'Communication@sendSMS');
            Router::post('/send/email', 'Communication@sendEmail');

            //Invoices
            Router::get('/invoices', 'Invoice@get');
            Router::get('/invoice/preview/{invoiceid}', 'Invoice@preview', array(
                'as' => 'invoiceid'
            ));
            Router::get('/invoice/download/{invoiceid}', 'Invoice@download', array(
                'as' => 'invoiceid'
            ));
            Router::post('/invoice/update', 'Invoice@update');
            Router::post('/invoice/update/view', 'Invoice@updateview');
            Router::post('/invoice/delete', 'Invoice@delete');
            Router::post('/invoices/payment/add', 'Invoice@addpayment');
            Router::post('/invoices/payment/delete', 'Invoice@deletepayment');
            Router::post('/invoices/payments/view', 'Invoice@viewpayments');

            //Fleet
            Router::get('/fleet', 'Fleet@get');
            Router::post('/fleet/add', 'Fleet@add');
            Router::post('/fleet/delete', 'Fleet@delete');
            Router::post('/fleet/update', 'Fleet@update');
            Router::post('/fleet/update/view', 'Fleet@updateview');
            
            //Inquiries
            Router::get('/inquiries', 'Inquiries@get');
            Router::post('/inquiries/add', 'Inquiries@add');
            Router::post('/inquiries/delete', 'Inquiries@delete');
            Router::post('/inquiries/update', 'Inquiries@update');
            Router::post('/inquiries/update/view', 'Inquiries@updateview');


            //Courses
            Router::get('/courses', 'Course@get');
            Router::get('/course/{courseid}', 'Course@preview', array(
                'as' => 'courseid'
            ));
            Router::post('/course/create', 'Course@create');
            Router::post('/course/delete', 'Course@delete');
            Router::post('/course/update', 'Course@update');
            Router::post('/course/update/view', 'Course@updateview');

            //Communication
            Router::get('/communication', 'Communication@get');
            Router::post('/communication/delete', 'Communication@delete');
            Router::post('/communication/sms/send', 'Communication@sms');
            Router::post('/communication/email/send', 'Communication@email');
            Router::post('/communication/read', 'Communication@read');

            //Instructors
            Router::get('/instructors', 'Instructor@get');
            Router::post('/instructor/create', 'Instructor@create');

            //Signout
            Router::get('/signout', 'Auth@signout');
        });
        
        Router::group(array(
            'middleware' => Simcify\Middleware\RedirectIfAuthenticated::class
        ), function()
        {
            
            /**
             * No login Required pages
             **/
            Router::get('/signin', 'Auth@get');
            Router::post('/reset', 'Auth@reset');
            Router::post('/forgot', 'Auth@forgot');
            Router::get('/reset/{token}', 'Auth@resetview', array(
                'as' => 'token'
            ));
            Router::post('/signin/authenticate', 'Auth@signin');
            Router::post('/signup', 'Auth@signup');
            
        });
        // error pages
        Router::get('/404', function()
        {
            response()->httpCode(404);
            echo view();
        });
        Router::get('/405', function()
        {
            response()->httpCode(405);
            echo view('errors/405');
        });
        
    });
    
// });

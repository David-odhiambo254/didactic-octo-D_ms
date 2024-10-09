<?php
namespace Simcify\Controllers;

use Simcify\Auth;
use Simcify\Database;
use Simcify\Mail;

class Instructor {
    /**
     * Get instructors view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user    = Auth::user();
        $courses = Database::table('courses')->where('school', $user->school)->get();
        
        if (isset($_GET['search']) OR isset($_GET['gender'])) {
            if (!empty($_GET['gender']) && !empty($_GET['search'])) {
                $instructors = Database::table('users')->where(array(
                    'role' => 'instructor',
                    'school' => $user->school,
                    'branch' => $user->branch,
                    'gender' => $_GET['gender']
                ))->orWhere("fname", "LIKE", "%" . $_GET['search'] . "%")->where(array(
                    'role' => 'instructor',
                    'school' => $user->school,
                    'branch' => $user->branch
                ))->get();
            } elseif (!empty($_GET['gender'])) {
                $instructors = Database::table('users')->where(array(
                    'role' => 'instructor',
                    'school' => $user->school,
                    'branch' => $user->branch,
                    'gender' => $_GET['gender']
                ))->get();
            } elseif (!empty($_GET['search'])) {
                $instructors = Database::table('users')->where(array(
                    'role' => 'instructor',
                    'school' => $user->school,
                    'branch' => $user->branch
                ))->where("fname", "LIKE", "%" . $_GET['search'] . "%")->get();
            } else {
                $instructors = Database::table('users')->where(array(
                    'role' => 'instructor',
                    'school' => $user->school,
                    'branch' => $user->branch
                ))->get();
            }
        } else {
            $instructors = Database::table('users')->where(array(
                'role' => 'instructor',
                'school' => $user->school,
                'branch' => $user->branch
            ))->get();
        }
        
        foreach ($instructors as $instructor) {
            $instructor->courses = Database::table('courseinstructor')->where('instructor', $instructor->id)->count("id", "total")[0]->total;
            $instructor->completed = Database::table('schedules')->where('instructor', $instructor->id)->where('status', "Complete")->count("id", "total")[0]->total;
        }
        return view('instructors', compact("user", "instructors", "courses"));
    }
    
    
    /**
     * Create Instructor Account
     * 
     * @return Json
     */
    public function create() {
        $user = Database::table("users")->where("email", input('email'))->first();
        if (!empty($user)) {
            return response()->json(array(
                "status" => "error",
                "title" => "Email Already exists.",
                "message" => "Email Already exists."
            ));
        }
        $user     = Auth::user();
        $password = rand(111111, 999999);
        $signup   = Auth::signup(array(
            "fname" => escape(input('fname')),
            "lname" => escape(input('lname')),
            "email" => escape(input('email')),
            "phone" => escape(input('phone')),
            "gender" => escape(input('gender')),
            "password" => Auth::password($password),
            "school" => $user->school,
            "branch" => $user->branch,
            "role" => 'instructor'
        ), array(
            "authenticate" => false,
            "uniqueEmail" => escape(input('email'))
        ));
        
        
        if ($signup["status"] == "success") {
            Mail::send(input('email'), "Welcome to " . env("APP_NAME") . "!", array(
                "title" => "Welcome to " . env("APP_NAME") . "!",
                "subtitle" => "Your school has created an instructor account for you at " . env("APP_NAME") . ".",
                "buttonText" => "Login Now",
                "buttonLink" => env("APP_URL"),
                "message" => "These are your login Credentials:<br><br><strong>Email:</strong>" . input('email') . "<br><strong>Password:</strong>" . $password . "<br><br>Cheers!<br>" . env("APP_NAME") . " Team."
            ), "withbutton");
            return response()->json(responder("success", "Account Created", "Instructor account successfully created", "reload()"));
        }
        
    }
    
}
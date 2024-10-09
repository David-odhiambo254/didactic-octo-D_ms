<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;
use Simcify\Mail;

class Staff{
    /**
     * Get staff view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user = Auth::user();
        $branches =  Database::table('branches')->where('school',$user->school)->get();
        if (isset($_GET['search']) OR isset($_GET['gender'])) {
            if (!empty($_GET['gender']) && !empty($_GET['search'])) {
                $staffs = Database::table('users')->where(array(
                    'role' => 'staff',
                    'school' => $user->school,
                    'gender' => $_GET['gender']
                ))->orWhere("fname", "LIKE", "%" . $_GET['search'] . "%")->where(array(
                    'role' => 'staff',
                    'branch' => $user->branch
                ))->get();
            } elseif (!empty($_GET['gender'])) {
                $staffs = Database::table('users')->where(array(
                    'role' => 'staff',
                    'school' => $user->school,
                    'gender' => $_GET['gender']
                ))->get();
            } elseif (!empty($_GET['search'])) {
                $staffs = Database::table('users')->where(array(
                    'role' => 'staff',
                    'school' => $user->school
                ))->where("fname", "LIKE", "%" . $_GET['search'] . "%")->get();
            } else {
                $staffs = Database::table('staffs')->where(array(
                    'role' => 'staff',
                    'school' => $user->school
                ))->get();
            }
        } else {
            $staffs = Database::table('users')->where(array(
                'role' => 'staff',
                'school' => $user->school
            ))->get();
        }
        foreach($staffs as $staff){
            $branch =  Database::table('branches')->where('id',$staff->branch)->first();
            $staff->branchname = $branch->name;
        }

        return view('staff', compact("user", "branches", "staffs"));
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
            "national_id" => escape(input('NID')),
            "gender" => escape(input('gender')),
            "permissions" => escape(input('permissions')),
            "branch" => escape(input('branch')),
            "password" => Auth::password($password),
            "school" => $user->school,
            "role" => 'staff'
        ), array(
            "authenticate" => false,
            "uniqueEmail" => escape(input('email'))
        ));
        
        
        if ($signup["status"] == "success") {
            Mail::send(input('email'), "Welcome to " . env("APP_NAME") . "!", array(
                "title" => "Welcome to " . env("APP_NAME") . "!",
                "subtitle" => "Your school has created a staff account for you at " . env("APP_NAME") . ".",
                "buttonText" => "Login Now",
                "buttonLink" => env("APP_URL"),
                "message" => "These are your login Credentials:<br><br><strong>Email:</strong>" . input('email') . "<br><strong>Password:</strong>" . $password . "<br><br>Cheers!<br>" . env("APP_NAME") . " Team."
            ), "withbutton");
            return response()->json(responder("success", "Account Created", "Instructor account successfully created", "reload()"));
        }
        
    }
}

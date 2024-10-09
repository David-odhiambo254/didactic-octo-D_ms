<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;
use Simcify\Sms;
use Simcify\Mail;
use Simcify\File;

class School {
    
    
    /**
     * Get schools view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user = Auth::user();
        if ($user->role != 'superadmin') {
            return view('errors/404');
        }
        $schools = Database::table('schools')->where('id', '>', 1)->orderBy("id", false)->get();
        foreach ($schools as $school) {
            $school->branches    = Database::table('branches')->where('school', $school->id)->count("id", "total")[0]->total;
            $school->instructors = Database::table('users')->where(array(
                'school' => $school->id,
                'role' => 'instructor'
            ))->count("id", "total")[0]->total;
            $school->students    = Database::table('users')->where(array(
                'school' => $school->id,
                'role' => 'student'
            ))->count("id", "total")[0]->total;
        }
        return view('schools', compact("user", "schools"));
    }
    
    /**
     * Create School Account
     * 
     * @return Json
     */
    public function create() {
        $user = Database::table(config('auth.table'))->where(config('auth.emailColumn'), input('email'))->first();
        if (!empty($user)) {
            return response()->json(array(
                "status" => "error",
                "title" => "Email Already exists.",
                "message" => "Email Already exists."
            ));
        }
        
        $schoolData = array(
            "name" => escape(input('schoolname')),
            "phone" => escape(input('phone')),
            "address" => escape(input('address')),
            "email" => escape(input('email'))
        );
        Database::table("schools")->insert($schoolData);
        $schoolId = Database::table("schools")->insertId();
        
        $branchData = array(
            "name" => "Headquarters",
            "school" => $schoolId,
            "phone" => escape(input('phone')),
            "email" => escape(input('email'))
        );
        Database::table("branches")->insert($branchData);
        $branchId = Database::table("branches")->insertId();
        
        $password = rand(111111, 999999);
        $signup   = Auth::signup(array(
            "fname" => escape(input('fname')),
            "lname" => escape(input('lname')),
            "email" => escape(input('email')),
            "phone" => escape(input('phone')),
            "password" => Auth::password($password),
            "school" => $schoolId,
            "branch" => $branchId,
            "role" => 'admin'
        ), array(
            "authenticate" => false,
            "uniqueEmail" => escape(input('email'))
        ));
        
        
        if ($signup["status"] == "success") {
            Mail::send(input('email'), "Welcome to " . env("APP_NAME") . "!", array(
                "title" => "Welcome to " . env("APP_NAME") . "!",
                "subtitle" => "A new account has been created for you at " . env("APP_NAME") . ".",
                "buttonText" => "Login Now",
                "buttonLink" => env("APP_URL"),
                "message" => "These are your login Credentials:<br><br><strong>Email:</strong>" . input('email') . "<br><strong>Password:</strong>" . $password . "<br><br>Cheers!<br>" . env("APP_NAME") . " Team."
            ), "withbutton");
            return response()->json(responder("success", "School Created", "School account successfully created", "reload()"));
        }
        
    }
    
    
    /**
     * Delete school account
     * 
     * @return Json
     */
    public function delete() {
        $users = Database::table("users")->where("school", input("schoolid"))->get();
        foreach ($users as $user) {
            if (!empty($user->avatar)) {
                File::delete($user->avatar, "avatar");
            }
        }
        Database::table("schools")->where("id", input("schoolid"))->delete();
        return response()->json(responder("success", "School Deleted", "School account successfully deleted", "reload()"));
    }
    
    /**
     * School update view
     * 
     * @return Json
     */
    public function updateview() {
        $school = Database::table("schools")->where("id", input("schoolid"))->first();
        return view('extras/updateschool', compact("school"));
    }
    
    /**
     * Update School
     * 
     * @return Json
     */
    public function update() {
        $data = array(
            "name" => escape(input("schoolname")),
            "phone" => escape(input("phone")),
            "email" => escape(input("email")),
            "address" => escape(input("address")),
            "status" => escape(input("status"))
        );
        Database::table("schools")->where("id", input("schoolid"))->update($data);
        return response()->json(responder("success", "Alright", "School successfully updated", "reload()"));
    }
    
    
    /**
     * Send Email to School
     * 
     * @return Json
     */
    public function sendemail() {
        $user = Auth::user();
        $school = Database::table("schools")->where("id", input("schoolid"))->first();
        $send   = Mail::send($school->email, input("subject"), array(
            "message" => input("message")
        ), "basic");
        
        
        if ($send) {
            $status = "Sent";
        } else {
            $status = "Failed";
        }
        Database::table("schoolmessages")->insert(array(
            "receiver" => $school->id,
            "type" => "email",
            "contact" => $school->email,
            "subject" => escape(input("subject")),
            "message" => escape(input("message")),
            "school" => $user->school,
            "branch" => $user->branch,
            "status" => $status
        ));
        
        if ($send) {
            return response()->json(responder("success", "Alright", "Email successfully sent", "reload()"));
        } else {
            return response()->json(responder("error", "Hmm!", $send->ErrorInfo));
        }
    }
    
    /**
     * Send SMS to School
     * 
     * @return Json
     */
    public function sendsms() {
        $user = Auth::user();
        $school = Database::table("schools")->where("id", input("schoolid"))->first();
        if (empty($school->phone)) {
            return response()->json(responder("error", "Hmm!", "This school has not set it's phone number."));
        }
        
        if (env("DEFAULT_SMS_GATEWAY") == "africastalking") {
            if (empty(env("AFRICASTALKING_USERNAME"))) {
                return response()->json(responder("error", "Hmm!", "Your Africa's Talking Username is not set."));
            }
            if (empty(env("AFRICASTALKING_KEY"))) {
                return response()->json(responder("error", "Hmm!", "Your Africa's Talking API KEY is not set."));
            }
            
            $send = Sms::africastalking($school->phone, input("message"));
            
            if ($send) {
                $status = "Sent";
            } else {
                $status = "Failed";
            }
            Database::table("schoolmessages")->insert(array(
                "receiver" => $school->id,
                "type" => "sms",
                "contact" => $school->phone,
                "message" => escape(input("message")),
                "school" => $user->school,
                "branch" => $user->branch,
                "status" => $status
            ));
            
            if ($send) {
                return response()->json(responder("success", "Alright", "SMS successfully sent", "reload()"));
            } else {
                return response()->json(responder("error", "Hmm!", "Failed to send SMS please try again."));
            }
            
        } elseif (env("DEFAULT_SMS_GATEWAY") == "twilio") {
            if (empty(env("TWILIO_SID"))) {
                return response()->json(responder("error", "Hmm!", "Your Twilio SID is not set."));
            }
            if (empty(env("TWILIO_AUTHTOKEN"))) {
                return response()->json(responder("error", "Hmm!", "Your Twilio Auth Token is not set."));
            }
            if (empty(env("TWILIO_PHONENUMBER"))) {
                return response()->json(responder("error", "Hmm!", "Your Twilio Phone Number is not set."));
            }
            
            $send = Sms::twilio($school->phone, input("message"));
            
            
            if ($send) {
                $status = "Sent";
            } else {
                $status = "Failed";
            }
            Database::table("schoolmessages")->insert(array(
                "receiver" => $school->id,
                "type" => "sms",
                "contact" => $school->phone,
                "message" => escape(input("message")),
                "school" => $user->school,
                "branch" => $user->branch,
                "status" => $status
            ));
            
            if ($send) {
                return response()->json(responder("success", "Alright", "SMS successfully sent", "reload()"));
            } else {
                return response()->json(responder("error", "Hmm!", "Failed to send SMS please try again."));
            }
        }
        
    }
    
}
 
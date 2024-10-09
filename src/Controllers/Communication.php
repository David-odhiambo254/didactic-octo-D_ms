<?php
namespace Simcify\Controllers;

use Simcify\Exception;
use Simcify\Database;
use Simcify\Landa;
use Simcify\Auth;
use Simcify\Sms;
use Simcify\Mail;

class Communication {
    
    /**
     * Get communication view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user = Auth::user();
        if (!isset($_GET['view'])) {
            $messages = Database::table('usermessages')->where("usermessages`.`school", $user->school)->where("usermessages`.`branch", $user->branch)->leftJoin("users", "users.id", "receiver")->get("`users.avatar`", "`users.fname`", "`users.lname`", "`usermessages.contact`", "`usermessages.status`", "`usermessages.sent_at`", "`usermessages.subject`", "`usermessages.message`", "`usermessages.type`", "`usermessages.id`", "`usermessages.receiver`");
            $type     = "user";
        } elseif (isset($_GET['view']) && $_GET['view'] == "branches") {
            $messages = Database::table('branchmessages')->where("branchmessages`.`school", $user->school)->where("branchmessages`.`branch", $user->branch)->leftJoin("branches", "branches.id", "receiver")->get("`branches.name`", "`branchmessages.contact`", "`branchmessages.status`", "`branchmessages.sent_at`", "`branchmessages.subject`", "`branchmessages.message`", "`branchmessages.type`", "`branchmessages.id`");
            $type     = "branch";
        } elseif (isset($_GET['view']) && $_GET['view'] == "schools" && $user->role == "superadmin") {
            $messages = Database::table('schoolmessages')->leftJoin("schools", "schools.id", "receiver")->get("`schools.name`", "`schoolmessages.contact`", "`schoolmessages.status`", "`schoolmessages.sent_at`", "`schoolmessages.subject`", "`schoolmessages.message`", "`schoolmessages.type`", "`schoolmessages.id`");
            $type     = "school";
        }
        $users = Database::table('users')->where("school", $user->school)->get();
        return view('communication', compact("recipients", "users", "user", "messages", "type"));
    }
    
    /**
     * Send SMS
     * 
     * @return Json
     */
    public function sms() {
        $user         = Auth::user();
        $recipient    = input('recipient');
        $receivers    = array();
        $type         = "user";
        $messageTable = "usermessages";
        if ($recipient == "student" || $recipient == "staff" || $recipient == "instructor" || $recipient == "everyone" || $recipient == "branches" || $recipient == "schools") {
            if ($recipient == "everyone") {
                $recipients   = Database::table('users')->where("school", $user->school)->get();
                $notification = 'SMS sent to everyone by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
            } elseif ($recipient == "branches") {
                $recipients   = Database::table('branches')->where("school", $user->school)->get();
                $type         = "branch";
                $messageTable = "branchmessages";
                $notification = 'SMS sent to all branches by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
            } elseif ($recipient == "schools") {
                $recipients   = Database::table('schools')->get();
                $type         = "school";
                $messageTable = "schoolmessages";
                $notification = 'SMS sent to all schools by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
            } else {
                $notification = 'SMS sent to all ' . $recipient . 's by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
                $recipients   = Database::table('users')->where("role", $recipient)->where("school", $user->school)->get();
            }
            Landa::notify($notification, $user->id, "message");
            foreach ($recipients as $account) {
                if (empty($account->phone)) {
                    continue;
                }
                $receivers[] = array(
                    $account->id,
                    $account->phone,
                    $type
                );
            }
        } else {
            $recipients  = Database::table('users')->where("id", $recipient)->first();
            $receivers[] = array(
                $recipients->id,
                $recipients->phone,
                $type
            );
            Database::table("schools")->insert($schoolData);
        }
        if (empty($receivers)) {
            return response()->json(responder("error", "Hmm!", "Selected recipients have not set numbers."));
        }
        
        if (env("DEFAULT_SMS_GATEWAY") == "africastalking") {
            if (empty(env("AFRICASTALKING_USERNAME"))) {
                return response()->json(responder("error", "Hmm!", "Your Africa's Talking Username is not set."));
            }
            if (empty(env("AFRICASTALKING_KEY"))) {
                return response()->json(responder("error", "Hmm!", "Your Africa's Talking API KEY is not set."));
            }
            foreach ($receivers as $receiver) {
                $send = Sms::africastalking($receiver[1], input("message"));
                if ($send) {
                    $status = "Sent";
                } else {
                    $status = "Failed";
                }
                Database::table($messageTable)->insert(array(
                    "receiver" => $receiver[0],
                    "type" => "sms",
                    "contact" => $receiver[1],
                    "message" => escape(input("message")),
                    "school" => $user->school,
                    "branch" => $user->branch,
                    "status" => $status
                ));
            }
            
            return response()->json(responder("success", "Alright", "Message queued to be sent. ", "reload()"));
            
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
            
            foreach ($receivers as $receiver) {
                $send = Sms::twilio($receiver[1], input("message"));
                if ($send) {
                    $status = "Sent";
                } else {
                    $status = "Failed";
                }
                Database::table($messageTable)->insert(array(
                    "receiver" => $receiver[0],
                    "type" => "sms",
                    "contact" => $receiver[1],
                    "message" => escape(input("message")),
                    "school" => $user->school,
                    "branch" => $user->branch,
                    "status" => $status
                ));
            }
            
            return response()->json(responder("success", "Alright", "Message queued to be sent. ", "reload()"));
            
        }
    }
    
    /**
     * Send Email
     * 
     * @return Json
     */
    public function email() {
        $user         = Auth::user();
        $recipient    = input('recipient');
        $receivers    = array();
        $type         = "user";
        $messageTable = "usermessages";
        if ($recipient == "student" || $recipient == "staff" || $recipient == "instructor" || $recipient == "everyone" || $recipient == "branches" || $recipient == "schools") {
            if ($recipient == "everyone") {
                $recipients   = Database::table('users')->where("school", $user->school)->get();
                $notification = 'Email sent to everyone by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
            } elseif ($recipient == "branches") {
                $recipients   = Database::table('branches')->where("school", $user->school)->get();
                $type         = "branch";
                $messageTable = "branchmessages";
                $notification = 'Email sent to all branches by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
            } elseif ($recipient == "schools") {
                $recipients   = Database::table('schools')->get();
                $type         = "school";
                $messageTable = "schoolmessages";
                $notification = 'Email sent to all schools by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
            } else {
                $notification = 'Email sent to all ' . $recipient . 's by <strong>' . $user->fname . ' ' . $user->lname . '</strong>.';
                $recipients   = Database::table('users')->where("role", $recipient)->where("school", $user->school)->get();
            }
            Landa::notify($notification, $user->id, "message");
            foreach ($recipients as $account) {
                if (empty($account->email)) {
                    continue;
                }
                $receivers[] = array(
                    $account->id,
                    $account->email,
                    $type
                );
            }
        } else {
            $recipients  = Database::table('users')->where("id", $recipient)->first();
            $receivers[] = array(
                $recipients->id,
                $recipients->email,
                $type
            );
            Database::table("schools")->insert($schoolData);
        }
        if (empty($receivers)) {
            return response()->json(responder("error", "Hmm!", "Selected recipients have not set emails."));
        }
        
        foreach ($receivers as $receiver) {
            $send = Mail::send($receiver[1], input('subject'), array(
                "message" => input('message')
            ), "basic");
            if ($send) {
                $status = "Sent";
            } else {
                $status = "Failed";
            }
            Database::table($messageTable)->insert(array(
                "receiver" => $receiver[0],
                "type" => "email",
                "contact" => $receiver[1],
                "subject" => escape(input("subject")),
                "message" => escape(input("message")),
                "school" => $user->school,
                "branch" => $user->branch,
                "status" => $status
            ));
        }
        
        return response()->json(responder("success", "Alright", "Messages queued to be sent. ", "reload()"));
        
    }
    
    
    
    /**
     * Read message
     * 
     * @return \Pecee\Http\Response
     */
    public function read() {
        if (input("type") == "user") {
            $message = Database::table("usermessages")->where("id", input("messageid"))->first();
        } elseif (input("type") == "branch") {
            $message = Database::table("branchmessages")->where("id", input("messageid"))->first();
        } elseif (input("type") == "school") {
            $message = Database::table("schoolmessages")->where("id", input("messageid"))->first();
        }
        return view('extras/readmessage', compact("message"));
    }
    
    
    /**
     * Delete message
     * 
     * @return Json
     */
    public function delete() {
        if (input("type") == "user") {
            Database::table("usermessages")->where("id", input("messageid"))->delete();
        } elseif (input("type") == "branch") {
            Database::table("branchmessages")->where("id", input("messageid"))->delete();
        } elseif (input("type") == "school") {
            Database::table("schoolmessages")->where("id", input("messageid"))->delete();
        }
        return response()->json(responder("success", "Message Deleted", "Message successfully deleted.", "reload()"));
    }
    
}
 
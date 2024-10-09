<?php
namespace Simcify\Controllers;

use Simcify\Auth;
use Simcify\Database;
use Simcify\File;
use Simcify\Sms;
use Simcify\Mail;
use Simcify\FS;

class Profile{

    /**
     * Get profile view
     * 
     * @return \Pecee\Http\Response
     */
    public function get($userid) {
        $user = Auth::user();
        $enrollments = $invoices = $payments = array();
        $profile = Database::table('users')->where('id',$userid)->first();
        $branches =  Database::table('branches')->where('school',$user->school)->get();
        $courses = Database::table('courses')->where('school',$user->school)->where('status',"Available")->get();
        $instructors = Database::table('users')->where(['role'=>'instructor','branch'=>$user->branch,'school'=>$user->school])->get();
        $fleets = Database::table('fleet')->where('branch',$user->branch)->get();
        $timeline = Database::table('timeline')->where('user',$userid)->get();
        $students = Database::table('users')->where(['role'=>'student','branch'=>$user->branch,'school'=>$user->school])->get();
        if ($profile->role == "student") {
            
            $enrollments = Database::table('coursesenrolled')->leftJoin('courses','coursesenrolled.course','courses.id')->where('student',$profile->id)->orderBy('coursesenrolled.id', false)->get("`courses.name`", "`courses.duration`", "`courses.period`", "`coursesenrolled.created_at`", "`coursesenrolled.id`", "`coursesenrolled.course`", "`coursesenrolled.total_practical`", "`coursesenrolled.total_theory`", "`coursesenrolled.completed_theory`", "`coursesenrolled.completed_practical`");
            $payments = Database::table('payments')->leftJoin('invoices','payments.invoice','invoices.id')->where('payments`.`student',$profile->id)->orderBy('payments.id', false)->get("`payments.id`", "`payments.created_at`", "`payments.amount`", "`payments.method`", "`payments.invoice`", "`invoices.reference`");
            $invoices = Database::table("invoices")->where("student", $profile->id)->orderBy('id', false)->get();
        }
        $notes = Database::table('notes')->leftJoin('users','notes.note_by','users.id')->where('note_for',$profile->id)->orderBy('notes.id', false)->get("`users.fname`", "`users.lname`", "`users.avatar`", "`notes.created_at`", "`notes.note`", "`notes.id`", "`notes.note_by`");
        $attachments = Database::table('attachments')->leftJoin('users','attachments.uploaded_by','users.id')->where('attachment_for',$profile->id)->orderBy('attachments.id', false)->get("`users.fname`", "`users.lname`", "`users.avatar`", "`attachments.created_at`", "`attachments.name`", "`attachments.attachment`", "`attachments.id`", "`attachments.uploaded_by`");
        return view('profile', compact("user", "profile","branches","enrollments","courses","notes","attachments","invoices","payments","instructors","fleets","students","timeline"));
    }

    /**
     * Update profile
     * 
     * @return Json
     */
    public function update() {
        if (!empty(input("date_of_birth"))) {
            $date_of_birth = date('Y-m-d', strtotime(input("date_of_birth")));
        }else{
            $date_of_birth = '';
        }
        $data = array(
            "fname" => escape(input("fname")),
            "lname" => escape(input("lname")),
            "phone" => escape(input("phone")),
            "email" => escape(input("email")),
            "address" => escape(input("address")),
            "date_of_birth" => $date_of_birth,
            "gender" => escape(input("gender"))
        );
        if (!empty(input("permissions"))) {
            $data['permissions'] = escape(input("permissions"));
            $data['branch'] =  escape(input("branch"));
        }
        Database::table("users")->where("id", input("userid"))->update($data);
        return response()->json(responder("success", "Alright", "Profile successfully updated", "reload()"));
    }
    
    /**
     * Delete user account
     * 
     * @return Json
     */
    public function delete() {
        $account = Database::table("users")->where("school", input("userid"))->get();
        if (!empty($account->avatar)) {
            File::delete($account->avatar, "avatar");
        }
        Database::table("users")->where("id", input("userid"))->delete();
        return response()->json(responder("success", "User Deleted", "User account successfully deleted", "redirect('".url('')."', true)"));
    }
    
    
    /**
     * Send Email to user
     * 
     * @return Json
     */
    public function sendemail() {
        $user = Database::table("users")->where("id", input("userid"))->first();
        $send   = Mail::send($user->email, input("subject"), array(
            "message" => input("message")
        ), "basic");
        
        if ($send) {
            return response()->json(responder("success", "Alright", "Email successfully sent", "reload()"));
        } else {
            return response()->json(responder("error", "Hmm!", $send->ErrorInfo));
        }
    }
    
    /**
     * Send SMS to user
     * 
     * @return Json
     */
    public function sendsms() {
        $user = Database::table("users")->where("id", input("userid"))->first();
        if (empty($user->phone)) {
            return response()->json(responder("error", "Hmm!", "This user has not set it's phone number."));
        }
        
        if (env("DEFAULT_SMS_GATEWAY") == "africastalking") {
            if (empty(env("AFRICASTALKING_USERNAME"))) {
                return response()->json(responder("error", "Hmm!", "Your Africa's Talking Username is not set."));
            }
            if (empty(env("AFRICASTALKING_KEY"))) {
                return response()->json(responder("error", "Hmm!", "Your Africa's Talking API KEY is not set."));
            }
            
            $send = Sms::africastalking($user->phone, input("message"));
            
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
            
            $send = Sms::twilio($user->phone, input("message"));
            
            if ($send) {
                return response()->json(responder("success", "Alright", "SMS successfully sent", "reload()"));
            } else {
                return response()->json(responder("error", "Hmm!", "Failed to send SMS please try again."));
            }
        }
        
    }


    /**
     * Add note to profile
     * 
     * @return Json
     */
    public function addnote() {
        $user = Auth::user();
        $data = array(
                'note_by'=>$user->id,
                'note_for'=>input('userid'),
                'note'=> escape(input('note'))
                );
        Database::table('notes')->insert($data);
        return response()->json(responder("success", "Alright", "Note successfully published", "reload()"));
    }
    
    /**
     * Delete note
     * 
     * @return Json
     */
    public function deletenote() {
        Database::table("notes")->where("id", input("noteid"))->delete();
        return response()->json(responder("success", "Note Deleted", "Note successfully deleted.", "reload()"));
    }
    
    /**
     * Note details view
     * 
     * @return Json
     */
    public function readnote() {
        $note = Database::table("notes")->where("id", input("noteid"))->first();
        return view('extras/readnote', compact("note"));
    }
    
    /**
     * Note update view
     * 
     * @return Json
     */
    public function updatenoteview() {
        $note = Database::table("notes")->where("id", input("noteid"))->first();
        return view('extras/updatenote', compact("note"));
    }
    
    /**
     * Update Note
     * 
     * @return Json
     */
    public function updatenote() {
        $data = array(
            "note" => escape(input("note"))
        );
        Database::table("notes")->where("id", input("noteid"))->update($data);
        return response()->json(responder("success", "Alright", "Note successfully updated", "reload()"));
    }

    /**
    *Upload attachment
    * 
    * @return Json
    */
    public function uploadattachment() {
        $user = Auth::user();
        $upload = File::upload(
            $_FILES['attachment'], 
            "attachments"
            ,array(
                "source" => "form",
                "allowedExtesions" => "pdf, png, gif, jpg, jpeg",
                 )
        );

        if($upload['status'] == 'success'){
            $data = array(
                'name'=>escape(input('name')),
                'attachment'=>$upload['info']['name'],
                'uploaded_by'=>$user->id,
                'attachment_for'=>escape(input('userid'))
            );
            Database::table('attachments')->insert($data);
            return response()->json(responder("success", "Alright", "Attachment successfully uploaded", "reload()"));
        }else{
            return response()->json(responder("error", "Hmm!", "Something went wrong, please try again."));
        }
    }
    
    /**
     * Delete attachment
     * 
     * @return Json
     */
    public function deleteattachment() {
        $attachment = Database::table("attachments")->where("id", input("attachmentid"))->first();
        File::delete($attachment->attachment, "attachments");
        Database::table("attachments")->where("id", input("attachmentid"))->delete();
        return response()->json(responder("success", "Attachment Deleted", "Attachment successfully deleted", "reload()"));
    }

}

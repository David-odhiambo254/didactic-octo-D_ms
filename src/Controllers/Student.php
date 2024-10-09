<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Landa;
use Simcify\Auth;
use Simcify\Mail;

class Student{

    /**
     * Get students view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user = Auth::user();
        if (isset($_GET['search']) OR isset($_GET['gender'])) {
            if (!empty($_GET['gender']) && !empty($_GET['search'])) {
                $students = Database::table('users')->where(array(
                    'role' => 'student',
                    'school' => $user->school,
                    'gender' => $_GET['gender']
                ))->orWhere("fname", "LIKE", "%" . $_GET['search'] . "%")->where(array(
                    'role' => 'student',
                    'branch' => $user->branch
                ))->orderBy('id', false)->get();
            } elseif (!empty($_GET['gender'])) {
                $students = Database::table('users')->where(array(
                    'role' => 'student',
                    'school' => $user->school,
                    'gender' => $_GET['gender']
                ))->orderBy('id', false)->get();
            } elseif (!empty($_GET['search'])) {
                $students = Database::table('users')->where(array(
                    'role' => 'student',
                    'school' => $user->school
                ))->where("fname", "LIKE", "%" . $_GET['search'] . "%")->orderBy('id', false)->get();
            } else {
                $students = Database::table('staffs')->where(array(
                    'role' => 'student',
                    'school' => $user->school
                ))->orderBy('id', false)->get();
            }
        } else {
            $students = Database::table('users')->where(array(
                'role' => 'student',
                'school' => $user->school
            ))->orderBy('id', false)->get();
        }

        foreach ($students as $student) {
            $student->courses = Database::table('coursesenrolled')->where('student', $student->id)->count("id", "total")[0]->total;
            $student->completed = Database::table('schedules')->where('student', $student->id)->where('status', "Complete")->count("id", "total")[0]->total;
        }
        $courses = Database::table('courses')->where('school',$user->school)->where('status',"Available")->get();
        return view('students', compact("user", "students", "courses"));
    }
  
    
    /**
     * Create student Account
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
        $user = Auth::user();
        $school = Database::table("schools")->where("id", $user->school)->first();
        $password = rand(111111, 999999);
        $signup   = Auth::signup(array(
            "fname" => escape(input('fname')),
            "lname" => escape(input('lname')),
            "email" => escape(input('email')),
            "phone" => escape(input('phone')),
            "gender" => escape(input('gender')),
            "national_id" => escape(input('NID')),
            "permissions" => escape(input('permissions')),
            "branch" => $user->branch,
            "password" => Auth::password($password),
            "school" => $user->school,
            "role" => 'student'
        ), array(
            "authenticate" => false,
            "uniqueEmail" => escape(input('email'))
        ));
        
        
        if ($signup["status"] == "success") {
            $timeline = 'Account created by <strong>'.$user->fname.' '.$user->lname.'</strong>';
            Landa::timeline($signup["id"], $timeline);  
            Landa::notify($timeline, $signup["id"], "newaccount", "personal");
            $notification = 'New Student account created for <strong>'.input('fname').' '.input('lname').'</strong>.';
            Landa::notify($notification, $user->id, "newaccount");
          // send welcome email
            Mail::send(input('email'), "Welcome to " . $school->name. "!", array(
                "title" => "Welcome to " . $school->name . "!",
                "subtitle" => "Your school (" . $school->name . ") has created a student account for you at " . env("APP_NAME") . ".",
                "buttonText" => "Login Now",
                "buttonLink" => env("APP_URL"),
                "message" => "These are your login Credentials:<br><br><strong>Email:</strong>" . input('email') . "<br><strong>Password:</strong>" . $password . "<br><br>Cheers!<br>" . env("APP_NAME") . " Team."
            ), "withbutton");


            if (!empty(input("newcourse"))) {
              self::enroll($signup["id"], input("newcourse"));
              self::createinvoice($signup["id"], input("newcourse"), input("amountpaid"), input("method"));
            }


            return response()->json(responder("success", "Account Created", "Student account successfully created", "reload()"));
        }else{
          return response()->json(responder("error", "Hmm!", "Something went wrong please try again."));
        }
        
    }


    /**
     * Add course
    *
    */
    public function addcourse(){
          self::enroll(input("studentid"), input("newcourse"));
          self::createinvoice(input("studentid"), input("newcourse"), input("amountpaid"), input("method"));
          return response()->json(responder("success", "Alright", "Student successfully enrolled to the course", "reload()"));
    }

    /**
     * Enroll student to a course
    *
     * @return true
    */
    private function enroll($student, $course){
        $user = Auth::user();
        $school = Database::table('schools')->where('id',$user->school)->first();
        $course = Database::table('courses')->where('id',$course)->first();
        $student = Database::table('users')->where('id',$student)->first();

        $data = array(
            'school'=>$user->school,
            'branch'=>$user->branch,
            'student'=>$student->id,
            'course'=>$course->id,
            'total_theory'=>$course->practical_classes,
            'total_practical'=>$course->theory_classes
        );
        Database::table('coursesenrolled')->insert($data);   
          $timeline = 'Enrolled to <strong>'.$course->name.'</strong> course.';
          Landa::timeline($student->id, $timeline);  
        // send enrollment email
          Mail::send($student->email, "Course enrollment at ".$school->name, array(
              "message" => "Hello ".$student->fname.",<br><br>You have successfully been enrolled to <strong>(" . $course->name . ")</strong> course at ".$school->name.". <br>This course will have <strong>".$course->practical_classes." practical classes</strong> and <strong>".$course->theory_classes." theory classes</strong>. <br><br>Cheers!<br>" . $school->name. " Team."
          ), "basic");

          return true;
    }

    /**
     * Create an invoice
    *
     * @return true
    */
    private function createinvoice($student, $course, $amountpaid = 0, $paymentmethod = "Other"){
        $user = Auth::user();
        $school = Database::table('schools')->where('id',$user->school)->first();
        $course = Database::table('courses')->where('id',$course)->first();
        $student = Database::table('users')->where('id',$student)->first();

        $reference = rand(111111,999999);
        $data = array(
            'school'=>$user->school,
            'branch'=>$user->branch,
            'student'=>$student->id,
            'reference'=> $reference,
            'item'=>$course->name,
            'amount'=>$course->price,
            'amountpaid'=>$amountpaid
          );  
          Database::table('invoices')->insert($data); 
          $invoiceId = Database::table('invoices')->insertId();  

          if ($amountpaid > 0) {
            $data = array(
                'invoice'=>$invoiceId,
                'school'=>$user->school,
                'branch'=>$user->branch,
                'student'=>$student->id,
                'method'=>$paymentmethod,
                'amount'=>$amountpaid
            );   
            Database::table('payments')->insert($data);
            $notification = 'You made a payment of <strong>'.money($amountpaid).'</strong>.';
            Landa::notify($notification, $student->id, "payment", "personal");
            $notification = 'A payment of <strong>'.money($amountpaid).'</strong> has been received from <strong>'.$student->fname.' '.$student->lname.'</strong>.';
            Landa::notify($notification, $user->id, "payment");
          }  


        // send invoice email
          Mail::send($student->email, $school->name." invoice #".$reference, 
            array(
                "title" => "Thank you for joining us!",
                "subtitle" => "This is an invoice for your ".$school->name." enrollment. <strong>$".$amountpaid." </strong> paid.",
                "summary" => array(
                                "currency" => currency(),
                                "subtotal" => $course->price,
                                "tax" => 0,
                                "total" => $course->price,
                            ),
                "items" => array(
                            array(
                                "name" => $course->name,
                                "quantity" => "1",
                                "price" => $course->price,
                            )
                        )
            ), "invoice");

          return true;

    }


}

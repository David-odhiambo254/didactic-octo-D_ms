<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;
use Simcify\File;

class Course {
    
    /**
     * Get courses view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user    = Auth::user();
        $courses = Database::table('courses')->where('school', $user->school)->get();
        foreach ($courses as $course) {
            $instructors         = Database::table('courseinstructor')->where('courseinstructor`.`course', $course->id)->leftJoin("users", "users.id", "instructor")->get("`users.avatar`", "`users.fname`", "`users.lname`");
            $course->instructors = $instructors;
            $course->students = Database::table('coursesenrolled')->where('course', $course->id)->count("id","total")[0]->total;
        }
        $instructors = Database::table('users')->where('school', $user->school)->where("role", "instructor")->get();
        return view('courses', compact("user", "courses", "instructors"));
    }
    
    
    /**
     * Add a course
     * 
     * @return Json
     */
    public function create() {
        $image = '';
        $user  = Auth::user();
        if (!empty(input("image"))) {
            $upload = File::upload(input("image"), "courses", array(
                "source" => "base64",
                "extension" => "png"
            ));
            if ($upload['status'] == "success") {
                $image = $upload['info']['name'];
            }
        }
        $data = array(
            'image' => $image,
            'school' => $user->school,
            'branch' => $user->branch,
            'name' => escape(input('name')),
            'price' => escape(input('price')),
            'duration' => escape(input('duration')),
            'period' => escape(input('period')),
            'practical_classes' => escape(input('practical_classes')),
            'theory_classes' => escape(input('theory_classes')),
            'status' => escape(input('status'))
        );
        Database::table('courses')->insert($data);
        $courseId = Database::table('courses')->insertId();
        if (!empty(input("instructors"))) {
            foreach (input("instructors") as $instructor) {
                Database::table('courseinstructor')->insert(array(
                    "course" => $courseId,
                    "instructor" => $instructor
                ));
            }
        }
        return response()->json(responder("success", "Course Added", "Course successfully added", "reload()"));
    }
    
    /**
     * Delete course
     * 
     * @return Json
     */
    public function delete() {
        $course = Database::table("courses")->where("id", input("courseid"))->get();
        if (!empty($course->image)) {
            File::delete($course->image, "courses");
        }
        Database::table("courses")->where("id", input("courseid"))->delete();
        return response()->json(responder("success", "Course Deleted", "Course successfully deleted", "redirect('" . url("Course@get") . "')"));
    }
    
    /**
     * Course update view
     * 
     * @return Json
     */
    public function updateview() {
        $user           = Auth::user();
        $course         = Database::table("courses")->where("id", input("courseid"))->first();
        $instructors    = Database::table("courseinstructor")->where("course", input("courseid"))->get("instructor");
        $instructorsIds = array();
        foreach ($instructors as $instructor) {
            $instructorsIds[] = $instructor->instructor;
        }
        $instructors = Database::table('users')->where('school', $user->school)->where("role", "instructor")->get();
        return view('extras/updatecourse', compact("course", "instructors", "instructorsIds"));
    }
    
    /**
     * Update course
     * 
     * @return Json
     */
    public function update() {
        $course = Database::table("course")->where("id", input("courseid"))->first();
        if (!empty(input("image"))) {
            $upload = File::upload(input("image"), "courses", array(
                "source" => "base64",
                "extension" => "png"
            ));
            if ($upload['status'] == "success") {
                if (!empty($course->image)) {
                    File::delete($course->image, "courses");
                }
                Database::table("courses")->where("id", input("courseid"))->update(array(
                    "image" => $upload['info']['name']
                ));
            }
        }
        $data = array(
            'name' => escape(input('name')),
            'price' => escape(input('price')),
            'duration' => escape(input('duration')),
            'period' => escape(input('period')),
            'practical_classes' => escape(input('practical_classes')),
            'theory_classes' => escape(input('theory_classes')),
            'status' => escape(input('status'))
        );
        Database::table("courses")->where("id", input("courseid"))->update($data);
        Database::table("courseinstructor")->where("course", input("courseid"))->delete();
        if (!empty(input("instructors"))) {
            foreach (input("instructors") as $instructor) {
                Database::table('courseinstructor')->insert(array(
                    "course" => input("courseid"),
                    "instructor" => $instructor
                ));
            }
        }
        return response()->json(responder("success", "Alright", "Course successfully updated", "reload()"));
    }
    
    
    
    /**
     * Course preview
     * 
     * @return Json
     */
    public function preview($courseid) {
        $user   = Auth::user();
        $course = Database::table('courses')->where('id', $courseid)->first();
        if (empty($course)) {
            return view("error/404");
        }
        $total = Database::table('coursesenrolled')->where('course', $course->id)->count("id","total")[0]->total;
        $fleets = Database::table('fleet')->where('branch',$user->branch)->get();
        $instructors = Database::table('users')->where(['role'=>'instructor','branch'=>$user->branch,'school'=>$user->school])->get();
        $students = Database::table('users')->where(['role'=>'student','branch'=>$user->branch,'school'=>$user->school])->get();
        $courseinstructors = Database::table('courseinstructor')->where('courseinstructor`.`course', $course->id)->leftJoin("users", "users.id", "instructor")->get("`users.avatar`", "`users.fname`", "`users.lname`", "`users.email`", "`users.id`");
        $enrolledstudents = Database::table('coursesenrolled')->where('coursesenrolled`.`course', $course->id)->leftJoin("users", "users.id", "student")->get("`users.avatar`", "`users.fname`", "`users.lname`", "`users.email`", "`users.id`", "`coursesenrolled.created_at`", "`coursesenrolled.total_practical`", "`coursesenrolled.total_theory`", "`coursesenrolled.completed_theory`", "`coursesenrolled.completed_practical`", "`coursesenrolled.completed_on`");
        return view('coursepreview', compact("user", "course", "courses", "courseinstructors", "instructors","fleets","students","total","enrolledstudents"));
        
    }
    
    
    
}
 
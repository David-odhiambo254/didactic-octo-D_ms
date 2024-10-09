<?php
namespace Simcify\Controllers;

use Simcify\Auth;
use Simcify\Database;
use Simcify\File;

class Inquiries {
    
    /**
     * Get inquiry view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user        = Auth::user();
        // $instructors = Database::table('users')->where(array(
        //     'branch' => $user->branch,
        //     'role' => 'instructor'
        // ))->get();
        $inquiries       = Database::table('inquiries')->where('branch', $user->branch)->get();
        // foreach ($inquiries as $car) {
        //     if (empty($car->instructor)) {
        //         $car->instructor = "Un-Assigned";
        //     } else {
        //         $instructor = Database::table('users')->where('id', $car->instructor)->first();
        //         if (!empty($instructor)) {
        //             $car->instructor = $instructor->fname . " " . $instructor->lname;
        //         } else {
        //             $car->instructor = "Un-Assigned";
        //         }
        //     }
        // }
        return view('inquiries', compact("user", "inquiries"));
    }
    
    /**
     * Add inquiry
     * 
     * @return Json
     */
    public function add() {
        $user = Auth::user();
        $data = array(
            'name' => escape(input('name')),
            'phone' => escape(input('number')),
            'email' => escape(input('email')),
            'inquiry' => escape(input('inquiry')),
            'branch' => $user->branch,
        );
        Database::table('inquiries')->insert($data);
        return response()->json(responder("success", "Query added", "Query successfully added to inquiries", "reload()"));
    }
    
    
    /**
     * Delete inquiry
     * 
     * @return Json
     */
    public function delete() {
        Database::table("inquiries")->where("id", input("carid"))->delete();
        return response()->json(responder("success", "Car Deleted", "Car successfully deleted flom inquiries", "reload()"));
    }
    
    /**
     * Car update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        $user        = Auth::user();
        $instructors = Database::table('users')->where(array(
            'branch' => $user->branch,
            'role' => 'instructor'
        ))->get();
        $car         = Database::table("inquiries")->where("id", input("carid"))->first();
        return view('extras/updatecar', compact("car", "instructors"));
    }
    
    /**
     * Update Car
     * 
     * @return Json
     */
    public function update() {
        $data = array(
            'carno_' => escape(input('carno')),
            'carplate' => escape(input('carplate')),
            'make' => escape(input('make')),
            'model' => escape(input('model')),
            'instructor' => escape(input('instructor')),
            'modelyear' => escape(input('modelyear'))
        );
        Database::table("inquiries")->where("id", input("carid"))->update($data);
        return response()->json(responder("success", "Alright", "Car successfully updated", "reload()"));
    }
    
}
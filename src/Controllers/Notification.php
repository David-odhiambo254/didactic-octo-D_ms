<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Notification {
    
    /**
     * Get notifications view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user = Auth::user();
        if ($user->role == "student" || $user->role == "instructor") {
            $notifications = Database::table('notifications')->where("user", $user->id)->orderBy('id', false)->get();
        } elseif ($user->role == "admin" || $user->role == "staff" || $user->role == "superadmin") {
            $notifications = Database::table('notifications')->where("branch", $user->branch)->where("class", "branch")->orWhere("user", $user->id)->orderBy('id', false)->get();
        }
        return view('notifications', compact("user", "notifications"));
    }
    
    /**
     * Mark notifications as read
     * 
     * @return Json
     */
    public function read() {
        $user = Auth::user();
        Database::table("users")->where("id", $user->id)->update(array(
            "lastnotification" => 'NOW()'
        ));
        exit(json_encode(responder("success", "", "", "", false)));
    }
    
    
    /**
     * Count notifications 
     * 
     * @return Json
     */
    public static function count() {
        $user = Auth::user();
        if ($user->role == "student" || $user->role == "instructor") {
            $total = Database::table('notifications')->where("user", $user->id)->where("created_at", ">", $user->lastnotification)->orderBy('id', true)->count("id", "total")[0]->total;
        } elseif ($user->role == "admin" || $user->role == "staff" || $user->role == "superadmin") {
            $total = Database::table('notifications')->where("school", $user->school)->where("branch", $user->branch)->where("class", "branch")->where("created_at", ">", $user->lastnotification)->orWhere("user", $user->id)->where("created_at", ">", $user->lastnotification)->orderBy('id', true)->count("id", "total")[0]->total;
        }
        return $total;
        
    }
    
}
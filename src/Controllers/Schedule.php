<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Landa;
use Simcify\Auth;
use Simcify\Sms;

class Schedule{

    /**
     * Get scheduling view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
    	if (isset($_GET['filter']) && !empty($_GET['filter']) && isset($_GET['filterid']) && !empty($_GET['filterid'])) {
    		$filter = $_GET['filter'];
    		$filterid = $_GET['filterid'];
    	}else{
	    	$filterid = 0;
	    	$filter = "none";
    	}
		$user = Auth::user();
    	$fleets = Database::table('fleet')->where('branch',$user->branch)->get();
    	$courses = Database::table('courses')->where('school',$user->school)->where('status',"Available")->get();
    	$instructors = Database::table('users')->where(['role'=>'instructor','branch'=>$user->branch,'school'=>$user->school])->get();
    	$students = Database::table('users')->where(['role'=>'student','branch'=>$user->branch,'school'=>$user->school])->get();
        return view('scheduling', compact("courses","instructors","students","fleets","user","filter","filterid"));
    }

    /**
     * Create Schedule
     * 
     * @return Json
     */
    public function create() {
    	$user = Auth::user();
    	$school = Database::table('schools')->where('id',$user->school)->first();
    	$course = Database::table('courses')->where('id',input('course'))->first();
    	$startTime = self::landaDate(input('start'));
    	$endTime = self::landaDate(input('end'));
    	if ($endTime <= $startTime) {
    		return response()->json(responder("warning", "Time Overlap", "Class end time must be greater than start time."));
    	}
    	if (input("class_type") == "Practical" && $school->multibooking == "Disabled") {
	    	$overlap = Database::table('schedules')->where('branch',$user->branch)
	    											->where('class_type',"Practical")->where('start','<',$startTime)->where('end','>',$startTime)
	    											->orWhere('branch',$user->branch)
	    											->where('class_type',"Practical")->where('start','<',$endTime)->where('end','>',$endTime)
	    											->orWhere('branch',$user->branch)
	    											->where('class_type',"Practical")->where('start',$startTime)->where('end',$endTime)
	    											->first();
	    	if (!empty($overlap)) {
	    		return response()->json(responder("warning", "Schedule Overlap", "Another class is scheduled to start at ".date('j M Y H:i:s', strtotime($overlap->start))." and end at ".date('j M Y H:i:s', strtotime($overlap->end))));
	    	}
    	}
        $data = array(
			'school'=>$user->school,
			'branch'=>$user->branch,
            'start'=>$startTime,
            'end'=>$endTime,
            'course'=>input('course'),
            'student'=>input('student'),
            'instructor'=>input('instructor'),
            'class_type'=>input('class_type'),
            'car'=>input('car'),
            'status'=>input('status')
        );
        if (input('status') == "Complete") {
            $timeline = 'Completed a '.input('class_type').' class for <strong>'.$course->name.'</strong> course.';
            Landa::timeline(input('student'), $timeline);  
        	$enrollment =  Database::table('coursesenrolled')->where('course', input('course'))->where('student', input('student'))->first();
        	if (input('class_type') == "Theory" && !empty($enrollment)) {
        		Database::table('coursesenrolled')->where('id', $enrollment->id)->update('completed_theory', $enrollment->completed_theory + 1);
        	}elseif(input('class_type') == "Practical" && !empty($enrollment)){
        		Database::table('coursesenrolled')->where('id', $enrollment->id)->update('completed_practical', $enrollment->completed_practical + 1);
        	}
        	if (!empty($enrollment)) {
        		$total = $enrollment->total_theory + $enrollment->total_practical;
        		$completed = $enrollment->completed_theory + $enrollment->completed_practical + 1;
        		if ($total == $completed) {
		            $timeline = 'Completed <strong>'.$course->name.'</strong> course.';
		            Landa::timeline(input('student'), $timeline);  
        			$complete = array("completed_on" => date("Y-m-d"), "status" => "Complete");
        			Database::table('coursesenrolled')->where('id', $enrollment->id)->update($complete);
        		}
        	}
        }
        Database::table('schedules')->insert($data);
        $scheduleId = Database::table('schedules')->insertId();
        if ($school->class_sms_notifications == "Enabled" && input('status') == "New") {
        	self::smsnotification($data, $scheduleId);
        }
        return response()->json(responder("success", "Schedule set", "Class schedule successfully saved","reload()"));

	 }


    /**
     * Update date format
     * 
     * @return date time
     */
	    public function landaDate($string){
	    	$array = explode(" - ", $string);
	    	$date = date('Y-m-d', strtotime($array[0]));
	    	$time = date('H:i:s', strtotime($array[1]));
	        $datetime = $date." ".$time;
	        return $datetime;
	    }


    /**
     * Fetch schedules
     * 
     * @return array
     */
	    public function fetch(){
	    	error_reporting(0);
			$user = Auth::user();
			$table = Database::table('schedules');
	    	$schedules = $ready = $filter = array();
			$groups = array(
				"practical" => array(
						"label" => "practical",
						"class" => "success",
						"title" => "Practical class(es)",
						"data" => array()
					),
				"complete" => array(
						"label" => "complete",
						"class" => "muted",
						"title" => "Completed class(es)",
						"data" => array()
					),
				"missed" => array(
						"label" => "missed",
						"class" => "danger",
						"title" => "Missed class(es)",
						"data" => array()
					),
				"theory" => array(
						"label" => "theory",
						"class" => "primary",
						"title" => "Theory class(es)",
						"data" => array()
					)
			);
			$type = input("type");
			$date = input("date");
			if (input("filter") != "none") {
				$filter = array(input("filter") => input("filterid"));
			}
			if ($type == "month") {
				$month = date('m', strtotime($date));
				$year = date('Y', strtotime($date));
				$base = array_merge($filter, array('branch'=>$user->branch, 'MONTH(`start`)' => $month, 'YEAR(`start`)' => $year));
		    	$groups['missed']['data'] = Database::table('schedules')->where(array_merge($base, array('status'=>"Missed")))->get("start");
		    	$groups['complete']['data'] = Database::table('schedules')->where(array_merge($base, array('branch'=>$user->branch,'status'=>"Complete")))->get("start");
		    	$groups['theory']['data'] = Database::table('schedules')->where(array_merge($base, array('status'=>"New",'class_type'=>"Theory")))->get("start");
		    	$groups['practical']['data'] = Database::table('schedules')->where(array_merge($base, array('status'=>"New",'class_type'=>"Practical")))->get("start");
		    	foreach ($groups as $group) {
		    		foreach ($group['data'] as $class) {
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['count'] += 1;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['allDay'] = 1;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['id'] = 0;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['class'] = $group['class'];
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['title'] = $group['title'];
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['description'] = $group['title'];
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['start'] = date('Y-m-d', strtotime($class->start));
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['end'] = date('Y-m-d', strtotime($class->start));
			    	}
		    	}
			}else{
				if ($type == "agendaDay") {
					$agendaDayStart = date('Y-m-d', strtotime($date))." 00:00:00";
					$agendaDayEnd = date('Y-m-d', strtotime($date))." 23:59:59";
					$range = $agendaDayStart."' AND '".$agendaDayEnd;
				}elseif ($type == "agendaWeek") {
					$dateArrayOne = explode(", ", $date);
					$year = $dateArrayOne[1];
					$dateArrayTwo = explode(" – ", $dateArrayOne[0]);
					$monthOverlap = explode(" ", $dateArrayTwo[1]);
					if (isset($monthOverlap[1])) {
						$firstDayOfWeek = $year."-".date('m-d', strtotime($dateArrayTwo[0]))." 00:00:00";
						$lastDayOfWeek = $year."-".date('m-d', strtotime($dateArrayTwo[1]))." 23:59:59";
					}else{
						$month = date('m', strtotime($dateArrayTwo[0]));
						$firstDayOfWeek = $year."-".$month."-".date('d', strtotime($dateArrayTwo[0]))." 00:00:00";
						$lastDayOfWeek = $year."-".$month."-".$dateArrayTwo[1]." 23:59:59";
					}
					$range = $firstDayOfWeek."' AND '".$lastDayOfWeek;
				}
	    		 $groups['missed']['data'] = Database::table('schedules')->where('schedules`.`branch',$user->branch)
	    		 															->where('start','BETWEEN',$range)
	    		 															->where(array_merge($filter, array('schedules`.`status'=>"Missed")))     
																			->leftJoin("users", "users.id", "student")->get("`users.fname`", "`users.lname`", "`schedules.start`", "`schedules.end`", "`schedules.class_type`", "`schedules.id`");
	    		 $groups['complete']['data'] = Database::table('schedules')->where('schedules`.`branch',$user->branch)
	    		 															->where('start','BETWEEN',$range)
	    		 															->where(array_merge($filter, array('schedules`.`status'=>"Complete")))     
																			->leftJoin("users", "users.id", "student")->get("`users.fname`", "`users.lname`", "`schedules.start`", "`schedules.end`", "`schedules.class_type`", "`schedules.id`");
	    		 $groups['theory']['data'] = Database::table('schedules')->where('schedules`.`branch',$user->branch)
	    		 															->where('start','BETWEEN',$range)
	    		 															->where(array_merge($filter, array('schedules`.`status'=>"New",'class_type'=>"Theory")))     
																			->leftJoin("users", "users.id", "student")->get("`users.fname`", "`users.lname`", "`schedules.start`", "`schedules.end`", "`schedules.class_type`", "`schedules.id`");
	    		 $groups['practical']['data'] = Database::table('schedules')->where('schedules`.`branch',$user->branch)
	    		 															->where('start','BETWEEN',$range)
	    		 															->where(array_merge($filter, array('schedules`.`status'=>"New",'class_type'=>"Practical")))     
																			->leftJoin("users", "users.id", "student")->get("`users.fname`", "`users.lname`", "`schedules.start`", "`schedules.end`", "`schedules.class_type`", "`schedules.id`");


		    	foreach ($groups as $group) {
		    		foreach ($group['data'] as $class) {
		    			$title = $class->fname." ".$class->lname." - ".$class->class_type;
		    			$description = $class->fname." ".$class->lname." has a ".$class->class_type." class from ".date('h:ia l F, Y', strtotime($class->start))." to ".date('h:i a l F, Y', strtotime($class->end));
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['allDay'] = 'false';
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['title'] = $title;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['description'] = $description;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['start'] = $class->start;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['end'] = $class->end;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['id'] = $class->id;
			    		$schedules[date('Y-m-d', strtotime($class->start))][$group['label']]['class'] = $group['class'];
			    	}
		    	}

			}

	    	foreach ($schedules as $schedule) {
	    		foreach ($groups as $group) {
		    		if (isset($schedule[$group['label']])) {
		    			$class = $schedule[$group['label']];
		    			if (isset($class['count'])) {
		    				$class['title'] = $class['count']." ".$class['title'];
		    				$class['description'] = $class['count']." ".$class['description'];
		    			}
		    			$ready[] = array( 'id' => $class['id'], 'title' => $class['title'], 'description' => $class['description'], 'start' => $class['start'], 'end' => $class['end'], 'className' => $class['class'], 'allDay' => $class['allDay']);
		    		}
	    		}
	    	}
	        return response()->json($ready);
	    }


    
    /**
     * Delete schedule
     * 
     * @return Json
     */
    public function delete() {
    	$schedule = Database::table('schedules')->where('id',input("scheduleid"))->first();
    	$school = Database::table('schools')->where('id',$schedule->school)->first();
    	if ($school->class_sms_notifications == "Enabled") {
	    	$course = Database::table('courses')->where('id',$schedule->course)->first();
	    	$student = Database::table('users')->where('id',$schedule->student)->first();
	    	$studentMessage = "Hello ".$student->fname.", your ".$schedule->class_type." class for ".$course->name." course that was scheduled for ".date('h:i a - d F Y', strtotime($schedule->start))." to ".date('h:i a - d F Y', strtotime($schedule->end))." has been cancelled";
	    	if (!empty($student->phone)) {
		        $send = Sms::africastalking($student->phone, $studentMessage);
		        if ($send) { $status = "Sent"; } else { $status = "Failed"; }
		        Database::table("usermessages")->insert(array(
		            "receiver" => $student->id, "type" => "sms", "contact" => $student->phone,
		            "message" => escape($studentMessage),
		            "school" => $student->school, "branch" => $student->branch, "status" => $status
		        ));
	    	}
	    }
        Database::table("schedules")->where("id", input("scheduleid"))->delete();
        return response()->json(responder("success", "Class Deleted", "Class successfully removed from schedule.", "reload()"));
    }
    
    /**
     * Schedule update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
		$user = Auth::user();
    	$fleets = Database::table('fleet')->where('branch',$user->branch)->get();
    	$courses = Database::table('courses')->where('school',$user->school)->where('status',"Available")->get();
    	$instructors = Database::table('users')->where(['role'=>'instructor','branch'=>$user->branch,'school'=>$user->school])->get();
    	$students = Database::table('users')->where(['role'=>'student','branch'=>$user->branch,'school'=>$user->school])->get();
    	$schedule = Database::table("schedules")->where("id", input("scheduleid"))->first();
        return view('extras/updateschedule', compact("courses","instructors","schedule","students","fleets","user"));
    }
    
    /**
     * Update schedule
     * 
     * @return Json
     */
    public function update() {
    	$user = Auth::user();
    	$school = Database::table('schools')->where('id',$user->id)->first();
    	$course = Database::table('courses')->where('id',input('course'))->first();
    	$startTime = self::landaDate(input('start'));
    	$endTime = self::landaDate(input('end'));
    	if ($endTime <= $startTime) {
    		return response()->json(responder("warning", "Time Overlap", "Class end time must be greater than start time."));
    	}
    	if (input("class_type") == "Practical" && $school->multibooking == "Disabled") {
	    	$overlap = Database::table('schedules')->where('branch',$user->branch)->where('id','!=',input("scheduleid"))
	    											->where('class_type',"Practical")->where('start','<',$startTime)->where('end','>',$startTime)
	    											->orWhere('branch',$user->branch)->where('id','!=',input("scheduleid"))
	    											->where('class_type',"Practical")->where('start','<',$endTime)->where('end','>',$endTime)
	    											->orWhere('branch',$user->branch)->where('id','!=',input("scheduleid"))
	    											->where('class_type',"Practical")->where('start',$startTime)->where('end',$endTime)
	    											->first();
	    	if (!empty($overlap)) {
	    		return response()->json(responder("warning", "Schedule Overlap", "Another class is scheduled to start at ".date('j M Y H:i:s', strtotime($overlap->start))." and end at ".date('j M Y H:i:s', strtotime($overlap->end))));
	    	}
    	}
        $data = array(
            'start'=>$startTime,
            'end'=>$endTime,
            'course'=>input('course'),
            'student'=>input('student'),
            'instructor'=>input('instructor'),
            'class_type'=>input('class_type'),
            'car'=>input('car'),
            'status'=>input('status')
        );
        Database::table('schedules')->where('id',input("scheduleid"))->update($data);

        if (input('status') == "Complete") {
            $timeline = 'Completed a '.input('class_type').' class for <strong>'.$course->name.'</strong> course.';
            Landa::timeline(input('student'), $timeline);  
        	$enrollment =  Database::table('coursesenrolled')->where('course', input('course'))->where('student', input('student'))->first();
        	if (input('class_type') == "Theory" && !empty($enrollment)) {
        		Database::table('coursesenrolled')->where('id', $enrollment->id)->update('completed_theory', $enrollment->completed_theory + 1);
        	}elseif(input('class_type') == "Practical" && !empty($enrollment)){
        		Database::table('coursesenrolled')->where('id', $enrollment->id)->update('completed_practical', $enrollment->completed_practical + 1);
        	}
        	if (!empty($enrollment)) {
        		$total = $enrollment->total_theory + $enrollment->total_practical;
        		$completed = $enrollment->completed_theory + $enrollment->completed_practical + 1;
        		if ($total == $completed) {
		            $timeline = 'Completed <strong>'.$course->name.'</strong> course.';
		            Landa::timeline(input('student'), $timeline);  
        			$complete = array("completed_on" => date("Y-m-d"), "status" => "Complete");
        			Database::table('coursesenrolled')->where('id', $enrollment->id)->update($complete);
        		}
        	}
        }

        if ($school->class_sms_notifications == "Enabled" && input('status') == "New") {
        	self::smsnotification($data, input("scheduleid"));
        }
        return response()->json(responder("success", "Schedule updated", "Class schedule successfully updated","reload()"));
    }
    
    /**
     * Schedule sms notification
     * 
     * @return Json
     */
    public function smsnotification($scheduledata, $scheduleid) {
    	$course = Database::table('courses')->where('id',$scheduledata['course'])->first();
    	$student = Database::table('users')->where('id',$scheduledata['student'])->first();
    	$schedule = Database::table('schedules')->where('id',$scheduleid)->first();
    	$studentMessage = "Hello ".$student->fname.", a ".$schedule->class_type." class for your ".$course->name." course is scheduled for ".date('h:i a - d F Y', strtotime($schedule->start))." to ".date('h:i a - d F Y', strtotime($schedule->end));
    	if (!empty($student->phone)) {
	        $send = Sms::africastalking($student->phone, $studentMessage);
	        if ($send) { $status = "Sent"; } else { $status = "Failed"; }
	        Database::table("usermessages")->insert(array(
	            "receiver" => $student->id, "type" => "sms", "contact" => $student->phone,
	            "message" => escape($studentMessage),
	            "school" => $student->school, "branch" => $student->branch, "status" => $status
	        ));
    	}
    }



}

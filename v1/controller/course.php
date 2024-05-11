<?php
	require_once('../helper/conn.php');
	require_once('../helper/message.php');
	require_once('../model/response.php');
	require_once('../model/course.php');

	try {
		$writeDB = DB::connectionWriteDB();
		$readDB = DB::connectionReadDB();
	} catch (PDOException $ex) {
		error_log("Connection Error - ".$ex, 0);
		sendResponse(500,false,"Database Connection Error");
	}

	$method = $_SERVER['REQUEST_METHOD'];

	if ($method === 'GET') {
		if (array_key_exists("mode", $_GET)) {
			$mode        = $_GET["mode"];
			$coursesArray = array();

			if ($mode == "all-course") {
				$query = $readDB->prepare("SELECT * FROM eg_course WHERE isActive = 1 ORDER BY course ASC;");
				$query->execute();

				while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
					$courses = new Courses(
						$row["id"],
						$row["course"],
						$row["description"]
					);

					$coursesArray[] = $courses->returnCourseAsArray();
				}

				$returnData = array();
				$returnData["rows_returned"] = count($coursesArray);
				$returnData["course"] = $coursesArray;

				sendResponse(201,true,"Courses has been retreived",$returnData,false);
			} else {
				sendResponse(405,false,"Mode not allowed");
			}
		} else {
			sendResponse(405,false,"Request method not allowed");
		}
	}
?>
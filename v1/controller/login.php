<?php
	require_once('../helper/conn.php');
	require_once('../helper/message.php');
	require_once('../model/response.php');
	require_once('../model/course.php');
	require_once('../model/login.php');

	try {
		$writeDB = DB::connectionWriteDB();
		$readDB = DB::connectionReadDB();
	} catch (PDOException $ex) {
		error_log("Connection Error - ".$ex, 0);
		sendResponse(500,false,"Database Connection Error");
	}

	$method = $_SERVER['REQUEST_METHOD'];

	if ($method === 'POST') {
		if(!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
			sendResponse(400,false,"Content type header is not JSON");
		}

		$rawPOSTData = file_get_contents('php://input');

		if (!$jsonData = json_decode($rawPOSTData)) {
			sendResponse(400,false,"Request body is not JSON");
		}

		if (
			!isset($jsonData->studentNumber) ||
			!isset($jsonData->password)
		) {
			sendResponse(400,false,"Please provide your login credentials");
		}

		$studentNumber = $jsonData->studentNumber;
		$password      = $jsonData->password;
		$STR_APPROVE   = "Approve";
		$STR_BAN       = "Ban";
		$STR_LOCK      = "Lock";

		if (
			$studentNumber == "" ||
			$password      == ""
		) {
			sendResponse(400,false,"Please provide your login credentials");
		}

		$query_login = $writeDB->prepare("SELECT a.*,b.course FROM eg_app_registration a INNER JOIN eg_course b ON a.courseId = b.id  WHERE studentNumber = :studentNumber AND password = MD5(:password)");
		$query_login->bindParam(':studentNumber',$studentNumber,PDO::PARAM_INT);
		$query_login->bindParam(':password',$password,PDO::PARAM_STR);
		$query_login->execute();

		$rowCount = $query_login->rowCount();
		$login    = array();

		if ($rowCount === 0) {
			sendResponse(409,false,"Account doesnt exist.");
		} else {
			while ($row = $query_login->fetch(PDO::FETCH_ASSOC)) {
				$status = $row["status"];

				if ($status == $STR_APPROVE) {
					$login = new Login(
						$row["id"],
						$row["studentNumber"],
						$row["yearGraduated"],
						$row["courseId"],
						$row["firstName"],
						$row["middleName"],
						$row["lastName"],
						$row["emailAddress"],
						$row["isPasswordChanged"],
						$row["course"]
					);

					$loginArray[] = $login->returnLoginAsArray();

					$returnData = array();
					$returnData["rows_returned"] = count($loginArray);
					$returnData["login"] = $loginArray;

					sendResponse(201,true,"Login Account has been retreived",$returnData,false);
				} else if ($status == $STR_BAN) {
					sendResponse(405,false,"Unable to login. Account has been disabled");
				} else if ($status == $STR_LOCK) {
					sendResponse(405,false,"Unable to login. Account has been locked for previous school year");
				} else {
					sendResponse(405,false,"Unable to login. Account has not been verified");
				}
			}
		}
	} else {
		sendResponse(405,false,"Request method not allowed");
	}
?>
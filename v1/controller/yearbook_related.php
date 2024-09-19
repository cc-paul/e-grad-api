<?php
	use PHPMailer\PHPMailer\PHPMailer; 
    use PHPMailer\PHPMailer\Exception;
    //
    require '../phpmailer/src/Exception.php';
    require '../phpmailer/src/PHPMailer.php';
    require '../phpmailer/src/SMTP.php';

	require_once('../helper/conn.php');
	require_once('../model/response.php');
	require_once('../helper/date.php');
	require_once('../helper/message.php');
	require_once('../helper/utils.php');
	require_once('../model/course.php');

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

		if (!isset($jsonData->mode)) {
			sendResponse(400,false,"Method not found");
		} else {
			$mode = $jsonData->mode;

			if ($mode == "get_all_courses_with_grad") {
				if (!isset($jsonData->schoolYear)) {
					sendResponse(400,false,"Incomplete Response");
				}

				$schoolYear   = $jsonData->schoolYear;
				$coursesArray = array();

				$query = $readDB->prepare("
					SELECT
						* 
					FROM
						eg_course 
					WHERE
						isActive = 1 
					AND 
						id IN (SELECT courseId FROM eg_gradpics WHERE isActive = 1 AND schoolYear = :schoolYear GROUP BY courseId)
					ORDER BY
						course ASC
				");
				$query->bindParam(':schoolYear',$schoolYear,PDO::PARAM_INT);
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
			}

			if ($mode == "get_grad_pics") {
				if (
					!isset($jsonData->courseId) ||
					!isset($jsonData->search) || 
					!isset($jsonData->schoolYear)
				) {
					sendResponse(400,false,"Incomplete Response");
				}

				$courseId   = $jsonData->courseId;
				$search     = "%" . $jsonData->search . "%";
				$schoolYear = $jsonData->schoolYear;

				if ($courseId == 0) {
			        $queryGetAllGrad = $writeDB->prepare("
			            SELECT 
			            	b.id AS studentId,
							CONCAT(b.lastName,', ',b.firstName,' ',IFNULL(b.middleName,'')) AS fullName,
							fileName AS gradPicFileName,
							a.folderName
						FROM
							eg_gradpics a 
						INNER JOIN		
							eg_graduates b 
						ON 
							a.studentId = b.id 
						AND 
							a.isActive = 1 
						AND 
							b.isActive = 1 
						AND
							a.schoolYear = :schoolYear 
						WHERE
							b.lastName LIKE :search
						ORDER BY
							b.lastName ASC
			        ");
			        $queryGetAllGrad->bindParam(':schoolYear', $schoolYear, PDO::PARAM_STR);
			        $queryGetAllGrad->bindParam(':search', $search, PDO::PARAM_STR);
			    } else {
			        $queryGetAllGrad = $writeDB->prepare("
			            SELECT 
			            	b.id AS studentId,
							CONCAT(b.lastName,', ',b.firstName,' ',IFNULL(b.middleName,'')) AS fullName,
							fileName AS gradPicFileName,
							a.folderName
						FROM
							eg_gradpics a 
						INNER JOIN		
							eg_graduates b 
						ON 
							a.studentId = b.id 
						AND 
							a.isActive = 1 
						AND 
							b.isActive = 1 
						AND
							a.schoolYear = :schoolYear 
						AND 
							a.courseId = :courseId
						WHERE
							b.lastName LIKE :search
						ORDER BY
							b.lastName ASC
			        ");
			        $queryGetAllGrad->bindParam(':schoolYear', $schoolYear, PDO::PARAM_INT);
			        $queryGetAllGrad->bindParam(':courseId', $courseId, PDO::PARAM_INT);
			        $queryGetAllGrad->bindParam(':search', $search, PDO::PARAM_STR);
			    }
			    $queryGetAllGrad->execute();
			    $rowCount = $queryGetAllGrad->rowCount();
			    $gradArray = array();
			    $groupedGrads = [];

			    $gradArray = array();
				$groupedGrads = [];

				while ($row = $queryGetAllGrad->fetch(PDO::FETCH_ASSOC)) {
				    $currentRowData = array(
				        "studentId"       => $row["studentId"],
				        "fullName"        => $row["fullName"],
				        "gradPicFileName" => $row["gradPicFileName"],
				        "folderName" => $row["folderName"]
				    );

				    $lastNameFirstLetter = strtoupper($row["fullName"][0]);

				    if (!isset($groupedGrads[$lastNameFirstLetter])) {
				        $groupedGrads[$lastNameFirstLetter] = array(
				            "letter" => $lastNameFirstLetter,
				            "graduates" => []
				        );
				    }

				    $groupedGrads[$lastNameFirstLetter]["graduates"][] = $currentRowData;
				}

			    $returnData = array();
			    $returnData["rows_returned"] = $rowCount;
			    $returnData["graduates"] = array_values($groupedGrads);
			    sendResponse(200, true, "Graduates have been retrieved", $returnData, false);
			}

			sendResponse(400,false,"Mode not found");
		}
	} else {
		sendResponse(404,false,"Endpoint not found");
	}
?>
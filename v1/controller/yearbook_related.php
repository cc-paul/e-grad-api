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

			if ($mode == "get_awardee") {
				if (!isset($jsonData->schoolYear)) {
					sendResponse(400,false,"Incomplete Response");
				}

				$schoolYear   = $jsonData->schoolYear;
				$awardeeArray = array();

				$query = $readDB->prepare("
					SELECT 
						c.id,
						a.studentId,
						a.folderName,
						a.fileName,
						CONCAT(c.lastName,', ',c.firstName,' ',IFNULL(c.middleName,'')) AS fullName,
						b.titleName,
						d.course
					FROM
						eg_gradpics a 
					INNER JOIN
						eg_reward b 
					ON 
						a.studentId = b.studentId 
					AND 
						b.isActive = 1 
					AND 
						b.isAwardee = 1
					INNER JOIN
						eg_graduates c 
					ON 
						a.studentId = c.id
					INNER JOIN 
						eg_course d 
					ON 
						a.courseId = d.id
					WHERE
						a.isActive = 1 
					AND 
						a.schoolYear = :schoolYear
					GROUP BY
						a.studentId				
				");
				$query->bindParam(':schoolYear',$schoolYear,PDO::PARAM_INT);
				$query->execute();

				while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
					$currentRowData = array(
						"id"         => $row["id"],
						"studentId"  => $row["studentId"],
						"folderName" => $row["folderName"],
						"fileName"   => $row["fileName"],
						"fullName"   => $row["fullName"],
						"titleName"  => $row["titleName"],
						"course"     => $row["course"]
					);

					$awardeeArray[] = $currentRowData;
				}

				$returnData = array();
				$returnData["rows_returned"] = count($awardeeArray);
				$returnData["awardee"] = $awardeeArray;

				sendResponse(201,true,"Awardee has been retreived",$returnData,false);
			}


			if ($mode == "get_media") {
				if (!isset($jsonData->schoolYear)) {
					sendResponse(400,false,"Incomplete Response");
				}

				$schoolYear   = $jsonData->schoolYear;
				$mediaArray = array();

				$query = $readDB->prepare("
					SELECT 
						a.* 
					FROM (
						SELECT 
							a.schoolYear,
							a.fileName,
							a.description,
							'pic' AS type
						FROM
							eg_grad_gallery a 
						WHERE
							a.isActive = 1
						UNION ALL 
						SELECT 
							a.folderName,
							CONCAT(a.fileName,'.mp4') AS fileName,
							a.description,
							'vid' AS type
						FROM
							eg_videos a 
						WHERE
							a.isActive = 1
					) a 
					WHERE
						a.schoolYear = :schoolYear
				");
				$query->bindParam(':schoolYear',$schoolYear,PDO::PARAM_INT);
				$query->execute();

				while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
					$currentRowData = array(
						"schoolYear"  => $row["schoolYear"],
						"fileName"    => $row["fileName"],
						"description" => strlen($row["description"]) != 0 ? $row["description"] : '',
						"type"        => $row["type"]
					);

					$mediaArray[] = $currentRowData;
				}

				$returnData = array();
				$returnData["rows_returned"] = count($mediaArray);
				$returnData["media"] = $mediaArray;

				sendResponse(201,true,"Media has been retreived",$returnData,false);
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
							a.folderName,
							b.studentNumber,
							(SELECT COUNT(*) FROM eg_reward WHERE studentId = b.id AND isActive = 1) AS totalAchievement
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
							b.lastName LIKE :search1
						OR
							b.firstName LIKE :search2
						OR
							IFNULL(b.middleName,'') LIKE :search3
						ORDER BY
							b.lastName ASC
			        ");
			        $queryGetAllGrad->bindParam(':schoolYear', $schoolYear, PDO::PARAM_STR);
			        $queryGetAllGrad->bindParam(':search1', $search, PDO::PARAM_STR);
			        $queryGetAllGrad->bindParam(':search2', $search, PDO::PARAM_STR);
			        $queryGetAllGrad->bindParam(':search3', $search, PDO::PARAM_STR);
			    } else {
			        $queryGetAllGrad = $writeDB->prepare("
			            SELECT 
			            	b.id AS studentId,
							CONCAT(b.lastName,', ',b.firstName,' ',IFNULL(b.middleName,'')) AS fullName,
							fileName AS gradPicFileName,
							a.folderName,
							b.studentNumber,
							(SELECT COUNT(*) FROM eg_reward WHERE studentId = b.id AND isActive = 1) AS totalAchievement
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
				        "folderName" => $row["folderName"],
				        "studentNumber" => $row["studentNumber"],
				        "totalAchievement" => $row["totalAchievement"]
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

			if ($mode == "get_achievement") {
				if (
					!isset($jsonData->studentNumber)
				) {
					sendResponse(400,false,"Incomplete Response");
				}

				$studentNumber = $jsonData->studentNumber;
				$achievementArray = array();

				$queryGetAllAch = $writeDB->prepare("
		            SELECT
						a.studentNumber,
						CONCAT(a.lastName,', ',a.firstName,' ', IFNULL(a.middleName,'')) AS fullName,
						b.course,
						a.id
					FROM
						eg_graduates a
					INNER JOIN
						eg_course b 
					ON 
						a.courseId = b.id
					WHERE
						a.studentNumber = :studentNumber
		        ");
		        $queryGetAllAch->bindParam(':studentNumber', $studentNumber, PDO::PARAM_STR);
				$queryGetAllAch->execute();

				while ($row = $queryGetAllAch->fetch(PDO::FETCH_ASSOC)) {
					$currentRowData = array(
						"studentNumber" => $row["studentNumber"],
						"fullName"      => $row["fullName"],
						"course"        => $row["course"],
						"id"            => $row["id"],
						"achievement"   => getAchievement($row["id"],$writeDB)
					);

					$achievementArray[] = $currentRowData;
				}

				$returnData = array();
				$returnData["rows_returned"] = count($achievementArray);
				$returnData["achievement"] = $achievementArray;

				sendResponse(201,true,"Achievement has been retreived",$returnData,false);
			}

			sendResponse(400,false,"Mode not found");
		}
	} else {
		sendResponse(404,false,"Endpoint not found");
	}


	/* Child Queries */
	function getAchievement($studentId,$writeDB) {
		$achievementArray = array();

		$queryGetAllAch = $writeDB->prepare("
            SELECT 
            	a.id,
			    a.studentId,
			    a.titleName,
			    DATE_FORMAT(a.dateReceived, '%m/%d/%Y') AS dateReceived,
			    a.remarks,
			    a.isAwardee
            FROM
            	eg_reward a 
            WHERE
            	a.studentId = :studentId
        ");
        $queryGetAllAch->bindParam(':studentId', $studentId, PDO::PARAM_INT);
		$queryGetAllAch->execute();

		while ($row = $queryGetAllAch->fetch(PDO::FETCH_ASSOC)) {
			$currentRowData = array(
				"id"             => $row["id"],
				"studentId"      => $row["studentId"],
				"titleName"      => $row["titleName"],
				"dateReceived"   => $row["dateReceived"],
				"remarks"        => $row["remarks"],
				"isAwardee"      => $row["isAwardee"]
			);

			$achievementArray[] = $currentRowData;
		}

		return $achievementArray;
	}
?>
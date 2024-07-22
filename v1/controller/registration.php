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
			!isset($jsonData->yearGraduated) ||
			!isset($jsonData->courseId) ||
			!isset($jsonData->firstName) ||
			!isset($jsonData->middleName) ||
			!isset($jsonData->lastName) ||
			!isset($jsonData->emailAddress) || 
			!isset($jsonData->password)
		) {
			sendResponse(400,false,"Incomplete data submitted");
		}

		$studentNumber      = $jsonData->studentNumber;
		$yearGraduated      = $jsonData->yearGraduated;
		$courseId           = $jsonData->courseId;
		$firstName          = $jsonData->firstName;
		$middleName         = $jsonData->middleName;
		$lastName           = $jsonData->lastName;
		$emailAddress       = $jsonData->emailAddress;
		$password           = $jsonData->password;
		$minSchoolYear      = 1906;
		$maxSchoolYear      = getCurrentYear();
		$maxCharString      = 225;
		$currentDateAndTime = getCurrentDateAndTime();
		$isPasswordChanged  = 1;

		if (
			$studentNumber == "" ||
			$yearGraduated == "" ||
			$courseId      == "" ||
			$courseId      == 0 ||
			$firstName     == "" ||
			$lastName      == "" ||
			$emailAddress  == ""
		) {
			sendResponse(400,false,"Please fill in all required fields");
		}

		if (strlen($studentNumber) > $maxCharString) {
			sendResponse(400,false,"Student Number is too long");
		}

		if (strlen($firstName) > $maxCharString) {
			sendResponse(400,false,"First name is too long");
		}

		if (strlen($middleName) > $maxCharString) {
			sendResponse(400,false,"Middle name is too long");
		}

		if (strlen($lastName) > $maxCharString) {
			sendResponse(400,false,"Last name is too long");
		}

		if ($yearGraduated < $minSchoolYear || $yearGraduated > $maxSchoolYear) {
			sendResponse(400,false,"Incorrect School Year");
		}

		if (!validateEmail($emailAddress)) {
			sendResponse(400,false,"Invalid Email Address");
		}

		$query_email = $writeDB->prepare("SELECT * FROM eg_app_registration WHERE emailAddress = :email");
		$query_email->bindParam(':email',$emailAddress,PDO::PARAM_STR);
		$query_email->execute();

		$rowCount = $query_email->rowCount();

		if ($rowCount !== 0) {
			sendResponse(409,false,"Email address already exist");
		}

		$query = $writeDB->prepare("
			INSERT INTO eg_app_registration 
				(studentNumber,yearGraduated,courseId,firstName,middleName,lastName,emailAddress,dateCreated,`password`,isPasswordChanged) 
			VALUES 
				(:studentNumber,:yearGraduated,:courseId,:firstName,:middleName,:lastName,:emailAddress,:dateCreated,MD5(:password),:isPasswordChanged)
		");
		$query->bindParam(':studentNumber',$studentNumber,PDO::PARAM_INT);
		$query->bindParam(':yearGraduated',$yearGraduated,PDO::PARAM_INT);
		$query->bindParam(':courseId',$courseId,PDO::PARAM_STR);
		$query->bindParam(':firstName',$firstName,PDO::PARAM_STR);
		$query->bindParam(':middleName',$middleName,PDO::PARAM_STR);
		$query->bindParam(':lastName',$lastName,PDO::PARAM_STR);
		$query->bindParam(':emailAddress',$emailAddress,PDO::PARAM_STR);
		$query->bindParam(':dateCreated',$currentDateAndTime,PDO::PARAM_STR);
		$query->bindParam(':password',$password,PDO::PARAM_STR);
		$query->bindParam(':isPasswordChanged',$isPasswordChanged,PDO::PARAM_INT);
		$query->execute();

		$rowCount = $query->rowCount();

		if ($rowCount === 0) {
			sendResponse(500,false,"There was an issue creating your user account.Please try again");
		}

		$query_student_number = $writeDB->prepare("SELECT * FROM eg_graduates WHERE studentNumber=:studentNumber AND isActive = 1 AND firstName=:firstName AND middleName=:middleName AND lastName=:lastName");
		$query_student_number->bindParam(':studentNumber',$studentNumber,PDO::PARAM_STR);
		$query_student_number->bindParam(':firstName',$firstName,PDO::PARAM_STR);
		$query_student_number->bindParam(':middleName',$middleName,PDO::PARAM_STR);
		$query_student_number->bindParam(':lastName',$lastName,PDO::PARAM_STR);
		$query_student_number->execute();
		$rowCount = $query_student_number->rowCount();

		if ($rowCount !== 0) {
			while ($row = $query_student_number->fetch(PDO::FETCH_ASSOC)) {
				$approve  = "Approve";
				$password = $studentNumber ."-". $yearGraduated;

				$query = $writeDB->prepare("UPDATE eg_app_registration SET `status`=:status,`password`=MD5(:password) WHERE id IN (SELECT id FROM eg_app_registration WHERE studentNumber = :studentNumber)");
				$query->bindParam(':status',$approve,PDO::PARAM_STR);
				$query->bindParam(':password',$password,PDO::PARAM_STR);
				$query->bindParam(':studentNumber',$studentNumber,PDO::PARAM_INT);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount !== 0) {
					$mail = new PHPMailer(true);

					try {
			            $mail->isSMTP();                                 
			            $mail->Host = 'smtp.gmail.com';
			            $mail->SMTPAuth = true;              
			            $mail->Username = 'digitalyearbookcvsuccc.noreply@gmail.com';         
			            $mail->Password = 'obmi sqrt qqod bcrt';             
			            $mail->SMTPSecure = 'tls';               
			            $mail->Port = 587;
			        
			            $mail->setFrom('digitalyearbookcvsuccc.noreply@gmail.com', "E-GradNayan");
			            $mail->addAddress($emailAddress, str_replace(",","",$emailAddress));
			        
			            $mail->Subject = 'E-GradNayan Account Approval';
			            $mail->Body    = "Congratulations! Your account has been approved. You may now login your registered account";
			        
			            $mail->send();

			            sendResponse(201,true,"User account has been created. Please wait for the verification of account to get your login credentials");
			        } catch (Exception $e) {
			            sendResponse(400,false,"Error sending email");
        			}
				} else {
					sendResponse(400,false,"Not updated");
				}
			}
		}

		sendResponse(201,true,"User account has been created. Please wait for the verification of account to get your login credentials");
	} else {
		sendResponse(404,false,"Endpoint not found");
	}
?>
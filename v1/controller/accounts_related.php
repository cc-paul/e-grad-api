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

		if (!isset($jsonData->mode)) {
			sendResponse(400,false,"Method not found");
		} else {
			$mode = $jsonData->mode;

			if ($mode == "change_password") {
				if (
					!isset($jsonData->id) || 
					!isset($jsonData->newPassword) || 
					!isset($jsonData->repeatPassword)
				) {
					sendResponse(400,false,"Incomplete Request");
				}

				$id             = $jsonData->id;
				$newPassword    = $jsonData->newPassword;
				$repeatPassword = $jsonData->repeatPassword;

				if ($id == null || $newPassword == "" || $repeatPassword == "") {
					sendResponse(400,false,"Incomplete Request");
				}

				if ($newPassword != $repeatPassword) {
					sendResponse(400,false,"Password doesnt match");
				}

				$has_eightchar    = strlen($newPassword) >= 8;
				$has_uppercase    = preg_match('@[A-Z]@', $newPassword);
				$has_lowercase    = preg_match('@[a-z]@', $newPassword);
				$has_number       = preg_match('@[0-9]@', $newPassword);
				$has_specialChars = preg_match('/[^a-zA-Z0-9]/', $newPassword);

				if (
					!$has_eightchar ||
					!$has_uppercase ||
					!$has_lowercase ||
					!$has_number    ||
					!$has_specialChars
				) {
					sendResponse(400,false,"Password doesnt meet the criteria");
				}

				$query = $writeDB->prepare("UPDATE eg_app_registration SET `password` = MD5(:password),isPasswordChanged=1 WHERE id = :id");
				$query->bindParam(':password',$newPassword,PDO::PARAM_STR);
				$query->bindParam(':id',$id,PDO::PARAM_INT);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"There was an error updating password.");
				}

				sendResponse(201,true,"Password has been updated. You may now login with your new password");

			} else if ($mode == "forgot_password") {
				if (!isset($jsonData->emailAddress)) {
					sendResponse(400,false,"Incomplete Request");
				}

				$emailAddress = $jsonData->emailAddress;

				if ($emailAddress == "") {
					sendResponse(400,false,"Email Address is Required");
				}

				$query_email = $writeDB->prepare("SELECT * FROM eg_app_registration WHERE emailAddress = :email AND status = 'Approve'");
				$query_email->bindParam(':email',$emailAddress,PDO::PARAM_STR);
				$query_email->execute();

				$rowCount = $query_email->rowCount();

				if ($rowCount === 0) {
					sendResponse(409,false,"There is no account associated with this email");
				} else {
					$mail = new PHPMailer(true);     
					$link = "";

					while ($row = $query_email->fetch(PDO::FETCH_ASSOC)) {
						$link = "https://apps.project4teen.online/cvsu-admin/pages/password_reset?id=".$row["id"]."&is_admin=0";
					} 

					try {
			            $mail->isSMTP();                                 
			            $mail->Host = 'smtp.gmail.com';
			            $mail->SMTPAuth = true;              
			            $mail->Username = 'digitalyearbookcvsuccc.noreply@gmail.com';         
			            $mail->Password = 'obmi sqrt qqod bcrt';             
			            $mail->SMTPSecure = 'tls';               
			            $mail->Port = 587;
			        
			            $mail->setFrom('digitalyearbookcvsuccc.noreply@gmail.com', "CVSU Yearbook");
			            $mail->addAddress($emailAddress, str_replace(",","",$emailAddress));
			        
			            $mail->Subject = 'CVSU Yearbook Password Reset';
			            $mail->Body    = "Please open the link bellow to change your password.\n\n".$link;
			        
			            $mail->send();

			            sendResponse(201,true,"Password Reset Link has been sent to your email");
			        } catch (Exception $e) {
			            sendResponse(400,false,"Error sending email");
        			}
				}
			} else {
				sendResponse(400,false,"Mode not found");
			}
		}
	} else {
		sendResponse(404,false,"Endpoint not found");
	}
?>
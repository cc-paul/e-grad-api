<?php
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>E-GradNayan Password Reset</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>
<body>

<div class="container" style="margin-top: 20px;">
	<?php
		if (!array_key_exists("id", $_GET)) {
			?>
				<div class="panel panel-default">
				  <div class="panel-heading">Password Reset Verification</div>
				  <div class="panel-body">Password failed to reset</div>
				</div>
			<?php
		} else {
			$id = $_GET['id'];

			$query = $writeDB->prepare("UPDATE eg_app_registration SET `password` = MD5(CONCAT(studentNumber,'-',yearGraduated)),isPasswordChanged=0 WHERE id = :id");
			$query->bindParam(':id',$id,PDO::PARAM_INT);
			$query->execute();

			?>
				<div class="panel panel-success">
				  <div class="panel-heading">Password Reset Verification</div>
				  <div class="panel-body">Password has been reset</div>
				</div>
			<?php
		}
	?>  	
</div>

</body>
</html>
<?php
?>
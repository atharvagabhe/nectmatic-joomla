<?php
$upload_file_size = 1 * 1024 * 1024; // 1 MB
$simTime = 30; // [s]
$steps = 1000;
$error_message = null;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>MATLAB demo</title>
  <meta name="description" content="Demo of php_mbc extension">
  <meta name="author" content="Stephan Schweig">
  <!--[if lt IE 9]>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.js"></script>
  <![endif]-->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.min.js"></script>
</head>
<body>
	<h1>MATLAB demo</h1>
	<h2>Upload-Form</h2>
	 <form action="<?php echo basename($_SERVER["SCRIPT_FILENAME"]); ?>" method="post" enctype="multipart/form-data">
		<table border="0">
			<tr><td>File:</td><td style="width:300px"><input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $upload_file_size; ?>" /><input type="file" name="package_file" style="width:100%" required></td></tr>
			<tr><td>Version:</td><td style="width:300px"><input type="text" maxlength="15" name="package_version" value="1.0.0.0" pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}" style="width:100%" required></td></tr>
			<tr><td>Author:</td><td style="width:300px"><input type="text" name="package_author" value="Max Mustermann" style="width:100%" required></td></tr>
			<tr><td>Description:</td><td style="width:300px"><input type="text" name="package_description" value="Testbuild <?php echo date("d.m.Y H:i:s"); ?>" style="width:100%"></td></tr>
		</table>
		<button type="submit" class="btn btn-primary pull-right">Upload package</button>
	</form>
<?php
if(isset($_FILES["package_file"])) {
	echo "	<hr />\n";
	if($_FILES["package_file"]["error"] == UPLOAD_ERR_OK)
	{
		$cpopt = array(MBC_COMPILE_OPTION_META_VERSION_NUMBER => 1); // Create array for compiler options with default version number 0.0.0.1 (meta data)
		if(isset($_REQUEST["package_version"])) {
			// Check if given version number has format w.x.y.z with max 3 digits per section
			$fv = filter_var($_REQUEST["package_version"], FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/')));
			if($fv != false && $fv != null) {
				// Limit each section to 254
				$tmp = explode(".",$fv);
				$va = (int)$tmp[0];
				if($va > 254) $va = 254;
				$vb = (int)$tmp[1];
				if($vb > 254) $vb = 254;
				$vc = (int)$tmp[2];
				if($vc > 254) $vc = 254;
				$vd = (int)$tmp[3];
				if($vd > 254) $vd = 254;
				$cpopt[MBC_COMPILE_OPTION_META_VERSION_NUMBER] = ($va<<24) + ($vb<<16) + ($vc<<8) + $vd; // Update default version number in compiler options array
			}
		}
		if(isset($_REQUEST["package_author"]))
		{
			$tmp = @trim($_REQUEST["package_author"]);
			if(@strlen($tmp) > 0) $cpopt[MBC_COMPILE_OPTION_META_AUTHOR] = $tmp; // Add given author name to meta data compiler options
		}
		if(isset($_REQUEST["package_description"]))
		{
			$tmp = @trim($_REQUEST["package_description"]);
			if(@strlen($tmp) > 0) $cpopt[MBC_COMPILE_OPTION_META_DESCRIPTION] = $tmp; // Add given package description to meta data compiler options
		}
		//if(TRUE)
		if($_FILES["package_file"]["type"] == "application/x-zip-compressed") // Check MIME type for zip file
		{
			$uploadfile = "uploads/".basename($_FILES['package_file']['name']);
			if (move_uploaded_file($_FILES['package_file']['tmp_name'], $uploadfile)) { // Move uploaded file from tmp to uploads folder for direct access
				$mbcfile = pathinfo($_SERVER['SCRIPT_FILENAME'])["dirname"]."/".$uploadfile; // Get absolute path of uploaded file
				echo "<h2>Compile package (<tt>mbc_package_compile</tt>)</h2>\n";
				$package_mbcid = mbc_package_compile($mbcfile, $cpopt); // Compile package into a shared library
				if(is_string($package_mbcid)) // Check result
					$error_message = $package_mbcid; // Get compiler error message
				else {
					// The package has been compiled successfully, the result is a package ID
					echo "<p>Package ID: $package_mbcid</p>\n";
					echo "<h2>Get demo model information (<tt>mbc_package_info</tt>)</h2>\n";
					$info_packet = mbc_package_info($package_mbcid); // Get information (meta data, inputs, outputs, step size, etc.) from package by given package ID
					if(isset($info_packet["stepSize"])) $steps = (int)((float)$simTime / $info_packet["stepSize"]); // Calculate number of simulation steps by given simulation time and step size
					echo "<pre>"; var_dump($info_packet); echo "</pre>\n";
					echo "<h2>Create instance of demo model (<tt>mbc_model_create</tt>)</h2>\n";
					$model_mbcid = mbc_model_create($package_mbcid); // Create an instance of a compiled model
					if(is_string($model_mbcid))
						$error_message = $model_mbcid; // Get model error message
					else {
						// A new model instance has been created successfully, the result is a model ID
						echo "<p>Model ID: $model_mbcid</p>\n";
						echo "<h2>Get model instance information (<tt>mbc_model_info</tt>)</h2>\n";
						$info_model = mbc_model_info($model_mbcid); // Get information (inputs, outputs, step size, task time, etc.) from model instance by given model ID
						echo "<pre>"; var_dump($info_model); echo "</pre>\n";
						echo "<h2>Simulate $steps steps in demo model instance (<tt>mbc_model_simulate</tt>)</h2>\n";
						// Info: The following part is designed complicated to compress everything into one file and produce a table and a csv file to download
						$fp = fopen("lastsim.csv", "w");
						echo "<table><tr><th>Step</th>";
						fwrite($fp, "Step");
						$inputs = array();
						$input_count = 0;
						// Prepare array for input values
						if(@count($info_packet["inputs"]) > 0)
						{
							$input_count = count($info_packet["inputs"]);
							for($i=0;$i<$input_count;$i++)
							{
								$inputs[$i] = 0.0;
								echo "<th>".$info_packet["inputs"][$i]."</th>";
								fwrite($fp, ";".$info_packet["inputs"][$i]);
							}
						}
						$outputs = array();
						$output_count = 0;
						echo "<th>TaskTime</th>";
						fwrite($fp, ";TaskTime");
						// Prepare array for output values
						if(@count($info_packet["outputs"]) > 0)
						{
							$output_count = count($info_packet["outputs"]);
							for($i=0;$i<$output_count;$i++)
							{
								$outputs[$i] = 0.0;
								echo "<th>".$info_packet["outputs"][$i]."</th>";
								fwrite($fp, ";".$info_packet["outputs"][$i]);
							}
						}
						echo "</tr>\n";
						fwrite($fp, "\n");
						$gtt = array();
						$gin = array();
						$gout = array();
						$tm = 0.0;
						// Run simulation
						for($s = 1; $s <= $steps+1; $s++)
						{
							$inputs = array(($tm < 5.0 || (10.0 < $tm && $tm < 15.0) || (20.0 < $tm && $tm < 25.0) || (30.0 < $tm && $tm < 35.0)) ? 1.0 : 0.0, $outputs[0]); // Create a square wave signal for first input and set the last output value to second input
							$output_arr = mbc_model_simulate($model_mbcid,$inputs); // Perform one simulation step
							$outputs = $output_arr["data"]; // Get output values
							echo "<tr><td><b>".$s."</b></td>";
							fwrite($fp, $s);
							for($i=0;$i<$input_count;$i++)
							{
								echo "<td>".number_format($inputs[$i],12)."</td>";
								fwrite($fp, ";".number_format($inputs[$i],12));
							}
							echo "<td>".number_format($output_arr["taskTime"],12)."</td>";
							fwrite($fp, ";".number_format($output_arr["taskTime"],12));
							for($i=0;$i<$output_count;$i++)
							{
								echo "<td>".number_format($outputs[$i],12)."</td>";
								fwrite($fp, ";".number_format($outputs[$i],12));
							}
							echo "</tr>";
							fwrite($fp, "\n");
							if(!is_nan($inputs[0]) && is_finite($inputs[0]) && !is_nan($outputs[0]) && is_finite($outputs[0])) {
								$gtt[] = number_format($output_arr["taskTime"], 4);
								$gin[] = $inputs[0];
								$gout[] = $outputs[0];
							}
							$tm = $output_arr["taskTime"]; // Get time of simulation step
						}
						echo "</table>\n";
						if(@file_exists("lastsim.csv") && @filesize("lastsim.csv") > 0) echo '<p><a href="lastsim.csv">Download data table</a></p>';
						fclose($fp);
?>	<canvas id="myChart" width="400" height="400"></canvas>
    <script>
        var config = {
            type: 'line',
            data: {
				labels: [<?php echo implode(",",$gtt); ?>],
                datasets: [{
                    label: "Input",
                    backgroundColor: '#0000ff',
                    borderColor: '#0000ff',
                    data: [<?php echo implode(",",$gin); ?>],
                    fill: false,
					lineTension: 0,
                }, {
                    label: "Output",
                    backgroundColor: '#ff0000',
                    borderColor: '#ff0000',
                    data: [<?php echo implode(",",$gout); ?>],
                    fill: false,
					lineTension: 0,
                }]
            },
            options: {
                responsive: true,
                title:{
                    display:true,
                    text:'Controller demo'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Time [s]'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Value'
                        }
                    }]
                }
            }
        };

        window.onload = function() {
            var ctx = document.getElementById("myChart").getContext("2d");
            window.myLine = new Chart(ctx, config);
        };
    </script>
<?php
						echo "<h2>Delete instance of demo model (<tt>mbc_model_terminate</tt>)</h2>\n";
						$rc = mbc_model_terminate($model_mbcid); // Delete model instance by given model ID
						echo "<pre>"; var_dump($rc); echo "</pre>\n";
						echo "<h2>Delete demo model (<tt>mbc_package_clean</tt>)</h2>\n";
						$rc = mbc_package_clean($package_mbcid); // Delete package by given package ID
						echo "<pre>"; var_dump($rc); echo "</pre>\n";
					}
				}
			} else {
				$error_message = "Possible file upload attack!";
			}
		} else {
			$error_message = "Code package must be a zip file!";
		}
		if(file_exists($_FILES['package_file']['tmp_name'])) @unlink($_FILES['package_file']['tmp_name']);
		if(file_exists($uploadfile)) @unlink($uploadfile);
	} else {
		switch($_FILES["package_file"]["error"])
		{
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				$error_message = "The uploaded file exceeds the maximal file size of $upload_file_size bytes!";
				break;
			case UPLOAD_ERR_PARTIAL:
				$error_message = "The uploaded file was only partially uploaded.";
				break;
			case UPLOAD_ERR_NO_FILE:
				$error_message = "No file was uploaded.";
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
			case UPLOAD_ERR_EXTENSION:
			default:
				$error_message = "Internal upload error ".$_FILES["package_file"]["error"];
				break;
		}
	}
	if($error_message != null) echo '		<p class="color:red">'.$error_message."</p>\n";
}
?>
<hr />
<p style="text-align:center">&copy; Copyright 2017, Stephan Schweig</p>
</body>
</html>

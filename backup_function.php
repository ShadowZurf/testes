<?php

error_reporting(0);

function logError($message, $databaseName = null)
{
	$logFile = 'error_log.txt';
	$errorDetails = date('Y-m-d H:i:s') . " - " . $message;
	if ($databaseName !== null) {
		$errorDetails .= " (Database: " . $databaseName . ")";
	}

	// Output the error using SweetAlert 2
	echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error Occurred',
                html: '" . addslashes($errorDetails) . "'
            });
          </script>";



	$errorDetails .= "\n";

	$current = file_get_contents($logFile);
	$current .= $errorDetails;
	file_put_contents($logFile, $current);
}



error_reporting(0);
function backDb($host, $user, $pass, $dbname, $tables = '*')
{
	$conn = new mysqli($host, $user, $pass, $dbname);
	if ($conn->connect_error) {
		logError("Connection failed: " . $conn->connect_error);
		die("Connection failed: " . $conn->connect_error);
	}
	echo "Connected successfully<br>";

	if ($tables == '*') {
		$tables = array();
		$sql = "SHOW TABLES";
		$query = $conn->query($sql);
		while ($row = $query->fetch_row()) {
			$tables[] = $row[0];
		}
	} else {
		$tables = is_array($tables) ? $tables : explode(',', $tables);
	}

	$outsql = '';
	foreach ($tables as $table) {
		$sql = "SHOW CREATE TABLE $table";
		$query = $conn->query($sql);
		$row = $query->fetch_row();
		$outsql .= "\n\n" . $row[1] . ";\n\n";

		$sql = "SELECT * FROM $table";
		$query = $conn->query($sql);
		$columnCount = $query->field_count;

		for ($i = 0; $i < $columnCount; $i++) {
			while ($row = $query->fetch_row()) {
				$outsql .= "INSERT INTO $table VALUES(";
				for ($j = 0; $j < $columnCount; $j++) {
					$outsql .= isset($row[$j]) ? '"' . $row[$j] . '"' : '""';
					if ($j < ($columnCount - 1)) {
						$outsql .= ',';
					}
				}
				$outsql .= ");\n";
			}
		}
		$outsql .= "\n";
	}

	$backup_file_name = $dbname . '_database.sql';
	$fileHandler = fopen($backup_file_name, 'w+');
	fwrite($fileHandler, $outsql);
	fclose($fileHandler);
	echo "Backup file created: $backup_file_name<br>";

	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($backup_file_name));
	ob_clean();
	flush();
	readfile($backup_file_name);
	unlink($backup_file_name);
	echo "Backup completed<br>";
}

function backupDiffDb($host, $user, $pass, $dbname, $lastBackupTime)
{
	$conn = new mysqli($host, $user, $pass, $dbname);
	if ($conn->connect_error) {
		logError("Connection failed: " . $conn->connect_error);
		die("Connection failed: " . $conn->connect_error);
	}

	$tables = array();
	$sql = "SHOW TABLES";
	$query = $conn->query($sql);
	while ($row = $query->fetch_row()) {
		$tables[] = $row[0];
	}

	$outsql = '';
	foreach ($tables as $table) {
		$sql = "SHOW CREATE TABLE $table";
		$query = $conn->query($sql);
		$row = $query->fetch_row();
		$outsql .= "\n\n" . $row[1] . ";\n\n";

		// Check if the table has an 'updated_at' column
		$sql = "SHOW COLUMNS FROM $table LIKE 'updated_at'";
		$query = $conn->query($sql);
		if ($query->num_rows > 0) {
			$sql = "SELECT * FROM $table WHERE updated_at > '$lastBackupTime'";
			$query = $conn->query($sql);

			if ($query) {
				$columnCount = $query->field_count;
				for ($i = 0; $i < $columnCount; $i++) {
					while ($row = $query->fetch_row()) {
						$outsql .= "INSERT INTO $table VALUES(";
						for ($j = 0; $j < $columnCount; $j++) {
							$outsql .= isset($row[$j]) ? '"' . $row[$j] . '"' : '""';
							if ($j < ($columnCount - 1)) {
								$outsql .= ',';
							}
						}
						$outsql .= ");\n";
					}
				}
				$outsql .= "\n";
			} else {
				logError("Query failed: " . $conn->error);
			}
		} else {
			logError("Table $table does not have an 'updated_at' column");
		}
	}

	$backup_file_name = $dbname . '_diff_database.sql';
	$fileHandler = fopen($backup_file_name, 'w+');
	fwrite($fileHandler, $outsql);
	fclose($fileHandler);

	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($backup_file_name));
	ob_clean();
	flush();
	readfile($backup_file_name);
	unlink($backup_file_name);
}
function backupTransactionLog($host, $user, $pass, $dbname)
{
	// Nama file backup
	$backup_file_name = $dbname . '_backup_' . date("Y-m-d_H-i-s") . '.sql';

	// Perintah mysqldump untuk melakukan backup
	$command = "mysqldump --host=$host --user=$user --password=$pass $dbname > $backup_file_name";

	// Menjalankan perintah dan menangkap keluaran dan kesalahan
	$output = [];
	$return_var = 0;
	exec($command . ' 2>&1', $output, $return_var);

	// Memeriksa apakah backup berhasil
	if ($return_var === 0) {
		// Set headers for download
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($backup_file_name));

		flush();  // Flush system output buffer
		readfile($backup_file_name);

		// Menghapus file setelah diunduh
		unlink($backup_file_name);

		// Keluar setelah unduhan
		exit();
	} else {
		// Menampilkan debugging
		echo "Backup database gagal. Perintah yang dijalankan: $command\n";
		echo "Output:\n" . implode("\n", $output) . "\n";
		echo "Return var: " . $return_var . "\n";

		// Memeriksa apakah mysqldump dapat ditemukan dan dijalankan
		exec('where mysqldump 2>&1', $where_output, $where_return_var);
		if ($where_return_var !== 0) {
			echo "Perintah mysqldump tidak ditemukan dalam path sistem.\n";
			echo "Output where mysqldump:\n" . implode("\n", $where_output) . "\n";
		} else {
			echo "Perintah mysqldump ditemukan di: " . implode("\n", $where_output) . "\n";
		}
	}

	error_log("Backup database MySQL gagal untuk: $dbname", 0);
}

function logShipping($host, $user, $pass, $primaryDb, $secondaryHost, $secondaryUser, $secondaryPass, $secondaryDb)
{
	// Koneksi ke database utama (primaryDb)
	$connPrimary = new mysqli($host, $user, $pass, $primaryDb);
	if ($connPrimary->connect_error) {
		die("Connection to primary server failed: " . $connPrimary->connect_error);
	}

	// Koneksi ke database sekunder (backuplog)
	$connSecondary = new mysqli($secondaryHost, $secondaryUser, $secondaryPass, $secondaryDb);
	if ($connSecondary->connect_error) {
		die("Connection to secondary server failed: " . $connSecondary->connect_error);
	}

	// Mendapatkan daftar tabel dari database utama
	$tables = [];
	$sql = "SHOW TABLES";
	$query = $connPrimary->query($sql);
	if ($query === false) {
		die("Error fetching tables: " . $connPrimary->error);
	}

	while ($row = $query->fetch_row()) {
		$tables[] = $row[0];
	}

	// Loop untuk setiap tabel, kopi struktur dan data ke database sekunder (backuplog)
	$connSecondary->begin_transaction();

	try {
		foreach ($tables as $table) {
			// Query untuk mendapatkan struktur tabel di database utama
			$sql = "SHOW CREATE TABLE $table";
			$result = $connPrimary->query($sql);
			if ($result === false) {
				throw new Exception("Error fetching table structure for $table: " . $connPrimary->error);
			}
			$row = $result->fetch_row();
			$createTableSql = $row[1];

			// Buat tabel di database sekunder (backuplog) jika belum ada
			$connSecondary->query("DROP TABLE IF EXISTS $table");
			$connSecondary->query($createTableSql);

			// Salin data dari tabel di database utama ke tabel yang sesuai di backuplog
			$sql = "INSERT INTO $secondaryDb.$table SELECT * FROM $primaryDb.$table";
			if ($connSecondary->query($sql) === false) {
				throw new Exception("Error copying data from $table: " . $connSecondary->error);
			}
		}

		$connSecondary->commit();
		echo "Table data shipping successful.";
	} catch (Exception $e) {
		$connSecondary->rollback();
		die("Error: " . $e->getMessage());
	}

	// Tutup koneksi database
	$connPrimary->close();
	$connSecondary->close();
}

function automatedBackup($host, $user, $pass, $dbname)
{
	// Backup full database
	backDb($host, $user, $pass, $dbname);

	// Ambil timestamp terakhir dari backup full
	$lastBackupTime = date("Y-m-d H:i:s");

	// Backup differential database berdasarkan timestamp terakhir
	backupDiffDb($host, $user, $pass, $dbname, $lastBackupTime);
}


function downloadBackupFile($backup_file_name)
{
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($backup_file_name));
	ob_clean();
	flush();
	readfile($backup_file_name);
}

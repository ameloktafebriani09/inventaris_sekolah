
<?php
require_once "koneksi.php";

$username = "sarpras1";
$password = password_hash("password", PASSWORD_DEFAULT);

$stmt = mysqli_prepare(
    $conn,
    "UPDATE pengguna SET password = ? WHERE username = ?"
);

mysqli_stmt_bind_param($stmt, "ss", $password, $username);

if (mysqli_stmt_execute($stmt)) {
    echo "Password berhasil diperbaiki!";
} else {
    echo "Gagal memperbaiki password.";
}

mysqli_stmt_close($stmt);
?>

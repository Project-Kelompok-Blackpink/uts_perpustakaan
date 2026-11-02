
<?php

header('Content-Type: application/json');

// koneksi database
$koneksi = mysqli_connect("localhost", "root", "", "db_perpustakaan");

if (!$koneksi) {
    sendResponse(false, "Koneksi database gagal", null, 500);
}

// fungsi response
function sendResponse($success, $message, $data = null, $status = 201)
{
    http_response_code($status);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}

// proses request
switch ($_SERVER['REQUEST_METHOD']) {

    case 'POST':

        $input = json_decode(file_get_contents('php://input'), true);

        // cek input wajib
        if (!$input || !isset($input['kode_anggota']) || !isset($input['nama_anggota']) || !isset($input['alamat']) || !isset($input['no_hp'])) {
            sendResponse(false, "Field kode_anggota, nama_anggota, alamat, no_hp wajib diisi", null, 400);
        }

        $kode_anggota = trim($input['kode_anggota']);
        $nama_anggota = trim($input['nama_anggota']);
        $alamat = trim($input['alamat']);
        $no_hp = trim($input['no_hp']);

        if ($kode_anggota == '' || $nama_anggota == '' || $alamat == '' || $no_hp == '') {
            sendResponse(false, "Data anggota tidak boleh kosong", null, 400);
        }

        // cek kode anggota
        $cek = mysqli_prepare($koneksi, "SELECT id FROM anggota WHERE kode_anggota = ?");
        mysqli_stmt_bind_param($cek, "s", $kode_anggota);
        mysqli_stmt_execute($cek);
        $hasil = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasil) > 0) {
            sendResponse(false, "Kode anggota sudah terdaftar", null, 409);
        }

        // insert data anggota
        $stmt = mysqli_prepare($koneksi, "INSERT INTO anggota (kode_anggota, nama_anggota, alamat, no_hp) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $kode_anggota, $nama_anggota, $alamat, $no_hp);

        if (mysqli_stmt_execute($stmt)) {
            sendResponse(
                true,
                "Data anggota berhasil tersimpan",
                ["id" => mysqli_insert_id($koneksi)],
                201
            );
        } else {
            sendResponse(false, mysqli_error($koneksi), null, 500);
        }

        break;

    default:
        sendResponse(false, "Method tidak diizinkan", null, 405);
}

?>

<?php

require_once "../config.php";
require_once "../helpers/response.php";

mysqli_set_charset($koneksi, "utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ================= GET =================
    case 'GET':

        // Get by ID
        if (isset($_GET['id'])) {

            $id = (int) $_GET['id'];

            if ($id <= 0) {
                sendResponse(false, "ID tidak valid", null, 400);
                exit;
            }

            $stmt = mysqli_prepare($koneksi,
                "SELECT * FROM buku WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $r = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($r);

            if (!$data) {
                sendResponse(false, "Buku tidak ditemukan", null, 404);
                exit;
            }

            sendResponse(true, "Berhasil", $data, 200);
            exit;
        }

        // Search
        if (isset($_GET['search'])) {

            $search = trim($_GET['search']);
            $term = "%" . $search . "%";

            $stmt = mysqli_prepare($koneksi,
                "SELECT * FROM buku
                 WHERE isbn LIKE ?
                 OR judul_buku LIKE ?
                 OR pengarang LIKE ?
                 ORDER BY id DESC"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $term,
                $term,
                $term
            );

            mysqli_stmt_execute($stmt);

            $r = mysqli_stmt_get_result($stmt);

            $data = [];

            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            sendResponse(true, "Berhasil", $data, 200);
            exit;
        }

        // Limit & Offset
        if (isset($_GET['page']) || isset($_GET['limit'])) {

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, (int) ($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;

            $stmt = mysqli_prepare($koneksi,
                "SELECT * FROM buku
                 ORDER BY id DESC
                 LIMIT ? OFFSET ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $limit,
                $offset
            );

            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            $data = [];

            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            sendResponse(true, "Berhasil", $data, 200);
            exit;
        }

        // Get all
        $r = mysqli_query(
            $koneksi,
            "SELECT * FROM buku ORDER BY id DESC"
        );

        if (!$r) {
            sendResponse(false, "Gagal mengambil data", null, 500);
            exit;
        }

        $data = [];

        while ($row = mysqli_fetch_assoc($r)) {
            $data[] = $row;
        }

        sendResponse(true, "Berhasil", $data, 200);
        exit;

case 'POST':
    $input = json_decode(file_get_contents('php://input'), true);

    // cek input wajib
    if (!$input || !isset($input['isbn']) || !isset($input['judul_buku']) || !isset($input['pengarang']) || !isset($input['tahun_terbit'])) {
        sendResponse(false, "Field isbn, judul_buku, pengarang, tahun_terbit wajib diisi", null, 400);
    }

    $isbn = trim($input['isbn']);
    $judul_buku = trim($input['judul_buku']);
    $pengarang = trim($input['pengarang']);
    $tahun_terbit = (int) $input['tahun_terbit'];

    if ($isbn == '' || $judul_buku == '' || $pengarang == '' || $tahun_terbit <= 0) {
        sendResponse(false, "Data buku tidak boleh kosong atau tidak valid", null, 400);
    }

    // cek ISBN apakah sudah ada
    $cek = mysqli_prepare($koneksi, "SELECT id FROM buku WHERE isbn = ?");

    if (!$cek) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($cek, "s", $isbn);
    mysqli_stmt_execute($cek);
    $hasil = mysqli_stmt_get_result($cek);

    if (mysqli_num_rows($hasil) > 0) {
        sendResponse(false, "ISBN buku sudah terdaftar", null, 409);
    }

    // insert data buku
    $stmt = mysqli_prepare($koneksi, "INSERT INTO buku (isbn, judul_buku, pengarang, tahun_terbit) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($stmt, "sssi", $isbn, $judul_buku, $pengarang, $tahun_terbit);

    if (mysqli_stmt_execute($stmt)) {
        sendResponse(
            true,
            "Data buku berhasil tersimpan",
            ["id" => mysqli_insert_id($koneksi)],
            201
        );
    } else {
        error_log(mysqli_stmt_error($stmt));
        sendResponse(false, "Data buku gagal disimpan", null, 500);
    }

    break;

    // ================= PUT =================
    case 'PUT':
    $input = json_decode(file_get_contents("php://input"), true);

    // Validasi input
    if (!$input || !isset($input['isbn']) || !isset($input['judul_buku']) ||
        !isset($input['pengarang']) || !isset($input['tahun_terbit'])) {
        sendResponse(false, "Input tidak boleh kosong", null, 400);
        break;
    }

    // Pertahanan pertama: cast integer
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendResponse(false, "ID tidak valid", null, 400);
        break;
    }

    $isbn = $input['isbn'];
    $judul_buku = $input['judul_buku'];
    $pengarang = $input['pengarang'];
    $tahun_terbit = $input['tahun_terbit'];

    // Pertahanan kedua: prepared statement untuk mengecek ID
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id FROM buku WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);

    if (!$r) {
        mysqli_stmt_close($stmt);
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    $data = mysqli_fetch_assoc($r);

    if (!$data) {
        sendResponse(false, "buku tidak ditemukan", null, 404);
        break;
    }

    // Query untuk mengubah data buku
    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE buku
        SET isbn = ?, judul_buku = ?, pengarang = ?, tahun_terbit = ?
        WHERE id = ?"
    );

    if (!$stmt) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    mysqli_stmt_bind_param($stmt, "ssssi", $isbn, $judul_buku, $pengarang, $tahun_terbit, $id);

    if (mysqli_stmt_execute($stmt)) {
        sendResponse(true, "Data buku berhasil diperbarui", ["id" => $id], 200);
    } else {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
    }

    break;
    
    // ================= DELETE =================

    case 'DELETE':

    // Mengambil ID
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        sendResponse(false, "ID tidak valid", null, 400);
        break;
    }

    // Cek apakah buku tersedia
    $stmt = mysqli_prepare($koneksi, "SELECT id FROM buku WHERE id = ?");

    if (!$stmt) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);

    if (!$r) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    $data = mysqli_fetch_assoc($r);

    if (!$data) {
        sendResponse(false, "Buku tidak ditemukan", null, 404);
        break;
    }

    // Hapus data buku
    $stmt = mysqli_prepare($koneksi, "DELETE FROM buku WHERE id = ?");

    if (!$stmt) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        sendResponse(true, "Data buku berhasil dihapus", ["id" => $id], 200);

    } else {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
    }

    break;
}
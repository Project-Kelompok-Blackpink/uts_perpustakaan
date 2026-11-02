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
                "SELECT * FROM anggota WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $r = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($r);

            if (!$data) {
                sendResponse(false, "Anggota tidak ditemukan", null, 404);
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
                "SELECT * FROM anggota
                 WHERE kode_anggota LIKE ?
                 OR nama_anggota LIKE ?
                 ORDER BY id DESC"
            );

            mysqli_stmt_bind_param($stmt, "ss", $term, $term);
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
                "SELECT * FROM anggota
                 ORDER BY id DESC
                 LIMIT ? OFFSET ?"
            );

            mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
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
        $r = mysqli_query($koneksi,
            "SELECT * FROM anggota ORDER BY id DESC"
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


    // ================= POST =================
    case 'POST':

        $input = json_decode(file_get_contents("php://input"), true);

        if (!is_array($input)) {
            sendResponse(false, "Format JSON tidak valid", null, 400);
            exit;
        }

        $kode_anggota = trim($input['kode_anggota'] ?? '');
        $nama_anggota = trim($input['nama_anggota'] ?? '');
        $alamat = trim($input['alamat'] ?? '');
        $no_hp = trim($input['no_hp'] ?? '');

        if ($kode_anggota == '' || $nama_anggota == '') {
            sendResponse(false, "Kode dan nama anggota wajib diisi", null, 400);
            exit;
        }

        $stmt = mysqli_prepare($koneksi,
            "INSERT INTO anggota
            (kode_anggota, nama_anggota, alamat, no_hp)
            VALUES (?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssss",
            $kode_anggota,
            $nama_anggota,
            $alamat,
            $no_hp
        );

        if (mysqli_stmt_execute($stmt)) {

            sendResponse(
                true,
                "Data anggota berhasil disimpan",
                ["id" => mysqli_insert_id($koneksi)],
                201
            );

            exit;
        }

        sendResponse(false, "Gagal menyimpan data anggota", null, 500);
        exit;


    default:

        sendResponse(false, "Method tidak diizinkan", null, 405);
        exit;
}
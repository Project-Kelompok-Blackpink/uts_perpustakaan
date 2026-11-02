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


    // ================= POST =================
    case 'POST':

        $input = json_decode(file_get_contents("php://input"), true);

        if (!is_array($input)) {
            sendResponse(false, "Format JSON tidak valid", null, 400);
            exit;
        }

        $isbn = trim($input['isbn'] ?? '');
        $judul_buku = trim($input['judul_buku'] ?? '');
        $pengarang = trim($input['pengarang'] ?? '');
        $tahun_terbit = trim((string) ($input['tahun_terbit'] ?? ''));

        if (
            $isbn == '' ||
            $judul_buku == '' ||
            $pengarang == '' ||
            $tahun_terbit == ''
        ) {
            sendResponse(false, "Semua data buku wajib diisi", null, 400);
            exit;
        }

        $stmt = mysqli_prepare($koneksi,
            "INSERT INTO buku
            (isbn, judul_buku, pengarang, tahun_terbit)
            VALUES (?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssss",
            $isbn,
            $judul_buku,
            $pengarang,
            $tahun_terbit
        );

        if (mysqli_stmt_execute($stmt)) {

            sendResponse(
                true,
                "Data buku berhasil disimpan",
                ["id" => mysqli_insert_id($koneksi)],
                201
            );

            exit;
        }

        sendResponse(false, "Gagal menyimpan data buku", null, 500);
        exit;


    default:

        sendResponse(false, "Method tidak diizinkan", null, 405);
        exit;
}
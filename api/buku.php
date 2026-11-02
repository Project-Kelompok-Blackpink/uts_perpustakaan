<?php

require_once "../config.php";
require_once "../helpers/response.php";
require_once "../helpers/security.php";

mysqli_set_charset($koneksi, "utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ================= GET =================
    case 'GET':

        // Get by ID
        if (isset($_GET['id'])) {

            $id = getValidId();

            $stmt = prepareQuery($koneksi,
                "SELECT * FROM buku WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $r = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($r);

            if (!$data) {
                securityError("Buku tidak ditemukan", 404);
            }

            sendResponse(true, "Berhasil", $data, 200);
            exit;
        }

        // Search
        if (isset($_GET['search'])) {

            $search = $_GET['search'];

            if (!is_string($search)) {
                securityError("Search harus berupa teks", 400);
            }

            $term = "%" . trim($search) . "%";

            $stmt = prepareQuery($koneksi,
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

            $paging = getPagination();

            $stmt = prepareQuery($koneksi,
                "SELECT * FROM buku
                 ORDER BY id DESC
                 LIMIT ? OFFSET ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $paging['limit'],
                $paging['offset']
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

        if (!$r) handleDatabaseError($koneksi);

        $data = [];

        while ($row = mysqli_fetch_assoc($r)) {
            $data[] = $row;
        }

        sendResponse(true, "Berhasil", $data, 200);
        exit;


    // ================= POST =================
    case 'POST':

        $input = getJsonInput();

        $isbn = requiredString($input, 'isbn', 30);
        $judul_buku = requiredString($input, 'judul_buku', 100);
        $pengarang = requiredString($input, 'pengarang', 100);
        $tahun_terbit = requiredYear($input, 'tahun_terbit');

        $stmt = prepareQuery($koneksi,
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

        handleDatabaseError($koneksi);
        break;


    // ================= PUT =================
    case 'PUT':

        $id = getValidId();
        $input = getJsonInput();

        $isbn = requiredString($input, 'isbn', 30);
        $judul_buku = requiredString($input, 'judul_buku', 100);
        $pengarang = requiredString($input, 'pengarang', 100);
        $tahun_terbit = requiredYear($input, 'tahun_terbit');

        // Cek buku
        $stmt = prepareQuery($koneksi,
            "SELECT id FROM buku WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            securityError("Buku tidak ditemukan", 404);
        }

        // Update buku
        $stmt = prepareQuery($koneksi,
            "UPDATE buku
             SET isbn = ?,
                 judul_buku = ?,
                 pengarang = ?,
                 tahun_terbit = ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssssi",
            $isbn,
            $judul_buku,
            $pengarang,
            $tahun_terbit,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {

            sendResponse(
                true,
                "Data buku berhasil diperbarui",
                ["id" => $id],
                200
            );

            exit;
        }

        handleDatabaseError($koneksi);
        break;


    // ================= DELETE =================
    case 'DELETE':

        $id = getValidId();

        $stmt = prepareQuery($koneksi,
            "DELETE FROM buku WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {

            if (mysqli_stmt_affected_rows($stmt) == 0) {
                securityError("Buku tidak ditemukan", 404);
            }

            sendResponse(
                true,
                "Data buku berhasil dihapus",
                ["id" => $id],
                200
            );

            exit;
        }

        handleDatabaseError($koneksi);
        break;


    default:

        sendResponse(false, "Method tidak diizinkan", null, 405);
        exit;
}
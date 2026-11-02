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
                "SELECT * FROM anggota WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $r = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($r);

            if (!$data) {
                securityError("Anggota tidak ditemukan", 404);
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

            $paging = getPagination();

            $stmt = prepareQuery($koneksi,
                "SELECT * FROM anggota
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
            "SELECT * FROM anggota ORDER BY id DESC"
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

        $kode_anggota = requiredString($input, 'kode_anggota', 20);
        $nama_anggota = requiredString($input, 'nama_anggota', 100);
        $alamat = optionalString($input, 'alamat', 100);
        $no_hp = optionalString($input, 'no_hp', 15);

        $stmt = prepareQuery($koneksi,
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

        handleDatabaseError($koneksi);
        break;


    // ================= PUT =================
    case 'PUT':

        $id = getValidId();
        $input = getJsonInput();

        $kode_anggota = requiredString($input, 'kode_anggota', 20);
        $nama_anggota = requiredString($input, 'nama_anggota', 100);
        $alamat = optionalString($input, 'alamat', 100);
        $no_hp = optionalString($input, 'no_hp', 15);

        // Cek anggota
        $stmt = prepareQuery($koneksi,
            "SELECT id FROM anggota WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            securityError("Anggota tidak ditemukan", 404);
        }

        // Update
        $stmt = prepareQuery($koneksi,
            "UPDATE anggota
             SET kode_anggota = ?,
                 nama_anggota = ?,
                 alamat = ?,
                 no_hp = ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssssi",
            $kode_anggota,
            $nama_anggota,
            $alamat,
            $no_hp,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {

            sendResponse(
                true,
                "Data anggota berhasil diperbarui",
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
            "DELETE FROM anggota WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {

            if (mysqli_stmt_affected_rows($stmt) == 0) {
                securityError("Anggota tidak ditemukan", 404);
            }

            sendResponse(
                true,
                "Data anggota berhasil dihapus",
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
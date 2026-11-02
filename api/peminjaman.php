<?php

require_once "../config.php";
require_once "../helpers/response.php";
require_once "../helpers/security.php";

mysqli_set_charset($koneksi, "utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ================= GET =================
    case 'GET':

        // Query JOIN
        $query = "SELECT p.id, p.id_anggota, a.nama_anggota,
                         p.id_buku, b.judul_buku,
                         p.tanggal_pinjam, p.tanggal_kembali
                  FROM peminjaman p
                  LEFT JOIN anggota a ON p.id_anggota = a.id
                  LEFT JOIN buku b ON p.id_buku = b.id";

        // Get by ID
        if (isset($_GET['id'])) {

            $id = getValidId();

            $stmt = prepareQuery($koneksi,
                $query . " WHERE p.id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $r = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($r);

            if (!$data) {
                securityError("Peminjaman tidak ditemukan", 404);
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
                $query . "
                WHERE a.nama_anggota LIKE ?
                OR b.judul_buku LIKE ?
                ORDER BY p.id DESC"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ss",
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
                $query . "
                ORDER BY p.id DESC
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
            $query . " ORDER BY p.id DESC"
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

        $id_anggota = requiredInt($input, 'id_anggota');
        $id_buku = requiredInt($input, 'id_buku');

        $tanggal_pinjam = requiredDate($input, 'tanggal_pinjam');
        $tanggal_kembali = requiredDate($input, 'tanggal_kembali');

        if ($tanggal_kembali < $tanggal_pinjam) {
            securityError(
                "Tanggal kembali tidak boleh sebelum tanggal pinjam",
                400
            );
        }

        // Cek anggota
        $stmt = prepareQuery($koneksi,
            "SELECT id FROM anggota WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_anggota);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            securityError("Anggota tidak ditemukan", 404);
        }

        // Cek buku
        $stmt = prepareQuery($koneksi,
            "SELECT id FROM buku WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_buku);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            securityError("Buku tidak ditemukan", 404);
        }

        // Insert peminjaman
        $stmt = prepareQuery($koneksi,
            "INSERT INTO peminjaman
            (id_anggota, id_buku, tanggal_pinjam, tanggal_kembali)
            VALUES (?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iiss",
            $id_anggota,
            $id_buku,
            $tanggal_pinjam,
            $tanggal_kembali
        );

        if (mysqli_stmt_execute($stmt)) {

            sendResponse(
                true,
                "Data peminjaman berhasil disimpan",
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

        $id_anggota = requiredInt($input, 'id_anggota');
        $id_buku = requiredInt($input, 'id_buku');

        $tanggal_pinjam = requiredDate($input, 'tanggal_pinjam');
        $tanggal_kembali = requiredDate($input, 'tanggal_kembali');

        if ($tanggal_kembali < $tanggal_pinjam) {
            securityError(
                "Tanggal kembali tidak boleh sebelum tanggal pinjam",
                400
            );
        }

        // Cek peminjaman
        $stmt = prepareQuery($koneksi,
            "SELECT id FROM peminjaman WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            securityError("Peminjaman tidak ditemukan", 404);
        }

        // Cek anggota
        $stmt = prepareQuery($koneksi,
            "SELECT id FROM anggota WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_anggota);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            securityError("Anggota tidak ditemukan", 404);
        }

        // Cek buku
        $stmt = prepareQuery($koneksi,
            "SELECT id FROM buku WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_buku);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            securityError("Buku tidak ditemukan", 404);
        }

        // Update peminjaman
        $stmt = prepareQuery($koneksi,
            "UPDATE peminjaman
             SET id_anggota = ?,
                 id_buku = ?,
                 tanggal_pinjam = ?,
                 tanggal_kembali = ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iissi",
            $id_anggota,
            $id_buku,
            $tanggal_pinjam,
            $tanggal_kembali,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {

            sendResponse(
                true,
                "Data peminjaman berhasil diperbarui",
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
            "DELETE FROM peminjaman WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {

            if (mysqli_stmt_affected_rows($stmt) == 0) {
                securityError("Peminjaman tidak ditemukan", 404);
            }

            sendResponse(
                true,
                "Data peminjaman berhasil dihapus",
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
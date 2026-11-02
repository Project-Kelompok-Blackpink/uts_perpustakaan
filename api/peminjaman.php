<?php

require_once "../config.php";
require_once "../helpers/response.php";

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

            $id = (int) $_GET['id'];

            if ($id <= 0) {
                sendResponse(false, "ID tidak valid", null, 400);
                exit;
            }

            $stmt = mysqli_prepare($koneksi,
                $query . " WHERE p.id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $r = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($r);

            if (!$data) {
                sendResponse(false, "Peminjaman tidak ditemukan", null, 404);
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

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, (int) ($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;

            $stmt = mysqli_prepare($koneksi,
                $query . "
                ORDER BY p.id DESC
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
            $query . " ORDER BY p.id DESC"
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

        $id_anggota = (int) ($input['id_anggota'] ?? 0);
        $id_buku = (int) ($input['id_buku'] ?? 0);

        $tanggal_pinjam = trim($input['tanggal_pinjam'] ?? '');
        $tanggal_kembali = trim($input['tanggal_kembali'] ?? '');

        // Validasi input
        if (
            $id_anggota <= 0 ||
            $id_buku <= 0 ||
            $tanggal_pinjam == '' ||
            $tanggal_kembali == ''
        ) {
            sendResponse(false, "Semua data wajib diisi dengan benar", null, 400);
            exit;
        }

        // Validasi tanggal
        $formatPinjam = DateTime::createFromFormat('!Y-m-d', $tanggal_pinjam);
        $formatKembali = DateTime::createFromFormat('!Y-m-d', $tanggal_kembali);

        if (
            !$formatPinjam ||
            $formatPinjam->format('Y-m-d') !== $tanggal_pinjam ||
            !$formatKembali ||
            $formatKembali->format('Y-m-d') !== $tanggal_kembali
        ) {
            sendResponse(false, "Format tanggal tidak valid", null, 400);
            exit;
        }

        if ($tanggal_kembali < $tanggal_pinjam) {
            sendResponse(
                false,
                "Tanggal kembali tidak boleh sebelum tanggal pinjam",
                null,
                400
            );
            exit;
        }

        // Cek anggota
        $stmt = mysqli_prepare($koneksi,
            "SELECT id FROM anggota WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_anggota);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            sendResponse(false, "Anggota tidak ditemukan", null, 404);
            exit;
        }

        // Cek buku
        $stmt = mysqli_prepare($koneksi,
            "SELECT id FROM buku WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_buku);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) == 0) {
            sendResponse(false, "Buku tidak ditemukan", null, 404);
            exit;
        }

        // Insert peminjaman
        $stmt = mysqli_prepare($koneksi,
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

        sendResponse(false, "Gagal menyimpan data peminjaman", null, 500);
        exit;


    default:

        sendResponse(false, "Method tidak diizinkan", null, 405);
        exit;
}
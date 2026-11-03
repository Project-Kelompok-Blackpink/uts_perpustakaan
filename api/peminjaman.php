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

case 'POST':
    $input = json_decode(file_get_contents('php://input'), true);

    // cek input wajib
    if (!$input || !isset($input['id_anggota']) || !isset($input['id_buku']) || !isset($input['tanggal_pinjam']) || !isset($input['tanggal_kembali'])) {
        sendResponse(false, "Field id_anggota, id_buku, tanggal_pinjam, tanggal_kembali wajib diisi", null, 400);
    }

    $id_anggota = (int) $input['id_anggota'];
    $id_buku = (int) $input['id_buku'];
    $tanggal_pinjam = $input['tanggal_pinjam'];
    $tanggal_kembali = $input['tanggal_kembali'];

    // cek data tidak boleh kosong
    if ($id_anggota <= 0 || $id_buku <= 0) {
        sendResponse(false, "ID anggota dan ID buku tidak valid", null, 400);
    }

    // cek format tanggal
    $validPinjam = is_string($tanggal_pinjam) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_pinjam) &&
        checkdate((int)substr($tanggal_pinjam, 5, 2), (int)substr($tanggal_pinjam, 8, 2), (int)substr($tanggal_pinjam, 0, 4));

    $validKembali = is_string($tanggal_kembali) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_kembali) &&
        checkdate((int)substr($tanggal_kembali, 5, 2), (int)substr($tanggal_kembali, 8, 2), (int)substr($tanggal_kembali, 0, 4));

    if (!$validPinjam || !$validKembali || $tanggal_kembali < $tanggal_pinjam) {
        sendResponse(false, "Format tanggal tidak valid", null, 400);
    }

    // cek anggota
    $cek = mysqli_prepare($koneksi, "SELECT id FROM anggota WHERE id = ?");

    if (!$cek) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($cek, "i", $id_anggota);
    mysqli_stmt_execute($cek);
    $hasil = mysqli_stmt_get_result($cek);

    if (mysqli_num_rows($hasil) == 0) {
        sendResponse(false, "Anggota tidak ditemukan", null, 404);
    }

    // cek buku
    $cek = mysqli_prepare($koneksi, "SELECT id FROM buku WHERE id = ?");

    if (!$cek) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($cek, "i", $id_buku);
    mysqli_stmt_execute($cek);
    $hasil = mysqli_stmt_get_result($cek);

    if (mysqli_num_rows($hasil) == 0) {
        sendResponse(false, "Buku tidak ditemukan", null, 404);
    }

    // cek peminjaman yang sama
    $cek = mysqli_prepare($koneksi, "SELECT id FROM peminjaman WHERE id_anggota = ? AND id_buku = ? AND tanggal_pinjam = ?");

    if (!$cek) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($cek, "iis", $id_anggota, $id_buku, $tanggal_pinjam);
    mysqli_stmt_execute($cek);
    $hasil = mysqli_stmt_get_result($cek);

    if (mysqli_num_rows($hasil) > 0) {
        sendResponse(false, "Data peminjaman sudah terdaftar", null, 409);
    }

    // insert data peminjaman
    $stmt = mysqli_prepare($koneksi, "INSERT INTO peminjaman (id_anggota, id_buku, tanggal_pinjam, tanggal_kembali) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($stmt, "iiss", $id_anggota, $id_buku, $tanggal_pinjam, $tanggal_kembali);

    if (mysqli_stmt_execute($stmt)) {
        sendResponse(
            true,
            "Data peminjaman berhasil tersimpan",
            ["id" => mysqli_insert_id($koneksi)],
            201
        );
    } else {
        error_log(mysqli_stmt_error($stmt));
        sendResponse(false, "Data peminjaman gagal disimpan", null, 500);
    }

    break;
    default:

        sendResponse(false, "Method tidak diizinkan", null, 405);
        exit;

    // ================= PUT =================

    case 'PUT':
    $input = json_decode(file_get_contents("php://input"), true);

    // Validasi input
    if (
        !$input ||
        !isset($input['id_anggota']) ||
        !isset($input['id_buku']) ||
        !isset($input['tanggal_pinjam']) ||
        !isset($input['tanggal_kembali'])
    ) {
        sendResponse(false, "Input tidak boleh kosong", null, 400);
        break;
    }

    // Pertahanan pertama: cast integer
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendResponse(false, "ID tidak valid", null, 400);
        break;
    }

    $id_anggota = (int) $input['id_anggota'];
    $id_buku = (int) $input['id_buku'];
    $tanggal_pinjam = $input['tanggal_pinjam'];
    $tanggal_kembali = $input['tanggal_kembali'];

    // Pertahanan kedua: prepared statement untuk mengecek ID
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id FROM peminjaman WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);

    if (!$r) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    $data = mysqli_fetch_assoc($r);
    mysqli_stmt_close($stmt);

    if (!$data) {
        sendResponse(false, "Data peminjaman tidak ditemukan", null, 404);
        break;
    }

    // Query untuk mengubah data peminjaman
    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE peminjaman
        SET id_anggota = ?, id_buku = ?, tanggal_pinjam = ?, tanggal_kembali = ?
        WHERE id = ?"
    );

    if (!$stmt) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    mysqli_stmt_bind_param($stmt, "iissi", $id_anggota, $id_buku, $tanggal_pinjam, $tanggal_kembali, $id);

    if (mysqli_stmt_execute($stmt)) {
        sendResponse(true, "Data peminjaman berhasil diperbarui", ["id" => $id], 200);
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

    // Cek apakah peminjaman tersedia
    $stmt = mysqli_prepare($koneksi, "SELECT id FROM peminjaman WHERE id = ?");

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
        sendResponse(false, "Peminjaman tidak ditemukan", null, 404);
        break;
    }

    // Hapus data peminjaman
    $stmt = mysqli_prepare($koneksi, "DELETE FROM peminjaman WHERE id = ?");

    if (!$stmt) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {

        sendResponse(true, "Data peminjaman berhasil dihapus", ["id" => $id], 200);

    } else {

        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
    }

    break;

}
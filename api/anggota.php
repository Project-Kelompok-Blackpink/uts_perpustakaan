<?php

require_once "../config.php";
require_once "../helpers/response.php";

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {

    case 'GET':
        // GET berdasarkan ID
        // api/anggota.php?id=1
        if (isset($_GET['id'])) {
            $id = (int) ($_GET['id'] ?? 0);

            $stmt = mysqli_prepare($koneksi, "SELECT id, kode_anggota, nama_anggota, alamat, no_hp FROM anggota WHERE id = ?");

            if (!$stmt) {
                gagalQuery($koneksi);
            }

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            // Ini jika query error
            if (!$r)
                gagalQuery($koneksi);

            $data = mysqli_fetch_assoc($r);

            if (!$data) {
                sendResponse(
                    false,
                    "Anggota tidak ditemukan",
                    null,
                    404
                );
            }

            sendResponse(
                true,
                "Berhasil",
                $data,
                200
            );
        }

        // SEARCH
        // api/anggota.php?search=Andi
        if (isset($_GET['search'])) {
            $search = $_GET['search'] ?? "";

            // Joker LIKE disambung di PHP
            $term = "%" . $search . "%";
            $stmt = mysqli_prepare($koneksi, "SELECT id, kode_anggota, nama_anggota, alamat, no_hp FROM anggota WHERE nama_anggota LIKE ? OR kode_anggota LIKE ? ORDER BY id DESC");

            if (!$stmt) {
                gagalQuery($koneksi);
            }

            mysqli_stmt_bind_param($stmt, "ss", $term, $term);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            // Ini kalau query gagal
            if (!$r)
                gagalQuery($koneksi);
            $data = [];

            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            sendResponse(
                true,
                "Berhasil",
                $data,
                200
            );
        }

        // LIMIT & OFFSET
        // PAGINATION
        // api/anggota.php?limit=2
        // api/anggota.php?page=2

        if (isset($_GET['page']) || isset($_GET['limit'])) {
            $page = isset($_GET['page'])
                ? (int) $_GET['page']
                : 1;

            $limit = isset($_GET['limit'])
                ? (int) $_GET['limit']
                : 10;

            if ($page < 1) {
                $page = 1;
            }

            if ($limit < 1) {
                $limit = 10;
            }

            $offset = ($page - 1) * $limit;
            $stmt = mysqli_prepare($koneksi, "SELECT id, kode_anggota, nama_anggota, alamat, no_hp FROM anggota ORDER BY id DESC LIMIT ? OFFSET ?");

            if (!$stmt) {
                gagalQuery($koneksi);
            }

            mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            if (!$r)
                gagalQuery($koneksi);

            $data = [];

            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            sendResponse(
                true,
                "Berhasil",
                $data,
                200
            );
        }

        // GET SEMUA DATA
        $stmt = mysqli_prepare($koneksi, "SELECT id, kode_anggota, nama_anggota, alamat, no_hp FROM anggota ORDER BY id DESC");

        if (!$stmt) {
            gagalQuery($koneksi);
        }

        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);

        if (!$r)
            gagalQuery($koneksi);

        $data = [];

        while ($row = mysqli_fetch_assoc($r)) {
            $data[] = $row;
        }

        sendResponse(
            true,
            "Berhasil",
            $data,
            200
        );

        break;
    default:

        sendResponse(
            false,
            "Method tidak diizinkan",
            null,
            405
        );
     break;

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

    if (!$cek) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($cek, "s", $kode_anggota);
    mysqli_stmt_execute($cek);
    $hasil = mysqli_stmt_get_result($cek);

    if (mysqli_num_rows($hasil) > 0) {
        sendResponse(false, "Kode anggota sudah terdaftar", null, 409);
    }

    // insert data anggota
    $stmt = mysqli_prepare($koneksi, "INSERT INTO anggota (kode_anggota, nama_anggota, alamat, no_hp) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        error_log(mysqli_error($koneksi));
        sendResponse(false, "Terjadi kesalahan server", null, 500);
    }

    mysqli_stmt_bind_param($stmt, "ssss", $kode_anggota, $nama_anggota, $alamat, $no_hp);

    if (mysqli_stmt_execute($stmt)) {
        sendResponse(
            true,
            "Data anggota berhasil tersimpan",
            ["id" => mysqli_insert_id($koneksi)],
            201
        );
    } else {
        error_log(mysqli_stmt_error($stmt));
        sendResponse(false, "Data anggota gagal disimpan", null, 500);
    }

    break;

    // ================= PUT =================
    case 'PUT':
    $input = json_decode(file_get_contents("php://input"), true);

    // Validasi input
    if (
        !$input || !isset($input['kode_anggota']) || !isset($input['nama_anggota']) ) {
        sendResponse(false, "Input tidak boleh kosong", null, 400);
        break;
    }

    // Pertahanan pertama: cast integer
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendResponse(false, "ID tidak valid", null, 400);
        break;
    }

    $kode_anggota = $input['kode_anggota'];
    $nama_anggota = $input['nama_anggota'];
    $alamat = $input['alamat'] ?? null;
    $no_hp = $input['no_hp'] ?? null;

    if ($kode_anggota == '' || $nama_anggota == '' || $alamat == '' || $no_hp == '') {
        sendResponse(false, "Data anggota tidak boleh kosong", null, 400);
    }


    // Pertahanan kedua: prepared statement untuk mengecek ID
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id FROM anggota WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);

    if (!$r) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    $data = mysqli_fetch_assoc($r);

    if (!$data) {
        sendResponse(false, "Anggota tidak ditemukan", null, 404);
        break;
    }

    // Query untuk mengubah data anggota
    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE anggota
        SET kode_anggota = ?, nama_anggota = ?, alamat = ?, no_hp = ?
        WHERE id = ?"
    );

    if (!$stmt) {
        sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        break;
    }

    mysqli_stmt_bind_param($stmt, "ssssi", $kode_anggota, $nama_anggota, $alamat, $no_hp, $id);

    if (mysqli_stmt_execute($stmt)) {
        sendResponse(true, "Data anggota berhasil diperbarui", ["id" => $id], 200);
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

        // Cek apakah anggota tersedia
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM anggota WHERE id = ?");

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
            sendResponse(false, "Anggota tidak ditemukan", null, 404);
            break;
        }

        // Hapus data anggota
        $stmt = mysqli_prepare($koneksi, "DELETE FROM anggota WHERE id = ?");

        if (!$stmt) {
            sendResponse(false, "Terjadi kesalahan pada server", null, 500);
            break;
        }

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {

            sendResponse(true, "Data anggota berhasil dihapus", ["id" => $id], 200);
        } else {

            sendResponse(false, "Terjadi kesalahan pada server", null, 500);
        }

        break;
}
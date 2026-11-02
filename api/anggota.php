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
            $stmt = mysqli_prepare($koneksi,"SELECT id, kode_anggota, nama_anggota, alamat, no_hp FROM anggota ORDER BY id DESC LIMIT ? OFFSET ?");

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
        $stmt = mysqli_prepare($koneksi,"SELECT id, kode_anggota, nama_anggota, alamat, no_hp FROM anggota ORDER BY id DESC");

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
}
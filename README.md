# uts_perpustkaan  
Kelompok Blackpink
Anggota Kelompok :
1. Redia Yuana Dinandika Tjandring (2511500027) : Database dan perancangan
2. Monica Trie Hapsari (2511500082) : Config, Helper, dan Endpoint A
3. Maharani Indah Saputri (2511500079) : Endpoint B (POST)
4. Chelsea Clarisa (2511500065) : Endpoint C (PUT/DELETE)

## Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    ANGGOTA {
        int id PK
        varchar kode_anggota UK
        varchar nama_anggota
        varchar alamat
        varchar no_hp
    }

    BUKU {
        int id PK
        varchar isbn UK
        varchar judul_buku
        varchar pengarang
        varchar tahun_terbit
    }

    PEMINJAMAN {
        int id PK
        int id_anggota FK
        int id_buku FK
        date tanggal_pinjam
        date tanggal_kembali
    }

    ANGGOTA ||--o{ PEMINJAMAN : melakukan
    BUKU ||--o{ PEMINJAMAN : dipinjam
```

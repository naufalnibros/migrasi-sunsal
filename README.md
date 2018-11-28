# migrasi-sunsal

  1. Inisialisasi  
  $migrasi = new Migrasi("source", "target");
  ```php
  $migrasi = new Migrasi("landa_sunsal", "akademik_sunsal");
  ```

  2. Mengambil Query utk update & tambah kolom
  ```php
  echo ($migrasi->compare_columns()->get_query()->all);
  echo ($migrasi->compare_columns()->get_query()->add);
  echo ($migrasi->compare_columns()->get_query()->update);
  ```

  3. Mengambil Query utk update & tambah kolom & create table
  ```php
  echo ($migrasi->compare_tables()->compare_columns()->get_query()->all);
  echo ($migrasi->compare_tables()->compare_columns()->get_query()->update_columns);
  echo ($migrasi->compare_tables()->compare_columns()->get_query()->add_columns);
  echo ($migrasi->compare_tables()->compare_columns()->get_query()->create_table);
  ```

  4. Mengambil semua isi table di database
  ```php
  echo json_encode($migrasi->all_table());
  echo json_encode($migrasi->all_table()->source);
  echo json_encode($migrasi->all_table()->target);
  ```

  5. Mengambil semua isi kolom di database
  ```php
  echo json_encode($migrasi->all_column());
  echo json_encode($migrasi->all_column()->source);
  echo json_encode($migrasi->all_column()->target);

  6. Mengambil semua isi kolom tertentu di database
  ```php
  echo json_encode($migrasi->get_column("m_setting"));
  echo json_encode($migrasi->get_column("m_setting")->source);
  echo json_encode($migrasi->get_column("m_setting")->target);
  ```

  7. Menggunakan fungsi dari LandaDb
  ```php
  echo json_encode($migrasi->findAll("SELECT * FROM NAMA_KOLOM WHERE id='1'"));
  ```

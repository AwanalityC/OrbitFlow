# OrbitFlow

OrbitFlow is a Laravel 12 + MySQL school equipment borrowing and inventory application.

## What's included in this source archive

- Student equipment browsing, filtering, request submission, confirmation and status pages.
- Admin borrowing request review, approval and rejection with a required, student-visible rejection reason.
- Physical unit allocation records when an admin approves a request.
- Return processing per physical unit, with the condition recorded before and after borrowing to help distinguish existing damage from new damage.
- Admin inventory page to mark units Baik, Rusak Ringan, Rusak Berat, or Hilang and restore repaired units.
- Normal-condition units are allocated first; functional Rusak Ringan units are a second-priority fallback. Rusak Berat and Hilang units are excluded from borrowing.
- Automatic transition to `Overdue` when a borrowing deadline has passed and the admin dashboard or student borrowing-status page is opened.
- Feature test for allocation and return processing.

The original page layouts were preserved. The new return page reuses the same OrbitFlow header, spacing, cards, tables and simple responsive styling rather than redesigning the existing wireframe-based screens.

## Requirements

- PHP 8.2 or later
- Composer
- MySQL or MariaDB
- Node.js and npm (only needed to build frontend assets)

## Local setup

1. Extract the ZIP into your project folder.
2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Create your local environment file and app key:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

   On PowerShell, `Copy-Item .env.example .env` can be used instead of `copy`.

4. In `.env`, configure the database you use for OrbitFlow:

   ```dotenv
   APP_NAME=OrbitFlow
   APP_URL=http://127.0.0.1:8000
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=orbitflow
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   Create the `orbitflow` database in MySQL/MariaDB first if it does not exist. Use your own local database username and password if they differ.

5. Run migrations and create the local development accounts:

   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. Optional: install/build frontend assets if needed:

   ```bash
   npm install
   npm run build
   ```

7. Start the development server:

   ```bash
   php artisan serve
   ```

   Open `http://127.0.0.1:8000`.

## Local demo data

For local development, run `php artisan db:seed` to populate the database with sample users, equipment, and borrowing records.

The demo data is defined in `database/seeders/OrbitFlowDemoSeeder.php`.

Use the demo seeder only with a development database. Do not run it against a production database or one containing real school records.

## Return workflow

1. A student submits a borrowing request. Its status is `Pending`; stock is not reserved at this stage.
2. An admin reviews and approves it. The system rechecks availability inside a database transaction, records the specific physical units allocated to each borrowing item, marks those units `Dipinjam`, and marks the borrowing `Borrowed`.
3. From the admin dashboard, choose **Proses Pengembalian** for an active borrowing.
4. For each returned unit, review the recorded condition at the start of the loan, then choose `Normal / Baik`, `Rusak Ringan`, or `Rusak Berat` and optionally enter notes. The default follows the recorded starting condition.
5. Normal returns become `Tersedia`. Functional minor damage also remains `Tersedia` but is lower priority than units in good condition. Major damage becomes `Tidak Tersedia`. A minor-damage follow-up is only created when the start condition was recorded as `Baik`; existing minor damage is not automatically attributed to the borrower.
6. Use **Kelola Kondisi & Perbaikan Unit** from the admin dashboard to record repairs, change an available unit's condition, or mark it lost. Units currently marked `Dipinjam` must go through return processing before their condition/status can be changed.
6. The borrowing becomes `Returned` once all allocated units have been returned. A partial return leaves the request active.

**Existing in-progress borrowings:** Requests approved before physical-unit allocation was added may not have allocation rows in the new pivot table. The return page will show a warning rather than guess which unit belongs to an old borrowing. Test the complete workflow with a new request after migrating.

## Run tests

```bash
php artisan test
```

Feature tests cover unit-priority allocation, preservation of minor-damage availability, rejection reasons, repair-status updates, return records, and compensation follow-up for newly recorded damage.

## Migrations added in this update

- `2026_10_20_080000_create_borrowing_item_equipment_unit_table.php`
- `2026_10_20_080100_add_equipment_unit_id_to_return_records_table.php`
- `2026_10_21_100000_add_rejection_reason_and_borrowed_condition.php`
- `2026_11_03_100000_create_student_account_activations_table.php`

For an existing local installation, back up the database and run `php artisan migrate`. Do not run `migrate:fresh` against your existing database because it drops the tables and their data.

## Student bulk import and account activation (Phase 1, Stage 3)

Administrative users with access to student management can import a CSV file from **Manajemen Pengguna → Impor CSV**.

The required UTF-8 CSV columns are:

```csv
nis,name,email,class_name
```

- Maximum file size: 2 MB.
- Maximum rows per import: 1,000.
- Comma and semicolon delimiters are supported.
- Duplicate NIS or email addresses and invalid rows are flagged in the preview, can be exported as a CSV error report, and are skipped during import.
- Imported accounts are created as inactive students. They receive a random, undisclosed initial password.
- OrbitFlow creates a one-time activation link for each imported student. Only a SHA-256 hash of the token is stored in the database.
- Activation links expire after 7 days. Students use the link to set a password of at least 10 characters; successful activation invalidates all other outstanding links for that account.
- The admin receives activation links in a private CSV download. The download is available once for 30 minutes. If it is lost or a link expires, the admin can issue a replacement from the student directory.
- Activation links are not emailed automatically. Share each link only with the matching student through a school-approved private channel. Configure `APP_URL` to a host students can reach before distributing links outside the development computer.
- CSV preview data is held in private application storage for up to 60 minutes and is restricted to the admin who uploaded it.

For an existing installation, back up the database and run:

```bash
php artisan migrate
php artisan test
```

Do not use `php artisan migrate:fresh` with a database that contains school or test records.

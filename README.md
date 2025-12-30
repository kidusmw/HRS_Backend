# StayFinder (HRS) Backend

Laravel backend API for the **Hotel Reservation System (StayFinder)**.

## Requirements

- PHP **8.2+**
- Composer **2+**
- A database (MySQL / PostgreSQL / SQLite)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Run the server:

```bash
php artisan serve
```

The API is typically served under `/api` (e.g. `http://localhost:8000/api`).

## Cloudinary media storage (optional)

This backend supports storing user-uploaded media (hotel logos/images, room images, user avatars, system logo) on **Cloudinary** via the Laravel filesystem driver from [`cloudinary-laravel`](https://github.com/cloudinary-community/cloudinary-laravel/).

### Required environment variables

- **Media disk selection**
  - `MEDIA_DISK=cloudinary` (store new uploads in Cloudinary)
  - `MEDIA_DISK=public` (store new uploads on local public storage; default)
- **Cloudinary credentials**
  - `CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME`
  - Optional: `CLOUDINARY_UPLOAD_PRESET=...`
  - Optional: `CLOUDINARY_NOTIFICATION_URL=...`

## Notes

- If you use `MEDIA_DISK=public`, make sure you’ve run `php artisan storage:link`.

# Super Admin API Documentation

## Base URL
All Super Admin endpoints are prefixed with `/api/super_admin`

## Authentication
All endpoints require:
- `auth:sanctum` middleware (Bearer token or session cookie)
- `role:superadmin` middleware (user must have `superadmin` role)

## Endpoints

### Dashboard
- `GET /api/super_admin/dashboard/metrics` - Get dashboard metrics (hotels, users by role, bookings, rooms)

### Users
- `GET /api/super_admin/users` - List users (filters: search, role, hotelId, active)
- `POST /api/super_admin/users` - Create user
- `GET /api/super_admin/users/{id}` - Get user by ID
- `PUT /api/super_admin/users/{id}` - Update user
- `PATCH /api/super_admin/users/{id}/activate` - Activate user
- `PATCH /api/super_admin/users/{id}/deactivate` - Deactivate user
- `POST /api/super_admin/users/{id}/reset-password` - Reset user password

### Hotels
- `GET /api/super_admin/hotels` - List hotels (filters: search, timezone, hasAdmin)
- `POST /api/super_admin/hotels` - Create hotel
- `GET /api/super_admin/hotels/{id}` - Get hotel by ID
- `PUT /api/super_admin/hotels/{id}` - Update hotel
- `DELETE /api/super_admin/hotels/{id}` - Delete hotel

### Audit Logs
- `GET /api/super_admin/logs` - List audit logs (filters: userId, hotelId, action, from, to)
- `GET /api/super_admin/logs/{id}` - Get log by ID

### Backups
- `GET /api/super_admin/backups` - List backups
- `POST /api/super_admin/backups/full` - Run full system backup (Spatie)
- `POST /api/super_admin/backups/hotel/{hotelId}` - Run hotel JSON export
- `GET /api/super_admin/backups/{id}/download` - Download backup file

### Settings
- `GET /api/super_admin/settings/system` - Get system settings
- `PUT /api/super_admin/settings/system` - Update system settings
- `GET /api/super_admin/settings/hotel/{hotelId}` - Get hotel settings
- `PUT /api/super_admin/settings/hotel/{hotelId}` - Update hotel settings

### Notifications
- `GET /api/super_admin/notifications` - List notifications (MVP - basic implementation)
- `PATCH /api/super_admin/notifications/{id}/read` - Mark notification as read

## Default Super Admin User
After seeding:
- Email: `superadmin@example.com`
- Password: `password`

## Environment Variables
- `BACKUP_DISK=local` (development) - Set to `s3` for production


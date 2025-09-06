# ServiceLink Setup Guide

This guide will help you set up the ServiceLink PHP project.

## Prerequisites

1. **WAMP/XAMPP/MAMP** - Web server with PHP and MySQL
2. **PHP 7.4+** with PDO extension
3. **MySQL 5.7+** or MariaDB

## Installation Steps

### 1. Database Setup

1. Open phpMyAdmin or MySQL command line
2. Run the database schema file:
   ```sql
   source database/schema.sql
   ```
3. (Optional) Load sample data:
   ```sql
   source database/sample_data.sql
   ```

### 2. Configuration

1. Edit `config/config.php`:
   - Update `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` with your MySQL credentials
   - Update `BASE_URL` to match your local setup (e.g., `http://localhost/Service_Delivery_Web`)

### 3. File Permissions

1. Create uploads directory:
   ```bash
   mkdir uploads
   chmod 755 uploads
   ```

### 4. Test Installation

1. Visit `http://localhost/Service_Delivery_Web/` in your browser
2. You should see the homepage with categories loaded from the database

## Default Accounts

After loading sample data, you can use these accounts:

- **Admin**: admin / admin123
- **Provider**: alex_carpenter / password123  
- **Customer**: john_user / password123

## Project Structure

```
Service_Delivery_Web/
├── admin/              # Admin panel pages
├── api/               # API endpoints (future use)
├── assets/            # CSS, JS, images
├── config/            # Configuration files
├── database/          # SQL schema and sample data
├── includes/          # Reusable PHP components
├── pages/             # Additional pages
├── uploads/           # User uploaded files
├── index.php          # Homepage
├── services.php       # Service listings
├── wanted.php         # Wanted ads
├── login.php          # Authentication
├── my-service.php     # Provider profile management
├── provider-profile.php # View provider details
└── logout.php         # Logout handler
```

## Features Implemented

✅ **User Authentication**
- Registration and login
- Role-based access (user, provider, admin)
- Session management

✅ **Service Providers**
- Provider registration and profiles
- Service categories and filtering
- Reviews and ratings system
- Working hours and availability

✅ **Wanted Ads**
- Post service requests
- Browse and filter requests
- Urgency levels and budget ranges

✅ **Admin Panel**
- User management
- Provider verification
- System statistics
- Category management

✅ **Responsive Design**
- TailwindCSS styling
- Mobile-friendly interface
- Modern UI components

## Database Features

- **Users**: Authentication and profile management
- **Providers**: Service provider profiles and business info
- **Categories**: Service categories with icons
- **Wanted Ads**: Service request postings
- **Reviews**: Rating and feedback system
- **Messages**: Communication between users
- **Qualifications**: Provider certifications

## Security Features

- Password hashing with PHP's `password_hash()`
- CSRF protection on forms
- SQL injection prevention with prepared statements
- Session security with timeouts
- Input validation and sanitization

## Development Notes

- Uses PDO for database connections
- Follows MVC-like structure with separation of concerns
- Includes error handling and user feedback
- Ready for further customization and feature additions

## Troubleshooting

1. **Database connection errors**: Check your MySQL credentials in `config/config.php`
2. **Permission errors**: Ensure web server has read/write access to project files
3. **Session issues**: Check PHP session configuration
4. **Missing functions**: Ensure all required PHP extensions are installed

## Next Steps for Production

1. Update error reporting settings
2. Implement email verification
3. Add file upload functionality for profile images
4. Set up SSL certificates
5. Configure backup procedures
6. Implement caching for better performance

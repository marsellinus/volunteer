# VolunteerHub

VolunteerHub is a web application for connecting volunteers with volunteer opportunities. The platform helps organizations find suitable volunteers and enables individuals to discover and participate in volunteer activities that match their interests and skills.

## Features

### For Volunteers (Users)
- **Account Management**: Create and manage a volunteer profile
- **Search for Opportunities**: Find volunteer activities with advanced filtering
- **Apply to Activities**: Submit applications for volunteer opportunities
- **Receive Notifications**: Get updates on application status and activities
- **Digital Certificates**: Download certificates for completed volunteer work
- **Track Applications**: Monitor the status of all submitted applications

### For Organizations (Owners)
- **Account Management**: Create and manage an organization profile
- **Post Opportunities**: Create and manage volunteer activity listings
- **Review Applications**: Approve or reject volunteer applications
- **Generate Certificates**: Issue certificates to volunteers who completed activities
- **Receive Notifications**: Get updates on new applications
- **Communication Tools**: Interact with volunteers and applicants

## Installation

1. **Prerequisites**:
   - PHP 7.4 or higher
   - MySQL/MariaDB
   - Web server (Apache/Nginx)

2. **Setup**:
   - Clone or download this repository to your web server directory
   - Import the database schema from `schema/database.sql`
   - Configure your database connection in `config/database.php`
   - Run the setup scripts to ensure all tables are properly created:
     - `setup/create_notifications_table.php`
     - `setup/update_applications_table.php`
   - Ensure the `assets` directory is writable for certificate generation

3. **First Login**:
   - Register as either a volunteer (user) or an organization (owner)
   - For testing purposes, you can use the sample accounts (if available)

## File Structure

```
d:\try\pweb2\
├── auth/                 # Authentication files (login, register, logout)
├── assets/               # Static assets (images, styles, etc.)
├── config/               # Configuration files
│   └── database.php      # Database connection settings
├── dashboard/            # User interfaces after login
│   ├── owner/            # Organization dashboard
│   └── user/             # Volunteer dashboard
├── includes/             # Reusable PHP components
│   └── notifications.php # Notification system
├── logic/                # Business logic
│   └── recommendation.php# Recommendation algorithms
├── public/               # Publicly accessible files
│   ├── index.php         # Landing page
│   └── activity.php      # Public activity display
├── schema/               # Database schema and migrations
│   └── database.sql      # Initial database setup
└── setup/                # Setup and installation scripts
```

## Usage

### For Volunteers
1. Register or login as a user
2. Browse volunteer opportunities using the search function
3. Apply for activities matching your interests
4. Track your applications in the dashboard
5. Download certificates for completed volunteer work

### For Organizations
1. Register or login as an owner
2. Create volunteer opportunities from your dashboard
3. Manage applications for your activities
4. Approve or reject applicants
5. Generate certificates for volunteers who completed their service

## Database Update Scripts

If you encounter any error messages about missing tables or columns, run the appropriate setup script:

- `setup/create_notifications_table.php` - Creates the notifications system table
- `setup/update_applications_table.php` - Adds certificate generation columns to applications

## Technologies Used

- PHP (Backend)
- MySQL (Database)
- TailwindCSS (Styling)
- JavaScript (Frontend interactivity)

## Screenshots

(Add screenshots here)

## Future Improvements

- Private messaging between organizations and volunteers
- Social sharing for certificates
- Volunteer hour tracking and reporting
- Skills endorsement system
- Mobile app for on-the-go volunteer management

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
